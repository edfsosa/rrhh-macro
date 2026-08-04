<?php

use App\Filament\Resources\LoanResource\Pages\ListLoans;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Regresión: el filtro de empleado mostraba nombre completo+CI pero solo
 * buscaba por 'first_name' (relationshipTitleAttribute), por ->searchable()
 * sin array explícito.
 */
it('busca el empleado por nombre, apellido o CI en el filtro de tabla de préstamos', function () {
    $this->actingAs(User::factory()->create());

    $filter = Livewire::test(ListLoans::class)
        ->instance()
        ->getTable()
        ->getFilter('employee_id');

    expect($filter->getFormField()->getSearchColumns())->toBe(['first_name', 'last_name', 'ci']);
});
