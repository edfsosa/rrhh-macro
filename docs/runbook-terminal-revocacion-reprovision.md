# Runbook: Revocación y reprovisión de terminales offline

Guía operativa para el equipo de soporte/administración de **Nominapp** ante la pérdida, robo o reemplazo de un terminal de marcación de asistencia (offline vía PWA).

---

## Cuándo aplica

- El dispositivo físico del terminal se perdió o fue robado.
- Se reemplaza el hardware de un terminal existente (tablet/PC nueva en la misma sucursal).
- Se sospecha que el token de sincronización de un terminal fue comprometido (ej. acceso no autorizado al dispositivo).
- Un terminal se va a dar de baja definitivamente (la sucursal cierra, o se reemplaza el proceso de marcación).

En todos estos casos, el token Sanctum del terminal (`ability: terminal:sync`) sigue siendo válido hasta que se revoque explícitamente — **no expira solo**. Actuar rápido ante una pérdida es importante: quien tenga el dispositivo físico puede seguir marcando asistencias (o consultando la caché de empleados sincronizada) hasta que el token se revoque.

## Qué NO cubre este runbook

- **Borrado remoto del dispositivo perdido**: no existe hoy un mecanismo para borrar la caché de IndexedDB (empleados, descriptores faciales) de un dispositivo físico que ya no está bajo control. Revocar el token evita que ese dispositivo pueda seguir *sincronizando* con el servidor, pero los datos que ya tenía cacheados localmente permanecen en el dispositivo. Ver [Riesgos aceptados](#riesgos-aceptados) más abajo.
- Incidentes de seguridad más amplios (compromiso del servidor, credenciales de admin filtradas) — este runbook es específico para un terminal individual.

---

## Paso 1 — Revocar el token del terminal comprometido/perdido

1. Ir a **Filament → Asistencias → Terminales**.
2. Ubicar el terminal afectado (por nombre o sucursal).
3. Abrir el menú de acciones de la fila (`⋮`) y seleccionar **"Revocar token"**.
4. Confirmar en el modal. Esto invalida inmediatamente el token Sanctum — el próximo intento de sincronización desde ese dispositivo recibirá `401`/`403` y el terminal mostrará "Terminal sin configurar" (o el mensaje equivalente de re-provisión).

**No hace falta desactivar el terminal** (`status: inactive`) al revocar el token — son conceptos independientes: `status` controla si el terminal puede usarse para marcar (vía `/terminal/{code}`), mientras que el token controla el acceso a la API de sincronización offline. Si el dispositivo físico se perdió y no se va a recuperar, desactivar el terminal además de revocar el token evita que alguien reactive el acceso reutilizando la URL pública.

## Paso 2 — Confirmar que el terminal quedó sin acceso

En la tabla de Terminales, la columna **Conectividad** del terminal revocado eventualmente pasará a "Desconectado" y luego (tras el umbral configurado en `Configuración General → Terminales de Marcación`) no se actualizará más — es esperado, ya no puede completar heartbeats. No hace falta esperar a que esto pase para continuar con la reprovisión.

## Paso 3 — Reprovisionar (dispositivo nuevo o el mismo ya recuperado)

1. En la misma fila del terminal, abrir **"Generar enlace de configuración"**.
2. Se genera un enlace/QR de un solo uso, válido por 30 minutos. Este paso también invalida cualquier enlace de configuración anterior sin usar.
3. Con el dispositivo físico **conectado a internet**, abrir el enlace (o escanear el QR) una única vez. Esto reclama un token Sanctum nuevo y lo guarda en el propio dispositivo (IndexedDB) — el token nunca aparece en la URL ni se muestra en pantalla más que la primera vez.
4. Una vez completada la configuración, navegar a `/terminal/{code}` normalmente y dejar el dispositivo funcionando con conexión al menos hasta que complete el primer heartbeat y el primer sync de empleados.

## Paso 4 — Verificar que la reprovisión funcionó

En **Filament → Asistencias → Terminales → (ver el terminal)**, sección **Conectividad**:

- **Estado**: debe pasar a "En línea" dentro de los primeros segundos/minutos (heartbeat cada 90s mientras hay red).
- **Último sync de empleados**: debe tener una fecha reciente — confirma que la caché de empleados/descriptores se está descargando de nuevo en el dispositivo nuevo.
- **Cola de sincronización**: debe estar en "Sin pendientes" — si aparece "Con pendientes" o "Con conflictos" persistentemente, revisar `AttendanceMarkFailureResource` (filtrado por el terminal/sucursal) antes de dar el proceso por cerrado.

Si el terminal no pasa a "En línea" en un tiempo razonable, verificar:
- Que el dispositivo tenga conexión real a internet (no solo a una red local sin salida).
- Que la URL usada sea la correcta (`/terminal/{code}` con el código del terminal, no uno viejo).
- Los logs del servidor (`storage/logs/laravel-*.log`) por errores 401/403 repetidos, que indicarían que el token no se guardó correctamente en el dispositivo.

---

## Riesgos aceptados

- **Biometría sin cifrar en el dispositivo perdido**: la caché de `employees_cache` (IndexedDB) incluye los descriptores faciales de los empleados de la sucursal, en texto plano — mismo nivel de exposición que el caché server-side (`Cache::remember('employees_face_descriptors', ...)`). Revocar el token detiene la sincronización futura, pero no borra lo que ya estaba cacheado en el dispositivo perdido. Si el dispositivo se recupera físicamente, no hace falta ninguna acción adicional — el token revocado ya impide que vuelva a sincronizar sin pasar por reprovisión.
- **Ventana entre la pérdida y la revocación**: mientras el token no se revoca, el dispositivo puede seguir marcando asistencias y sincronizando eventos con normalidad. No hay alerta automática de "terminal reportado como perdido" — depende de que el equipo de soporte actúe apenas se entera del incidente.

## Checklist rápido

- [ ] Revocar el token del terminal afectado (Filament → Terminales → Revocar token)
- [ ] Si el dispositivo no se va a recuperar: marcar el terminal como `Inactiva`
- [ ] Generar un nuevo enlace de configuración
- [ ] Provisionar el dispositivo nuevo (o el mismo, ya recuperado) con el enlace, online
- [ ] Confirmar "En línea" + sync de empleados reciente + "Sin pendientes" en la sección Conectividad
- [ ] Si el terminal fue reemplazado por hardware nuevo: actualizar los datos del dispositivo físico (marca/modelo/serie) en la ficha del terminal
