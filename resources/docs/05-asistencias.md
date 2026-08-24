# Asistencias

El módulo de Asistencias registra las entradas y salidas de los empleados y calcula automáticamente horas trabajadas, horas extra, tardanzas y ausencias.

## Modos de marcación

Ir a **Asistencias → Modos de Marcación** para ver los enlaces y códigos QR de cada modo.

### Terminal compartida (kiosco)
Un dispositivo fijo en la sucursal (tablet o PC). Todos los empleados marcan desde ese mismo equipo. No requiere que el empleado tenga cuenta en el sistema. El reconocimiento facial identifica automáticamente quién está marcando.

Cada terminal tiene su propia URL con un código único (ej: `/terminal/a3x9bc7q`). Ver sección **Gestión de Terminales** más abajo.

### Modo móvil
El empleado usa su propio dispositivo (celular o tablet). Puede marcar desde cualquier lugar. Útil para empleados remotos o en campo.

---

## Gestión de Terminales

Ir a **Asistencias → Terminales** para administrar los dispositivos físicos de marcación.

### Crear una terminal

1. Clic en **Nueva Terminal**
2. Completar:
   - **Nombre** — identificador descriptivo (ej: "Terminal Recepción")
   - **Sucursal** — sucursal a la que pertenece el dispositivo
   - **Datos del dispositivo** — marca, modelo, número de serie, MAC (opcional, para inventario)
   - **Fecha de instalación** e **instalado por** (opcional)
3. Guardar

El sistema genera automáticamente un **código único de 8 caracteres** que forma la URL de la terminal.

### URL y código QR

Desde la vista de detalle de la terminal se puede ver:
- La **URL** de acceso (ej: `https://sistema.com/terminal/a3x9bc7q`)
- Un **código QR** para escanear directamente con el dispositivo

Configurar el dispositivo físico para que abra esa URL al iniciar.

### Activar y desactivar

- **Desactivar** — la terminal deja de aceptar marcaciones y muestra una pantalla de fuera de servicio. Útil para mantenimiento o dispositivos retirados temporalmente.
- **Activar** — vuelve a habilitar la terminal.

### Regenerar código

Si la URL de una terminal fue comprometida o el dispositivo fue reemplazado, usar **Regenerar código** para generar una nueva URL.

> ⚠️ Al regenerar el código, la URL anterior deja de funcionar. El dispositivo físico debe reconfigurarse con la nueva URL.

### Marcación en sucursal diferente

Si un empleado marca desde una terminal de una sucursal distinta a la suya, el sistema registra el evento igualmente pero lo marca internamente como **marcación en sucursal diferente** (`branch_mismatch`). Esto permite detectar situaciones atípicas sin bloquear al empleado.

---

## Reconocimiento facial

El sistema usa reconocimiento facial para identificar al empleado sin usuario ni contraseña. El proceso tiene dos etapas: **registro** y **aprobación**.

### Proceso completo de registro facial

**Paso 1 — Generar el enlace de captura:**

1. Ir al perfil del empleado en **Empleados**
2. Ir a la pestaña **Registro Facial**
3. Clic en **Nuevo registro** (o **Generar enlace**)
4. El sistema genera un enlace con token temporal

**Paso 2 — El empleado captura su rostro:**

1. El empleado abre el enlace generado en su dispositivo (celular o PC con cámara)
2. Sigue el proceso de captura facial (el sistema toma varias muestras)
3. Al finalizar, el registro queda en estado **Pendiente de aprobación**

**Paso 3 — El administrador aprueba:**

1. Ir a **Asistencias → Registro Facial**
2. Buscar el registro en estado **Pendiente de aprobación**
3. Revisar la imagen capturada y el score de calidad
4. Clic en **Aprobar** (o **Rechazar** si la calidad es insuficiente)
5. Al aprobar, el descriptor facial queda activo y el empleado puede marcar

### Estados del registro facial

| Estado | Descripción |
|--------|-------------|
| **Pendiente de Captura** | Enlace generado, el empleado aún no capturó |
| **Pendiente de Aprobación** | Rostro capturado, esperando revisión del admin |
| **Aprobado** | Activo, el empleado puede marcar |
| **Rechazado** | Captura rechazada, debe generarse un nuevo enlace |
| **Expirado** | El token venció antes de completar la captura |

### Calidad del registro

| Nivel | Score | Color |
|-------|-------|-------|
| Alta | ≥ 0.85 | Verde |
| Media | ≥ 0.70 | Amarillo |
| Baja | < 0.70 | Rojo |

Se recomienda aprobar solo registros con calidad media o alta.

### Expiración

Los registros faciales tienen una vigencia configurable (en **Configuración General**). Al expirar, el empleado debe renovar su registro repitiendo el proceso desde el Paso 1.

---

## Tipos de eventos de marcación

Cada vez que un empleado interactúa con el sistema de asistencia, se registra un evento:

| Tipo | Descripción |
|------|-------------|
| **Entrada jornada** | Inicio del turno |
| **Inicio descanso** | Pausa (ej: almuerzo) |
| **Fin descanso** | Regreso de pausa |
| **Salida jornada** | Fin del turno |

### Orígenes

| Origen | Descripción |
|--------|-------------|
| **Terminal (kiosco)** | Desde dispositivo compartido de la sucursal |
| **Móvil** | Desde el dispositivo personal del empleado |
| **Manual (admin)** | Ingresado manualmente por el administrador |

---

## Marcaciones manuales

Si un empleado no puede marcar por problema técnico, olvido u otra razón, el administrador puede agregar eventos manualmente.

### Crear una marcación desde el listado de marcaciones

1. Ir a **Asistencias → Marcaciones**
2. Clic en **Nueva marcación**
3. Seleccionar **empleado**, **tipo de evento** (entrada, salida, etc.) y **fecha y hora**
4. Guardar

### Crear una marcación desde el detalle del día

Desde el detalle de un registro de asistencia (**Asistencias → Asistencias** → clic en el registro), se puede gestionar directamente la lista de marcaciones del día:

1. Ir a la sección **Marcaciones** al pie de la página
2. Clic en **Nueva marcación**
3. Seleccionar el tipo de evento y la hora
4. Guardar — el sistema recalcula automáticamente el resumen del día

### Editar una marcación existente

Desde la sección **Marcaciones** del detalle del día, clic en el ícono de edición del evento.

> ⚠️ Al editar una marcación, solo se puede modificar el **tipo de evento** y la **hora**. La fecha no puede cambiarse para evitar mover el evento a otro día de forma accidental.

Después de editar, usar el botón **Recalcular** en el encabezado para actualizar los totales del día.

### Eliminar una marcación

Desde la sección **Marcaciones** del detalle del día, clic en el ícono de eliminar del evento.

> ⚠️ Eliminar una marcación puede afectar el cálculo del día. Después de eliminar, usar el botón **Recalcular** para actualizar los totales.

---

## Resumen diario de asistencia

Ir a **Asistencias → Asistencias** para ver el resumen calculado de cada empleado por día. La tabla muestra únicamente los días con estado **Presente**.

### Pestañas de filtrado

| Pestaña | Descripción |
|---------|-------------|
| **Todos** | Todos los registros presentes |
| **Calculados** | Registros donde ya se ejecutó el cálculo de horas |
| **Sin calcular** | Registros pendientes de cálculo (recién creados o con eventos nuevos) |

### Columnas de la tabla

| Columna | Descripción |
|---------|-------------|
| **Fecha** | Día del registro |
| **Empleado** | Nombre y CI del empleado |
| **Sucursal** | Sucursal a la que pertenece el empleado |
| **Estado** | Estado del día (Presente, Ausente, etc.) |
| **Entrada** | Hora de entrada marcada, con indicador de color |
| **Salida** | Hora de salida marcada, con indicador de color |
| **Tardanza** | Minutos de retraso respecto al horario esperado |
| **Horas trabajadas** | Total de horas marcadas en el día |
| **Hrs Extra** | Horas extras calculadas (en amarillo si hay) |

### Indicadores de color en Entrada y Salida

| Color | Significado |
|-------|-------------|
| **Gris** | No hay marcación registrada para esa columna |
| **Verde** | Marcación en horario (sin tardanza ni salida anticipada) |
| **Rojo** | Entrada con tardanza |
| **Naranja** | Salida anticipada respecto al horario |

### Acciones en la tabla (fila a fila)

Cada fila tiene botones de acción según el estado del registro:

| Acción | Cuándo aparece | Qué hace |
|--------|----------------|----------|
| **Aprobar / Revocar Horas Extra** | El día tiene horas extras calculadas | Aprueba o revoca la aprobación. Solo las HE aprobadas se incluyen en la nómina |
| **Aprobar / Revocar Tardanza** | El día tiene minutos de tardanza | Aprueba o revoca el descuento por tardanza en nómina |
| **PDF** | Siempre visible | Descarga el comprobante PDF del día de asistencia |
| **Ajustar HE** (menú "...") | Siempre visible | Abre el formulario para ingresar horas extras manualmente |
| **Calcular / Recalcular** (menú "...") | Siempre visible | Ejecuta el cálculo de horas, tardanza y extras para ese día |

### Acciones globales del encabezado

Desde el encabezado del listado de asistencias están disponibles:

| Acción | Descripción |
|--------|-------------|
| **Exportar** | Descarga el listado visible en Excel según los filtros activos |
| **Aprobar HE del período** | Aprueba masivamente todas las horas extras pendientes en un rango de fechas |
| **Registrar Horas Extras** | Registra horas extras para empleados que no marcaron ese día (ver sección más abajo) |

### Filtros disponibles

- **Empleado** — buscar por nombre, apellido o CI; selección múltiple
- **Sucursal** — selección múltiple
- **Rango de fechas** — acceso rápido (hoy, esta semana, quincena, mes) o fechas manuales

---

## Vista de detalle de un día de asistencia

Clic en cualquier fila de la tabla para abrir el detalle completo del día. Desde aquí se puede ver toda la información calculada y ejecutar las acciones disponibles.

### Información que muestra el detalle

- **Empleado** — nombre, CI, empresa, sucursal, cargo y departamento
- **Estado** — del día y del cálculo (calculado / pendiente)
- **Tiempos** — entrada y salida esperada vs. marcada, minutos de tardanza y salida anticipada
- **Horas** — esperadas, trabajadas, netas (sin descanso) y extras (diurnas y nocturnas)
- **Marcaciones** — tabla con todos los eventos del día (hora, tipo, origen)

### Acciones disponibles desde el detalle

| Acción | Descripción |
|--------|-------------|
| **Editar** | Edita campos generales del registro (estado, notas, banderas) |
| **Aprobar / Revocar Horas Extra** | Igual que en la tabla; visible solo si hay horas extras |
| **Aprobar / Revocar Tardanza** | Igual que en la tabla; visible solo si hay tardanza |
| **Ajustar Horas Extra** | Ingresa horas extras manualmente sin necesidad de volver al listado |
| **Exportar PDF** | Descarga el comprobante PDF del día |
| **Calcular / Recalcular** | Recalcula todos los totales del día a partir de las marcaciones |

---

## Horas extras manuales

El sistema calcula las horas extras automáticamente cuando hay marcaciones completas (entrada y salida). Sin embargo, hay situaciones donde el administrador debe registrarlas manualmente:

- El empleado trabajó horas extras pero **no marcó** entrada ni salida ese día
- El empleado marcó entrada pero **no marcó salida** y el cálculo automático no capturó las extras

El sistema distingue dos situaciones. Usar la acción correcta para cada caso:

---

### Caso 1 — El empleado tiene registro del día pero las HE no fueron calculadas

Usar cuando ya existe un registro de asistencia para el empleado en esa fecha (por ejemplo, marcó la entrada pero no la salida, o las HE calculadas no son correctas).

**Desde el listado:**

1. Ir a **Asistencias → Asistencias**
2. Localizar el registro del empleado en la fecha correspondiente
3. En la fila, abrir el menú "..." → **Ajustar HE**
4. Ingresar las horas extras diurnas y nocturnas por separado
5. Agregar notas si corresponde (ej: "Tarea especial autorizada por gerencia")
6. Clic en **Guardar**

**Desde el detalle del día:**

1. Abrir el registro haciendo clic en la fila
2. En el encabezado, clic en **Ajustar Horas Extra**
3. Ingresar las horas extras diurnas y nocturnas
4. Clic en **Guardar**

Al guardar, el sistema:
- Actualiza los campos de horas extras (diurnas, nocturnas y total)
- Marca el registro como **ajuste manual** (para que los recálculos automáticos no lo sobreescriban)
- Aprueba las horas extras automáticamente

---

### Caso 2 — El empleado no tiene ningún registro ese día

Usar cuando el empleado trabajó horas extras en un día donde no hay ningún registro en el sistema (no marcó ni entrada ni salida).

1. Ir a **Asistencias → Asistencias**
2. Clic en el botón **Registrar Horas Extras** (encabezado superior)
3. En el formulario:
   - **Empleado** — seleccionar (solo aparecen empleados activos)
   - **Fecha** — el día en que se trabajaron las horas extras
   - **Horas extras diurnas** — en pasos de 0.5 hrs
   - **Horas extras nocturnas** — en pasos de 0.5 hrs
   - **Notas** — campo opcional para justificar o documentar el motivo
4. Clic en **Registrar**

> ⚠️ Si ya existe un registro para ese empleado en esa fecha (por ejemplo, fue registrado como ausente), el sistema lo informará antes de guardar. Al confirmar, los valores de horas extras serán reemplazados y el estado del día pasará a **Presente**.

Al guardar, el sistema crea (o actualiza) el registro con:
- Estado: **Presente**
- Horas extras diurnas y nocturnas ingresadas
- Aprobación automática de las horas extras
- Marca de **ajuste manual**

---

### Diferencia entre horas extras diurnas y nocturnas

La clasificación determina el multiplicador salarial aplicado al momento de generar la nómina:

| Tipo | Referencia horaria | Multiplicador |
|------|-------------------|---------------|
| **Diurnas** | Trabajadas dentro del horario diurno | Configurado en Ajustes de Nómina |
| **Nocturnas** | Trabajadas en horario nocturno | Mayor multiplicador; configurado en Ajustes de Nómina |

Los multiplicadores exactos se definen en **Configuración → Ajustes de Nómina**. Al registrar horas extras manualmente, el formulario muestra el porcentaje vigente en el label de cada campo.

> 💡 Si no se conoce con exactitud la distribución diurna/nocturna, cargar todo en el campo que corresponda según el horario habitual del empleado y agregar una nota explicativa.

---

## Aprobación de horas extras

Las horas extras deben ser aprobadas para incluirse en el cálculo de la nómina. Las horas registradas pero no aprobadas no generan percepción adicional.

### Aprobación individual

En la tabla **Asistencias → Asistencias**, cuando un registro tiene horas extras calculadas, aparece el botón **Aprobar** en la fila. Hacer clic muestra un modal con:

- Total de horas extras y desglose (diurnas / nocturnas) con el porcentaje correspondiente
- Total acumulado de la semana vs. el límite legal semanal
- Aviso si se excede el límite diario (Art. 202 CLT)

Clic en **Sí, aprobar** para confirmar. El botón cambia a **Revocar** para deshacer si es necesario.

La misma acción está disponible desde el detalle del registro (**Aprobar / Revocar Horas Extra** en el encabezado).

### Aprobación masiva por período

Para aprobar todas las horas extras pendientes de un rango de fechas en un solo paso:

1. Desde **Asistencias → Asistencias**, clic en **Aprobar HE del período** (encabezado)
2. Seleccionar la **fecha de inicio** y **fecha de fin**
3. El formulario muestra una vista previa: cuántos registros tienen HE pendientes y cuántas horas en total
4. Clic en **Sí, aprobar todo**

### Aprobación masiva por selección

Para aprobar un conjunto específico de registros seleccionados:

1. Marcar los registros en la tabla usando las casillas de verificación
2. En el menú de acciones masivas (pie de tabla), seleccionar **Aprobar Horas Extra**
3. Confirmar en el modal

---

## Aprobación del descuento por tardanza

Al igual que las horas extras, los descuentos por tardanza también deben aprobarse para aplicarse en nómina.

### Aprobación individual

En la tabla, cuando un registro tiene minutos de tardanza, aparece el botón **Aprobar Tardanza**. El modal muestra:

- Minutos de tardanza y nombre del empleado
- Importe aproximado del descuento en guaraníes

Clic en **Sí, aprobar**. El botón cambia a **Revocar** para deshacer.

La misma acción está disponible desde el detalle del registro.

### Aprobación masiva por selección

1. Seleccionar registros en la tabla
2. En acciones masivas → **Aprobar Descuento Tardanza**
3. Confirmar

---

## Calcular y recalcular

El cálculo del resumen diario se ejecuta automáticamente cada noche a las 23:00. Sin embargo, el administrador puede forzarlo en cualquier momento.

### Cuándo recalcular manualmente

- Después de agregar, editar o eliminar una marcación del día
- Cuando un registro aparece en la pestaña **Sin calcular**
- Cuando los datos del registro no parecen correctos (ej: el total de horas no coincide con las marcaciones)

> ⚠️ Recalcular un registro con **ajuste manual** activo (horas extras ingresadas manualmente) **no sobreescribe** las horas extras ajustadas. El flag de ajuste manual protege esos valores.

### Recalcular un registro

- **Desde la tabla:** menú "..." → **Recalcular**
- **Desde el detalle:** botón **Recalcular** en el encabezado

### Recalcular múltiples registros

1. Seleccionar registros en la tabla
2. En acciones masivas → **Calcular/Recalcular**
3. Confirmar

---

## Reporte de asistencias

Ir a **Asistencias → Reportes de Asistencia** para ver resúmenes por período.

**Filtros disponibles:** período (desde/hasta), empresa, sucursal, departamento.

**Pestaña Asistencias:** días presentes, ausentes, horas trabajadas, horas extra y tardanzas por empleado.

**Pestaña Ausencias:** total de ausencias, justificadas, injustificadas y descuentos por empleado.

Ambas pestañas permiten exportar a Excel (resumen y detalle).
