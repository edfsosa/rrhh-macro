<?php

namespace App\Services;

use App\Models\Advance;
use App\Models\Deduction;
use App\Models\Employee;
use App\Models\EmployeeDeduction;
use App\Models\PayrollPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Procesa adelantos de salario pendientes de descuento en el período de nómina.
 *
 * Flujo de integración con nómina:
 *  1. calculate()             → crea EmployeeDeduction con ADE001 para cada adelanto aprobado sin payroll_id.
 *  2. DeductionCalculator     → procesa la EmployeeDeduction junto al resto de deducciones.
 *  3. markAdvancesAsPaid()    → marca el adelanto como pagado y registra el payroll_id.
 *
 * Los préstamos en cuotas son manejados por LoanInstallmentCalculator.
 */
class AdvanceCalculator
{
    /** @var int|null ID de la deducción ADE001 (Adelanto de Salario), cacheado por instancia. */
    private ?int $advanceDeductionId = null;

    /**
     * Identifica adelantos aprobados pendientes de descuento, crea sus EmployeeDeduction
     * y retorna la colección para trazabilidad posterior.
     *
     * Solo procesa adelantos con status='disbursed' y payroll_id IS NULL.
     *
     * @return array{advances: Collection}
     */
    public function calculate(Employee $employee, PayrollPeriod $period): array
    {
        $processedAdvances = collect();

        $deductionId = $this->getAdvanceDeductionId();

        if ($deductionId === null) {
            Log::warning("AdvanceCalculator: deducción ADE001 no encontrada — adelantos omitidos para CI {$employee->ci} {$employee->first_name}. Verificá el seeder.", [
                'employee_id' => $employee->id,
                'period_id' => $period->id,
            ]);

            return ['advances' => $processedAdvances];
        }

        // Adelantos entregados al empleado (disbursed), sin nómina asignada aún
        $advances = Advance::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'disbursed')
            ->whereNull('payroll_id')
            ->lockForUpdate()
            ->get();

        foreach ($advances as $advance) {
            $notes = 'Adelanto de Salario';

            // Idempotente: si ya tiene EmployeeDeduction (regeneración de nómina),
            // actualiza el monto; si no, crea uno nuevo.
            if ($advance->employee_deduction_id) {
                $employeeDeduction = EmployeeDeduction::find($advance->employee_deduction_id);

                if ($employeeDeduction) {
                    $employeeDeduction->update([
                        'custom_amount' => (float) $advance->amount,
                        'notes' => $notes,
                    ]);
                } else {
                    $employeeDeduction = $this->createEmployeeDeduction(
                        $employee->id, $deductionId, $advance, $notes
                    );
                }
            } else {
                $employeeDeduction = $this->createEmployeeDeduction(
                    $employee->id, $deductionId, $advance, $notes
                );
            }

            $advance->update(['employee_deduction_id' => $employeeDeduction->id]);

            $processedAdvances->push($advance);
        }

        return ['advances' => $processedAdvances];
    }

    /**
     * Marca los adelantos como pagados una vez creada la nómina.
     *
     * Llamado por PayrollService después de crear el registro de nómina.
     *
     * @param  array  $advanceIds  IDs de los adelantos a marcar como pagados.
     * @param  int  $payrollId  ID de la nómina que cubre estos adelantos.
     * @return int Número de adelantos marcados como pagados.
     */
    public function markAdvancesAsPaid(array $advanceIds, int $payrollId): int
    {
        if (empty($advanceIds)) {
            return 0;
        }

        $advances = Advance::whereIn('id', $advanceIds)
            ->where('status', 'disbursed')
            ->get();

        foreach ($advances as $advance) {
            $advance->markAsPaid($payrollId);
        }

        return $advances->count();
    }

    // =========================================================================
    // HELPERS PRIVADOS
    // =========================================================================

    /**
     * Crea un EmployeeDeduction puntual para el adelanto.
     *
     * start_date y end_date se fijan al inicio del período para que
     * DeductionCalculator lo recoja en este ciclo específico.
     */
    private function createEmployeeDeduction(
        int $employeeId,
        int $deductionId,
        Advance $advance,
        string $notes,
    ): EmployeeDeduction {
        return EmployeeDeduction::create([
            'employee_id' => $employeeId,
            'deduction_id' => $deductionId,
            'start_date' => $advance->approved_at?->toDateString() ?? now()->toDateString(),
            'end_date' => $advance->approved_at?->toDateString() ?? now()->toDateString(),
            'custom_amount' => (float) $advance->amount,
            'notes' => $notes,
        ]);
    }

    /**
     * Retorna el ID de la deducción ADE001, cacheado para el ciclo de nómina.
     */
    private function getAdvanceDeductionId(): ?int
    {
        if ($this->advanceDeductionId === null) {
            $this->advanceDeductionId = Deduction::where('code', 'ADE001')->value('id');
        }

        return $this->advanceDeductionId;
    }
}
