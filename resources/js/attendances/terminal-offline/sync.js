/**
 * =============================================================================
 * SINCRONIZACIÓN CON LA API DE TERMINALES (routes/api.php, Sanctum)
 * =============================================================================
 *
 * @fileoverview Cliente delgado para /api/v1/terminal/* — delta de empleados,
 *               heartbeat, y helper de fetch autenticado reutilizado por el
 *               envío de eventos (ver terminal.js). Requiere que el terminal
 *               ya haya sido provisionado (token en terminal_meta.api_token,
 *               ver TerminalSetupController / terminal-setup.blade.php).
 */

import { getMeta, setMeta, applyEmployeesDelta, logSync } from './db.js';

const API_BASE = '/api/v1/terminal';

/** Error específico de token ausente/inválido — el caller decide cómo mostrarlo. */
export class TerminalAuthError extends Error {}

/**
 * fetch autenticado con el token Sanctum del terminal.
 * @param {string} path - Path relativo a /api/v1/terminal (ej. '/heartbeat').
 * @param {RequestInit} [options]
 * @returns {Promise<any>} Cuerpo JSON de la respuesta.
 */
export async function apiFetch(path, options = {}) {
    const token = await getMeta('api_token');
    if (!token) {
        throw new TerminalAuthError('Este terminal no está configurado — falta vincular el dispositivo.');
    }

    const response = await fetch(`${API_BASE}${path}`, {
        ...options,
        headers: {
            Authorization: `Bearer ${token}`,
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...(options.headers || {}),
        },
    });

    if (response.status === 401 || response.status === 403) {
        throw new TerminalAuthError('El token de este terminal fue revocado o expiró — necesita re-provisión.');
    }

    return response.json();
}

/**
 * Trae el delta de empleados/descriptores desde la última sincronización y
 * lo aplica a la caché local.
 * @returns {Promise<{employees: number, tombstones: number}>}
 */
export async function syncEmployees() {
    try {
        const since = await getMeta('last_employee_sync_version');
        const query = since ? `?since=${encodeURIComponent(since)}` : '';
        const data = await apiFetch(`/employees/sync${query}`);

        if (!data.ok) throw new Error(data.message || 'Error al sincronizar empleados');

        await applyEmployeesDelta(data.employees, data.tombstones);
        await setMeta('last_employee_sync_version', data.sync_version);
        await setMeta('employees_synced_at', Date.now());

        await logSync('employees_sync', true, `${data.employees.length} empleados, ${data.tombstones.length} tombstones`);
        return { employees: data.employees.length, tombstones: data.tombstones.length };
    } catch (error) {
        await logSync('employees_sync', false, error.message);
        throw error;
    }
}

/**
 * Heartbeat: mantiene `last_seen_at` vivo en el servidor y refresca la
 * configuración de reconocimiento facial (umbral/gap) usada por el matcher local.
 * @returns {Promise<void>}
 */
export async function heartbeat() {
    try {
        const data = await apiFetch('/heartbeat', { method: 'POST' });
        if (!data.ok) throw new Error(data.message || 'Error en heartbeat');

        await setMeta('face_threshold', data.config.face_threshold);
        await setMeta('face_min_confidence_gap', data.config.face_min_confidence_gap);
        await setMeta('server_clock_offset_ms', new Date(data.server_time).getTime() - Date.now());
        await setMeta('last_heartbeat_at', Date.now());

        await logSync('heartbeat', true);
    } catch (error) {
        await logSync('heartbeat', false, error.message);
        throw error;
    }
}

/**
 * Config de reconocimiento facial vigente (sincronizada vía heartbeat).
 * @returns {Promise<{threshold: number|null, minGap: number|null}>} null si todavía no hubo un heartbeat exitoso.
 */
export async function getFaceConfig() {
    const threshold = await getMeta('face_threshold');
    const minGap = await getMeta('face_min_confidence_gap');
    return { threshold: threshold ?? null, minGap: minGap ?? null };
}

/**
 * Estado del día (último evento / eventos permitidos) para un empleado ya
 * identificado localmente. Requiere red — en esta fase la marcación sigue
 * siendo síncrona/online, esto no se resuelve desde la caché local todavía.
 * @param {number} employeeId
 * @returns {Promise<{last_event: string|null, last_event_time: string|null, allowed_events: string[]}>}
 */
export async function fetchEmployeeStatus(employeeId) {
    const data = await apiFetch(`/employees/${employeeId}/status`);
    if (!data.ok) throw new Error(data.message || 'No se pudo obtener el estado del empleado.');
    return data;
}

/**
 * Envía un lote de eventos de marcación (hoy siempre de a uno, la cola
 * offline en lote llega en una fase posterior).
 * @param {Array<{client_event_id: string, employee_id: number, event_type: string, recorded_at: string, location?: object|null}>} events
 * @returns {Promise<Array<{client_event_id: string, status: string, event_id?: number, message?: string}>>}
 */
export async function submitEvents(events) {
    const data = await apiFetch('/events/sync', {
        method: 'POST',
        body: JSON.stringify({ events }),
    });
    if (!data.ok) throw new Error(data.message || 'No se pudo registrar la marcación.');
    return data.results;
}
