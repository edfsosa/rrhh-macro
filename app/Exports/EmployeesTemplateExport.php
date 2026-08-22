<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Genera la plantilla Excel en blanco para la creación masiva de empleados
 * (v1: solo datos básicos — sin contrato ni enrolamiento facial, cada
 * empleado importado queda listo para que un admin le complete el contrato
 * después, igual que la sección opcional "Contrato Inicial" en el alta
 * individual).
 *
 * Deliberadamente SIN fila de ejemplo: `EmployeesImport` arranca a leer en
 * la fila 2 (después del encabezado) — una fila de ejemplo ahí se
 * importaría como un empleado real si el admin se olvida de borrarla. El
 * formato esperado se explica en el texto del modal de descarga, no en el
 * archivo.
 */
class EmployeesTemplateExport implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles
{
    /**
     * @return Collection<int, array<int, string>>
     */
    public function collection(): Collection
    {
        return collect();
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['CI', 'Nombre(s)', 'Apellido(s)', 'Fecha de Nacimiento (DD/MM/AAAA)', 'Género', 'Sucursal', 'Teléfono', 'Email', 'Nacionalidad'];
    }

    /**
     * Encabezado en negrita con fondo gris.
     *
     * @return array<int|string, mixed>
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'D9D9D9'],
                ],
            ],
        ];
    }
}
