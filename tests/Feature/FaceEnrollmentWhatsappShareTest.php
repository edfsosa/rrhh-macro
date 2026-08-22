<?php

use App\Filament\Resources\FaceEnrollmentResource;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Regresión: "Enviar por WhatsApp" existía al generar el enlace de
 * enrolamiento facial desde EmployeeResource, pero faltaba en las dos
 * acciones de FaceEnrollmentResource donde un admin recupera/reenvía el
 * enlace más tarde ("Ver Enlace" y "Regenerar Enlace").
 */
function callFaceEnrollmentWhatsappHelper(string $method, ?Employee $employee, string $url): array
{
    $reflection = new ReflectionMethod(FaceEnrollmentResource::class, $method);
    $reflection->setAccessible(true);

    return $reflection->invoke(null, $employee, $url);
}

it('genera la acción de WhatsApp para el modal "Ver Enlace" cuando el empleado tiene teléfono', function () {
    $company = Company::create(['name' => 'Empresa WA', 'ruc' => '1-1', 'employer_number' => 1]);
    $branch = Branch::create(['name' => 'Sucursal WA', 'company_id' => $company->id]);
    $employee = Employee::create([
        'first_name' => 'Ana', 'last_name' => 'Gomez', 'ci' => '1234567',
        'branch_id' => $branch->id, 'status' => 'active', 'phone' => '0981123456',
    ]);

    $actions = callFaceEnrollmentWhatsappHelper('whatsappShareAction', $employee, 'https://nominapp.test/registro-facial/abc');

    expect($actions)->toHaveCount(1);
    expect($actions[0])->toBeInstanceOf(\Filament\Tables\Actions\Action::class);
    expect($actions[0]->getUrl())->toContain('https://api.whatsapp.com/send?phone=595981123456')
        ->and($actions[0]->getUrl())->toContain(urlencode('https://nominapp.test/registro-facial/abc'));
});

it('no genera la acción de WhatsApp para "Ver Enlace" si el empleado no tiene teléfono', function () {
    $company = Company::create(['name' => 'Empresa WA2', 'ruc' => '2-2', 'employer_number' => 2]);
    $branch = Branch::create(['name' => 'Sucursal WA2', 'company_id' => $company->id]);
    $employee = Employee::create([
        'first_name' => 'Bruno', 'last_name' => 'Lopez', 'ci' => '7654321',
        'branch_id' => $branch->id, 'status' => 'active', 'phone' => null,
    ]);

    $actions = callFaceEnrollmentWhatsappHelper('whatsappShareAction', $employee, 'https://nominapp.test/registro-facial/def');

    expect($actions)->toBeEmpty();
});

it('no genera la acción de WhatsApp para "Ver Enlace" si el empleado fue eliminado', function () {
    $actions = callFaceEnrollmentWhatsappHelper('whatsappShareAction', null, 'https://nominapp.test/registro-facial/ghi');

    expect($actions)->toBeEmpty();
});

it('genera la acción de WhatsApp para la notificación de "Regenerar Enlace" cuando el empleado tiene teléfono', function () {
    $company = Company::create(['name' => 'Empresa WA3', 'ruc' => '3-3', 'employer_number' => 3]);
    $branch = Branch::create(['name' => 'Sucursal WA3', 'company_id' => $company->id]);
    $employee = Employee::create([
        'first_name' => 'Carla', 'last_name' => 'Diaz', 'ci' => '1112223',
        'branch_id' => $branch->id, 'status' => 'active', 'phone' => '0971987654',
    ]);

    $actions = callFaceEnrollmentWhatsappHelper('whatsappNotificationAction', $employee, 'https://nominapp.test/registro-facial/jkl');

    expect($actions)->toHaveCount(1);
    expect($actions[0])->toBeInstanceOf(\Filament\Notifications\Actions\Action::class);
    expect($actions[0]->getUrl())->toContain('https://api.whatsapp.com/send?phone=595971987654');
});
