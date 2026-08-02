<?php

namespace App\Models;

use App\Settings\PayrollSettings;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Adelanto de salario: retiro anticipado del sueldo del mes en curso.
 *
 * Ciclo de vida:
 *   pending → approved → disbursed → paid
 *                      ↘ cancelled
 *          ↘ rejected (terminal)
 *   pending/approved → cancelled
 *
 * disbursed = dinero entregado al empleado (banco o efectivo), pendiente de descuento en nómina.
 * paid      = descontado en nómina.
 */
class Advance extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    /** Campos auditados: solo cambios de estado y metadatos operacionales. */
    protected array $auditInclude = [
        'status',
        'payment_method',
        'approved_by_id',
        'approved_at',
        'disbursed_at',
        'disbursed_by_id',
        'bank_rejection_reason',
        'notes',
    ];

    protected $fillable = [
        'employee_id',
        'amount',
        'status',
        'payment_method',
        'disbursed_at',
        'disbursed_by_id',
        'transfer_receipt_path',
        'disbursement_batch_id',
        'bank_rejection_reason',
        'approved_by_id',
        'approved_at',
        'notes',
        'employee_deduction_id',
        'payroll_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'disbursed_at' => 'datetime',
    ];

    // =========================================================================
    // RELACIONES
    // =========================================================================

    /** Empleado al que pertenece el adelanto. */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** Usuario que aprobó el adelanto. */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    /** Usuario que marcó el adelanto como entregado. */
    public function disbursedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disbursed_by_id');
    }

    /** Lote de acreditación bancaria al que pertenece el adelanto (solo transferencias masivas). */
    public function disbursementBatch(): BelongsTo
    {
        return $this->belongsTo(DisbursementBatch::class);
    }

    /** Nómina en la que fue descontado el adelanto. */
    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    /** Deducción puntual generada en nómina para descontar el adelanto. */
    public function employeeDeduction(): BelongsTo
    {
        return $this->belongsTo(EmployeeDeduction::class);
    }

    // =========================================================================
    // HELPERS ESTÁTICOS — ESTADOS
    // =========================================================================

    /**
     * Retorna el label legible de un estado.
     */
    public static function getStatusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Pendiente',
            'approved' => 'Aprobado',
            'disbursed' => 'Entregado',
            'paid' => 'Descontado',
            'rejected' => 'Rechazado',
            'cancelled' => 'Cancelado',
            default => 'Desconocido',
        };
    }

    /**
     * Retorna el color semántico Filament para un estado.
     */
    public static function getStatusColor(string $status): string
    {
        return match ($status) {
            'pending' => 'warning',
            'approved' => 'info',
            'disbursed' => 'primary',
            'paid' => 'success',
            'rejected' => 'danger',
            'cancelled' => 'gray',
            default => 'gray',
        };
    }

    /**
     * Retorna el icono heroicon para un estado.
     */
    public static function getStatusIcon(string $status): string
    {
        return match ($status) {
            'pending' => 'heroicon-o-clock',
            'approved' => 'heroicon-o-check',
            'disbursed' => 'heroicon-o-banknotes',
            'paid' => 'heroicon-o-check-circle',
            'rejected' => 'heroicon-o-x-circle',
            'cancelled' => 'heroicon-o-minus-circle',
            default => 'heroicon-o-question-mark-circle',
        };
    }

    /**
     * Retorna las opciones de estado para selects Filament.
     *
     * @return array<string, string>
     */
    public static function getStatusOptions(): array
    {
        return [
            'pending' => 'Pendiente',
            'approved' => 'Aprobado',
            'disbursed' => 'Entregado',
            'paid' => 'Descontado',
            'rejected' => 'Rechazado',
            'cancelled' => 'Cancelado',
        ];
    }

    // =========================================================================
    // HELPERS ESTÁTICOS — MÉTODO DE PAGO
    // =========================================================================

    /**
     * Retorna las opciones de método de pago para selects Filament.
     *
     * @return array<string, string>
     */
    public static function getPaymentMethodOptions(): array
    {
        return [
            'transfer' => 'Acreditación bancaria',
            'cash' => 'Efectivo',
        ];
    }

    /**
     * Retorna el label legible del método de pago.
     */
    public static function getPaymentMethodLabel(string $method): string
    {
        return match ($method) {
            'transfer' => 'Acreditación bancaria',
            'cash' => 'Efectivo',
            default => 'Desconocido',
        };
    }

    /**
     * Retorna el color semántico Filament para el método de pago.
     */
    public static function getPaymentMethodColor(string $method): string
    {
        return match ($method) {
            'transfer' => 'info',
            'cash' => 'success',
            default => 'gray',
        };
    }

    /**
     * Retorna el icono heroicon para el método de pago.
     */
    public static function getPaymentMethodIcon(string $method): string
    {
        return match ($method) {
            'transfer' => 'heroicon-o-building-library',
            'cash' => 'heroicon-o-banknotes',
            default => 'heroicon-o-question-mark-circle',
        };
    }

    // =========================================================================
    // HELPERS ESTÁTICOS — MOTIVO DE RECHAZO BANCARIO
    // =========================================================================

    /**
     * @return array<string, string>
     */
    public static function getBankRejectionReasonOptions(): array
    {
        return [
            'cuenta_inexistente' => 'Cuenta inexistente',
            'cuenta_bloqueada' => 'Cuenta bloqueada',
            'fondos_insuficientes' => 'Fondos insuficientes',
            'datos_incorrectos' => 'Datos incorrectos',
            'otro' => 'Otro',
        ];
    }

    public static function getBankRejectionReasonLabel(?string $reason): string
    {
        if ($reason === null) {
            return '-';
        }

        return match ($reason) {
            'cuenta_inexistente' => 'Cuenta inexistente',
            'cuenta_bloqueada' => 'Cuenta bloqueada',
            'fondos_insuficientes' => 'Fondos insuficientes',
            'datos_incorrectos' => 'Datos incorrectos',
            'otro' => 'Otro',
            default => $reason,
        };
    }

    // =========================================================================
    // VERIFICADORES DE ESTADO
    // =========================================================================

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isDisbursed(): bool
    {
        return $this->status === 'disbursed';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    // =========================================================================
    // ATRIBUTOS COMPUTADOS
    // =========================================================================

    /**
     * Retorna el label del estado actual.
     */
    public function getStatusLabelAttribute(): string
    {
        return self::getStatusLabel($this->status);
    }

    // =========================================================================
    // ACCIONES PRINCIPALES
    // =========================================================================

    /**
     * Aprueba el adelanto.
     *
     * Para pagos por transferencia, valida que el empleado tenga cuenta bancaria
     * principal activa. Para pagos en efectivo, omite esa validación.
     *
     * @param  int  $approvedById  ID del usuario que aprueba.
     * @param  string|null  $paymentMethod  'transfer' o 'cash'. Null usa el valor almacenado en el registro.
     * @return array{success: bool, message: string}
     */
    public function approve(int $approvedById, ?string $paymentMethod = null): array
    {
        if ($paymentMethod !== null) {
            $this->payment_method = $paymentMethod;
        }

        if (! $this->isPending()) {
            return [
                'success' => false,
                'message' => 'Solo se pueden aprobar adelantos en estado Pendiente.',
            ];
        }

        if (! $this->employee->activeContract) {
            return [
                'success' => false,
                'message' => 'El empleado no tiene un contrato activo.',
            ];
        }

        if ($this->payment_method !== 'cash' && ! $this->employee->bankAccounts()->where('is_primary', true)->where('status', 'active')->exists()) {
            return [
                'success' => false,
                'message' => 'El empleado no tiene cuenta bancaria principal activa. Registre una o seleccione pago en efectivo.',
            ];
        }

        // Verificar que no exista nómina del período actual ya generada
        $currentPeriod = $this->getCurrentPeriod();
        if ($currentPeriod && $this->payrollExistsForPeriod($currentPeriod)) {
            return [
                'success' => false,
                'message' => 'No se puede aprobar el adelanto porque la nómina del período actual ya fue generada para este empleado.',
            ];
        }

        // Verificar límite de adelantos activos por período (pending + approved + disbursed)
        $maxPerPeriod = app(PayrollSettings::class)->advance_max_per_period;
        if ($maxPerPeriod > 0) {
            $activeCount = static::where('employee_id', $this->employee_id)
                ->whereIn('status', ['pending', 'approved', 'disbursed'])
                ->where('id', '!=', $this->id)
                ->count();

            if ($activeCount >= $maxPerPeriod) {
                return [
                    'success' => false,
                    'message' => "El empleado ya tiene {$activeCount} adelanto(s) activo(s), lo que alcanza el límite de {$maxPerPeriod} por período.",
                ];
            }
        }

        // Para empleados mensuales: verificar que el total de adelantos activos no supere el salario
        if ($this->employee->activeContract->salary_type === 'mensual') {
            $salary = (float) $this->employee->activeContract->salary;
            $activeTotal = (float) static::where('employee_id', $this->employee_id)
                ->whereIn('status', ['pending', 'approved', 'disbursed'])
                ->where('id', '!=', $this->id)
                ->sum('amount');

            if (($activeTotal + (float) $this->amount) > $salary) {
                $formatted = number_format($salary - $activeTotal, 0, ',', '.');

                return [
                    'success' => false,
                    'message' => "El total de adelantos activos superaría el salario mensual del empleado. Monto disponible: Gs. {$formatted}.",
                ];
            }
        }

        // Para jornaleros: verificar que el total de adelantos activos no supere el salario
        // devengado (días efectivamente trabajados en el período actual × jornal diario).
        if ($this->employee->activeContract->salary_type === 'jornal') {
            $reference = $this->employee->getAdvanceReferenceSalary();

            if ($reference !== null) {
                $activeTotal = (float) static::where('employee_id', $this->employee_id)
                    ->whereIn('status', ['pending', 'approved', 'disbursed'])
                    ->where('id', '!=', $this->id)
                    ->sum('amount');

                if (($activeTotal + (float) $this->amount) > $reference) {
                    $formatted = number_format($reference - $activeTotal, 0, ',', '.');

                    return [
                        'success' => false,
                        'message' => "El total de adelantos activos superaría el salario devengado del jornalero. Monto disponible: Gs. {$formatted}.",
                    ];
                }
            }
        }

        $this->update([
            'status' => 'approved',
            'payment_method' => $this->payment_method,
            'approved_by_id' => $approvedById,
            'approved_at' => now(),
        ]);

        return [
            'success' => true,
            'message' => 'Adelanto aprobado. Se entregará al empleado y se descontará en la próxima liquidación de nómina.',
        ];
    }

    /**
     * Rechaza el adelanto (solo desde estado pending).
     *
     * @param  string|null  $reason  Motivo del rechazo.
     * @return array{success: bool, message: string}
     */
    public function reject(?string $reason = null): array
    {
        if (! $this->isPending()) {
            return [
                'success' => false,
                'message' => 'Solo se pueden rechazar adelantos en estado Pendiente.',
            ];
        }

        $notes = $this->notes;
        if ($reason) {
            $notes = $notes ? "{$notes}\n\nRechazo: {$reason}" : "Rechazo: {$reason}";
        }

        $this->update([
            'status' => 'rejected',
            'notes' => $notes,
        ]);

        return [
            'success' => true,
            'message' => 'Adelanto rechazado.',
        ];
    }

    /**
     * Cancela el adelanto (solo desde pending o approved).
     *
     * Un adelanto disbursed o paid no puede cancelarse — el dinero ya fue entregado.
     *
     * @param  string|null  $reason  Motivo de la cancelación.
     * @return array{success: bool, message: string}
     */
    public function cancel(?string $reason = null): array
    {
        if (! $this->isPending() && ! $this->isApproved()) {
            return [
                'success' => false,
                'message' => 'Solo se pueden cancelar adelantos en estado Pendiente o Aprobado.',
            ];
        }

        $notes = $this->notes;
        if ($reason) {
            $notes = $notes ? "{$notes}\n\nCancelación: {$reason}" : "Cancelación: {$reason}";
        }

        $this->update([
            'status' => 'cancelled',
            'notes' => $notes,
        ]);

        return [
            'success' => true,
            'message' => 'Adelanto cancelado.',
        ];
    }

    /**
     * Revierte un adelanto aprobado de vuelta a pendiente para permitir su edición.
     *
     * No disponible si el adelanto está asignado a un lote bancario.
     *
     * @return array{success: bool, message: string}
     */
    public function revertToPending(): array
    {
        if (! $this->isApproved()) {
            return [
                'success' => false,
                'message' => 'Solo se pueden desaprobar adelantos en estado Aprobado.',
            ];
        }

        if ($this->disbursement_batch_id !== null) {
            return [
                'success' => false,
                'message' => 'El adelanto pertenece a un lote de pago bancario. Retíralo del lote antes de desaprobar.',
            ];
        }

        $this->update([
            'status' => 'pending',
            'approved_at' => null,
            'approved_by_id' => null,
        ]);

        return [
            'success' => true,
            'message' => 'El adelanto volvió a estado Pendiente y puede editarse nuevamente.',
        ];
    }

    /**
     * Marca el adelanto como entregado al empleado.
     *
     * Para efectivo: el usuario marca manualmente, opcionalmente con comprobante.
     * Para transferencia individual: comprobante obligatorio (validado en la UI).
     * Para transferencia masiva: lo hace DisbursementBatch::confirm() — no llamar directamente.
     *
     * @param  string|null  $disbursedAt  Fecha de entrega (Y-m-d). Null para hoy.
     * @param  int|null  $disbursedById  ID del usuario que marca la entrega.
     * @param  string|null  $receiptPath  Ruta del comprobante (obligatorio para transfer).
     * @return array{success: bool, message: string}
     */
    public function markAsDisbursed(
        ?string $disbursedAt = null,
        ?int $disbursedById = null,
        ?string $receiptPath = null,
    ): array {
        if (! $this->isApproved()) {
            return [
                'success' => false,
                'message' => 'Solo se pueden marcar como Entregados adelantos en estado Aprobado.',
            ];
        }

        $this->update([
            'status' => 'disbursed',
            'disbursed_at' => $disbursedAt ?? now(),
            'disbursed_by_id' => $disbursedById,
            'transfer_receipt_path' => $receiptPath,
            'bank_rejection_reason' => null,
        ]);

        return [
            'success' => true,
            'message' => 'El adelanto fue marcado como Entregado y se descontará en la próxima liquidación de nómina.',
        ];
    }

    /**
     * Revierte un adelanto entregado (disbursed) de vuelta a aprobado.
     *
     * Solo aplica cuando payroll_id IS NULL — si ya fue descontado en nómina,
     * la reversión se hace eliminando la nómina correspondiente.
     *
     * @return array{success: bool, message: string}
     */
    public function revertToApproved(): array
    {
        if (! $this->isDisbursed()) {
            return [
                'success' => false,
                'message' => 'Solo se pueden revertir adelantos en estado Entregado.',
            ];
        }

        if ($this->payroll_id !== null) {
            return [
                'success' => false,
                'message' => 'Este adelanto fue descontado en nómina. Para revertirlo, eliminá la nómina correspondiente.',
            ];
        }

        if ($this->transfer_receipt_path) {
            Storage::disk('public')->delete($this->transfer_receipt_path);
        }

        $this->update([
            'status' => 'approved',
            'disbursed_at' => null,
            'disbursed_by_id' => null,
            'transfer_receipt_path' => null,
            'disbursement_batch_id' => null,
            'bank_rejection_reason' => null,
        ]);

        return [
            'success' => true,
            'message' => 'El adelanto fue revertido a Aprobado.',
        ];
    }

    /**
     * Marca el adelanto como descontado (paid) al ser procesado en nómina.
     *
     * Llamado por AdvanceCalculator::markAdvancesAsPaid().
     *
     * @param  int  $payrollId  ID de la nómina que procesó el descuento.
     */
    public function markAsPaid(int $payrollId): void
    {
        $this->update([
            'status' => 'paid',
            'payroll_id' => $payrollId,
        ]);
    }

    // =========================================================================
    // MÉTODOS DE PERÍODO
    // =========================================================================

    /**
     * Obtiene el período de nómina activo para el tipo de nómina del empleado.
     */
    protected function getCurrentPeriod(): ?PayrollPeriod
    {
        $payrollType = $this->employee->activeContract?->payroll_type;

        if (! $payrollType) {
            return null;
        }

        return PayrollPeriod::where('frequency', $payrollType)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->first();
    }

    /**
     * Verifica si ya existe nómina para el empleado en el período dado.
     */
    protected function payrollExistsForPeriod(PayrollPeriod $period): bool
    {
        return Payroll::where('employee_id', $this->employee_id)
            ->where('payroll_period_id', $period->id)
            ->exists();
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Filtra por estado.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Filtra adelantos pendientes.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Filtra adelantos aprobados.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Filtra adelantos entregados al empleado (pendientes de descuento en nómina).
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeDisbursed($query)
    {
        return $query->where('status', 'disbursed');
    }

    /**
     * Filtra adelantos de un empleado específico.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    // =========================================================================
    // MÉTODOS ESTÁTICOS DE CONSULTA
    // =========================================================================

    /**
     * Retorna el adelanto activo (pending/approved/disbursed) de un empleado, si existe.
     */
    public static function getActiveForEmployee(int $employeeId): ?self
    {
        return static::where('employee_id', $employeeId)
            ->whereIn('status', ['pending', 'approved', 'disbursed'])
            ->first();
    }

    /**
     * Cantidad de adelantos pendientes de aprobación (para badge de navegación).
     */
    public static function getPendingCount(): int
    {
        return static::where('status', 'pending')->count();
    }

    // =========================================================================
    // AUDITORÍA — PRESENTACIÓN
    // =========================================================================

    /**
     * Formatea los valores de un registro de auditoría para su presentación en el
     * AdvanceAuditsRelationManager. Muestra nombres de campo y valores legibles en español.
     *
     * @param  string  $column  'old_values' o 'new_values'
     * @param  mixed  $auditRecord  Registro de auditoría (OwenIt\Auditing\Models\Audit)
     */
    public function formatAuditFieldsForPresentation(string $column, mixed $auditRecord): HtmlString
    {
        $values = $auditRecord->{$column} ?? [];

        if (empty($values)) {
            return new HtmlString('<span class="text-gray-400 text-xs">—</span>');
        }

        $fieldLabels = [
            'status' => 'Estado',
            'payment_method' => 'Método de pago',
            'approved_by_id' => 'Aprobado por',
            'approved_at' => 'Fecha de aprobación',
            'disbursed_at' => 'Fecha de entrega',
            'disbursed_by_id' => 'Entregado por',
            'bank_rejection_reason' => 'Motivo de rechazo',
            'notes' => 'Notas',
        ];

        $html = '<ul class="space-y-0.5 text-sm">';
        foreach ($values as $key => $value) {
            $label = $fieldLabels[$key] ?? Str::headline($key);
            $formatted = $this->formatAuditValue($key, $value);
            $html .= "<li><span class=\"text-gray-500\">{$label}:</span> <span class=\"font-medium\">{$formatted}</span></li>";
        }
        $html .= '</ul>';

        return new HtmlString($html);
    }

    /**
     * Convierte el valor crudo de un campo auditado a su representación legible.
     */
    private function formatAuditValue(string $key, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($key) {
            'status' => static::getStatusLabel($value),
            'payment_method' => static::getPaymentMethodLabel($value),
            'bank_rejection_reason' => static::getBankRejectionReasonLabel($value),
            'approved_by_id', 'disbursed_by_id' => User::find($value)?->name ?? "ID {$value}",
            'approved_at', 'disbursed_at' => Carbon::parse($value)->format('d/m/Y H:i'),
            'notes' => Str::limit((string) $value, 120),
            default => (string) $value,
        };
    }
}
