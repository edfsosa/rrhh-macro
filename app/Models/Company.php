<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\DB;

class Company extends Model
{
    protected $fillable = [
        'name',
        'trade_name',
        'legal_type',
        'founded_at',
        'legal_rep_name',
        'legal_rep_ci',
        'ruc',
        'employer_number',
        'logo',
        'logo_thumbnail',
        'address',
        'phone',
        'email',
        'city',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'founded_at' => 'date',
    ];

    /**
     * Tipos societarios legales permitidos
     */
    public static array $legalTypes = [
        'EAS' => 'Empresa por Acciones Simplificadas (EAS)',
        'SA' => 'Sociedad Anónima (SA)',
        'SRL' => 'Sociedad de Resp. Limitada (SRL)',
        'SACI' => 'Sociedad Anónima de Cap. e Industria (SACI)',
        'SC' => 'Sociedad Colectiva (SC)',
        'EU' => 'Empresa Unipersonal',
        'Cooperativa' => 'Cooperativa',
        'Consorcio' => 'Consorcio',
        'Fundacion' => 'Fundación',
        'Asociacion' => 'Asociación',
    ];

    /**
     * Ciudades disponibles (pueden ser ampliadas según necesidades)
     */
    public static array $cities = [
        'Asunción',
        'Areguá',
        'Capiatá',
        'Fernando de la Mora',
        'Guarambaré',
        'Itá',
        'Itauguá',
        'J. Augusto Saldívar',
        'Lambaré',
        'Limpio',
        'Luque',
        'Mariano Roque Alonso',
        'Nueva Italia',
        'Ñemby',
        'San Antonio',
        'San Lorenzo',
        'Villa Elisa',
        'Villeta',
        'Ypacaraí',
        'Ypané',
    ];

    /**
     * Sucursales de la empresa
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /** Cuentas bancarias de la empresa. */
    public function bankAccounts(): HasMany
    {
        return $this->hasMany(CompanyBankAccount::class);
    }

    /** Cuenta bancaria principal activa de la empresa, si existe. */
    public function primaryBankAccount(): ?CompanyBankAccount
    {
        return $this->bankAccounts()
            ->where('is_primary', true)
            ->where('status', 'active')
            ->first();
    }

    /**
     * Empleados de la empresa (a través de sucursales)
     */
    public function employees(): HasManyThrough
    {
        return $this->hasManyThrough(Employee::class, Branch::class);
    }

    /** Empleados activos de la empresa (a través de sucursales). */
    public function activeEmployees(): HasManyThrough
    {
        return $this->hasManyThrough(Employee::class, Branch::class)
            ->where('employees.status', 'active');
    }

    /** Departamentos de la empresa. */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    /**
     * Nombre para mostrar (comercial o razón social)
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->trade_name ?? $this->name;
    }

    /**
     * Label del tipo societario
     */
    public function getLegalTypeLabelAttribute(): ?string
    {
        return $this->legal_type ? (static::$legalTypes[$this->legal_type] ?? $this->legal_type) : null;
    }

    /**
     * Opciones de ciudades para selects (nombre como clave y valor)
     */
    public static function citiesOptions(): array
    {
        return array_combine(static::$cities, static::$cities);
    }

    /**
     * Label de la ciudad
     */
    public function getCityLabelAttribute(): ?string
    {
        return $this->city ?? null;
    }

    /**
     * Resumen de empleados activos y total en una sola query
     */
    public function getEmployeesSummary(): string
    {
        $result = DB::table('employees')
            ->join('branches', 'branches.id', '=', 'employees.branch_id')
            ->where('branches.company_id', $this->id)
            ->selectRaw('COUNT(*) as total, SUM(employees.status = "active") as active_count')
            ->first();

        return ($result?->active_count ?? 0).' / '.($result?->total ?? 0);
    }

    /**
     * Contratos activos de todos los empleados de la empresa
     */
    public function activeContractsCount(): int
    {
        return Contract::whereIn(
            'employee_id',
            $this->employees()->select('employees.id')
        )->where('status', 'active')->count();
    }

    /**
     * Contratos activos a plazo fijo que vencen dentro de X días
     */
    public function expiringSoonContractsCount(int $days = 30): int
    {
        return Contract::whereIn(
            'employee_id',
            $this->employees()->select('employees.id')
        )->where('status', 'active')
            ->whereNotNull('end_date')
            ->where('end_date', '<=', now()->addDays($days))
            ->count();
    }

    /**
     * Scope para empresas activas
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
