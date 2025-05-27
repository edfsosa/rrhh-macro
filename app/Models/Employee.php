<?php

namespace App\Models;

use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Testing\Fluent\Concerns\Has;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'ci',
        'phone',
        'email',
        'hire_date',
        'contract_type',
        'base_salary',
        'payment_method',
        'position_id',
        'branch_id',
        'status',
        'photo',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'base_salary' => 'integer',
    ];

    /**
     * Relación con el modelo Position, un empleado pertenece a una posición
     */
    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * Relación con el modelo Branch, un empleado pertenece a una sucursal
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Deducciones aplicadas al empleado
     */
    public function deductions()
    {
        return $this->hasMany(EmployeeDeduction::class);
    }

    /**
     * Percepciones aplicadas al empleado
     */
    public function perceptions()
    {
        return $this->hasMany(EmployeePerception::class);
    }

    /**
     * Recibos de sueldo generados
     */
    public function paySlips()
    {
        return $this->hasMany(PaySlip::class);
    }

    /**
     * Genera o actualiza el recibo de sueldo para un período dado
     */
    public function generatePaySlip(PayPeriod $period)
    {
        return app(PayrollService::class)->generatePaySlip($this, $period);
    }

    // Relación con el modelo Payroll, un empleado puede tener muchas nóminas
    public function payrolls()
    {
        return $this->hasMany(Payroll::class);
    }

    // Relación con el modelo Attendance, un empleado puede tener muchas asistencias
    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Asignaciones de horario
     */
    public function schedules()
    {
        return $this->hasMany(EmployeeSchedule::class);
    }

    /**
     * Tipos de turno a través de la tabla intermedia
     */
    public function scheduleTypes()
    {
        return $this->hasManyThrough(
            ScheduleType::class,
            EmployeeSchedule::class,
            'employee_id',      // FK en employee_schedules
            'id',               // PK en schedule_types
            'id',               // PK en employees
            'schedule_type_id'  // FK en employee_schedules
        );
    }

    /**
     * Relación con el modelo Document, un empleado puede tener muchos documentos
     */
    public function documents()
    {
        return $this->hasMany(Document::class);
    }


    /**
     * Turno asignado al empleado para una fecha dada.
     */
    public function scheduleForDate(string $fecha)
    {
        return $this->schedules()
            ->where(function ($q) use ($fecha) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', $fecha);
            })
            ->where(function ($q) use ($fecha) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', $fecha);
            })
            ->with(['scheduleType.daySchedules', 'scheduleType.breakPeriods'])
            ->first()
            ->scheduleType ?? null;
    }

    /**
     * Helper: devuelve los DaySchedule para el día de la semana dado.
     */
    public function dayScheduleForDate(string $fecha)
    {
        $st = $this->scheduleForDate($fecha);
        if (! $st) return null;
        $dow = Carbon::parse($fecha)->dayOfWeek;
        return $st->daySchedules->first(fn($ds) => $ds->day_of_week == $dow);
    }

    /**
     * Helper: devuelve los BreakPeriod para ese turno.
     */
    public function breaksForDate(string $fecha)
    {
        $st = $this->scheduleForDate($fecha);
        return $st
            ? $st->breakPeriods
            : collect();
    }

    /**
     * Calcula horas trabajadas, desglosadas en diurnas, nocturnas y extras.
     *
     * @return array [
     *   'diurno'    => float (horas normales diurnas),
     *   'nocturno'  => float (horas normales nocturnas),
     *   'extras'    => float (horas fuera de la jornada prevista)
     * ]
     */
    public function calculateHours(string $fecha): array
    {
        $logs = Attendance::where('employee_id', $this->id)
            ->whereDate('created_at', $fecha)
            ->orderBy('created_at')
            ->get();
        $in  = $logs->first(fn($l) => $l->type === 'entrada');
        $out = $logs->last(fn($l)  => $l->type === 'salida');
        if (! $in || ! $out) {
            return ['diurno' => 0, 'nocturno' => 0, 'extras' => 0];
        }

        // Obtengo los límites legales
        $startDay = Carbon::parse("$fecha 06:00");
        $endDay   = Carbon::parse("$fecha 20:00");
        $startNight = $endDay;
        $endNight   = Carbon::parse("$fecha 23:59:59")->addSecond();
        // (y también la franja 00:00–06:00 del día siguiente)
        $startNight2 = Carbon::parse("$fecha 00:00");
        $endNight2   = Carbon::parse("$fecha 06:00");

        // Jornada prevista
        $ds = $this->dayScheduleForDate($fecha);
        $plannedStart = Carbon::parse("$fecha {$ds->start_time}");
        $plannedEnd   = Carbon::parse("$fecha {$ds->end_time}");

        // Rango efectivo
        $workStart = $in->created_at;
        $workEnd   = $out->created_at;

        // Funcion para restar solapamientos con descansos
        $subtractBreaks = function ($from, $to) use ($fecha) {
            $mins = 0;
            foreach ($this->breaksForDate($fecha) as $b) {
                $bs = Carbon::parse("$fecha {$b->start_time}");
                $be = Carbon::parse("$fecha {$b->end_time}");
                $ovStart = $from->greaterThan($bs) ? $from : $bs;
                $ovEnd   = $to->lessThan($be)   ? $to   : $be;
                if ($ovEnd->greaterThan($ovStart)) {
                    $mins += $ovEnd->diffInMinutes($ovStart);
                }
            }
            return $mins;
        };

        // Calcula minutos de un rango solapado con otro rango
        $overlapMins = function ($from, $to, $segFrom, $segTo) {
            $a = $from->greaterThan($segFrom) ? $from : $segFrom;
            $b = $to->lessThan($segTo)       ? $to   : $segTo;
            return $b->greaterThan($a)
                ? $b->diffInMinutes($a)
                : 0;
        };

        // 1) Minutos totales
        $totalMins = $workEnd->diffInMinutes($workStart)
            - $subtractBreaks($workStart, $workEnd);

        // 2) Normal diurno
        $mDiurno =
            $overlapMins($workStart, $workEnd, $startDay, $endDay)
            - $subtractBreaks(
                max($workStart, $startDay),
                min($workEnd, $endDay)
            );

        // 3) Normal nocturno (parte 1)
        $mNoct1 = $overlapMins($workStart, $workEnd, $startNight, $endNight);
        // nocturno (parte 2, madrugada)
        $mNoct2 = $overlapMins($workStart, $workEnd, $startNight2, $endNight2);
        $mNoct = $mNoct1 + $mNoct2;

        // 4) Horas previstas en jornada
        $plannedMins = $plannedEnd->diffInMinutes($plannedStart)
            - $subtractBreaks($plannedStart, $plannedEnd);

        // 5) Extras = total menos jornada prevista (si >0)
        $mExtras = max(0, $totalMins - $plannedMins);

        return [
            'diurno'   => round($mDiurno / 60, 2),
            'nocturno' => round($mNoct / 60,   2),
            'extras'   => round($mExtras / 60,  2),
        ];
    }
}
