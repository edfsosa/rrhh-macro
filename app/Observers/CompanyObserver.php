<?php

namespace App\Observers;

use App\Models\Company;
use App\Services\CompanyLogoThumbnailService;

class CompanyObserver
{
    /**
     * Regenera `logo_thumbnail` en la misma escritura que cambia `logo` — en
     * `saving()` (no `updated()`/`created()`) para que quede en el mismo
     * INSERT/UPDATE, sin una segunda query. Mismo patrón que
     * EmployeeObserver::saving() para `photo_thumbnail`.
     */
    public function saving(Company $company): void
    {
        if ($company->isDirty('logo')) {
            $company->logo_thumbnail = CompanyLogoThumbnailService::generate($company->logo);
        }
    }
}
