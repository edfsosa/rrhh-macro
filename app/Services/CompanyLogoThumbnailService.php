<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

/**
 * Genera un thumbnail de baja resolución del logo de una empresa, codificado
 * como data URI PNG en base64. Se persiste en `Company::logo_thumbnail` (ver
 * CompanyObserver) para viajar embebido en el shell cacheado del kiosko
 * (`window.terminalData`) y en el payload de heartbeat del celular, sin
 * depender de red para pedir la imagen original por separado.
 *
 * A diferencia de EmployeePhotoThumbnailService (recorte cuadrado + JPEG,
 * apto para un rostro), acá se ajusta preservando la proporción original —
 * los logos rara vez son cuadrados, un recorte centrado le cortaría contenido
 * a un logotipo ancho — y se exporta en PNG para conservar transparencia
 * (el logo convive con headers en tema claro y oscuro).
 *
 * SVG no se procesa: GD no puede rasterizar vectores. `imagecreatefromstring`
 * simplemente falla sobre contenido SVG (no es un formato de imagen binario
 * reconocible) y esta clase devuelve null, igual que si no hubiera logo.
 */
class CompanyLogoThumbnailService
{
    /** Ancho máximo del thumbnail, en píxeles. */
    private const MAX_WIDTH = 240;

    /** Alto máximo del thumbnail, en píxeles. */
    private const MAX_HEIGHT = 80;

    /** Nivel de compresión PNG (0-9) — 6 es el balance por defecto de GD entre tamaño y velocidad. */
    private const COMPRESSION = 6;

    /**
     * @param  string|null  $logoPath  Path relativo en el disco `public` (ej. `companies/logos/foo.png`).
     * @return string|null Data URI (`data:image/png;base64,...`), o null si el logo no existe, es SVG, o no se pudo procesar.
     */
    public static function generate(?string $logoPath): ?string
    {
        if (! $logoPath || ! Storage::disk('public')->exists($logoPath)) {
            return null;
        }

        $source = @imagecreatefromstring(Storage::disk('public')->get($logoPath));

        if ($source === false) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);

        // Nunca se agranda un logo más chico que el bounding box — solo se achica si excede.
        $scale = min(self::MAX_WIDTH / $width, self::MAX_HEIGHT / $height, 1);
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $thumbnail = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
        $transparent = imagecolorallocatealpha($thumbnail, 0, 0, 0, 127);
        imagefill($thumbnail, 0, 0, $transparent);

        imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        ob_start();
        imagepng($thumbnail, null, self::COMPRESSION);
        $pngData = ob_get_clean();

        imagedestroy($source);
        imagedestroy($thumbnail);

        return 'data:image/png;base64,'.base64_encode($pngData);
    }
}
