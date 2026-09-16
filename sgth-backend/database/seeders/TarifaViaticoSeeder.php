<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Tarifas diarias de viático, confirmadas por Gestión Financiera.
 *
 * La del exterior es la base: se multiplica por el coeficiente del país que
 * Financiero ingresa al aprobar. Estaba escrita en el código del servicio de
 * estados, donde nadie podía actualizarla.
 *
 * No hay tarifas de subsistencia: un viaje sin pernocte no genera viático.
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
            ['zona' => 'fuera_provincia',  'nivel' => 'servidor',  'tipo_tarifa' => 'con_pernocte', 'valor_diario' => 80.00],
            ['zona' => 'exterior',         'nivel' => 'servidor',  'tipo_tarifa' => 'con_pernocte', 'valor_diario' => 185.00],
            // NIVEL: autoridad (el Prefecto o la Prefecta; dignatario en el exterior)
            ['zona' => 'dentro_provincia', 'nivel' => 'autoridad', 'tipo_tarifa' => 'con_pernocte', 'valor_diario' => 130.00],
            ['zona' => 'fuera_provincia',  'nivel' => 'autoridad', 'tipo_tarifa' => 'con_pernocte', 'valor_diario' => 130.00],
            ['zona' => 'exterior',         'nivel' => 'autoridad', 'tipo_tarifa' => 'con_pernocte', 'valor_diario' => 220.00],
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
