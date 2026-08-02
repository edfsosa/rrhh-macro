<?php

use App\Models\Contract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

function seedContractPayrollSettings(int $minMonthly = 2_550_328, int $minJornal = 87_950): void
{
    $settings = [
        'min_salary_monthly' => $minMonthly,
        'min_salary_daily_jornal' => $minJornal,
    ];

    foreach ($settings as $name => $value) {
        DB::table('settings')->updateOrInsert(
            ['group' => 'payroll', 'name' => $name],
            ['payload' => json_encode($value)]
        );
    }
}

// ─── Contract::getMinSalaryFor() ─────────────────────────────────────────────

it('getMinSalaryFor retorna el mínimo mensual para salary_type mensual', function () {
    seedContractPayrollSettings(minMonthly: 2_550_328, minJornal: 87_950);

    expect(Contract::getMinSalaryFor('mensual'))->toBe(2_550_328);
});

it('getMinSalaryFor retorna el mínimo diario para salary_type jornal', function () {
    seedContractPayrollSettings(minMonthly: 2_550_328, minJornal: 87_950);

    expect(Contract::getMinSalaryFor('jornal'))->toBe(87_950);
});

it('getMinSalaryFor asume mensual cuando salary_type es nulo', function () {
    seedContractPayrollSettings(minMonthly: 2_550_328, minJornal: 87_950);

    expect(Contract::getMinSalaryFor(null))->toBe(2_550_328);
});
