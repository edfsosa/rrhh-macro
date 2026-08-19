{{-- Modal con el enlace/QR de configuración de un solo uso para provisionar el terminal offline --}}
<div class="space-y-4">
    <p class="text-sm text-gray-600 dark:text-gray-300">
        Abra este enlace <strong>una sola vez, con el dispositivo conectado a internet</strong>, durante la instalación
        física del terminal. Vence en <strong>{{ $expiresInMinutes }} minutos</strong> y no puede reutilizarse.
    </p>

    <div class="flex justify-center">
        <div style="display:inline-block;background:#fff;padding:12px;border-radius:8px;border:1px solid #e5e7eb">
            {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::size(200)->generate($url) !!}
        </div>
    </div>

    <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-3 text-xs break-all font-mono text-gray-700 dark:text-gray-300">
        {{ $url }}
    </div>

    <p class="text-xs text-gray-500 dark:text-gray-400">
        Si el terminal ya tiene un token activo, este enlace lo reemplaza al vincularse — el token anterior queda
        inválido de inmediato.
    </p>
</div>
