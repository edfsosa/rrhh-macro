<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Employee::count() === 0) {
            $this->command->warn('No hay empleados. Generando 10 empleados primero.');
            Employee::factory()->count(10)->create();
        }

        Attendance::factory()->count(100)->create();
    }
}
