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
                'mision' => 'Administrar, dar soporte y garantizar la disponibilidad de la '
                    . 'infraestructura tecnológica, sistemas de información y comunicaciones '
                    . 'del GADPE.',
                'clasificacion_personal' => 'empleado',
                'activo' => true,
            ]
        );
    }
}
