<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marcación offline desde el celular personal del empleado (Parte C, Fase 1):
 * `mobile_linked_at` registra cuándo el empleado vinculó su dispositivo
 * (`Employee::claimMobileToken()`) — se usa en `EmployeeResource` para
 * mostrar el estado sin necesidad de consultar `->tokens()->exists()` por
 * fila en la tabla (evitaría un N+1). El token Sanctum en sí vive en
 * `personal_access_tokens` (polimórfica, ya existente — sin migración
 * nueva ahí, mismo mecanismo que usa `Terminal`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->timestamp('mobile_linked_at')->nullable()->after('face_descriptor');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('mobile_linked_at');
        });
    }
};
