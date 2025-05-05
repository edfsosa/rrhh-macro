<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\Employee;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DocumentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        if (Employee::count() === 0) {
            $this->command->warn('No hay empleados. Generando 10 empleados primero.');
            Employee::factory()->count(10)->create();
        }

        Document::factory()->count(30)->create();
    }
}
