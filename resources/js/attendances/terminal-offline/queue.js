/**
 * =============================================================================
 * COLA DE EVENTOS OFFLINE
 * =============================================================================
 *
 * @fileoverview Orquesta la captura, resolución de estado y sincronización en
 *               segundo plano de las marcaciones del kiosko. A diferencia de
 *               la Fase 3 (matching client-side, pero marcación aún síncrona),
 *               acá el envío puede diferirse: toda marcación se guarda primero
 *               en `outbound_events` (durable) y recién después se intenta
 *               sincronizar — si falla por falta de red, queda en cola para
 *               el próximo intento (background o al reconectar).
 */

import {
    queueEvent,
    getPendingEvents,
    getEventsForEmployeeOnDate,
    removeQueuedEvent,
    markQueuedEventConflict,
    incrementQueuedEventAttempts,
    countPendingEvents,
    countConflictEvents,
    getEmployeeStatusCache,
    setEmployeeStatusCache,
} from './db.js';
import { submitEvents, fetchEmployeeStatus } from './sync.js';

/**
 * Máquina de estados de marcación diaria — mismo criterio que
 * AttendanceEvent::allowedNextEventTypes() en el backend. Se necesita acá
 * (además de en el servidor) para poder resolver localmente qué botones
 * mostrar cuando no hay red para preguntarle al servidor.
 * @param {string|null} lastEventType
 * @returns {string[]}
 */
export function allowedNextEventTypes(lastEventType) {
    switch (lastEventType) {
        case null:
        case undefined:
            return ['check_in'];
        case 'check_in':
            return ['break_start', 'check_out'];
        case 'break_start':
            return ['break_end'];
        case 'break_end':
            return ['break_start', 'check_out'];
        case 'check_out':
            return [];
        default:
            return ['check_in'];
    }
}

/** @returns {string} Fecha local del dispositivo en formato YYYY-MM-DD. */
function localDateString(date = new Date()) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

/**
 * Resuelve el estado de marcación de un empleado para hoy, combinando:
 * 1) el último estado que el servidor confirmó (cacheado en la última
 *    consulta online exitosa), con
 * 2) los eventos que este mismo kiosko ya encoló hoy para ese empleado pero
 *    el servidor todavía no confirmó — para no repetir ni saltear pasos
 *    mientras está offline.
 * @param {number} employeeId
 * @returns {Promise<{last_event: string|null, last_event_time: string|null, allowed_events: string[]}>}
 */
export async function resolveEmployeeStatus(employeeId) {
    const cached = await getEmployeeStatusCache(employeeId);
    const today = localDateString();
    const localEvents = (await getEventsForEmployeeOnDate(employeeId, today))
        .filter((event) => event.status === 'pending') // los en conflicto no cuentan como "ya registrados"
        .sort((a, b) => a.recorded_at.localeCompare(b.recorded_at));

    let lastEvent = cached?.last_event ?? null;
    let lastEventTime = cached?.last_event_time ?? null;

    for (const event of localEvents) {
        lastEvent = event.event_type;
        lastEventTime = new Date(event.recorded_at).toTimeString().slice(0, 5);
    }

    return {
        last_event: lastEvent,
        last_event_time: lastEventTime,
        allowed_events: allowedNextEventTypes(lastEvent),
    };
}

/**
 * Estado de marcación de un empleado ya identificado localmente: intenta la
 * consulta en línea (y cachea el resultado para uso offline futuro); si falla
 * por falta de red, cae a resolveEmployeeStatus() con lo que el kiosko ya sabe.
 * @param {number} employeeId
 */
export async function getEmployeeStatus(employeeId) {
    try {
        const status = await fetchEmployeeStatus(employeeId);
        await setEmployeeStatusCache(employeeId, status);
        return status;
    } catch (error) {
        console.warn(`No se pudo consultar el estado en línea del empleado ${employeeId}, usando estado local:`, error.message);
        return resolveEmployeeStatus(employeeId);
    }
}

/**
 * Encola una marcación capturada localmente. Debe llamarse ANTES de intentar
 * sincronizar — así la marcación queda a salvo aunque el envío falle o la
 * pestaña se cierre a mitad de camino.
 * @param {number} employeeId
 * @param {string} eventType
 * @returns {Promise<{client_event_id: string, recorded_at: string}>}
 */
export async function enqueueMark(employeeId, eventType) {
    const clientEventId = crypto.randomUUID();
    const recordedAt = new Date();

    await queueEvent({
        client_event_id: clientEventId,
        employee_id: employeeId,
        event_type: eventType,
        recorded_at: recordedAt.toISOString(),
        date: localDateString(recordedAt),
    });

    return { client_event_id: clientEventId, recorded_at: recordedAt.toISOString() };
}

/** @type {boolean} Evita que dos flush corran en simultáneo (ej. click manual + timer de fondo). */
let flushInProgress = false;

/**
 * Intenta sincronizar todos los eventos pendientes en un solo lote. Los
 * `synced`/`duplicate` se eliminan de la cola; los `conflict`/`rejected` se
 * marcan como tal (no se reintentan más) para revisión manual en Filament.
 * Si la red falla directamente (ni siquiera hay respuesta), todos quedan
 * `pending` para el próximo intento.
 *
 * Devuelve `results` (la respuesta cruda del servidor, una entrada por
 * `client_event_id`) para que el caller pueda saber qué pasó específicamente
 * con SU evento cuando encoló varios a la vez con otros ya pendientes.
 * @returns {Promise<{synced: number, conflicts: number, stillPending: number, results: Array<object>}>}
 */
export async function flushQueue() {
    if (flushInProgress) return { synced: 0, conflicts: 0, stillPending: await countPendingEvents(), results: [] };
    flushInProgress = true;

    try {
        const pending = await getPendingEvents();
        if (pending.length === 0) return { synced: 0, conflicts: 0, stillPending: 0, results: [] };

        let results;
        try {
            results = await submitEvents(pending.map(({ client_event_id, employee_id, event_type, recorded_at }) => ({
                client_event_id,
                employee_id,
                event_type,
                recorded_at,
            })));
        } catch (error) {
            // Sin red o el servidor no respondió — todos quedan pendientes, se reintenta después.
            for (const event of pending) await incrementQueuedEventAttempts(event.client_event_id);
            console.warn('flushQueue: no se pudo sincronizar (sin red o error de servidor):', error.message);
            return { synced: 0, conflicts: 0, stillPending: pending.length, results: [] };
        }

        let synced = 0;
        let conflicts = 0;

        for (const result of results) {
            if (result.status === 'synced' || result.status === 'duplicate') {
                await removeQueuedEvent(result.client_event_id);
                synced++;
            } else {
                await markQueuedEventConflict(result.client_event_id, result.message);
                conflicts++;
            }
        }

        return { synced, conflicts, stillPending: await countPendingEvents(), results };
    } finally {
        flushInProgress = false;
    }
}

export { countPendingEvents, countConflictEvents };
