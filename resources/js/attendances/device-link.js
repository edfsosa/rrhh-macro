/**
 * =============================================================================
 * VINCULACIÓN DE DISPOSITIVO — AVISO DE RE-VINCULACIÓN
 * =============================================================================
 *
 * @fileoverview device-link.blade.php es HTML+JS plano sin build step, salvo
 *               por este módulo: chequea si el propio navegador ya tiene un
 *               dispositivo vinculado (IndexedDB) antes de dejar completar el
 *               formulario de nuevo. Sin esto, volver a esta URL desde un
 *               dispositivo ya vinculado y reenviar el mismo CI+fecha genera
 *               una re-vinculación real (revoca el token actual, dispara
 *               MobileDeviceRelinkedNotification a los admins por campanita +
 *               email) sin que el empleado se entere de que está pasando —
 *               indistinguible para RRHH de un intento real de secuestro del
 *               dispositivo.
 */

import { getMeta, getOwnEmployee, migrateTokenFromLocalStorage } from './mobile-offline/db.js';

document.addEventListener('DOMContentLoaded', async () => {
    // Por si quedó un token sin migrar de una vinculación interrumpida (ej. el
    // empleado cerró la pestaña antes de que /marcar corriera la migración).
    await migrateTokenFromLocalStorage();

    const token = await getMeta('api_token');
    if (!token) return; // caso normal: primera vinculación, nada que avisar

    const warning = document.getElementById('alreadyLinkedWarning');
    const form = document.getElementById('linkForm');
    const nameEl = document.getElementById('alreadyLinkedName');
    const btnContinue = document.getElementById('btnContinueAnyway');
    const btnCancel = document.getElementById('btnCancelRelink');
    if (!warning || !form) return;

    if (nameEl) {
        const employee = await getOwnEmployee();
        const fullName = [employee?.first_name, employee?.last_name].filter(Boolean).join(' ');
        nameEl.textContent = fullName || 'este empleado';
    }

    form.classList.add('hidden');
    warning.classList.remove('hidden');

    btnContinue?.addEventListener('click', () => {
        warning.classList.add('hidden');
        form.classList.remove('hidden');
    });

    btnCancel?.addEventListener('click', () => {
        window.location.href = '/marcar';
    });
});
