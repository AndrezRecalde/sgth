<?php

namespace Database\Seeders;

use App\Models\Estructura\Cargo;
use Illuminate\Database\Seeder;

class CargoTicSeeder extends Seeder
{
    public function run(): void
    {
        Cargo::updateOrCreate(
            ['nombre' => 'Analista de Tecnologías de la Información y Comunicación'],
            [
                'denominacion_generica' => 'Analista',
                // La misión es del puesto, en su perfil (PuestoTicSeeder).
                'activo' => true,
            ]
        );
    }
}
