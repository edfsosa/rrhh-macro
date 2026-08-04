<?php

use App\Filament\Resources\AguinaldoResource\Pages\ListAguinaldos;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Regresión: el filtro de empleado mostraba el nombre completo pero solo
 * buscaba por 'first_name' (relationshipTitleAttribute), por ->searchable()
 * sin array explícito.
 */
it('busca el empleado por nombre o apellido en el filtro de tabla de aguinaldos', function () {
    $this->actingAs(User::factory()->create());

    $filter = Livewire::test(ListAguinaldos::class)
        ->instance()
        ->getTable()
        ->getFilter('employee_id');

    expect($filter->getFormField()->getSearchColumns())->toBe(['first_name', 'last_name']);
});
