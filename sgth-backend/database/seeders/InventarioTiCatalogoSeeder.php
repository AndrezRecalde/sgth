<?php

namespace Database\Seeders;

use App\Models\InventarioTi\SubtipoBien;
use App\Models\InventarioTi\TipoBien;
use Illuminate\Database\Seeder;

/**
 * Los catálogos de partida del inventario de TIC, sacados del Excel de la
 * unidad (docs/diseno-subsistema-tic.md, 2.1): los tipos y subtipos de la
 * pestaña «Equipos», los tipos de las otras pestañas y de los equipos que se
 * prestan. Las ubicaciones ya no van aquí: las siembra la migración
 * `2026_10_28_000000_ubicaciones_de_tic` con las sedes que fijó TIC.
 *
 * Idempotente: se puede correr en producción sin duplicar nada. Las marcas no
 * se siembran: las crea la carga del Excel (fase 1.4).
 *
 * Los años de vida útil quedan en 0 (sin calcular) hasta que TIC los fije
 * desde Configuración: el Excel no los trae.
 */
class InventarioTiCatalogoSeeder extends Seeder
{
    /** Tipo => subtipos. */
    private const TIPOS = [
        'Computador'  => ['Todo en uno', 'Completo', 'CPU', 'MAC'],
        'Portátil'    => [],
        'Monitor'     => [],
        'Impresora'   => ['Láser', 'Multifunción', 'Matricial', 'Tinta continua'],
        'Escáner'     => [],
        'Proyector'   => [],
        'Parlante'    => [],
        'Auricular'   => [],
        'Teclado'     => [],
        'Mouse'       => [],
    ];

    public function run(): void
    {
        foreach (self::TIPOS as $nombre => $subtipos) {
            $tipo = TipoBien::firstOrCreate(['nombre' => $nombre], ['anios_vida_util' => 0]);

            foreach ($subtipos as $subtipo) {
                SubtipoBien::firstOrCreate(['tipo_bien_id' => $tipo->id, 'nombre' => $subtipo]);
            }
        }
    }
}
