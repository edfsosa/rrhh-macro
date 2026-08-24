<?php

namespace App\Http\Controllers;

use App\Models\AttendanceDay;
use App\Models\AttendanceEvent;
use App\Models\AttendanceMarkFailure;
use App\Models\Employee;
use App\Models\Terminal;
use App\Rules\FaceDescriptor;
use App\Settings\GeneralSettings;
use Carbon\Carbon;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use JsonException;
use Throwable;

class AttendanceFaceMarkController extends Controller
{
    /** Muestra la página de marcación facial */
    public function show(): ViewContract
    {
        return view('attendances.mark', ['title' => 'Marcación Facial']);
    }

    /** Muestra la página de marcación en modo terminal/kiosco (legacy — URL sin código) */
    public function terminal(): ViewContract
    {
        return view('attendances.terminal', ['title' => 'Terminal de Marcación']);
    }

    /**
     * Muestra la terminal de marcación identificada por su código único.
     * Actualiza last_seen_at en cada carga. Si está inactiva, muestra pantalla de fuera de servicio.
     *
     * @param  string  $code  Código único de 8 caracteres de la terminal
     */
    public function terminalByCode(string $code): ViewContract
    {
        $terminal = Terminal::with('branch.company')->where('code', $code)->first();

        if (! $terminal) {
            abort(404);
        }

        if ($terminal->isInactive()) {
            return view('attendances.terminal-inactive', [
                'title' => 'Terminal fuera de servicio',
                'terminal' => $terminal,
            ]);
        }

        $terminal->update(['last_seen_at' => now()]);

        return view('attendances.terminal', [
            'title' => "Terminal — {$terminal->name}",
            'terminal' => $terminal,
        ]);
    }

    /** 1) Identifica al empleado por descriptor y devuelve data + eventos permitidos */
    public function identify(Request $request): JsonResponse
    {
        try {
            // CORRECCIÓN 1: Mover validación dentro del try-catch para capturar ValidationException
            $data = $request->validate([
                'face_descriptor' => ['required', new FaceDescriptor],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Datos de entrada inválidos.',
                'errors' => $e->errors(),
            ], 422);
        }

        // Normalizar descriptor (acepta string JSON o array)
        try {
            $raw = $data['face_descriptor'];
            $live = is_string($raw)
                ? json_decode($raw, true, 512, JSON_THROW_ON_ERROR)
                : (is_array($raw) ? $raw : null);

            if (! is_array($live) || count($live) !== 128) {
                // CORRECCIÓN 2: Verificar que todos los elementos sean numéricos
                return response()->json([
                    'ok' => false,
                    'message' => 'El descriptor facial no tiene el formato esperado (debe ser array de 128 números).',
                ], 422);
            }

            // CORRECCIÓN 3: Validar que todos los elementos del descriptor sean numéricos
            foreach ($live as $value) {
                if (! is_numeric($value)) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'El descriptor facial debe contener solo valores numéricos.',
                    ], 422);
                }
            }
        } catch (JsonException $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Descriptor facial inválido (JSON malformado).',
            ], 422);
        }

        try {
            // CORRECCIÓN 4: Validar que el threshold sea válido
            $threshold = (float) app(GeneralSettings::class)->face_threshold;
            if ($threshold <= 0 || $threshold > 2.0) {
                Log::warning("Umbral de reconocimiento facial inválido ({$threshold}), usando valor por defecto 0.45", ['threshold' => $threshold]);
                $threshold = 0.45; // valor por defecto seguro
            }

            [$employee, $distance, $reason] = $this->identifyEmployeeByDescriptor($live, $threshold);

            if ($employee) {
                $employee->load('branch');
            }

            if (! $employee) {
                $failureType = match ($reason) {
                    'ambiguous' => 'face_ambiguous',
                    'no_match' => 'face_no_match',
                    'no_candidates' => 'face_no_candidates',
                    default => 'internal_error',
                };

                $message = match ($reason) {
                    'ambiguous' => 'Rostro ambiguo. Por favor, reposicione su cara e intente de nuevo.',
                    'no_match' => 'No se pudo identificar el rostro. Intente nuevamente.',
                    default => 'Error al procesar el rostro.',
                };

                $this->recordFailure($request, $failureType, $message, [
                    'best_distance' => $distance !== INF ? round($distance, 4) : null,
                    'reason' => $reason,
                ]);

                return response()->json([
                    'ok' => false,
                    'message' => $message,
                    'reason' => $reason,
                ], 422);
            }

            // CORRECCIÓN 5: Usar timezone de la aplicación
            $today = Carbon::now(config('app.timezone'))->toDateString();

            $day = AttendanceDay::where('employee_id', $employee->id)
                ->where('date', $today)
                ->first();

            $last = null;
            if ($day) {
                $last = AttendanceEvent::where('attendance_day_id', $day->id)
                    ->latest('recorded_at')
                    ->first();
            }

            $allowed = $this->allowedNextEvents($last?->event_type);

            $photoUrl = $employee->photo
                ? Storage::url($employee->photo)
                : url('/images/default-avatar.png');

            return response()->json([
                'ok' => true,
                'employee' => [
                    'id' => $employee->id,
                    'first_name' => $employee->first_name ?? '',
                    'last_name' => $employee->last_name ?? '',
                    'ci' => $employee->ci ?? null,
                    'branch_name' => $employee->branch?->name ?? null,
                    'photo_url' => $photoUrl,
                ],
                'distance' => round($distance, 4),
                'last_event' => $last?->event_type,
                'last_event_time' => $last?->recorded_at?->format('H:i'),
                'allowed_events' => $allowed,
            ]);
        } catch (Throwable $e) {
            Log::error("Error en identificación facial: {$e->getMessage()}", [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => app()->hasDebugModeEnabled() ? $e->getTraceAsString() : null,
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Error interno al identificar empleado.',
            ], 500);
        }
    }

    /** 2) Registra la marcación (ubicación opcional - si no se envía, usa coordenadas de sucursal) */
    public function store(Request $request): JsonResponse
    {
        try {
            // CORRECCIÓN 6: Validación más robusta con mensajes personalizados
            // TERMINAL MODE: location es ahora opcional (nullable)
            $data = $request->validate([
                'employee_id' => ['required', 'integer', 'exists:employees,id'],
                'event_type' => ['required', 'string', 'in:check_in,break_start,break_end,check_out'],
                'source' => ['nullable', 'string', 'in:terminal,mobile,manual'],
                'terminal_code' => ['nullable', 'string'],
                'location' => ['nullable', 'array'],
                'location.lat' => ['required_with:location', 'numeric', 'between:-90,90'],
                'location.lng' => ['required_with:location', 'numeric', 'between:-180,180'],
            ], [
                'employee_id.required' => 'ID de empleado es requerido.',
                'employee_id.exists' => 'El empleado no existe.',
                'event_type.required' => 'Tipo de evento es requerido.',
                'event_type.in' => 'Tipo de evento inválido.',
                'location.lat.between' => 'Latitud debe estar entre -90 y 90.',
                'location.lng.between' => 'Longitud debe estar entre -180 y 180.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Datos de entrada inválidos.',
                'errors' => $e->errors(),
            ], 422);
        }

        try {
            // CORRECCIÓN 7: Verificar que el empleado esté activo y cargar sucursal
            $employee = Employee::with('branch')
                ->where('id', $data['employee_id'])
                ->where('status', 'active')
                ->firstOrFail();
        } catch (ModelNotFoundException $e) {
            Log::warning("Marcación fallida: empleado ID {$data['employee_id']} no encontrado o inactivo", [
                'employee_id' => $data['employee_id'],
                'ip' => $request->ip(),
            ]);

            $this->recordFailure($request, 'employee_not_found', 'Empleado no encontrado o inactivo.', [
                'employee_id' => $data['employee_id'],
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Empleado no encontrado o inactivo.',
            ], 422);
        }

        // Resolver la terminal si se envió terminal_code
        $terminal = null;
        if (! empty($data['terminal_code'])) {
            $terminal = Terminal::with('branch')->where('code', $data['terminal_code'])->first();
        }

        // TERMINAL MODE: Si no se envía location, usar coordenadas de la sucursal de la terminal (o del empleado)
        $locationBranch = $terminal?->branch ?? $employee->branch;

        if (empty($data['location'])) {
            // Verificar que exista sucursal con coordenadas
            if (! $locationBranch) {
                Log::warning("Marcación fallida — CI {$employee->ci} {$employee->first_name} {$employee->last_name}: sin sucursal asignada", [
                    'employee_id' => $employee->id,
                    'employee_ci' => $employee->ci,
                ]);

                $this->recordFailure($request, 'employee_no_branch', 'El empleado no tiene una sucursal asignada.', [], $employee);

                return response()->json([
                    'ok' => false,
                    'message' => 'El empleado no tiene una sucursal asignada. No se puede determinar la ubicación.',
                ], 422);
            }

            $branchCoords = $locationBranch->coordinates;

            // Verificar que la sucursal tenga coordenadas configuradas
            if (! $branchCoords || ! isset($branchCoords['lat'], $branchCoords['lng'])) {
                Log::warning("Marcación fallida — CI {$employee->ci} {$employee->first_name} {$employee->last_name}: sucursal '{$locationBranch->name}' sin coordenadas GPS configuradas", [
                    'employee_id' => $employee->id,
                    'branch_id' => $locationBranch->id,
                    'branch_name' => $locationBranch->name,
                ]);

                $this->recordFailure($request, 'branch_no_coordinates', 'Sucursal sin coordenadas configuradas.', [
                    'branch_id' => $locationBranch->id,
                    'branch_name' => $locationBranch->name,
                ], $employee, $locationBranch);

                return response()->json([
                    'ok' => false,
                    'message' => 'La sucursal no tiene coordenadas configuradas. Por favor, contacte al administrador.',
                ], 422);
            }

            // Usar coordenadas de la sucursal (de la terminal si aplica, sino del empleado)
            $location = [
                'lat' => (float) $branchCoords['lat'],
                'lng' => (float) $branchCoords['lng'],
            ];

            $gpsSource = $terminal ? 'terminal' : 'sucursal del empleado';
            Log::info("Marcación sin GPS — CI {$employee->ci} {$employee->first_name}: coords de '{$locationBranch->name}' (fuente: {$gpsSource})", [
                'employee_id' => $employee->id,
                'branch_id' => $locationBranch->id,
                'branch_name' => $locationBranch->name,
                'source' => $terminal ? 'terminal' : 'employee_branch',
                'coordinates' => $location,
            ]);
        } else {
            // Usar ubicación enviada desde el cliente (modo normal)
            $location = $data['location'];
        }

        // CORRECCIÓN 8: Usar timezone de la aplicación
        $today = Carbon::now(config('app.timezone'))->toDateString();

        // Captura de fallos que ocurren dentro de la transacción.
        // recordFailure() NO puede llamarse dentro de DB::transaction() porque el INSERT
        // quedaría incluido en el rollback cuando se lanza ValidationException.
        $pendingFailure = null;

        try {
            $result = DB::transaction(function () use ($employee, $today, $data, $location, $terminal, &$pendingFailure) {
                // Asegurar AttendanceDay del día (firstOrCreate es atómico)
                $day = AttendanceDay::firstOrCreate(
                    ['employee_id' => $employee->id, 'date' => $today],
                    ['status' => 'present']
                );

                // Si existe y el status no es 'present', actualizarlo
                if ($day->status !== 'present') {
                    $day->update(['status' => 'present']);
                }

                // Bloquear lectura del último evento para evitar condiciones de carrera
                $last = AttendanceEvent::where('attendance_day_id', $day->id)
                    ->lockForUpdate()
                    ->latest('recorded_at')
                    ->first();

                $allowed = $this->allowedNextEvents($last?->event_type);

                if (! in_array($data['event_type'], $allowed, true)) {
                    Log::warning("Marcación fallida — CI {$employee->ci} {$employee->first_name}: evento '{$data['event_type']}' no permitido (último: ".($last ? $last->event_type : 'ninguno').')', [
                        'employee_id' => $employee->id,
                        'event_type_attempted' => $data['event_type'],
                        'last_event' => $last?->event_type,
                        'allowed_events' => $allowed,
                    ]);

                    // Guardar para persistir fuera de la transacción (evitar rollback del INSERT)
                    $pendingFailure = [
                        'type' => 'invalid_event_sequence',
                        'message' => 'Secuencia de evento no permitida.',
                        'metadata' => [
                            'attempted_event' => $data['event_type'],
                            'last_event' => $last?->event_type,
                            'allowed_events' => $allowed,
                        ],
                        'employee' => $employee,
                        'branch' => $employee->branch,
                        'eventType' => $data['event_type'],
                    ];

                    // CORRECCIÓN 9: Mensaje más descriptivo del error con traducciones en español
                    $eventTypeNames = $this->getEventTypeNames();
                    $employeeName = $employee->full_name ?? $employee->name ?? "Empleado #{$employee->id}";

                    $allowedNames = empty($allowed)
                        ? 'ninguno'
                        : implode(', ', array_map(fn ($e) => "'".($eventTypeNames[$e] ?? $e)."'", $allowed));

                    // Construir mensaje breve y claro
                    if ($last) {
                        $currentStateName = $eventTypeNames[$last->event_type] ?? $last->event_type;
                        $message = "{$employeeName}, su última marcación fue '{$currentStateName}'.\nAhora puede marcar: {$allowedNames}";
                    } else {
                        $message = "{$employeeName}, aún no tiene marcaciones hoy.\nDebe iniciar con: {$allowedNames}";
                    }

                    throw ValidationException::withMessages([
                        'event_type' => $message,
                    ]);
                }

                // CORRECCIÓN 10: Validar que la ubicación no sea 0,0 (ubicación inválida común)
                // TERMINAL MODE: Usar $location determinada arriba (de sucursal o de cliente)
                $lat = (float) $location['lat'];
                $lng = (float) $location['lng'];

                if ($lat === 0.0 && $lng === 0.0) {
                    Log::warning("Marcación fallida — CI {$employee->ci} {$employee->first_name}: coordenadas GPS inválidas (0,0)", [
                        'employee_id' => $employee->id,
                        'event_type' => $data['event_type'],
                    ]);

                    // Guardar para persistir fuera de la transacción (evitar rollback del INSERT)
                    $pendingFailure = [
                        'type' => 'invalid_location',
                        'message' => 'Ubicación 0,0 detectada.',
                        'metadata' => ['event_type' => $data['event_type']],
                        'employee' => $employee,
                        'branch' => $employee->branch,
                        'eventType' => $data['event_type'],
                        'location' => ['lat' => $lat, 'lng' => $lng],
                    ];

                    throw ValidationException::withMessages([
                        'location' => 'La ubicación proporcionada no es válida.',
                    ]);
                }

                // CORRECCIÓN 11: Usar timezone de la aplicación para recorded_at
                $recordedAt = Carbon::now(config('app.timezone'));

                // Detectar marcación en sucursal diferente a la del empleado
                $branchMismatch = $terminal
                    && $terminal->branch_id
                    && $employee->branch_id
                    && $terminal->branch_id !== $employee->branch_id;

                // Registrar el evento de asistencia
                $event = $day->events()->create([
                    'event_type' => $data['event_type'],
                    'recorded_at' => $recordedAt,
                    'source' => $data['source'] ?? 'manual',
                    'location' => ['lat' => $lat, 'lng' => $lng],
                    'terminal_id' => $terminal?->id,
                    'branch_mismatch' => $branchMismatch,
                ]);

                // CORRECCIÓN 12: Verificar que el evento se creó correctamente
                if (! $event || ! $event->id) {
                    throw new \Exception('No se pudo crear el evento de asistencia.');
                }

                return [
                    'employee' => $employee,
                    'event_type' => $data['event_type'],
                    'event_id' => $event->id,
                    'recorded_at' => $recordedAt->format('Y-m-d H:i:s'),
                ];
            });

            Log::info("Marcación registrada — CI {$result['employee']->ci} {$result['employee']->first_name} {$result['employee']->last_name}: {$result['event_type']} a las {$result['recorded_at']}", [
                'employee_id' => $result['employee']->id,
                'event_type' => $result['event_type'],
                'event_id' => $result['event_id'],
                'recorded_at' => $result['recorded_at'],
            ]);

            // CORRECCIÓN 13: Respuesta más informativa
            $eventTypes = [
                'check_in' => 'Ingreso',
                'break_start' => 'Inicio de descanso',
                'break_end' => 'Fin de descanso',
                'check_out' => 'Salida',
            ];

            $eventName = $eventTypes[$result['event_type']] ?? $result['event_type'];

            return response()->json([
                'ok' => true,
                'message' => "Marcación registrada correctamente: {$eventName} para {$result['employee']->first_name} {$result['employee']->last_name}",
                'data' => [
                    'event_id' => $result['event_id'],
                    'event_type' => $result['event_type'],
                    'recorded_at' => $result['recorded_at'],
                ],
            ]);
        } catch (ValidationException $ve) {
            // Persistir el fallo ahora que la transacción ya hizo rollback
            if ($pendingFailure !== null) {
                $this->recordFailure(
                    $request,
                    $pendingFailure['type'],
                    $pendingFailure['message'],
                    $pendingFailure['metadata'] ?? [],
                    $pendingFailure['employee'] ?? null,
                    $pendingFailure['branch'] ?? null,
                    $pendingFailure['eventType'] ?? null,
                    $pendingFailure['location'] ?? null,
                );
            }

            // Errores de negocio/reglas (422)
            return response()->json([
                'ok' => false,
                'message' => collect($ve->errors())->flatten()->first() ?? 'Datos inválidos.',
                'errors' => $ve->errors(),
            ], 422);
        } catch (Throwable $e) {
            Log::error("Error interno en marcación: {$e->getMessage()}", [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'employee_id' => $data['employee_id'] ?? null,
                'event_type' => $data['event_type'] ?? null,
                'trace' => app()->hasDebugModeEnabled() ? $e->getTraceAsString() : null,
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Error interno al registrar la marcación.',
            ], 500);
        }
    }

    /** Identifica empleado por distancia euclidiana */
    protected function identifyEmployeeByDescriptor(array $live, float $threshold = 0.45): array
    {
        try {
            $candidates = Cache::remember('employees_face_descriptors', 300, fn () => Employee::query()
                ->whereNotNull('face_descriptor')
                ->where('status', 'active')
                ->select('id', 'first_name', 'last_name', 'ci', 'photo', 'face_descriptor')
                ->get()
            );

            if ($candidates->isEmpty()) {
                Log::warning('No hay empleados con descriptores faciales activos.');

                return [null, INF, 'no_candidates'];
            }

            $best = null;
            $bestDist = INF;
            $secondBestDist = INF; // Guardar segundo mejor para validación de gap
            $processedCount = 0;

            foreach ($candidates as $emp) {
                // CORRECCIÓN 15: Mejor manejo del descriptor guardado
                $saved = $this->parseStoredDescriptor($emp->face_descriptor);

                if (! $saved) {
                    Log::warning("Descriptor facial corrompido — CI {$emp->ci} {$emp->first_name} {$emp->last_name} (ID: {$emp->id})", [
                        'employee_id' => $emp->id,
                        'employee_ci' => $emp->ci,
                    ]);

                    continue;
                }

                $dist = $this->euclideanDistance($live, $saved);

                if ($dist < $bestDist) {
                    $secondBestDist = $bestDist; // Guardar anterior mejor como segundo
                    $bestDist = $dist;
                    $best = $emp;
                } elseif ($dist < $secondBestDist) {
                    $secondBestDist = $dist;
                }

                $processedCount++;
            }

            // Obtener gap mínimo de configuración
            $minGap = (float) app(GeneralSettings::class)->face_min_confidence_gap;

            $identifiedLabel = $best ? "CI {$best->ci} {$best->first_name} {$best->last_name}" : 'ninguno';
            Log::info("Identificación facial: {$processedCount} candidatos procesados — resultado: {$identifiedLabel}", [
                'best_distance' => round($bestDist, 4),
                'second_best_distance' => $secondBestDist === INF ? 'N/A' : round($secondBestDist, 4),
                'gap' => $secondBestDist === INF ? 'N/A' : round($secondBestDist - $bestDist, 4),
                'min_gap_required' => $minGap,
                'threshold' => $threshold,
                'identified' => $identifiedLabel,
            ]);

            // Validar que supere el threshold
            if (! $best || $bestDist > $threshold) {
                return [null, $bestDist, 'no_match'];
            }

            // Validar que haya suficiente diferencia con el segundo mejor
            if ($secondBestDist !== INF) {
                $gap = $secondBestDist - $bestDist;

                if ($gap < $minGap) {
                    Log::warning("Identificación ambigua — CI {$best->ci} {$best->first_name}: gap ".round($gap, 4)." insuficiente (mínimo {$minGap})", [
                        'employee_id' => $best->id,
                        'employee_ci' => $best->ci,
                        'best_distance' => round($bestDist, 4),
                        'second_best_distance' => round($secondBestDist, 4),
                        'gap' => round($gap, 4),
                        'min_gap' => $minGap,
                    ]);

                    return [null, $bestDist, 'ambiguous'];
                }
            }

            return [$best, $bestDist, null];
        } catch (Throwable $e) {
            Log::error('Error en identifyEmployeeByDescriptor', [
                'msg' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return [null, INF, 'error'];
        }
    }

    // CORRECCIÓN 16: Nueva función para parsear descriptores guardados
    protected function parseStoredDescriptor($descriptor): ?array
    {
        if (is_array($descriptor)) {
            return (count($descriptor) === 128) ? $descriptor : null;
        }

        if (is_string($descriptor)) {
            try {
                $parsed = json_decode($descriptor, true, 512, JSON_THROW_ON_ERROR);

                return (is_array($parsed) && count($parsed) === 128) ? $parsed : null;
            } catch (JsonException $e) {
                return null;
            }
        }

        return null;
    }

    protected function euclideanDistance(array $a, array $b): float
    {
        // CORRECCIÓN 17: Verificar que ambos arrays tengan 128 elementos
        if (count($a) !== 128 || count($b) !== 128) {
            throw new \InvalidArgumentException('Los descriptores deben tener exactamente 128 elementos.');
        }

        $sum = 0.0;
        for ($i = 0; $i < 128; $i++) {
            $av = (float) ($a[$i] ?? 0.0);
            $bv = (float) ($b[$i] ?? 0.0);

            // CORRECCIÓN 18: Verificar valores numéricos válidos
            if (! is_finite($av) || ! is_finite($bv)) {
                throw new \InvalidArgumentException('Los descriptores contienen valores no finitos.');
            }

            $d = $av - $bv;
            $sum += $d * $d;
        }

        $distance = sqrt($sum);

        // CORRECCIÓN 19: Verificar que el resultado sea finito
        if (! is_finite($distance)) {
            throw new \RuntimeException('La distancia euclidiana resultó en un valor no finito.');
        }

        return $distance;
    }

    /**
     * Reglas de transición válidas — delega a AttendanceEvent::allowedNextEventTypes(),
     * compartida con AttendanceEventSyncService (sync de terminales offline) para no
     * duplicar la máquina de estados en dos lugares.
     */
    protected function allowedNextEvents(?string $last): array
    {
        // CORRECCIÓN 20: Validar tipos de eventos conocidos
        $validEventTypes = ['check_in', 'break_start', 'break_end', 'check_out'];

        if ($last !== null && ! in_array($last, $validEventTypes, true)) {
            Log::warning("Tipo de evento desconocido encontrado: {$last}");

            return ['check_in']; // Fallback seguro
        }

        return AttendanceEvent::allowedNextEventTypes($last);
    }

    /** Traducciones de tipos de eventos a español */
    protected function getEventTypeNames(): array
    {
        return [
            'check_in' => 'Entrada',
            'break_start' => 'Inicio de descanso',
            'break_end' => 'Fin de descanso',
            'check_out' => 'Salida',
        ];
    }

    /**
     * Persiste un intento fallido de marcación en la base de datos.
     *
     * @param  string  $failureType  Constante del tipo de fallo (ej. 'face_no_match')
     * @param  string  $message  Mensaje descriptivo del fallo
     * @param  array<string, mixed>  $metadata  Datos adicionales de contexto
     * @param  Employee|null  $employee  Empleado identificado (si aplica)
     * @param  mixed|null  $branch  Sucursal (si aplica)
     * @param  string|null  $eventType  Tipo de evento intentado (si aplica)
     * @param  array|null  $location  Coordenadas GPS (si aplica)
     */
    protected function recordFailure(
        Request $request,
        string $failureType,
        string $message,
        array $metadata = [],
        ?Employee $employee = null,
        mixed $branch = null,
        ?string $eventType = null,
        ?array $location = null,
    ): void {
        try {
            $source = $request->input('source', 'unknown');
            $mode = in_array($source, ['terminal', 'mobile'], true) ? $source : 'unknown';

            AttendanceMarkFailure::record([
                'mode' => $mode,
                'failure_type' => $failureType,
                'employee_id' => $employee?->id,
                'branch_id' => $branch?->id,
                'attempted_event_type' => $eventType,
                'failure_message' => $message,
                'metadata' => ! empty($metadata) ? $metadata : null,
                'ip_address' => $request->ip(),
                'location' => $location,
            ]);
        } catch (Throwable $e) {
            // No interrumpir el flujo principal si falla el registro del error
            Log::error('No se pudo persistir el fallo de marcación', [
                'failure_type' => $failureType,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
