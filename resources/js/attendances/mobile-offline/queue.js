/**
 * =============================================================================
 * COLA DE EVENTOS OFFLINE (dispositivo personal)
 * =============================================================================
 *
 * @fileoverview Orquesta la resolución de estado y sincronización en segundo
 *               plano de las marcaciones del dispositivo. Mismo diseño que
 *               `terminal-offline/queue.js` — toda marcación se guarda primero
 *               en `outbound_events` (durable) y recién después se intenta
 *               sincronizar — pero acá no hace falta un `employeeId` explícito
 *               en cada función: el dispositivo está vinculado a un único
 *               empleado, resuelto internamente desde `mobile_meta`.
 */

import {
    getMeta,
    queueEvent,
    getPendingEvents,
    getEventsOnDate,
    removeQueuedEvent,
    markQueuedEventConflict,
    incrementQueuedEventAttempts,
    countPendingEvents,
    countConflictEvents,
    dismissConflictEvents,
    getEmployeeStatusCache,
    setEmployeeStatusCache,
} from './db.js';
import { submitEvents, fetchStatus, MobileAuthError } from './sync.js';

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

/**
 * Hora actual corregida con el offset de reloj del último heartbeat exitoso
 * (`server_clock_offset_ms`, ver sync.js) — un dispositivo con el reloj desviado
 * (o con la hora del sistema manipulada) arrastraría el error a cada
 * marcación capturada offline. Sin heartbeat previo el offset es 0.
 * @returns {Promise<Date>}
 */
async function correctedNow() {
    const offsetMs = (await getMeta('server_clock_offset_ms')) || 0;
    return new Date(Date.now() + offsetMs);
}

/** @returns {string} Fecha local del dispositivo en formato YYYY-MM-DD. */
function localDateString(date = new Date()) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

/**
 * Resuelve el estado de marcación del propio empleado para hoy, combinando:
 * 1) el último estado que el servidor confirmó (cacheado en la última
 *    consulta online exitosa), con
 * 2) los eventos que este mismo dispositivo ya encoló hoy pero el servidor
 *    todavía no confirmó — para no repetir ni saltear pasos mientras está
 *    offline.
 * @returns {Promise<{last_event: string|null, last_event_time: string|null, allowed_events: string[]}>}
 */
export async function resolveOwnStatus() {
    const employeeId = await getMeta('employee_id');
    const cached = employeeId ? await getEmployeeStatusCache(employeeId) : undefined;
    const today = localDateString(await correctedNow());
    const localEvents = (await getEventsOnDate(today))
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
 * Estado de marcación del propio empleado: intenta la consulta en línea (y
 * cachea el resultado para uso offline futuro); si falla por falta de red,
 * cae a resolveOwnStatus() con lo que el dispositivo ya sabe.
 * @returns {Promise<{last_event: string|null, last_event_time: string|null, allowed_events: string[]}>}
 */
export async function getOwnStatus() {
    try {
        const status = await fetchStatus();
        const employeeId = await getMeta('employee_id');
        if (employeeId) await setEmployeeStatusCache(employeeId, status);
        return status;
    } catch (error) {
        // Token revocado — no es un fallo de red recuperable con el estado local, el
        // caller (mark.js) debe mandar al empleado a re-vincular el dispositivo.
        if (error instanceof MobileAuthError) throw error;
        console.warn('No se pudo consultar el estado en línea del empleado, usando estado local:', error.message);
        return resolveOwnStatus();
    }
}

/**
 * Encola una marcación capturada localmente. Debe llamarse ANTES de intentar
 * sincronizar — así la marcación queda a salvo aunque el envío falle o la
 * pestaña se cierre a mitad de camino.
 *
 * `recorded_at` se corrige con el offset de reloj calculado en el último
 * heartbeat exitoso (`server_clock_offset_ms`, ver sync.js). Sin heartbeat
 * previo el offset es 0 (no hay corrección posible).
 * @param {string} eventType
 * @param {{lat: number, lng: number}|null} [location] - GPS real del dispositivo (a diferencia del kiosko, que solo tiene fallback a coordenadas de sucursal).
 * @returns {Promise<{client_event_id: string, recorded_at: string}>}
 */
export async function enqueueMark(eventType, location = null) {
    const employeeId = await getMeta('employee_id');
    const clientEventId = crypto.randomUUID();
    const recordedAt = await correctedNow();

    await queueEvent({
        client_event_id: clientEventId,
        employee_id: employeeId,
        event_type: eventType,
        recorded_at: recordedAt.toISOString(),
        date: localDateString(recordedAt),
        location,
    });

    // Refresca el caché de estado de inmediato — sin esto, una vez que este
    // evento se sincroniza y se borra de outbound_events, resolveOwnStatus()
    // no tiene forma de saber que ya se registró (el caché queda con el
    // último estado confirmado ANTES de esta marcación) y vuelve a ofrecer
    // los mismos eventos permitidos que antes de marcar.
    if (employeeId) {
        await setEmployeeStatusCache(employeeId, {
            last_event: eventType,
            last_event_time: recordedAt.toTimeString().slice(0, 5),
            allowed_events: allowedNextEventTypes(eventType),
        });
    }

    return { client_event_id: clientEventId, recorded_at: recordedAt.toISOString() };
}

/** @type {boolean} Evita que dos flush corran en simultáneo (ej. reintento manual + timer de fondo). */
let flushInProgress = false;

/**
 * Máximo de eventos por request de sincronización — debe coincidir con el
 * límite del servidor (`MobileEventSyncController`: `'events' => [...,
 * 'max:200']`). En la práctica un dispositivo personal difícilmente acumule
 * tantas marcaciones (a diferencia de un kiosko con muchos empleados), pero
 * se mantiene el mismo chunking por las dudas y por paridad con el kiosko.
 */
const MAX_BATCH_SIZE = 200;

/**
 * Intenta sincronizar todos los eventos pendientes, en lotes de a lo sumo
 * `MAX_BATCH_SIZE`. Los `synced`/`duplicate` se eliminan de la cola; los
 * `conflict`/`rejected` se marcan como tal (no se reintentan más) para
 * revisión manual en Filament. Si un lote falla por red, ese lote y los
 * siguientes quedan `pending` para el próximo intento.
 *
 * @returns {Promise<{synced: number, conflicts: number, stillPending: number, results: Array<object>}>}
 */
export async function flushQueue() {
    if (flushInProgress) return { synced: 0, conflicts: 0, stillPending: await countPendingEvents(), results: [] };
    flushInProgress = true;

    try {
        const pending = await getPendingEvents();
        if (pending.length === 0) return { synced: 0, conflicts: 0, stillPending: 0, results: [] };

        let synced = 0;
        let conflicts = 0;
        const allResults = [];

        for (let offset = 0; offset < pending.length; offset += MAX_BATCH_SIZE) {
            const batch = pending.slice(offset, offset + MAX_BATCH_SIZE);

            let results;
            try {
                results = await submitEvents(batch.map(({ client_event_id, event_type, recorded_at, location }) => ({
                    client_event_id,
                    event_type,
                    recorded_at,
                    location: location ?? undefined,
                })));
            } catch (error) {
                // Token revocado — no es un fallo de red recuperable, el caller (mark.js)
                // debe mandar al empleado a re-vincular el dispositivo. Los eventos de este
                // lote y los restantes quedan `pending` tal cual (se reintentan solos una
                // vez que el empleado se re-vincule).
                if (error instanceof MobileAuthError) throw error;

                // Sin red o el servidor no respondió — este lote y los restantes (todavía no
                // enviados) quedan pendientes para el próximo intento.
                for (const event of pending.slice(offset)) await incrementQueuedEventAttempts(event.client_event_id);
                console.warn(`flushQueue: no se pudo sincronizar el lote (${batch.length} de ${pending.length - offset} restantes):`, error.message);
                break;
            }

            for (const result of results) {
                if (result.status === 'synced' || result.status === 'duplicate') {
                    await removeQueuedEvent(result.client_event_id);
                    synced++;
                } else {
                    await markQueuedEventConflict(result.client_event_id, result.message);
                    conflicts++;
                }
            }
            allResults.push(...results);
        }

        return { synced, conflicts, stillPending: await countPendingEvents(), results: allResults };
    } finally {
        flushInProgress = false;
    }
}

export { countPendingEvents, countConflictEvents, dismissConflictEvents };
