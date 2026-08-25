<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega `photo_thumbnail` a employees: un data URI base64 (JPEG 64x64,
 * generado por EmployeePhotoThumbnailService) para que el terminal offline
 * pueda mostrar la foto real del empleado en la pantalla de éxito sin
 * depender de red — se sincroniza embebido en el payload JSON de
 * EmployeeDescriptorSyncService, igual que `face_descriptor`.
 *
 * No se sincroniza la foto original (potencialmente varios MB): el
 * thumbnail se recalcula en EmployeeObserver cada vez que `photo` cambia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->text('photo_thumbnail')->nullable()->after('photo');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('photo_thumbnail');
        });
    }
};
