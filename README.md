# Nominapp

Sistema de gestión de recursos humanos y nómina, desarrollado con Laravel y Filament.

## Características

- Gestión de empleados y departamentos
- Control de asistencia con reconocimiento facial, con marcación offline vía PWA (kiosko de sucursal y celular personal)
- Gestión de nóminas y períodos de pago
- Administración de vacaciones y ausencias
- Sistema de préstamos con cuotas
- Percepciones y deducciones personalizadas
- Generación de reportes en PDF y Excel
- Calendario de días feriados
- Gestión de horarios y turnos

## Requisitos

- PHP ^8.2 (con extensiones: PDO, mbstring, OpenSSL, JSON, BCMath, Ctype, Fileinfo, Tokenizer)
- Composer
- Node.js ≥ 18
- MySQL / PostgreSQL / SQLite

## Instalación

1. Clonar el repositorio:
```bash
git clone <url-del-repositorio>
cd nominapp
```

2. Instalar dependencias de PHP:
```bash
composer install
```

3. Instalar dependencias de Node.js:
```bash
npm install
```

4. Copiar el archivo de entorno:
```bash
cp .env.example .env
```

5. Generar la clave de aplicación:
```bash
php artisan key:generate
```

6. Configurar el archivo `.env`:

   **Base de datos** (obligatorio):
   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=nombre_base_datos
   DB_USERNAME=usuario
   DB_PASSWORD=contraseña
   ```

   **URL de la aplicación** (ajustar según el entorno):
   ```
   APP_URL=http://localhost:8000
   ```

   **Google Maps** (requerido para el mapa de sucursales):
   ```
   GOOGLE_MAPS_API_KEY=tu_clave_aqui
   ```

   **Administrador inicial** (usado por el seeder de producción):
   ```
   ADMIN_NAME="Nombre Apellido"
   ADMIN_EMAIL=admin@ejemplo.com
   ADMIN_PASSWORD=contraseña_segura
   ```
   > Si `ADMIN_PASSWORD` se deja vacío, se genera una contraseña aleatoria y se muestra en consola.

   > Cola de trabajos, caché y sesiones ya están configurados como `database` en `.env.example` — no requieren cambios.

7. Ejecutar las migraciones:
```bash
php artisan migrate
```

8. Crear el enlace simbólico de almacenamiento (requerido para PDFs y logos):
```bash
php artisan storage:link
```

9. Ejecutar los seeders según el entorno:

   **Desarrollo** — carga datos de demostración (empleados, nóminas, etc.):
   ```bash
   php artisan db:seed
   ```

   **Producción / nuevo cliente** — crea el usuario administrador y los códigos de deducción obligatorios (`IPS001`, `PRE001`, `ADE001`):
   ```bash
   php artisan db:seed --class=ProductionSeeder
   ```

10. Compilar los assets:
```bash
npm run build
```

## Uso

Acceder a la aplicación en la URL configurada en `APP_URL` (por defecto `http://localhost:8000`).

Para producción, optimizar antes de desplegar:
```bash
php artisan optimize
php artisan filament:optimize
```

Configurar el scheduler en cron para tareas automáticas (ausencias, vencimientos, préstamos, etc.):
```
* * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1
```

## Desarrollo

Iniciar el servidor de desarrollo, cola de trabajos y compilador de assets simultáneamente:
```bash
composer run dev
```

Este comando ejecuta en paralelo:
- `php artisan serve` — servidor HTTP
- `php artisan queue:listen --tries=1` — procesador de colas
- `npm run dev` — Vite con HMR

## Licencia

MIT
