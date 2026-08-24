/**
 * =============================================================================
 * MATCHING FACIAL CLIENT-SIDE (dispositivo personal offline)
 * =============================================================================
 *
 * @fileoverview Idéntico a `terminal-offline/matcher.js` — mismo algoritmo de
 *               distancia euclidiana + umbral/gap de confianza que el backend
 *               (`AttendanceFaceMarkController::identifyEmployeeByDescriptor()`).
 *               Se duplica en vez de importarse desde `terminal-offline/` para
 *               mantener ambos módulos de captura offline independientes — acá
 *               `candidates` normalmente tiene un solo elemento (el propio
 *               descriptor cacheado), pero la función funciona igual con N: el
 *               gap de confianza simplemente no se evalúa si no hay un segundo
 *               candidato.
 */

/**
 * Distancia euclidiana entre dos descriptores faciales (arrays de 128 floats).
 * @param {number[]} a
 * @param {number[]} b
 * @returns {number}
 */
export function euclideanDistance(a, b) {
    let sum = 0;
    for (let i = 0; i < 128; i++) {
        const diff = (a[i] ?? 0) - (b[i] ?? 0);
        sum += diff * diff;
    }
    return Math.sqrt(sum);
}

/**
 * Identifica al candidato más cercano a un descriptor "en vivo" dentro de la
 * caché local (normalmente un único empleado — el dueño del dispositivo),
 * aplicando el mismo criterio de umbral + gap de confianza que el backend.
 *
 * @param {number[]} liveDescriptor - Descriptor capturado en el momento.
 * @param {Array<{id: number, first_name: string, last_name: string, ci: string|null, face_descriptor: number[], photo_thumbnail?: string|null}>} candidates - Normalmente un solo empleado (el propio dueño del dispositivo).
 * @param {number} threshold - Distancia máxima para aceptar un match (face_threshold).
 * @param {number} minGap - Diferencia mínima requerida con el segundo candidato (face_min_confidence_gap).
 * @returns {{employee: object|null, distance: number, reason: 'no_match'|'ambiguous'|'no_candidates'|null}}
 */
export function identifyEmployee(liveDescriptor, candidates, threshold, minGap) {
    if (!candidates || candidates.length === 0) {
        return { employee: null, distance: Infinity, reason: 'no_candidates' };
    }

    let best = null;
    let bestDist = Infinity;
    let secondBestDist = Infinity;

    for (const candidate of candidates) {
        if (!Array.isArray(candidate.face_descriptor) || candidate.face_descriptor.length !== 128) continue;

        const dist = euclideanDistance(liveDescriptor, candidate.face_descriptor);

        if (dist < bestDist) {
            secondBestDist = bestDist;
            bestDist = dist;
            best = candidate;
        } else if (dist < secondBestDist) {
            secondBestDist = dist;
        }
    }

    if (!best || bestDist > threshold) {
        return { employee: null, distance: bestDist, reason: 'no_match' };
    }

    if (secondBestDist !== Infinity) {
        const gap = secondBestDist - bestDist;
        if (gap < minGap) {
            return { employee: null, distance: bestDist, reason: 'ambiguous' };
        }
    }

    return { employee: best, distance: bestDist, reason: null };
}
