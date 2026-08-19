<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

/**
 * Genera un thumbnail cuadrado de baja resolución de la foto de un empleado,
 * codificado como data URI base64. Se persiste en `Employee::photo_thumbnail`
 * (ver EmployeeObserver) para poder viajar embebido en el payload JSON de
 * sincronización offline del kiosko (ver EmployeeDescriptorSyncService) sin
 * depender de que el dispositivo tenga red para pedir la imagen original por
 * separado — la foto completa (potencialmente varios MB) nunca se sincroniza.
 *
 * Usa GD directo (ya disponible como extensión PHP) en vez de agregar una
 * dependencia nueva tipo intervention/image, ya que el único uso es este
 * recorte cuadrado + reescalado puntual.
 */
class EmployeePhotoThumbnailService
{
    /** Lado del thumbnail cuadrado, en píxeles. */
    private const SIZE = 64;

    /** Calidad JPEG (0-100) — 60 mantiene el payload chico sin artefactos visibles a este tamaño. */
    private const QUALITY = 60;

    /**
     * @param  string|null  $photoPath  Path relativo en el disco `public` (ej. `employees/photos/foo.jpg`).
     * @return string|null Data URI (`data:image/jpeg;base64,...`), o null si la foto no existe o no se pudo procesar.
     */
    public static function generate(?string $photoPath): ?string
    {
        if (! $photoPath || ! Storage::disk('public')->exists($photoPath)) {
            return null;
        }

        $source = @imagecreatefromstring(Storage::disk('public')->get($photoPath));

        if ($source === false) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $cropSize = min($width, $height);
        $cropX = (int) (($width - $cropSize) / 2);
        $cropY = (int) (($height - $cropSize) / 2);

        $thumbnail = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagecopyresampled(
            $thumbnail,
            $source,
            0,
            0,
            $cropX,
            $cropY,
            self::SIZE,
            self::SIZE,
            $cropSize,
            $cropSize
        );

        ob_start();
        imagejpeg($thumbnail, null, self::QUALITY);
        $jpegData = ob_get_clean();

        imagedestroy($source);
        imagedestroy($thumbnail);

        return 'data:image/jpeg;base64,'.base64_encode($jpegData);
    }
}
