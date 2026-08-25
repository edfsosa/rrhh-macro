<?php

use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Terminal;
use App\Services\EmployeeDescriptorSyncService;
use App\Services\EmployeePhotoThumbnailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

function makePhotoEmployee(array $attributes = []): Employee
{
    static $ci = 5000000;
    $n = $ci++;

    $company = Company::create(['name' => "Empresa Photo {$n}", 'ruc' => "{$n}-1", 'employer_number' => $n]);
    $branch = Branch::create(['name' => "Sucursal Photo {$n}", 'company_id' => $company->id]);

    return Employee::create(array_merge([
        'first_name' => 'Foto',
        'last_name' => 'Test',
        'ci' => (string) $n,
        'email' => "photo{$n}@test.com",
        'branch_id' => $branch->id,
        'status' => 'active',
    ], $attributes));
}

// ─── Tests ──────────────────────────────────────────────────────────────────

it('genera un thumbnail cuadrado de 64x64 a partir de una foto rectangular', function () {
    Storage::fake('public');
    $image = UploadedFile::fake()->image('foto.jpg', 300, 200);
    $path = $image->store('employees/photos', 'public');

    $dataUri = EmployeePhotoThumbnailService::generate($path);

    expect($dataUri)->toStartWith('data:image/jpeg;base64,');

    $decoded = base64_decode(substr($dataUri, strlen('data:image/jpeg;base64,')));
    $gdImage = imagecreatefromstring($decoded);

    expect(imagesx($gdImage))->toBe(64)
        ->and(imagesy($gdImage))->toBe(64);
});

it('devuelve null si la foto no existe o el path es null', function () {
    Storage::fake('public');

    expect(EmployeePhotoThumbnailService::generate('employees/photos/no-existe.jpg'))->toBeNull()
        ->and(EmployeePhotoThumbnailService::generate(null))->toBeNull();
});

it('el observer genera photo_thumbnail automáticamente al crear un empleado con foto', function () {
    Storage::fake('public');
    $path = UploadedFile::fake()->image('foto.jpg', 300, 200)->store('employees/photos', 'public');

    $employee = makePhotoEmployee(['photo' => $path]);

    expect($employee->photo_thumbnail)->not->toBeNull()
        ->and($employee->photo_thumbnail)->toStartWith('data:image/jpeg;base64,');
});

it('el observer limpia photo_thumbnail cuando se quita la foto', function () {
    Storage::fake('public');
    $path = UploadedFile::fake()->image('foto.jpg', 300, 200)->store('employees/photos', 'public');
    $employee = makePhotoEmployee(['photo' => $path]);

    $employee->update(['photo' => null]);

    expect($employee->fresh()->photo_thumbnail)->toBeNull();
});

it('un update que no toca la foto no regenera ni borra el thumbnail existente', function () {
    Storage::fake('public');
    $path = UploadedFile::fake()->image('foto.jpg', 300, 200)->store('employees/photos', 'public');
    $employee = makePhotoEmployee(['photo' => $path]);
    $originalThumbnail = $employee->photo_thumbnail;

    $employee->update(['first_name' => 'Otro Nombre']);

    expect($employee->fresh()->photo_thumbnail)->toBe($originalThumbnail);
});

it('el delta de sincronización del terminal incluye photo_thumbnail', function () {
    Storage::fake('public');
    $path = UploadedFile::fake()->image('foto.jpg', 300, 200)->store('employees/photos', 'public');
    $employee = makePhotoEmployee([
        'photo' => $path,
        'face_descriptor' => array_fill(0, 128, 0.1),
    ]);
    $terminal = Terminal::create(['name' => 'Terminal Test', 'branch_id' => $employee->branch_id]);

    $delta = app(EmployeeDescriptorSyncService::class)->deltaSince($terminal, null);

    expect($delta['employees'])->toHaveCount(1)
        ->and($delta['employees'][0]['photo_thumbnail'])->not->toBeNull();
});
