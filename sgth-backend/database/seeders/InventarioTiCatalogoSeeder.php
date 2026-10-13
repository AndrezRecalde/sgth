<?php

namespace Database\Seeders;

use App\Models\InventarioTi\SubtipoBien;
use App\Models\InventarioTi\TipoBien;
use App\Models\InventarioTi\Ubicacion;
use Illuminate\Database\Seeder;

/**
 * Los catálogos de partida del inventario de TIC, sacados del Excel de la
 * unidad (docs/diseno-subsistema-tic.md, 2.1): los tipos y subtipos de la
 * pestaña «Equipos», los tipos de las otras pestañas y de los equipos que se
 * prestan, y las ubicaciones de la columna «PISO».
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

    /** Sede => pisos (vacío si la sede no tiene pisos). */
    private const UBICACIONES = [
        'Edificio principal' => ['PB', '1', '2', '3', '4', '5', '6'],
        'San Mateo'          => [],
        'Dispensario Médico' => [],
        'UNAMYDESC'          => [],
        'Quinindé'           => [],
    ];

    public function run(): void
    {
        foreach (self::TIPOS as $nombre => $subtipos) {
            $tipo = TipoBien::firstOrCreate(['nombre' => $nombre], ['anios_vida_util' => 0]);

            foreach ($subtipos as $subtipo) {
                SubtipoBien::firstOrCreate(['tipo_bien_id' => $tipo->id, 'nombre' => $subtipo]);
            }
        }

        foreach (self::UBICACIONES as $sede => $pisos) {
            foreach ($pisos ?: [null] as $piso) {
                Ubicacion::firstOrCreate(['sede' => $sede, 'piso' => $piso]);
            }
        }
    }
}
