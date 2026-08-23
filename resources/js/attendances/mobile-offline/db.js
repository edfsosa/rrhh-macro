/**
 * =============================================================================
 * INDEXEDDB — CACHÉ LOCAL DEL CELULAR PERSONAL
 * =============================================================================
 *
 * @fileoverview Wrapper delgado sobre `idb` para la base local del celular.
 *               Base de datos separada de `terminal-offline/db.js`
 *               (`nominapp-mobile` vs `nominapp-terminal`) para que ambos
 *               flujos puedan coexistir sin chocar si algún dispositivo
 *               llegara a usarse en los dos modos.
 *
 * Diferencia clave con el kiosko: acá NO existe un store de `employees_cache`
 * — el celular vinculado cachea el descriptor facial de un único empleado (el
 * dueño del dispositivo), guardado directamente en `mobile_meta` bajo la key
 * `own_employee`. Todo lo demás (cola de eventos, caché de estado, log de
 * sync) sigue el mismo diseño que `terminal-offline/db.js`.
 *
 * Stores:
 * - mobile_meta            — key/value: api_token, own_employee (id/nombre/CI/
 *                             descriptor), config de reconocimiento facial,
 *                             offset de reloj, timestamps de sync.
 * - outbound_events        — keyPath 'client_event_id': cola de marcaciones
 *                             capturadas localmente. `status`: 'pending'
 *                             (por sincronizar) | 'conflict' (el servidor la
 *                             rechazó — no se reintenta, queda para revisión).
 *                             Las sincronizadas con éxito se eliminan del store.
 * - employee_status_cache  — keyPath 'employee_id': último estado de marcación
 *                             conocido del servidor (last_event/allowed_events)
 *                             para el propio empleado — un único registro.
 * - sync_log               — autoIncrement: historial breve de intentos de
 *                             sync, para diagnóstico en pantalla.
 */

import { openDB } from 'idb';

const DB_NAME = 'nominapp-mobile';
const DB_VERSION = 1;

/** @type {Promise<import('idb').IDBPDatabase>|null} */
let dbPromise = null;

/** @returns {Promise<import('idb').IDBPDatabase>} */
export function getDb() {
    if (!dbPromise) {
        dbPromise = openDB(DB_NAME, DB_VERSION, {
            upgrade(db) {
                if (!db.objectStoreNames.contains('mobile_meta')) {
                    db.createObjectStore('mobile_meta');
                }
                if (!db.objectStoreNames.contains('outbound_events')) {
                    db.createObjectStore('outbound_events', { keyPath: 'client_event_id' });
                }
                if (!db.objectStoreNames.contains('employee_status_cache')) {
                    db.createObjectStore('employee_status_cache', { keyPath: 'employee_id' });
                }
                if (!db.objectStoreNames.contains('sync_log')) {
                    db.createObjectStore('sync_log', { keyPath: 'id', autoIncrement: true });
                }
            },
        });
    }
    return dbPromise;
}

/**
 * Lee un valor de `mobile_meta`.
 * @param {string} key
 * @returns {Promise<any>}
 */
export async function getMeta(key) {
    const db = await getDb();
    return db.get('mobile_meta', key);
}

/**
 * Escribe un valor en `mobile_meta`.
 * @param {string} key
 * @param {any} value
 */
export async function setMeta(key, value) {
    const db = await getDb();
    return db.put('mobile_meta', value, key);
}

/**
 * Migración única del token guardado en localStorage por la pantalla de
 * vinculación (mobile-link.blade.php) hacia IndexedDB. Se puede llamar en
 * cada carga: es un no-op si ya no queda nada en localStorage.
 * @returns {Promise<void>}
 */
export async function migrateTokenFromLocalStorage() {
    const legacyToken = localStorage.getItem('nominapp_mobile_token');
    if (!legacyToken) return;

    const legacyEmployeeId = localStorage.getItem('nominapp_mobile_employee_id');
    await setMeta('api_token', legacyToken);
    if (legacyEmployeeId) await setMeta('employee_id', Number(legacyEmployeeId));

    localStorage.removeItem('nominapp_mobile_token');
    localStorage.removeItem('nominapp_mobile_employee_id');
}

/**
 * Registra una entrada en `sync_log`, recortando el historial a las últimas 50.
 * @param {string} type
 * @param {boolean} ok
 * @param {string|null} [detail]
 */
export async function logSync(type, ok, detail = null) {
    const db = await getDb();
    await db.add('sync_log', { type, ok, detail, at: Date.now() });

    const allKeys = await db.getAllKeys('sync_log');
    if (allKeys.length > 50) {
        const tx = db.transaction('sync_log', 'readwrite');
        for (const key of allKeys.slice(0, allKeys.length - 50)) {
            await tx.store.delete(key);
        }
        await tx.done;
    }
}

/**
 * Vacía por completo la caché local del celular — llamado tras una
 * auto-desvinculación exitosa (ver `unlinkDevice()` en `sync.js`). El
 * dispositivo queda como recién instalado: sin token, sin empleado
 * cacheado, sin cola de eventos ni estado. Cualquier marcación pendiente de
 * sincronizar en `outbound_events` se pierde — el caller es responsable de
 * intentar `flushQueue()` y advertir al usuario antes de llamar a esto.
 * @returns {Promise<void>}
 */
export async function resetDb() {
    const db = await getDb();
    await Promise.all(
        ['mobile_meta', 'outbound_events', 'employee_status_cache', 'sync_log'].map((store) => db.clear(store))
    );
}

/**
 * Empleado dueño del dispositivo, con su descriptor facial cacheado — el
 * único "candidato" contra el que el matcher local compara.
 * @returns {Promise<{id: number, first_name: string, last_name: string, ci: string|null, face_descriptor: number[]}|undefined>}
 */
export async function getOwnEmployee() {
    return getMeta('own_employee');
}

/**
 * Actualiza el empleado propio cacheado (llamado tras cada heartbeat exitoso
 * — propaga automáticamente una re-inscripción facial sin que el empleado
 * tenga que re-vincular el dispositivo).
 * @param {{id: number, first_name: string, last_name: string, ci: string|null, face_descriptor: number[]}} employee
 */
export async function setOwnEmployee(employee) {
    await setMeta('own_employee', employee);
    await setMeta('employee_id', employee.id);
}

// =========================================================================
// COLA DE EVENTOS OFFLINE (outbound_events)
// =========================================================================

/**
 * Encola una marcación capturada localmente. `date` es la fecha local del
 * dispositivo (YYYY-MM-DD) al momento de la captura — se usa solo para
 * filtrar "eventos de hoy" del lado del cliente; la fecha real de negocio
 * (con el timezone de la app) la decide el servidor al sincronizar.
 * @param {{client_event_id: string, employee_id: number, event_type: string, recorded_at: string, date: string, location?: object|null}} event
 */
export async function queueEvent(event) {
    const db = await getDb();
    await db.put('outbound_events', { ...event, status: 'pending', attempts: 0, created_at: Date.now() });
}

/** @returns {Promise<Array<object>>} Eventos pendientes de sincronizar (no incluye los marcados 'conflict'). */
export async function getPendingEvents() {
    const db = await getDb();
    const all = await db.getAll('outbound_events');
    return all.filter((event) => event.status === 'pending');
}

/**
 * Eventos (pendientes o en conflicto) para la fecha local indicada — usado
 * para resolver localmente el último evento del día cuando no hay red para
 * consultar al servidor (ver queue.js).
 * @param {string} date - YYYY-MM-DD local.
 */
export async function getEventsOnDate(date) {
    const db = await getDb();
    const all = await db.getAll('outbound_events');
    return all.filter((event) => event.date === date);
}

/** Marca un evento encolado como sincronizado — se elimina del store (ya vive en el servidor). */
export async function removeQueuedEvent(clientEventId) {
    const db = await getDb();
    await db.delete('outbound_events', clientEventId);
}

/** Marca un evento encolado como rechazado por el servidor — no se reintenta más, queda para revisión manual. */
export async function markQueuedEventConflict(clientEventId, message) {
    const db = await getDb();
    const event = await db.get('outbound_events', clientEventId);
    if (!event) return;
    await db.put('outbound_events', { ...event, status: 'conflict', server_message: message ?? null });
}

/** Incrementa el contador de intentos de un evento encolado (diagnóstico, no afecta el reintento en sí). */
export async function incrementQueuedEventAttempts(clientEventId) {
    const db = await getDb();
    const event = await db.get('outbound_events', clientEventId);
    if (!event) return;
    await db.put('outbound_events', { ...event, attempts: (event.attempts ?? 0) + 1 });
}

/** @returns {Promise<number>} Cantidad de eventos pendientes de sincronizar. */
export async function countPendingEvents() {
    return (await getPendingEvents()).length;
}

/** @returns {Promise<number>} Cantidad de eventos en conflicto (requieren revisión manual en Filament). */
export async function countConflictEvents() {
    const db = await getDb();
    const all = await db.getAll('outbound_events');
    return all.filter((event) => event.status === 'conflict').length;
}

/**
 * Elimina del store local los eventos en conflicto — el registro real de lo
 * ocurrido ya vive en el servidor (`AttendanceMarkFailure`, revisado por un
 * admin en Filament); la copia local solo sirve para avisarle una vez al
 * empleado. Sin esto, el aviso de conflicto en /marcar quedaría pegado para
 * siempre (nada más limpia `outbound_events` del lado del cliente).
 */
export async function dismissConflictEvents() {
    const db = await getDb();
    const all = await db.getAll('outbound_events');
    const tx = db.transaction('outbound_events', 'readwrite');
    for (const event of all) {
        if (event.status === 'conflict') await tx.store.delete(event.client_event_id);
    }
    await tx.done;
}

// =========================================================================
// CACHÉ DE ESTADO (employee_status_cache)
// =========================================================================

/**
 * Guarda el último estado de marcación conocido del servidor para el propio
 * empleado (se actualiza cada vez que fetchStatus() tiene éxito).
 * @param {number} employeeId
 * @param {{last_event: string|null, last_event_time: string|null, allowed_events: string[]}} status
 */
export async function setEmployeeStatusCache(employeeId, status) {
    const db = await getDb();
    await db.put('employee_status_cache', { employee_id: employeeId, ...status, cached_at: Date.now() });
}

/** @param {number} employeeId @returns {Promise<object|undefined>} */
export async function getEmployeeStatusCache(employeeId) {
    const db = await getDb();
    return db.get('employee_status_cache', employeeId);
}
