<?php

use App\Models\Deduction;
use App\Models\Perception;

it('Perception::calculateAmount acepta una base salarial fraccionaria sin error', function () {
    $perception = new Perception([
        'calculation' => 'percentage',
        'percent' => 9,
    ]);

    // 1,000,000.50 × 9% = 90,000.045 → redondeado a entero (Perception siempre retorna Gs. enteros)
    expect($perception->calculateAmount(1_000_000.50))->toBe(90_000);
});

it('Deduction::calculateAmount preserva los decimales de una base salarial fraccionaria', function () {
    $deduction = new Deduction([
        'calculation' => 'percentage',
        'percent' => 9,
    ]);

    // 1,000,000.50 × 9% = 90,000.045 → round(...,2) = 90,000.05, no se trunca antes de multiplicar
    expect($deduction->calculateAmount(1_000_000.50))->toBe(90_000.05);
});

it('Deduction::calculateAmount con base entera produce el mismo resultado que antes', function () {
    $deduction = new Deduction([
        'calculation' => 'percentage',
        'percent' => 9,
    ]);

    expect($deduction->calculateAmount(1_000_000))->toBe(90_000.0);
});
