<?php

namespace App\Imports;

use App\Models\Branch;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Procesa la creación masiva de empleados desde el Excel plantilla (v1:
 * solo datos básicos — sin contrato inicial ni enrolamiento facial, ver
 * `EmployeesTemplateExport`). Cada empleado se crea con `status: active`;
 * el contrato se completa después desde la ficha del empleado, igual que
 * si se hubiera creado sin la sección opcional "Contrato Inicial" en el
 * alta individual.
 */
class EmployeesImport implements ToCollection, WithStartRow
{
    /** @var int Cantidad de empleados creados exitosamente. */
    public int $created = 0;

    /**
     * Lista de filas fallidas con nombre de referencia y motivo.
     *
     * @var array<int, array{row: int, name: string, reason: string}>
     */
    public array $failures = [];

    /**
     * Comienza a leer desde la fila 2 (la 1 es el encabezado) — la plantilla
     * generada por `EmployeesTemplateExport` no incluye fila de ejemplo,
     * así que no hace falta saltear ninguna fila adicional.
     */
    public function startRow(): int
    {
        return 2;
    }

    /**
     * Procesa cada fila del archivo y crea los empleados válidos.
     *
     * Columnas esperadas:
     *  0 = CI, 1 = Nombre(s), 2 = Apellido(s), 3 = Fecha de nacimiento,
     *  4 = Género, 5 = Sucursal, 6 = Teléfono, 7 = Email, 8 = Nacionalidad
     *
     * @param  Collection<int, Collection<int, mixed>>  $rows
     */
    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNum = $index + 2;

            $ciRaw = trim((string) ($row[0] ?? ''));
            $firstName = trim((string) ($row[1] ?? ''));
            $lastName = trim((string) ($row[2] ?? ''));
            $branchName = trim((string) ($row[5] ?? ''));

            // Fila completamente vacía — se salta en silencio (no es un error de datos).
            if ($ciRaw === '' && $firstName === '' && $lastName === '') {
                continue;
            }

            $name = trim("{$firstName} {$lastName}") ?: ($ciRaw !== '' ? "CI {$ciRaw}" : "Fila {$rowNum}");

            if ($ciRaw === '') {
                $this->failures[] = ['row' => $rowNum, 'name' => $name, 'reason' => 'CI vacío'];

                continue;
            }

            if ($firstName === '' || $lastName === '') {
                $this->failures[] = ['row' => $rowNum, 'name' => $name, 'reason' => 'Nombre y apellido son obligatorios'];

                continue;
            }

            // Se sanitiza recién acá (no antes) — sanitizeFormData() convierte una CI
            // vacía en '0', lo que rompería el chequeo de "CI vacío" de arriba.
            $sanitized = Employee::sanitizeFormData([
                'ci' => $ciRaw,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => trim((string) ($row[6] ?? '')),
                'email' => trim((string) ($row[7] ?? '')),
            ], isCreating: true);

            $ci = $sanitized['ci'];

            if (Employee::where('ci', $ci)->exists()) {
                $this->failures[] = ['row' => $rowNum, 'name' => $name, 'reason' => "Ya existe un empleado con CI {$ci}"];

                continue;
            }

            $birthDate = $this->parseDate($row[3] ?? null);

            if (! $birthDate) {
                $this->failures[] = ['row' => $rowNum, 'name' => $name, 'reason' => 'Fecha de nacimiento inválida — usar formato DD/MM/AAAA'];

                continue;
            }

            if ($birthDate->gt(now()->subYears(18))) {
                $this->failures[] = ['row' => $rowNum, 'name' => $name, 'reason' => 'El empleado debe ser mayor de 18 años'];

                continue;
            }

            $gender = $this->resolveGender($row[4] ?? '');

            if (! $gender) {
                $genderRaw = trim((string) ($row[4] ?? ''));
                $this->failures[] = ['row' => $rowNum, 'name' => $name, 'reason' => "Género inválido: '{$genderRaw}' — usar Masculino o Femenino"];

                continue;
            }

            $branch = Branch::whereRaw('LOWER(name) = ?', [mb_strtolower($branchName)])->first();

            if (! $branch) {
                $this->failures[] = ['row' => $rowNum, 'name' => $name, 'reason' => "Sucursal no encontrada: '{$branchName}'"];

                continue;
            }

            // sanitizeFormData() normaliza el email a string (nunca a null) aunque
            // venga vacío — a diferencia de 'phone', que sí lo hace. Sin este
            // ajuste, dos filas sin email insertarían '' en ambas, lo que en
            // MySQL real (a diferencia de SQLite) viola el unique constraint de
            // la columna (empresa con varios empleados sin email cargado).
            $email = filled($sanitized['email']) ? $sanitized['email'] : null;

            if ($email && Employee::where('email', $email)->exists()) {
                $this->failures[] = ['row' => $rowNum, 'name' => $name, 'reason' => "Ya existe un empleado con el email {$email}"];

                continue;
            }

            $nationality = trim((string) ($row[8] ?? ''));

            Employee::create([
                'ci' => $ci,
                'first_name' => $sanitized['first_name'],
                'last_name' => $sanitized['last_name'],
                'birth_date' => $birthDate,
                'gender' => $gender,
                'branch_id' => $branch->id,
                'phone' => $sanitized['phone'],
                'email' => $email,
                'nationality' => $nationality !== '' ? $nationality : 'Paraguaya',
                'status' => 'active',
                'face_descriptor' => null,
            ]);

            $this->created++;
        }
    }

    /**
     * Interpreta la celda de fecha de nacimiento — puede llegar como
     * instancia de fecha (celda formateada como fecha en Excel), número de
     * serie de Excel (celda sin formato de fecha), o texto DD/MM/AAAA
     * (planillas CSV o pegadas como texto).
     */
    private function parseDate(mixed $raw): ?Carbon
    {
        if ($raw instanceof \DateTimeInterface) {
            return Carbon::instance($raw)->startOfDay();
        }

        $raw = trim((string) $raw);

        if ($raw === '') {
            return null;
        }

        if (is_numeric($raw)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $raw))->startOfDay();
            } catch (Throwable) {
                // Era un número pero no una fecha de Excel válida — cae a los formatos de texto.
            }
        }

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d'] as $format) {
            try {
                // En modo estricto (Carbon 3) createFromFormat() lanza
                // InvalidFormatException en vez de retornar false cuando el
                // string no matchea — a diferencia de Carbon 2.
                return Carbon::createFromFormat('!'.$format, $raw)->startOfDay();
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    /**
     * Normaliza el género a los valores internos (`Employee::getGenderOptions()`).
     */
    private function resolveGender(mixed $raw): ?string
    {
        $raw = mb_strtolower(trim((string) $raw));

        return match ($raw) {
            'masculino', 'm', 'male' => 'masculino',
            'femenino', 'f', 'female' => 'femenino',
            default => null,
        };
    }
}
