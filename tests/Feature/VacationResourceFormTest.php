<?php

use App\Filament\Resources\VacationResource\Pages\CreateVacation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Regresión: el selector de empleado en el form de Vacaciones usaba 'id' como
 * relationshipTitleAttribute sin un ->searchable([...]) explícito, por lo que
 * Filament buscaba contra la columna `id` en vez del nombre — un empleado
 * activo no aparecía al buscarlo por nombre, solo escribiendo su ID numérico.
 */
it('busca el empleado por nombre, apellido o CI en el select de vacaciones, no solo por id', function () {
    $this->actingAs(User::factory()->create());

    $field = Livewire::test(CreateVacation::class)
        ->instance()
        ->form
        ->getFlatFields(withHidden: true)['employee_id'];

    expect($field->getSearchColumns())->toBe(['first_name', 'last_name', 'ci']);
});
