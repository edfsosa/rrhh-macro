<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Settings\PayrollSettings;

/**
 * Calcula la bonificación familiar según Arts. 253-262 del Código del Trabajo (Ley 213/93).
 *
 * Reglas:
 *  - Solo aplica a empleados con hijos menores de 18 años registrados.
 *  - Solo aplica si el salario base del contrato no supera dos salarios mínimos mensuales.
 *  - Monto por hijo = min_salary_monthly × (family_bonus_percentage / 100).
 *  - No es percepción salarial: no computa para IPS ni aguinaldo.
 */
class FamilyBonusCalculator
{
    /**
     * Calcula la bonificación familiar para un empleado en un período dado.
     *
     * @param  PayrollPeriod  $period  No se usa para el cálculo pero mantiene la firma uniforme.
     * @return array{total: float, items: array}
     */
    public function calculate(Employee $employee, PayrollPeriod $period): array
    {
        $emptyResult = ['total' => 0.0, 'items' => []];

        // Property access: usa la relación precargada por PayrollService cuando está
        // disponible (evita 1 query por empleado en generateForPeriod); si no está
        // precargada, Eloquent la carga aquí mismo con el mismo scope, sin cambio de
        // comportamiento — la relación no depende del período, siempre es segura.
        $eligibleCount = $employee->eligibleChildren->count();

        if ($eligibleCount <= 0) {
            return $emptyResult;
        }

        $settings = app(PayrollSettings::class);

        $minSalary = $settings->min_salary_monthly;

        // Tope legal: dos salarios mínimos (Art. 254 CLT)
        $salaryBase = (float) ($employee->activeContract?->salary ?? 0);
        if ($salaryBase > $minSalary * 2) {
            return $emptyResult;
        }

        $bonusPerChild = round($minSalary * ($settings->family_bonus_percentage / 100), 2);
        $total = round($bonusPerChild * $eligibleCount, 2);

        $label = $eligibleCount === 1
            ? 'Bonificación Familiar (1 hijo menor de edad)'
            : "Bonificación Familiar ({$eligibleCount} hijos menores de edad)";

        return [
            'total' => $total,
            'items' => [
                ['description' => $label, 'amount' => $total],
            ],
        ];
    }
}
