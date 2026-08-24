<?php

use App\Filament\Resources\AttendanceMarkFailureResource;
use App\Filament\Resources\EmployeeResource;
use App\Filament\Resources\TerminalResource;
use App\Models\AttendanceMarkFailure;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Department;
use App\Models\Employee;
use App\Models\FaceEnrollment;
use App\Models\Position;
use App\Models\Terminal;
use App\Models\User;
use App\Notifications\FaceEnrollmentPendingApprovalNotification;
use App\Notifications\MobileDeviceLinkedNotification;
use App\Notifications\MobileDeviceRelinkedNotification;
use App\Notifications\SyncConflictPendingNotification;
use App\Notifications\TerminalProvisionedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

function makeNotifiableEmployee(): Employee
{
    static $ci = 9700000;
    $n = $ci++;

    $company = Company::create(['name' => "Empresa Notif {$n}", 'ruc' => "{$n}-1", 'employer_number' => $n]);
    $branch = Branch::create(['name' => "Sucursal Notif {$n}", 'company_id' => $company->id]);
    $department = Department::create(['name' => "Depto Notif {$n}", 'company_id' => $company->id]);
    $position = Position::create(['name' => "Cargo Notif {$n}", 'department_id' => $department->id]);

    $employee = Employee::create([
        'first_name' => 'Notif', 'last_name' => 'Test', 'ci' => (string) $n,
        'birth_date' => '1990-05-15', 'email' => "notif{$n}@test.com",
        'branch_id' => $branch->id, 'status' => 'active',
    ]);

    Contract::create([
        'employee_id' => $employee->id, 'type' => 'indefinido', 'start_date' => now()->subYear(),
        'salary_type' => 'mensual', 'salary' => 2_550_000, 'position_id' => $position->id,
        'department_id' => $department->id, 'status' => 'active',
    ]);

    return $employee->fresh();
}

// ─── Terminal provisionado ──────────────────────────────────────────────────

it('notifica a los admins cuando un terminal completa su provisión', function () {
    $employee = makeNotifiableEmployee();
    $admin = User::create(['name' => 'Admin', 'email' => 'admin-term@test.com', 'password' => bcrypt('secret')]);
    $terminal = Terminal::create(['name' => 'Kiosko Test', 'branch_id' => $employee->branch_id]);

    Notification::fake();
    $terminal->claimSanctumToken();

    Notification::assertSentTo(
        $admin,
        TerminalProvisionedNotification::class,
        fn ($notification) => $notification->terminal->is($terminal)
    );
});

it('la url de la notificación de terminal apunta al detalle real del recurso', function () {
    $employee = makeNotifiableEmployee();
    $terminal = Terminal::create(['name' => 'Kiosko Test', 'branch_id' => $employee->branch_id]);

    $notification = new TerminalProvisionedNotification($terminal);
    $data = $notification->toDatabase(new User);

    expect($data['actions'][0]['url'])->toBe(TerminalResource::getUrl('view', ['record' => $terminal]));
});

it('la notificación de terminal provisionado también se envía por email', function () {
    $employee = makeNotifiableEmployee();
    $terminal = Terminal::create(['name' => 'Kiosko Test', 'branch_id' => $employee->branch_id]);

    $notification = new TerminalProvisionedNotification($terminal);

    expect($notification->via(new User))->toBe(['database', 'mail']);

    $mail = $notification->toMail(new User);

    expect($mail->subject)->toBe('Terminal provisionado — Kiosko Test')
        ->and($mail->actionUrl)->toBe(TerminalResource::getUrl('view', ['record' => $terminal]));
});

// ─── Autoenrolamiento pendiente de aprobación ──────────────────────────────

it('notifica a los admins cuando un empleado completa el autoenrolamiento facial', function () {
    $employee = makeNotifiableEmployee();
    $admin = User::create(['name' => 'Admin', 'email' => 'admin-enroll@test.com', 'password' => bcrypt('secret')]);

    $enrollment = FaceEnrollment::create([
        'employee_id' => $employee->id,
        'token' => Str::random(40),
        'status' => 'pending_capture',
        'expires_at' => now()->addHours(48),
    ]);

    Notification::fake();

    $this->postJson("/registro-facial/{$enrollment->token}", [
        'face_descriptor' => array_fill(0, 128, 0.2),
    ])->assertOk();

    Notification::assertSentTo(
        $admin,
        FaceEnrollmentPendingApprovalNotification::class,
        fn ($notification) => $notification->enrollment->is($enrollment->fresh())
    );
});

// ─── Conflicto de sync pendiente ───────────────────────────────────────────

it('notifica a los admins cuando se registra un conflicto de sincronización', function () {
    $employee = makeNotifiableEmployee();
    $admin = User::create(['name' => 'Admin', 'email' => 'admin-conflict@test.com', 'password' => bcrypt('secret')]);

    Notification::fake();

    $failure = AttendanceMarkFailure::record([
        'mode' => 'mobile',
        'failure_type' => 'sync_conflict',
        'employee_id' => $employee->id,
        'branch_id' => $employee->branch_id,
        'attempted_event_type' => 'break_start',
        'failure_message' => 'test',
    ]);

    Notification::assertSentTo(
        $admin,
        SyncConflictPendingNotification::class,
        fn ($notification) => $notification->failure->is($failure)
    );
});

it('no notifica a los admins por fallos que no son conflictos de sincronización', function () {
    User::create(['name' => 'Admin', 'email' => 'admin-noconflict@test.com', 'password' => bcrypt('secret')]);

    Notification::fake();

    AttendanceMarkFailure::record([
        'mode' => 'terminal',
        'failure_type' => 'face_no_match',
        'failure_message' => 'test',
    ]);

    Notification::assertNothingSent();
});

it('la url de la notificación de conflicto apunta al detalle real del fallo', function () {
    $employee = makeNotifiableEmployee();
    $failure = AttendanceMarkFailure::record([
        'mode' => 'mobile',
        'failure_type' => 'sync_conflict',
        'employee_id' => $employee->id,
        'branch_id' => $employee->branch_id,
        'attempted_event_type' => 'break_start',
        'failure_message' => 'test',
    ]);

    $notification = new SyncConflictPendingNotification($failure);
    $data = $notification->toDatabase(new User);

    expect($data['actions'][0]['url'])->toBe(AttendanceMarkFailureResource::getUrl('view', ['record' => $failure]));
});

// ─── Fix de URL en notificaciones de dispositivo ───────────────────────────

it('las notificaciones de dispositivo apuntan a la ficha real del empleado', function () {
    $employee = makeNotifiableEmployee();

    $linked = new MobileDeviceLinkedNotification($employee);
    $relinked = new MobileDeviceRelinkedNotification($employee);

    $expectedUrl = EmployeeResource::getUrl('view', ['record' => $employee]);

    expect($linked->toDatabase(new User)['actions'][0]['url'])->toBe($expectedUrl)
        ->and($relinked->toDatabase(new User)['actions'][0]['url'])->toBe($expectedUrl);
});

it('la notificación de re-vinculación también se envía por email, la de primera vinculación no', function () {
    $employee = makeNotifiableEmployee();

    $linked = new MobileDeviceLinkedNotification($employee);
    $relinked = new MobileDeviceRelinkedNotification($employee, 'Mozilla/5.0 Test');

    expect($linked->via(new User))->toBe(['database'])
        ->and($relinked->via(new User))->toBe(['database', 'mail']);

    $mail = $relinked->toMail(new User);

    expect($mail->subject)->toBe("Dispositivo re-vinculado — {$employee->full_name}")
        ->and($mail->actionUrl)->toBe(EmployeeResource::getUrl('view', ['record' => $employee]))
        ->and(collect($mail->introLines)->implode(' '))->toContain('Dispositivo nuevo: Mozilla/5.0 Test');
});
