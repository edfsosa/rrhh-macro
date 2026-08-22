<?php

use App\Imports\EmployeesImport;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

function makeImportBranch(string $name = 'Casa Central'): Branch
{
    static $n = 8800000;
    $n++;

    $company = Company::create(['name' => "Empresa Import {$n}", 'ruc' => "{$n}-1", 'employer_number' => $n]);

    return Branch::create(['name' => $name, 'company_id' => $company->id]);
}

/** @param array<int, array<int, mixed>> $rows */
function runEmployeesImport(array $rows): EmployeesImport
{
    $import = new EmployeesImport;
    $import->collection(new Collection(array_map(fn ($row) => new Collection($row), $rows)));

    return $import;
}

// ─── Tests ──────────────────────────────────────────────────────────────────

it('crea un empleado válido con status activo', function () {
    $branch = makeImportBranch();

    $import = runEmployeesImport([
        ['1111111', 'ana', 'gomez', '15/03/1990', 'Femenino', $branch->name, '0981123456', 'ana@test.com', 'Paraguaya'],
    ]);

    expect($import->created)->toBe(1)
        ->and($import->failures)->toBeEmpty();

    $employee = Employee::where('ci', '1111111')->first();
    expect($employee)->not->toBeNull()
        ->and($employee->first_name)->toBe('ANA')
        ->and($employee->status)->toBe('active')
        ->and($employee->gender)->toBe('femenino')
        ->and($employee->branch_id)->toBe($branch->id);
});

it('salta en silencio una fila completamente vacía', function () {
    $import = runEmployeesImport([
        ['', '', '', '', '', '', '', '', ''],
    ]);

    expect($import->created)->toBe(0)
        ->and($import->failures)->toBeEmpty();
});

it('reporta CI vacío como fallo', function () {
    $branch = makeImportBranch();

    $import = runEmployeesImport([
        ['', 'Ana', 'Gomez', '15/03/1990', 'Femenino', $branch->name, '', '', ''],
    ]);

    expect($import->created)->toBe(0)
        ->and($import->failures)->toHaveCount(1)
        ->and($import->failures[0]['reason'])->toBe('CI vacío');
});

it('reporta nombre o apellido faltante como fallo', function () {
    $branch = makeImportBranch();

    $import = runEmployeesImport([
        ['1111111', '', 'Gomez', '15/03/1990', 'Femenino', $branch->name, '', '', ''],
    ]);

    expect($import->created)->toBe(0)
        ->and($import->failures[0]['reason'])->toBe('Nombre y apellido son obligatorios');
});

it('rechaza una CI ya usada por otro empleado', function () {
    $branch = makeImportBranch();
    Employee::create(['first_name' => 'Existente', 'last_name' => 'X', 'ci' => '1111111', 'branch_id' => $branch->id, 'status' => 'active']);

    $import = runEmployeesImport([
        ['1111111', 'Ana', 'Gomez', '15/03/1990', 'Femenino', $branch->name, '', '', ''],
    ]);

    expect($import->created)->toBe(0)
        ->and($import->failures[0]['reason'])->toContain('Ya existe un empleado con CI');
});

it('rechaza CIs duplicadas dentro del mismo archivo (la segunda ocurrencia)', function () {
    $branch = makeImportBranch();

    $import = runEmployeesImport([
        ['1111111', 'Ana', 'Gomez', '15/03/1990', 'Femenino', $branch->name, '', '', ''],
        ['1111111', 'Ana', 'Otra', '15/03/1990', 'Femenino', $branch->name, '', '', ''],
    ]);

    expect($import->created)->toBe(1)
        ->and($import->failures)->toHaveCount(1)
        ->and($import->failures[0]['reason'])->toContain('Ya existe un empleado con CI');
});

it('rechaza una fecha de nacimiento con formato inválido', function () {
    $branch = makeImportBranch();

    $import = runEmployeesImport([
        ['1111111', 'Ana', 'Gomez', 'no-es-una-fecha', 'Femenino', $branch->name, '', '', ''],
    ]);

    expect($import->created)->toBe(0)
        ->and($import->failures[0]['reason'])->toContain('Fecha de nacimiento inválida');
});

it('rechaza a un empleado menor de 18 años', function () {
    $branch = makeImportBranch();
    $recentDate = now()->subYears(10)->format('d/m/Y');

    $import = runEmployeesImport([
        ['1111111', 'Ana', 'Gomez', $recentDate, 'Femenino', $branch->name, '', '', ''],
    ]);

    expect($import->created)->toBe(0)
        ->and($import->failures[0]['reason'])->toBe('El empleado debe ser mayor de 18 años');
});

it('acepta variantes de género (M, F, Masculino, Femenino) sin distinguir mayúsculas', function () {
    $branch = makeImportBranch();

    $import = runEmployeesImport([
        ['1111111', 'Uno', 'A', '15/03/1990', 'm', $branch->name, '', '', ''],
        ['2222222', 'Dos', 'B', '15/03/1990', 'F', $branch->name, '', '', ''],
        ['3333333', 'Tres', 'C', '15/03/1990', 'MASCULINO', $branch->name, '', '', ''],
    ]);

    expect($import->created)->toBe(3)
        ->and($import->failures)->toBeEmpty();

    expect(Employee::where('ci', '1111111')->value('gender'))->toBe('masculino')
        ->and(Employee::where('ci', '2222222')->value('gender'))->toBe('femenino')
        ->and(Employee::where('ci', '3333333')->value('gender'))->toBe('masculino');
});

it('rechaza un género que no es reconocible', function () {
    $branch = makeImportBranch();

    $import = runEmployeesImport([
        ['1111111', 'Ana', 'Gomez', '15/03/1990', 'Otro', $branch->name, '', '', ''],
    ]);

    expect($import->created)->toBe(0)
        ->and($import->failures[0]['reason'])->toContain("Género inválido: 'Otro'");
});

it('rechaza una sucursal que no existe, sin distinguir mayúsculas', function () {
    $branch = makeImportBranch('Casa Central');

    $import = runEmployeesImport([
        ['1111111', 'Ana', 'Gomez', '15/03/1990', 'Femenino', 'Sucursal Inexistente', '', '', ''],
    ]);

    expect($import->created)->toBe(0)
        ->and($import->failures[0]['reason'])->toBe("Sucursal no encontrada: 'Sucursal Inexistente'");
});

it('encuentra la sucursal sin distinguir mayúsculas ni espacios extra', function () {
    $branch = makeImportBranch('Casa Central');

    $import = runEmployeesImport([
        ['1111111', 'Ana', 'Gomez', '15/03/1990', 'Femenino', '  casa central  ', '', '', ''],
    ]);

    expect($import->created)->toBe(1)
        ->and(Employee::where('ci', '1111111')->value('branch_id'))->toBe($branch->id);
});

it('rechaza un email ya usado por otro empleado', function () {
    $branch = makeImportBranch();
    Employee::create(['first_name' => 'Existente', 'last_name' => 'X', 'ci' => '9999999', 'email' => 'ana@test.com', 'branch_id' => $branch->id, 'status' => 'active']);

    $import = runEmployeesImport([
        ['1111111', 'Ana', 'Gomez', '15/03/1990', 'Femenino', $branch->name, '', 'ana@test.com', ''],
    ]);

    expect($import->created)->toBe(0)
        ->and($import->failures[0]['reason'])->toContain('Ya existe un empleado con el email');
});

it('usa Paraguaya como nacionalidad por defecto si se deja vacía', function () {
    $branch = makeImportBranch();

    runEmployeesImport([
        ['1111111', 'Ana', 'Gomez', '15/03/1990', 'Femenino', $branch->name, '', '', ''],
    ]);

    expect(Employee::where('ci', '1111111')->value('nationality'))->toBe('Paraguaya');
});

it('acepta una fecha de nacimiento como instancia DateTime (celda Excel formateada como fecha)', function () {
    $branch = makeImportBranch();

    $import = runEmployeesImport([
        ['1111111', 'Ana', 'Gomez', new DateTime('1985-06-20'), 'Femenino', $branch->name, '', '', ''],
    ]);

    expect($import->created)->toBe(1);
    expect(Employee::where('ci', '1111111')->first()->birth_date->format('Y-m-d'))->toBe('1985-06-20');
});
