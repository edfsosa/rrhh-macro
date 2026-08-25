<?php

use App\Models\Company;
use App\Services\CompanyLogoThumbnailService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega `logo_thumbnail` a companies: un data URI base64 (PNG, ajustado
 * preservando proporción, generado por CompanyLogoThumbnailService) para que
 * el header persistente de ambos modos de marcación (celular y kiosko) pueda
 * mostrar el logo real sin depender de red — se sincroniza embebido en el
 * shell cacheado del kiosko y en el payload de heartbeat del celular, igual
 * que `photo_thumbnail` en employees (PR #83).
 *
 * A diferencia de `photo_thumbnail`, acá sí se hace backfill: la cantidad de
 * empresas por instalación es chica (a diferencia de miles de empleados), así
 * que no tiene sentido dejar el logo de una empresa ya configurada sin
 * thumbnail hasta que un admin la vuelva a guardar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->text('logo_thumbnail')->nullable()->after('logo');
        });

        Company::query()->whereNotNull('logo')->select(['id', 'logo'])->each(function (Company $company) {
            $company->forceFill([
                'logo_thumbnail' => CompanyLogoThumbnailService::generate($company->logo),
            ])->save();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('logo_thumbnail');
        });
    }
};
