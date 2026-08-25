<?php

use App\Services\DeviceHintsParser;

it('un Client Hint de modelo tiene prioridad sobre el User-Agent', function () {
    $result = DeviceHintsParser::guess('Mozilla/5.0 (iPhone; CPU iPhone OS 17_1 like Mac OS X)', 'Pixel 8 Pro');

    expect($result)->toBe(['brand' => 'Google', 'model' => 'Pixel 8 Pro']);
});

it('reconoce marcas Android comunes por prefijo del modelo', function (string $userAgent, ?string $brand, ?string $model) {
    expect(DeviceHintsParser::guess($userAgent))->toBe(['brand' => $brand, 'model' => $model]);
})->with([
    'Samsung (SM-)' => [
        'Mozilla/5.0 (Linux; Android 14; SM-S911B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
        'Samsung', 'SM-S911B',
    ],
    'Google Pixel' => [
        'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Mobile Safari/537.36',
        'Google', 'Pixel 7',
    ],
    'Motorola (moto)' => [
        'Mozilla/5.0 (Linux; Android 13; moto g73 5G) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
        'Motorola', 'moto g73 5G',
    ],
]);

it('iPhone/iPad devuelven marca Apple con modelo genérico (iOS nunca reporta el modelo real)', function (string $userAgent, string $model) {
    $result = DeviceHintsParser::guess($userAgent);

    expect($result)->toBe(['brand' => 'Apple', 'model' => $model]);
})->with([
    'iPhone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_1 like Mac OS X) AppleWebKit/605.1.15', 'iPhone'],
    'iPad' => ['Mozilla/5.0 (iPad; CPU OS 17_1 like Mac OS X) AppleWebKit/605.1.15', 'iPad'],
]);

it('Mac de escritorio da marca Apple sin modelo (no hay forma de saber cuál Mac es)', function () {
    $result = DeviceHintsParser::guess('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36');

    expect($result)->toBe(['brand' => 'Apple', 'model' => null]);
});

it('Windows de escritorio no adivina marca ni modelo (Windows no es un fabricante de hardware)', function () {
    $result = DeviceHintsParser::guess('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');

    expect($result)->toBe(['brand' => null, 'model' => null]);
});

it('sin User-Agent ni Client Hint devuelve null/null sin lanzar excepción', function () {
    expect(DeviceHintsParser::guess(null))->toBe(['brand' => null, 'model' => null])
        ->and(DeviceHintsParser::guess(''))->toBe(['brand' => null, 'model' => null]);
});

it('un modelo detectado sin marca reconocible deja la marca en null para que el admin la complete', function () {
    $result = DeviceHintsParser::guess(null, 'CustomDevice XYZ');

    expect($result)->toBe(['brand' => null, 'model' => 'CustomDevice XYZ']);
});
