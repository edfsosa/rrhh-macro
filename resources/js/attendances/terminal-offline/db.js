/**
 * =============================================================================
 * INDEXEDDB — CACHÉ LOCAL DEL TERMINAL
 * =============================================================================
 *
 * @fileoverview Wrapper delgado sobre `idb` para la base local del kiosko.
 *               Se crean los 4 stores de una vez (aunque `outbound_events` y
 *               `sync_log` recién se usan a partir de fases posteriores) para
 *               no requerir un bump de versión de IndexedDB más adelante.
 *
 * Stores:
 * - terminal_meta   — key/value: api_token, terminal_id/code/branch_id,
 *                      cursores de sync, config de reconocimiento facial.
 * - employees_cache — keyPath 'id': empleados activos con descriptor facial,
 *                      scopeados a la sucursal del terminal (ver sync.js).
 * - outbound_events — keyPath 'client_event_id': cola de marcaciones
 *                      pendientes de sincronizar (fase de cola offline).
 * - sync_log        — autoIncrement: historial breve de intentos de sync,
 *                      para diagnóstico en pantalla.
 */

import { openDB } from 'idb';

const DB_NAME = 'nominapp-terminal';
const DB_VERSION = 1;

/** @type {Promise<import('idb').IDBPDatabase>|null} */
let dbPromise = null;

/** @returns {Promise<import('idb').IDBPDatabase>} */
export function getDb() {
    if (!dbPromise) {
        dbPromise = openDB(DB_NAME, DB_VERSION, {
            upgrade(db) {
                if (!db.objectStoreNames.contains('terminal_meta')) {
                    db.createObjectStore('terminal_meta');
                }
                if (!db.objectStoreNames.contains('employees_cache')) {
                    db.createObjectStore('employees_cache', { keyPath: 'id' });
                }
                if (!db.objectStoreNames.contains('outbound_events')) {
                    db.createObjectStore('outbound_events', { keyPath: 'client_event_id' });
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
 * Lee un valor de `terminal_meta`.
 * @param {string} key
 * @returns {Promise<any>}
 */
export async function getMeta(key) {
    const db = await getDb();
    return db.get('terminal_meta', key);
}

/**
 * Escribe un valor en `terminal_meta`.
 * @param {string} key
 * @param {any} value
 */
export async function setMeta(key, value) {
    const db = await getDb();
    return db.put('terminal_meta', value, key);
}

/**
 * Migración única del token guardado en localStorage por la pantalla de
 * configuración (terminal-setup.blade.php) hacia IndexedDB. Se puede llamar
 * en cada carga: es un no-op si ya no queda nada en localStorage.
 * @returns {Promise<void>}
 */
export async function migrateTokenFromLocalStorage() {
    const legacyToken = localStorage.getItem('nominapp_terminal_token');
    if (!legacyToken) return;

    const legacyCode = localStorage.getItem('nominapp_terminal_code');
    await setMeta('api_token', legacyToken);
    if (legacyCode) await setMeta('terminal_code', legacyCode);

    localStorage.removeItem('nominapp_terminal_token');
    localStorage.removeItem('nominapp_terminal_code');
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

/** @returns {Promise<Array<object>>} */
export async function getCachedEmployees() {
    const db = await getDb();
    return db.getAll('employees_cache');
}

/**
 * Aplica un delta de sincronización de empleados: upsert de los modificados,
 * borrado de los tombstones.
 * @param {Array<object>} employees
 * @param {Array<number>} tombstones
 */
export async function applyEmployeesDelta(employees, tombstones) {
    const db = await getDb();
    const tx = db.transaction('employees_cache', 'readwrite');
    for (const employee of employees) {
        await tx.store.put(employee);
    }
    for (const id of tombstones) {
        await tx.store.delete(id);
    }
    await tx.done;
}
