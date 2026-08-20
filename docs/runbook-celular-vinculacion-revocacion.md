# Runbook: Vinculación y revocación de celulares personales

Guía operativa para el equipo de soporte/administración de **Nominapp** ante la pérdida, robo, cambio de celular, o sospecha de vinculación no autorizada del dispositivo personal de un empleado usado para marcar asistencia offline.

---

## Cuándo aplica

- El empleado perdió su celular o se lo robaron.
- El empleado cambió de celular (nuevo dispositivo, mismo número o no).
- Llega la notificación de campanita **"Celular re-vinculado"** (Filament) y hace falta confirmar con el empleado si el cambio fue legítimo.
- Se sospecha que alguien vinculó su propio celular usando el CI + fecha de nacimiento de otro empleado (denegación de servicio dirigida — ver [Riesgos aceptados](#riesgos-aceptados)).
- El empleado causa baja (aunque esto se revoca automáticamente — ver [Revocación automática](#revocación-automática-sin-intervención-manual)).

A diferencia del kiosko de sucursal, acá **no hay enlace de un solo uso generado por un admin**: el propio empleado se vincula en `/vincular-celular` identificándose con su CI + fecha de nacimiento. El token Sanctum del celular (`ability: mobile:sync`) sigue siendo válido hasta que se revoque explícitamente — no expira solo.

## Qué NO cubre este runbook

- **Borrado remoto del celular perdido**: no existe hoy un mecanismo para borrar la caché de IndexedDB (el descriptor facial propio del empleado) de un dispositivo físico que ya no está bajo control. Revocar el token evita que ese dispositivo pueda seguir *sincronizando* o *marcando*, pero el descriptor que ya tenía cacheado localmente permanece en el dispositivo — mismo riesgo aceptado que en el kiosko, mitigado acá por el hecho de que solo se cachea el descriptor de UN empleado (no toda la sucursal).
- Incidentes de seguridad más amplios (compromiso del servidor, credenciales de admin filtradas).

---

## Paso 1 — Revocar el celular afectado

1. Ir a **Filament → Empleados**, ubicar al empleado (por nombre, CI, o filtrando por la columna **Celular vinculado**).
2. Abrir la ficha del empleado y, en las acciones de fila (`⋮`) del listado o desde su vista, seleccionar **"Revocar sesión móvil"**.
3. Confirmar en el modal. Esto invalida inmediatamente el token Sanctum del celular — el próximo intento de identificación o sincronización desde ese dispositivo recibirá `401`/`403`, y `mark.js` lo redirige automáticamente a `/vincular-celular`.

**No hace falta desactivar al empleado** (`status: inactive`) para revocar el celular — son conceptos independientes. Si el empleado sigue activo y solo perdió el celular, revocar el token alcanza; el empleado puede re-vincular un celular nuevo apenas quiera.

## Paso 2 — Confirmar que el celular quedó sin acceso

En la ficha del empleado, la columna **Celular vinculado** debe pasar a "No vinculado" de inmediato (es síncrono, no depende de un heartbeat). La columna **Último sync celular** deja de actualizarse — es esperado, ya no puede completar heartbeats.

## Paso 3 — Re-vincular (celular nuevo o el mismo ya recuperado)

No hace falta que un admin genere nada — el propio empleado repite el flujo de auto-servicio:

1. El empleado abre `/vincular-celular` desde el celular (nuevo o recuperado), **con conexión a internet**.
2. Ingresa su CI y fecha de nacimiento (los mismos datos que usó la primera vez).
3. Si los datos coinciden con un empleado activo con reconocimiento facial habilitado, se emite un token nuevo y el empleado es redirigido automáticamente a `/marcar`.
4. El celular queda vinculado — el descriptor facial propio se sincroniza en el primer heartbeat.

**Vincular un celular nuevo revoca automáticamente cualquier token anterior** (`Employee::claimMobileToken()`) — no hace falta un Paso 1 explícito si el empleado simplemente está reemplazando un celular que ya no tiene: re-vincular directamente alcanza. El Paso 1 (revocar desde Filament) es necesario cuando el celular perdido/robado **no está en manos del empleado** para que él mismo lo reemplace vinculando uno nuevo.

## Paso 4 — Verificar que la vinculación funcionó

En la ficha del empleado en Filament:

- **Celular vinculado**: debe mostrar "Vinculado" con la fecha/hora reciente (tooltip).
- **Último sync celular**: debe actualizarse a "hace unos segundos" tras el primer heartbeat (ocurre automáticamente al cargar `/marcar`).

Si el empleado reporta que `/marcar` lo sigue mandando a `/vincular-celular` en un loop, o que la identificación facial falla repetidamente después de vincular, verificar:
- Que el empleado tenga `face_descriptor` cargado (reconocimiento facial habilitado) — sin esto, la vinculación se rechaza con el mismo mensaje genérico que credenciales incorrectas.
- Los logs del servidor (`storage/logs/laravel-*.log`) por el mensaje `Intento de vinculación de celular con datos inválidos` — incluye el CI intentado (no la fecha) para diagnosticar si el empleado está escribiendo mal su CI o su fecha de nacimiento.

---

## Revocación automática (sin intervención manual)

El sistema revoca el token móvil solo, sin que un admin tenga que actuar, en estos casos:

- **El empleado deja de estar activo** (`status !== 'active'`): tanto `MobileHeartbeatController` como `MobileEventSyncController` revocan el token en el primer intento de uso tras la baja/suspensión, y responden `403` con un mensaje pidiendo consultar a RRHH.
- **Vincular un celular nuevo**: revoca automáticamente el anterior (ver Paso 3).

## Ante la notificación "Celular re-vinculado"

Cuando un empleado que **ya tenía** un celular vinculado vincula uno nuevo, todos los admins reciben una notificación de campanita en Filament (`MobileDeviceRelinkedNotification`). Esto puede significar dos cosas:

1. **Legítimo**: el empleado cambió de celular y volvió a pasar por `/vincular-celular` él mismo — no hace falta ninguna acción, la notificación es solo informativa.
2. **Sospechoso**: alguien con el CI + fecha de nacimiento del empleado (datos no siempre secretos — ver [Riesgos aceptados](#riesgos-aceptados)) vinculó su propio celular, revocando silenciosamente el del empleado legítimo.

Ante la duda, contactar al empleado directamente (no por la app — su celular pudo haber sido desvinculado) y preguntar si reconoce el cambio. Si NO lo reconoce:

1. Revocar el celular actual (Paso 1).
2. Pedirle al empleado que vuelva a vincularse él mismo desde su celular (Paso 3).
3. Si el patrón se repite (varias re-vinculaciones no reconocidas seguidas), escalar — puede tratarse de un intento dirigido de denegación de servicio contra ese empleado específico, y ameritaría revisar los logs de `Intento de vinculación de celular con datos inválidos` filtrando por ese CI.

---

## Riesgos aceptados

- **CI + fecha de nacimiento como credencial de vinculación**: es débil por sí sola (datos semi-adivinables, no secretos como una contraseña) — el diseño la trata como "reclamar el dispositivo para cachear un descriptor", no como control de acceso completo. La marcación en sí sigue requiriendo un match facial exitoso contra el descriptor cacheado (segundo factor: algo que sos, no algo que sabés). Mitigado con throttling agresivo (`throttle:5,1` + `throttle:15,1440` en el POST de `/vincular-celular`) y la notificación de re-vinculación descrita arriba — no eliminado.
- **Ventana entre la pérdida y la revocación**: mientras el token no se revoca, el celular puede seguir marcando e identificando con normalidad. No hay alerta automática de "celular reportado como perdido" — depende de que el empleado avise a RRHH/soporte apenas se entera del incidente.
- **Sin borrado remoto**: ver [Qué NO cubre este runbook](#qué-no-cubre-este-runbook).

## Checklist rápido

- [ ] Revocar el celular afectado (Filament → Empleados → ficha del empleado → Revocar sesión móvil) — solo si el dispositivo perdido/robado NO está en manos del empleado
- [ ] Empleado re-vincula desde el celular nuevo/recuperado en `/vincular-celular`, online
- [ ] Confirmar "Vinculado" + "Último sync celular" reciente en la ficha del empleado
- [ ] Ante la notificación de re-vinculación: confirmar con el empleado (fuera de la app) si reconoce el cambio
- [ ] Si no lo reconoce: revocar + pedir que se re-vincule + evaluar si escalar por patrón de acoso
