<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Document>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => $this->faker->randomElement(Employee::pluck('id')->toArray()),
            'name' => $this->faker->randomElement(['Foto', 'Contrato', 'Certificado IPS', 'Permiso', 'Otros']),
            'file_path' => 'documents/' . $this->faker->word . '.pdf', // puedes guardar un archivo demo aquí
        ];
    }
}
