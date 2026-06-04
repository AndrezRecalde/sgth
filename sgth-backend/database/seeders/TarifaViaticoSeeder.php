<?php
namespace Database\Seeders;

use App\Models\Viatico\TarifaViatico;
use Illuminate\Database\Seeder;

class TarifaViaticoSeeder extends Seeder
{
    public function run(): void
    {
        // Eliminar tarifas existentes para re-sembrar
        TarifaViatico::truncate();

        $tarifas = [
            // Acuerdo MRL-2014-0165 (vigente)
            // NIVEL: servidor (todos los servidores y obreros)
            [
                'zona'       => 'dentro_provincia',
                'nivel'      => 'servidor',
                'tipo_tarifa'=> 'con_pernocte',
                'valor_diario'=> 80.00,
                'pais_destino'=> null,
                'created_by' => 1,
                'updated_by' => 1,
            ],
            [
                'zona'       => 'dentro_provincia',
                'nivel'      => 'servidor',
                'tipo_tarifa'=> 'subsistencia',
                'valor_diario'=> 40.00,
                'pais_destino'=> null,
                'created_by' => 1,
                'updated_by' => 1,
            ],
            [
                'zona'       => 'fuera_provincia',
                'nivel'      => 'servidor',
                'tipo_tarifa'=> 'con_pernocte',
                'valor_diario'=> 80.00,
                'pais_destino'=> null,
                'created_by' => 1,
                'updated_by' => 1,
            ],
            [
                'zona'       => 'fuera_provincia',
                'nivel'      => 'servidor',
                'tipo_tarifa'=> 'subsistencia',
                'valor_diario'=> 40.00,
                'pais_destino'=> null,
                'created_by' => 1,
                'updated_by' => 1,
            ],
            // NIVEL: autoridad (grados 6, 7, 8 NJS:
            // prefectos, alcaldes, generales, etc.)
            [
                'zona'       => 'dentro_provincia',
                'nivel'      => 'autoridad',
                'tipo_tarifa'=> 'con_pernocte',
                'valor_diario'=> 130.00,
                'pais_destino'=> null,
                'created_by' => 1,
                'updated_by' => 1,
            ],
            [
                'zona'       => 'dentro_provincia',
                'nivel'      => 'autoridad',
                'tipo_tarifa'=> 'subsistencia',
                'valor_diario'=> 65.00,
                'pais_destino'=> null,
                'created_by' => 1,
                'updated_by' => 1,
            ],
            [
                'zona'       => 'fuera_provincia',
                'nivel'      => 'autoridad',
                'tipo_tarifa'=> 'con_pernocte',
                'valor_diario'=> 130.00,
                'pais_destino'=> null,
                'created_by' => 1,
                'updated_by' => 1,
            ],
            [
                'zona'       => 'fuera_provincia',
                'nivel'      => 'autoridad',
                'tipo_tarifa'=> 'subsistencia',
                'valor_diario'=> 65.00,
                'pais_destino'=> null,
                'created_by' => 1,
                'updated_by' => 1,
            ],
        ];

        foreach ($tarifas as $tarifa) {
            TarifaViatico::create($tarifa);
        }

        $this->command->info(
            'Tarifas de viáticos sembradas: ' .
            count($tarifas) . ' registros.'
        );
    }
}
