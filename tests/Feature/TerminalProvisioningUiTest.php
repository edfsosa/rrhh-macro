<?php

use App\Filament\Resources\TerminalResource\Pages\CreateTerminal;
use App\Filament\Resources\TerminalResource\Pages\ViewTerminal;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Terminal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

function makeProvisioningTerminal(): Terminal
{
    static $n = 7500000;
    $n++;

    $company = Company::create(['name' => "Empresa Prov {$n}", 'ruc' => "{$n}-1", 'employer_number' => $n]);
    $branch = Branch::create(['name' => "Sucursal Prov {$n}", 'company_id' => $company->id]);

    return Terminal::create(['name' => 'Kiosko Prov', 'branch_id' => $branch->id]);
}

// ─── Tests ──────────────────────────────────────────────────────────────────

/**
 * Regresión: el QR se generaba como SVG y se inyectaba con
 * TextEntry->html(), lo que activa el sanitizador HTML de Filament (Symfony
 * HtmlSanitizer) — que elimina el <svg> completo porque no está en su lista
 * de elementos "seguros", dejando el QR invisible. Un data URI en
 * ImageEntry evita el sanitizador por completo.
 */
it('el QR de acceso es un data URI SVG válido, no HTML crudo', function () {
    $this->actingAs(User::factory()->create());
    $terminal = makeProvisioningTerminal();

    $component = collect(
        Livewire::test(ViewTerminal::class, ['record' => $terminal->getKey()])
            ->instance()
            ->getInfolist('infolist')
            ->getFlatComponents()
    )->first(fn ($c) => method_exists($c, 'getName') && $c->getName() === 'qr_code');

    expect($component)->not->toBeNull();

    $state = $component->getState();
    expect($state)->toStartWith('data:image/svg+xml;base64,');

    $decoded = base64_decode(substr($state, strpos($state, ',') + 1));
    expect($decoded)->toContain('<svg');
});

it('sin ?provision=1 no abre el modal de generar enlace automáticamente', function () {
    $this->actingAs(User::factory()->create());
    $terminal = makeProvisioningTerminal();

    $test = Livewire::test(ViewTerminal::class, ['record' => $terminal->getKey()]);

    expect($test->get('mountedActions'))->toBe([]);
});

/**
 * Regresión de implementación: mountAction() no puede llamarse dentro de
 * mount() porque cachedActions recién se puebla en el hook de Livewire
 * bootedInteractsWithHeaderActions(), que corre después — hacerlo antes
 * desmonta la acción de inmediato sin abrir el modal.
 */
it('con ?provision=1 abre el modal de generar enlace automáticamente tras crear', function () {
    $this->actingAs(User::factory()->create());
    $terminal = makeProvisioningTerminal();

    $test = Livewire::withQueryParams(['provision' => '1'])
        ->test(ViewTerminal::class, ['record' => $terminal->getKey()]);

    expect($test->get('mountedActions'))->toBe(['generate_setup_link']);
});

it('el redirect tras crear un terminal apunta a su vista con ?provision=1', function () {
    $terminal = makeProvisioningTerminal();

    $page = new CreateTerminal;
    $page->record = $terminal;

    $reflection = new ReflectionMethod(CreateTerminal::class, 'getRedirectUrl');
    $reflection->setAccessible(true);
    $redirectUrl = $reflection->invoke($page);

    expect($redirectUrl)->toContain("/terminales/{$terminal->id}")
        ->and($redirectUrl)->toEndWith('?provision=1');
});

it('la acción "Revocar token" solo es visible en el detalle si el terminal tiene un token activo', function () {
    $this->actingAs(User::factory()->create());
    $terminal = makeProvisioningTerminal();

    $withoutToken = Livewire::test(ViewTerminal::class, ['record' => $terminal->getKey()]);
    $action = collect($withoutToken->instance()->getCachedHeaderActions())
        ->first(fn ($a) => $a->getName() === 'revoke_token');

    expect($action->isVisible())->toBeFalse();

    $terminal->claimSanctumToken();

    $withToken = Livewire::test(ViewTerminal::class, ['record' => $terminal->fresh()->getKey()]);
    $action = collect($withToken->instance()->getCachedHeaderActions())
        ->first(fn ($a) => $a->getName() === 'revoke_token');

    expect($action->isVisible())->toBeTrue();
});

it('el detalle del terminal expone la acción "Generar enlace de configuración"', function () {
    $this->actingAs(User::factory()->create());
    $terminal = makeProvisioningTerminal();

    $test = Livewire::test(ViewTerminal::class, ['record' => $terminal->getKey()]);
    $action = collect($test->instance()->getCachedHeaderActions())
        ->first(fn ($a) => $a->getName() === 'generate_setup_link');

    expect($action)->not->toBeNull();
});

// ─── DeviceHintsParser: marca/modelo sugeridos al provisionar ──────────────

it('claimSanctumToken() guarda el User-Agent y sugiere marca/modelo cuando el terminal no tiene datos cargados', function () {
    $terminal = makeProvisioningTerminal();

    $terminal->claimSanctumToken('Mozilla/5.0 (iPhone; CPU iPhone OS 17_1 like Mac OS X) AppleWebKit/605.1.15');
    $terminal->refresh();

    expect($terminal->user_agent)->toContain('iPhone')
        ->and($terminal->device_brand)->toBe('Apple')
        ->and($terminal->device_model)->toBe('iPhone');
});

it('claimSanctumToken() NO pisa marca/modelo cargados manualmente al reprovisionar el mismo terminal', function () {
    $terminal = makeProvisioningTerminal();
    $terminal->update(['device_brand' => 'Apple (corregido a mano)', 'device_model' => 'iPad Pro 12.9 2022']);

    $terminal->claimSanctumToken('Mozilla/5.0 (iPhone; CPU iPhone OS 17_1 like Mac OS X)');
    $terminal->refresh();

    expect($terminal->device_brand)->toBe('Apple (corregido a mano)')
        ->and($terminal->device_model)->toBe('iPad Pro 12.9 2022');
});

it('POST .../claim pasa el device_model_hint del cliente hasta el terminal provisionado', function () {
    $terminal = makeProvisioningTerminal();
    $setupToken = $terminal->generateSetupToken();

    $this->postJson("/terminal/{$terminal->code}/setup/{$setupToken}/claim", [
        'device_model_hint' => 'Pixel 8 Pro',
    ])->assertOk()->assertJson(['ok' => true]);

    $terminal->refresh();
    expect($terminal->device_brand)->toBe('Google')
        ->and($terminal->device_model)->toBe('Pixel 8 Pro');
});
