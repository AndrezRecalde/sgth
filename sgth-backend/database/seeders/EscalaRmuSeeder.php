<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Estructura\Puesto;

class EscalaRmuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener una unidad genérica para asignar estos puestos de ejemplo
        $unidad = UnidadAdministrativa::firstOrCreate(
            ['codigo' => 'GAD-01'],
            [
                'nombre' => 'Gobierno Autónomo Descentralizado',
                'nivel' => 1,
                'estado' => true
            ]
        );

        $grados = [
            1 => ['rmu' => 527.00, 'grupo' => 'Servidor de Apoyo 1'],
            2 => ['rmu' => 553.00, 'grupo' => 'Servidor de Apoyo 2'],
            3 => ['rmu' => 585.00, 'grupo' => 'Servidor de Apoyo 3'],
            4 => ['rmu' => 622.00, 'grupo' => 'Servidor de Apoyo 4'],
            5 => ['rmu' => 675.00, 'grupo' => 'Servidor Público 1'],
            6 => ['rmu' => 733.00, 'grupo' => 'Servidor Público 2'],
            7 => ['rmu' => 817.00, 'grupo' => 'Servidor Público 3'],
            8 => ['rmu' => 901.00, 'grupo' => 'Servidor Público 4'],
            9 => ['rmu' => 986.00, 'grupo' => 'Servidor Público 5'],
            10 => ['rmu' => 1086.00, 'grupo' => 'Servidor Público 6'],
            11 => ['rmu' => 1212.00, 'grupo' => 'Servidor Público 7'],
            12 => ['rmu' => 1412.00, 'grupo' => 'Servidor Público 8'],
            13 => ['rmu' => 1676.00, 'grupo' => 'Servidor Público 9'],
            14 => ['rmu' => 1760.00, 'grupo' => 'Servidor Público 10'],
            15 => ['rmu' => 1900.00, 'grupo' => 'Servidor Público 11'],
            16 => ['rmu' => 2100.00, 'grupo' => 'Servidor Público 12'],
            17 => ['rmu' => 2200.00, 'grupo' => 'Servidor Público 13'],
            18 => ['rmu' => 2368.00, 'grupo' => 'Servidor Público 14'],
            19 => ['rmu' => 2588.00, 'grupo' => 'Nivel Jerárquico Superior 1'],
            20 => ['rmu' => 2734.00, 'grupo' => 'Nivel Jerárquico Superior 2'],
        ];

        foreach ($grados as $grado => $info) {
            Puesto::firstOrCreate(
                ['codigo' => 'GRADO-' . $grado],
                [
                    'denominacion' => $info['grupo'],
                    'unidad_administrativa_id' => $unidad->id,
                    'grupo_ocupacional' => $info['grupo'],
                    'grado_rmu' => $grado,
                    'rmu' => $info['rmu'],
                    'nivel' => 1,
                    'estado' => true,
                ]
            );
        }
    }
}
