<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Tarifas diarias de viático y subsistencia (Acuerdo MRL-2014-0165).
 *
 * Se puede volver a correr: inserta solo las combinaciones de zona, nivel y
 * tipo que falten. Antes vaciaba la tabla con `truncate()` —en una base en uso
 * se llevaba cualquier tarifa ajustada después— y firmaba cada fila con el
 * usuario 1, que en una instalación nueva no tiene por qué existir.
 */
class TarifaViaticoSeeder extends Seeder
{
    public function run(): void
    {
        $tarifas = [
            // NIVEL: servidor (todos los servidores y obreros)
            ['zona' => 'dentro_provincia', 'nivel' => 'servidor',  'tipo_tarifa' => 'con_pernocte', 'valor_diario' => 80.00],
            ['zona' => 'dentro_provincia', 'nivel' => 'servidor',  'tipo_tarifa' => 'subsistencia', 'valor_diario' => 40.00],
            ['zona' => 'fuera_provincia',  'nivel' => 'servidor',  'tipo_tarifa' => 'con_pernocte', 'valor_diario' => 80.00],
            ['zona' => 'fuera_provincia',  'nivel' => 'servidor',  'tipo_tarifa' => 'subsistencia', 'valor_diario' => 40.00],
            // NIVEL: autoridad (grados 6, 7, 8 NJS: prefectos, alcaldes, generales, etc.)
            ['zona' => 'dentro_provincia', 'nivel' => 'autoridad', 'tipo_tarifa' => 'con_pernocte', 'valor_diario' => 130.00],
            ['zona' => 'dentro_provincia', 'nivel' => 'autoridad', 'tipo_tarifa' => 'subsistencia', 'valor_diario' => 65.00],
            ['zona' => 'fuera_provincia',  'nivel' => 'autoridad', 'tipo_tarifa' => 'con_pernocte', 'valor_diario' => 130.00],
            ['zona' => 'fuera_provincia',  'nivel' => 'autoridad', 'tipo_tarifa' => 'subsistencia', 'valor_diario' => 65.00],
        ];

        $nuevas = 0;
        foreach ($tarifas as $tarifa) {
            // El índice único (zona, nivel, tipo_tarifa) hace que una tarifa
            // que ya existe —con su valor ajustado— se deje como está.
            $nuevas += DB::table('tarifas_viatico')->insertOrIgnore([
                ...$tarifa,
                'pais_destino' => null,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }

        $this->command?->info("Tarifas de viáticos: {$nuevas} nuevas de " . count($tarifas) . '.');
    }
}
