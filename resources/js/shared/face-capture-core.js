/**
 * =============================================================================
 * NÚCLEO COMPARTIDO DE CAPTURA FACIAL (matemática/captura, no UI)
 * =============================================================================
 *
 * @fileoverview Lógica de captura de múltiples muestras de descriptor facial y su
 *               promediado, extraída de mark.js, terminal.js y FaceCaptureApp.js
 *               para que un ajuste de tuning (tamaño mínimo de cara, umbrales) se
 *               haga en un solo lugar. Deliberadamente NO incluye manejo de DOM ni
 *               máquinas de estado de pantalla — cada caller (marcación manual,
 *               terminal, enrolamiento) tiene su propio flujo de UI distinto.
 *
 * @requires face-api.js cargado globalmente (faceapi) antes de invocar estas funciones.
 */

/**
 * Captura múltiples muestras válidas del descriptor facial de un elemento <video>.
 *
 * Dos modos de uso según `maxAttempts`:
 * - `maxAttempts === samples` (default): intenta exactamente `samples` veces, tantas
 *   muestras válidas como se logren capturar (comportamiento de mark.js/terminal.js).
 * - `maxAttempts > samples`: reintenta hasta lograr `samples` muestras válidas o
 *   agotar los intentos (comportamiento de FaceCaptureApp para enrolamiento, donde
 *   se prioriza calidad sobre velocidad).
 *
 * @param {HTMLVideoElement} video
 * @param {faceapi.TinyFaceDetectorOptions} tinyOptions
 * @param {Object} [options]
 * @param {number} [options.samples=5] - Cantidad objetivo de muestras válidas.
 * @param {number} [options.intervalMs=150] - Espera entre intentos.
 * @param {number} [options.minFaceSize=100] - Ancho/alto mínimo en px del bounding box para aceptar una muestra.
 * @param {number} [options.minRequired=3] - Mínimo de muestras válidas para no lanzar error.
 * @param {number} [options.maxAttempts] - Intentos máximos (default: igual a `samples`, sin reintentos extra).
 * @param {(validCount: number) => void} [options.onProgress] - Se llama cada vez que se acepta una muestra válida.
 * @param {(reason: 'too_small'|'no_face'|'error', detail?: any) => void} [options.onRejectedSample] - Se llama cuando un intento no produce una muestra válida.
 * @returns {Promise<{descriptors: number[][], samples: Array<{descriptor: number[], box: Object, score: number}>, averaged: number[]}>}
 * @throws {Error} Si no se alcanza `minRequired` muestras válidas.
 */
export async function captureFaceSamples(video, tinyOptions, options = {}) {
    const {
        samples = 5,
        intervalMs = 150,
        minFaceSize = 100,
        minRequired = 3,
        maxAttempts = samples,
        onProgress = null,
        onRejectedSample = null,
    } = options;

    const collected = [];
    let attempts = 0;

    while (collected.length < samples && attempts < maxAttempts) {
        attempts++;

        try {
            const detection = await faceapi
                .detectSingleFace(video, tinyOptions)
                .withFaceLandmarks()
                .withFaceDescriptor();

            if (detection?.descriptor) {
                const box = detection.detection.box;
                if (box.width >= minFaceSize && box.height >= minFaceSize) {
                    collected.push({
                        descriptor: Array.from(detection.descriptor),
                        box,
                        score: detection.detection.score,
                    });
                    onProgress?.(collected.length);
                } else {
                    onRejectedSample?.('too_small', box);
                }
            } else {
                onRejectedSample?.('no_face');
            }
        } catch (error) {
            onRejectedSample?.('error', error);
        }

        if (collected.length < samples && attempts < maxAttempts) {
            await sleep(intervalMs);
        }
    }

    if (collected.length < minRequired) {
        throw new Error(
            `Solo se capturaron ${collected.length} muestras válidas (mínimo ${minRequired}). Acerque el rostro a la cámara.`
        );
    }

    const descriptors = collected.map((s) => s.descriptor);

    return {
        descriptors,
        samples: collected,
        averaged: averageDescriptors(descriptors),
    };
}

/**
 * Promedia N descriptores faciales (arrays de 128 floats) elemento a elemento.
 * @param {number[][]} descriptors
 * @param {number} [length=128]
 * @returns {number[]}
 */
export function averageDescriptors(descriptors, length = 128) {
    const averaged = new Array(length).fill(0);

    for (const descriptor of descriptors) {
        for (let i = 0; i < length; i++) {
            averaged[i] += descriptor[i];
        }
    }
    for (let i = 0; i < length; i++) {
        averaged[i] /= descriptors.length;
    }

    return averaged;
}

/**
 * @param {number} ms
 * @returns {Promise<void>}
 */
export function sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}
