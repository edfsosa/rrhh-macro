<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Configurar terminal — {{ $terminal->name }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Arial, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 2rem;
        }
        .container { max-width: 480px; width: 100%; }
        .icon {
            width: 80px; height: 80px;
            border-radius: 50%;
            background: #1e293b;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.5rem;
            border: 2px solid #38bdf8;
        }
        .icon svg { width: 40px; height: 40px; color: #38bdf8; }
        h1 { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.75rem; }
        p { font-size: 1rem; color: #94a3b8; line-height: 1.6; margin-bottom: 0.5rem; }
        .terminal-meta { font-size: 0.875rem; color: #64748b; margin: 1rem 0 2rem; }
        button {
            font: inherit;
            font-weight: 600;
            font-size: 1rem;
            padding: 0.85rem 1.75rem;
            border-radius: 0.75rem;
            border: none;
            background: #38bdf8;
            color: #0f172a;
            cursor: pointer;
        }
        button:disabled { opacity: 0.6; cursor: not-allowed; }
        button:not(:disabled):hover { background: #7dd3fc; }
        .status { margin-top: 1.25rem; font-size: 0.9rem; min-height: 1.5rem; }
        .status--error { color: #f87171; }
        .status--success { color: #4ade80; }
    </style>
</head>

<body>
    <div class="container">
        <div class="icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25" />
            </svg>
        </div>
        <h1>Configurar terminal</h1>
        <p>Este dispositivo se vinculará como el terminal de marcación de la sucursal indicada, habilitando la marcación sin conexión.</p>
        <div class="terminal-meta">{{ $terminal->name }} — {{ $terminal->branch?->name }}</div>

        <button type="button" id="btnClaim">Vincular este dispositivo</button>
        <div id="status" class="status" role="status" aria-live="polite"></div>
    </div>

    <script>
        const btn = document.getElementById('btnClaim');
        const statusEl = document.getElementById('status');
        const csrf = document.querySelector('meta[name="csrf-token"]').content;

        // Modelo real del dispositivo, solo disponible vía Client Hints en navegadores
        // Chromium sobre Android (Chrome, Edge...) — null en iOS/Safari/Firefox/desktop,
        // donde el servidor cae al parseo del User-Agent (ver DeviceHintsParser). Sirve
        // solo para prellenar marca/modelo como sugerencia editable en el panel.
        async function getClientHintModel() {
            if (!navigator.userAgentData?.getHighEntropyValues) return null;
            try {
                const hints = await navigator.userAgentData.getHighEntropyValues(['model']);
                return hints.model || null;
            } catch {
                return null;
            }
        }

        btn.addEventListener('click', async () => {
            btn.disabled = true;
            statusEl.textContent = 'Vinculando...';
            statusEl.className = 'status';

            try {
                const response = await fetch(window.location.pathname.replace(/\/$/, '') + '/claim', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ device_model_hint: await getClientHintModel() }),
                });
                const data = await response.json();

                if (!data.ok) {
                    statusEl.textContent = data.message || 'No se pudo vincular el dispositivo.';
                    statusEl.className = 'status status--error';
                    btn.disabled = false;
                    return;
                }

                // Almacenamiento provisorio del token — en la fase de sincronización offline
                // (IndexedDB, módulo terminal-offline/) este valor pasa a vivir en el store
                // `terminal_meta` en vez de localStorage.
                localStorage.setItem('nominapp_terminal_token', data.token);
                localStorage.setItem('nominapp_terminal_code', data.terminal.code);

                statusEl.textContent = 'Dispositivo vinculado correctamente. Redirigiendo...';
                statusEl.className = 'status status--success';

                setTimeout(() => {
                    window.location.href = '/terminal/' + data.terminal.code;
                }, 1200);
            } catch (error) {
                statusEl.textContent = 'Error de conexión. Intente nuevamente.';
                statusEl.className = 'status status--error';
                btn.disabled = false;
            }
        });
    </script>
</body>

</html>
