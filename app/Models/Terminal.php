<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

/**
 * Representa un dispositivo físico de marcación de asistencia en una sucursal.
 *
 * Puede autenticarse contra la API de sincronización offline (ver routes/api.php)
 * mediante un token Sanctum con ability `terminal:sync`, emitido al reclamar un
 * enlace de configuración (setup_token) generado desde TerminalResource.
 */
class Terminal extends Model
{
    use HasApiTokens;

    /** Ability Sanctum requerida para consumir la API de sincronización de terminales. */
    public const SYNC_ABILITY = 'terminal:sync';

    protected $fillable = [
        'name',
        'code',
        'branch_id',
        'status',
        'device_brand',
        'device_model',
        'device_serial',
        'device_mac',
        'device_notes',
        'installed_at',
        'installed_by_id',
        'last_seen_at',
        'setup_token',
        'setup_token_expires_at',
    ];

    protected $hidden = [
        'setup_token',
    ];

    protected $casts = [
        'installed_at' => 'date',
        'last_seen_at' => 'datetime',
        'setup_token_expires_at' => 'datetime',
    ];

    // =========================================================================
    // BOOT
    // =========================================================================

    /** Genera el código único automáticamente al crear la terminal. */
    protected static function booted(): void
    {
        static::creating(function (Terminal $terminal) {
            if (empty($terminal->code)) {
                $terminal->code = static::generateUniqueCode();
            }
        });
    }

    // =========================================================================
    // RELACIONES
    // =========================================================================

    /** Sucursal a la que pertenece esta terminal. */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** Usuario que instaló la terminal. */
    public function installedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'installed_by_id');
    }

    /** Eventos de asistencia registrados desde esta terminal. */
    public function attendanceEvents(): HasMany
    {
        return $this->hasMany(AttendanceEvent::class);
    }

    // =========================================================================
    // HELPERS ESTÁTICOS — LABELS, COLORES, OPCIONES
    // =========================================================================

    /**
     * Opciones de estado para Select en formularios.
     *
     * @return array<string, string>
     */
    public static function getStatusOptions(): array
    {
        return [
            'active' => 'Activa',
            'inactive' => 'Inactiva',
        ];
    }

    /**
     * Labels cortos para badges y columnas.
     *
     * @return array<string, string>
     */
    public static function getStatusLabels(): array
    {
        return [
            'active' => 'Activa',
            'inactive' => 'Inactiva',
        ];
    }

    /**
     * Colores semánticos para badges de Filament.
     *
     * @return array<string, string>
     */
    public static function getStatusColors(): array
    {
        return [
            'active' => 'success',
            'inactive' => 'danger',
        ];
    }

    // =========================================================================
    // VERIFICADORES DE ESTADO
    // =========================================================================

    /** Indica si la terminal está activa. */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** Indica si la terminal está inactiva. */
    public function isInactive(): bool
    {
        return $this->status === 'inactive';
    }

    // =========================================================================
    // HELPERS DE INSTANCIA
    // =========================================================================

    /**
     * Retorna la URL pública de la terminal.
     */
    public function getUrlAttribute(): string
    {
        return route('terminal.show', $this->code);
    }

    /**
     * Descripción del dispositivo (marca + modelo).
     */
    public function getDeviceDescriptionAttribute(): ?string
    {
        $parts = array_filter([$this->device_brand, $this->device_model]);

        return $parts ? implode(' ', $parts) : null;
    }

    // =========================================================================
    // HELPERS ESTÁTICOS
    // =========================================================================

    /**
     * Genera un código alfanumérico único de 8 caracteres para la terminal.
     */
    public static function generateUniqueCode(): string
    {
        do {
            $code = strtolower(Str::random(8));
        } while (static::where('code', $code)->exists());

        return $code;
    }

    // =========================================================================
    // PROVISIÓN — TOKEN DE CONFIGURACIÓN Y TOKEN SANCTUM
    // =========================================================================

    /**
     * Genera un token de configuración de un solo uso (para el enlace/QR de
     * provisión) y lo persiste con expiración corta. Invalida cualquier
     * enlace de configuración previo sin consumir.
     *
     * @param  int  $expiresInMinutes  Vigencia del enlace, en minutos.
     * @return string El token plano a incluir en la URL de configuración.
     */
    public function generateSetupToken(int $expiresInMinutes = 30): string
    {
        $token = Str::random(40);

        $this->forceFill([
            'setup_token' => $token,
            'setup_token_expires_at' => now()->addMinutes($expiresInMinutes),
        ])->save();

        return $token;
    }

    /** Indica si el token de configuración recibido es válido (existe, coincide y no expiró). */
    public function isSetupTokenValid(string $token): bool
    {
        return $this->setup_token !== null
            && hash_equals($this->setup_token, $token)
            && $this->setup_token_expires_at instanceof Carbon
            && $this->setup_token_expires_at->isFuture();
    }

    /**
     * Consume el token de configuración (single-use) y emite un token Sanctum
     * con la ability de sincronización. Revoca tokens `terminal:sync`
     * previos para que solo quede uno activo por terminal.
     *
     * @return string Token Sanctum en texto plano — solo se retorna una vez, nunca se persiste en claro.
     */
    public function claimSanctumToken(): string
    {
        $this->tokens()->where('name', 'like', 'kiosk:%')->delete();

        $this->forceFill([
            'setup_token' => null,
            'setup_token_expires_at' => null,
        ])->save();

        return $this->createToken('kiosk:'.$this->code, [self::SYNC_ABILITY])->plainTextToken;
    }

    /** Revoca todos los tokens Sanctum activos del terminal (fuerza re-provisión). */
    public function revokeSyncTokens(): void
    {
        $this->tokens()->delete();
    }
}
