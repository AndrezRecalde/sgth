<?php

namespace Database\Seeders;

use App\Models\Asistencia\FeriadoInstitucional;
use Illuminate\Database\Seeder;

class FeriadoInstitucionalSeeder extends Seeder
{
    public function run(): void
    {
        // FERIADOS FIJOS — se registran UNA SOLA VEZ, aplican todos los años
        // mes y dia rellenados, fecha NULL
        $fijosNacionales = [
            ['mes' => 1,  'dia' => 1,  'descripcion' => 'Año Nuevo', 'es_nacional' => true, 'es_movil' => false, 'fecha' => null],
            ['mes' => 5,  'dia' => 1,  'descripcion' => 'Día del Trabajo', 'es_nacional' => true, 'es_movil' => false, 'fecha' => null],
            ['mes' => 5,  'dia' => 24, 'descripcion' => 'Batalla de Pichincha', 'es_nacional' => true, 'es_movil' => false, 'fecha' => null],
            ['mes' => 8,  'dia' => 10, 'descripcion' => 'Primer Grito de Independencia', 'es_nacional' => true, 'es_movil' => false, 'fecha' => null],
            ['mes' => 10, 'dia' => 9,  'descripcion' => 'Independencia de Guayaquil', 'es_nacional' => true, 'es_movil' => false, 'fecha' => null],
            ['mes' => 11, 'dia' => 2,  'descripcion' => 'Día de los Difuntos', 'es_nacional' => true, 'es_movil' => false, 'fecha' => null],
            ['mes' => 11, 'dia' => 3,  'descripcion' => 'Independencia de Cuenca', 'es_nacional' => true, 'es_movil' => false, 'fecha' => null],
            ['mes' => 12, 'dia' => 25, 'descripcion' => 'Navidad', 'es_nacional' => true, 'es_movil' => false, 'fecha' => null],
        ];

        foreach ($fijosNacionales as $fijo) {
            FeriadoInstitucional::firstOrCreate(
                [
                    'mes'      => $fijo['mes'],
                    'dia'      => $fijo['dia'],
                    'es_movil' => false,
                ],
                $fijo
            );
        }

        // FERIADOS MÓVILES 2026 — fecha exacta, mes y dia NULL
        // Carnaval y Viernes Santo cambian cada año
        $movilesNacionales2026 = [
            ['fecha' => '2026-02-16', 'descripcion' => 'Carnaval - Lunes', 'mes' => null, 'dia' => null, 'es_nacional' => true, 'es_movil' => true],
            ['fecha' => '2026-02-17', 'descripcion' => 'Carnaval - Martes', 'mes' => null, 'dia' => null, 'es_nacional' => true, 'es_movil' => true],
            ['fecha' => '2026-04-03', 'descripcion' => 'Viernes Santo 2026', 'mes' => null, 'dia' => null, 'es_nacional' => true, 'es_movil' => true],
        ];

        foreach ($movilesNacionales2026 as $movil) {
            FeriadoInstitucional::firstOrCreate(
                [
                    'fecha'    => $movil['fecha'],
                    'es_movil' => true,
                ],
                $movil
            );
        }
    }
}
