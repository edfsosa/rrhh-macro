<?php

use App\Filament\Widgets\LatestAttendances;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Regresión: el filtro de empleado en el widget de últimas marcaciones
 * mostraba nombre+apellido+CI pero solo buscaba por 'first_name'
 * (relationshipTitleAttribute), por ->searchable() sin array explícito.
 */
it('busca el empleado por nombre, apellido o CI en el filtro del widget de últimas marcaciones', function () {
    $this->actingAs(User::factory()->create());

    $filter = Livewire::test(LatestAttendances::class)
        ->instance()
        ->getTable()
        ->getFilter('employee_id');

    expect($filter->getFormField()->getSearchColumns())->toBe(['first_name', 'last_name', 'ci']);
});
