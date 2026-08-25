<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vincular dispositivo — Marcación de asistencia</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite('resources/js/attendances/device-link.js')
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
            padding: 2rem;
        }
        .container { max-width: 420px; width: 100%; text-align: center; }
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
        p { font-size: 1rem; color: #94a3b8; line-height: 1.6; margin-bottom: 1.5rem; }
        form { text-align: left; }
        label { display: block; font-size: 0.875rem; color: #94a3b8; margin-bottom: 0.35rem; }
        input {
            width: 100%;
            font: inherit;
            font-size: 1rem;
            padding: 0.75rem 1rem;
            border-radius: 0.6rem;
            border: 1px solid #334155;
            background: #1e293b;
            color: #e2e8f0;
            margin-bottom: 1.1rem;
        }
        input:focus { outline: none; border-color: #38bdf8; }
        button {
            width: 100%;
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
        .status { margin-top: 1.25rem; font-size: 0.9rem; min-height: 1.5rem; text-align: center; }
        .status--error { color: #f87171; }
        .status--success { color: #4ade80; }
        .hint { font-size: 0.8rem; color: #64748b; margin-top: 1.5rem; line-height: 1.5; }
        .hidden { display: none !important; }

        /* Aviso: este navegador ya tiene un dispositivo vinculado */
        .already-linked-warning {
            text-align: left;
            background: #422006;
            border: 1px solid #f59e0b;
            border-radius: 0.75rem;
            padding: 1.1rem 1.25rem;
            margin-bottom: 1.5rem;
        }
        .already-linked-warning p { color: #fcd34d; font-size: 0.9rem; margin-bottom: 1rem; }
        .already-linked-warning p:last-of-type { margin-bottom: 1.25rem; }
        .already-linked-actions { display: flex; flex-direction: column; gap: 0.6rem; }
        .btn-continue-anyway {
            width: 100%; font: inherit; font-weight: 600; font-size: 0.9rem;
            padding: 0.7rem 1.5rem; border-radius: 0.6rem; border: 1px solid #f59e0b;
            background: transparent; color: #fcd34d; cursor: pointer;
        }
        .btn-continue-anyway:hover { background: rgba(245, 158, 11, 0.12); }
        .btn-cancel-relink {
            width: 100%; font: inherit; font-weight: 600; font-size: 0.9rem;
            padding: 0.7rem 1.5rem; border-radius: 0.6rem; border: none;
            background: #38bdf8; color: #0f172a; cursor: pointer;
        }
        .btn-cancel-relink:hover { background: #7dd3fc; }
    </style>
</head>

<body>
    <div class="container">
        <div class="icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75A2.25 2.25 0 0015.75 1.5H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" />
            </svg>
        </div>
        <h1>Vincular este dispositivo</h1>
        <p>Ingresá tu CI y fecha de nacimiento para vincular este dispositivo. Vas a poder marcar tu asistencia aunque no tengas conexión a internet.</p>

        {{-- Solo se muestra si device-link.js detecta un token ya vinculado en este
        navegador (IndexedDB) — evita re-vinculaciones accidentales que disparan una
        alerta de seguridad real (MobileDeviceRelinkedNotification) a los admins. --}}
        <div id="alreadyLinkedWarning" class="already-linked-warning hidden">
            <p><strong>Este dispositivo ya está vinculado</strong> a <span id="alreadyLinkedName"></span>.</p>
            <p>Si continuás y vinculás uno nuevo, el dispositivo actual va a dejar de funcionar y se le va a avisar a RRHH.</p>
            <div class="already-linked-actions">
                <button type="button" id="btnCancelRelink" class="btn-cancel-relink">Ya tengo un dispositivo — volver a marcar</button>
                <button type="button" id="btnContinueAnyway" class="btn-continue-anyway">Continuar y vincular este de todos modos</button>
            </div>
        </div>

        <form id="linkForm">
            <label for="ci">CI</label>
            <input type="text" id="ci" name="ci" inputmode="numeric" autocomplete="off" required>

            <label for="birth_date">Fecha de nacimiento</label>
            <input type="date" id="birth_date" name="birth_date" required>

            <button type="submit" id="btnLink">Vincular este dispositivo</button>
        </form>
        <div id="status" class="status" role="status" aria-live="polite"></div>

        <p class="hint">Solo se puede vincular un dispositivo a la vez. Si vinculás uno nuevo, el anterior deja de funcionar automáticamente.</p>
    </div>

    <script>
        const form = document.getElementById('linkForm');
        const btn = document.getElementById('btnLink');
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

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            btn.disabled = true;
            statusEl.textContent = 'Vinculando...';
            statusEl.className = 'status';

            try {
                const response = await fetch(window.location.pathname.replace(/\/$/, ''), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({
                        ci: document.getElementById('ci').value.trim(),
                        birth_date: document.getElementById('birth_date').value,
                        device_model_hint: await getClientHintModel(),
                    }),
                });
                const data = await response.json();

                if (!data.ok) {
                    statusEl.textContent = data.message || 'No se pudo vincular el dispositivo.';
                    statusEl.className = 'status status--error';
                    btn.disabled = false;
                    return;
                }

                // Almacenamiento provisorio del token — en la fase de sincronización offline
                // (IndexedDB, módulo mobile-offline/) este valor pasa a vivir en su propio store,
                // mismo patrón que usó terminal-setup.blade.php para el terminal.
                localStorage.setItem('nominapp_mobile_token', data.token);
                localStorage.setItem('nominapp_mobile_employee_id', String(data.employee.id));

                statusEl.textContent = `Dispositivo vinculado. ¡Hola, ${data.employee.first_name}! Redirigiendo...`;
                statusEl.className = 'status status--success';

                setTimeout(() => {
                    window.location.href = '{{ route('mark.show') }}';
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
