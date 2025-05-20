<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Payroll;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PayrollSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Asegúrate de tener empleados antes de correr esto
        if (Employee::count() === 0) {
            $this->command->warn('No hay empleados. Generando 10 empleados primero.');
            Employee::factory()->count(10)->create();
        }

        // Generar 50 nóminas de prueba
        Payroll::factory()->count(5)->create();
    }
}
