<?php

use App\Models\Company;
use App\Services\CompanyLogoThumbnailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

function makeLogoCompany(array $attributes = []): Company
{
    static $n = 6000000;
    $n++;

    return Company::create(array_merge([
        'name' => "Empresa Logo {$n}",
        'ruc' => "{$n}-1",
        'employer_number' => $n,
    ], $attributes));
}

// ─── Tests ──────────────────────────────────────────────────────────────────

it('genera un thumbnail PNG preservando la proporción de un logo rectangular', function () {
    Storage::fake('public');
    $image = UploadedFile::fake()->image('logo.png', 800, 200);
    $path = $image->store('companies/logos', 'public');

    $dataUri = CompanyLogoThumbnailService::generate($path);

    expect($dataUri)->toStartWith('data:image/png;base64,');

    $decoded = base64_decode(substr($dataUri, strlen('data:image/png;base64,')));
    $gdImage = imagecreatefromstring($decoded);

    // 800x200 ajustado al bounding box 240x80 preservando proporción -> 240x60
    expect(imagesx($gdImage))->toBe(240)
        ->and(imagesy($gdImage))->toBe(60);
});

it('no agranda un logo más chico que el bounding box máximo', function () {
    Storage::fake('public');
    $path = UploadedFile::fake()->image('logo.png', 50, 30)->store('companies/logos', 'public');

    $dataUri = CompanyLogoThumbnailService::generate($path);
    $decoded = base64_decode(substr($dataUri, strlen('data:image/png;base64,')));
    $gdImage = imagecreatefromstring($decoded);

    expect(imagesx($gdImage))->toBe(50)
        ->and(imagesy($gdImage))->toBe(30);
});

it('devuelve null para un logo SVG (GD no puede rasterizarlo)', function () {
    Storage::fake('public');
    Storage::disk('public')->put('companies/logos/vector.svg', '<svg xmlns="http://www.w3.org/2000/svg"><rect width="10" height="10"/></svg>');

    expect(CompanyLogoThumbnailService::generate('companies/logos/vector.svg'))->toBeNull();
});

it('devuelve null si el logo no existe o el path es null', function () {
    Storage::fake('public');

    expect(CompanyLogoThumbnailService::generate('companies/logos/no-existe.png'))->toBeNull()
        ->and(CompanyLogoThumbnailService::generate(null))->toBeNull();
});

it('el observer genera logo_thumbnail automáticamente al crear una empresa con logo', function () {
    Storage::fake('public');
    $path = UploadedFile::fake()->image('logo.png', 300, 100)->store('companies/logos', 'public');

    $company = makeLogoCompany(['logo' => $path]);

    expect($company->logo_thumbnail)->not->toBeNull()
        ->and($company->logo_thumbnail)->toStartWith('data:image/png;base64,');
});

it('el observer limpia logo_thumbnail cuando se quita el logo', function () {
    Storage::fake('public');
    $path = UploadedFile::fake()->image('logo.png', 300, 100)->store('companies/logos', 'public');
    $company = makeLogoCompany(['logo' => $path]);

    $company->update(['logo' => null]);

    expect($company->fresh()->logo_thumbnail)->toBeNull();
});

it('un update que no toca el logo no regenera ni borra el thumbnail existente', function () {
    Storage::fake('public');
    $path = UploadedFile::fake()->image('logo.png', 300, 100)->store('companies/logos', 'public');
    $company = makeLogoCompany(['logo' => $path]);
    $originalThumbnail = $company->logo_thumbnail;

    $company->update(['name' => 'Otro Nombre']);

    expect($company->fresh()->logo_thumbnail)->toBe($originalThumbnail);
});
