<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pasa las existencias que ya hay a un lote «sin identificar» por medicina.
 *
 * La tentación es reconstruir los lotes desde `items_adquisicion`, que sí
 * guarda lote y caducidad de cada entrada. No se hace, y el motivo importa:
 * la suma de lo que entró no cuadra con lo que queda —por medio hubo
 * despachos, bajas y ajustes— y repartir la diferencia obligaría a suponer
 * qué unidades se consumieron. Eso es escribir ficción en un registro
 * sanitario.
 *
 * Así que se abre un solo lote por medicina, con la caducidad que hoy tiene
 * la ficha y sin código: dice exactamente lo que se sabe, ni más. Se agota
 * como cualquier otro y el inventario se cura solo conforme entren lotes
 * nuevos; quien quiera desglosarlo antes, hace recuento físico.
 */
return new class extends Migration
{
    public function up(): void
    {
        $medicinas = DB::table('inventario_medicinas')
            ->whereNull('deleted_at')
            ->where('stock_actual', '>', 0)
            ->get(['id', 'stock_actual', 'fecha_caducidad']);

        $ahora = now();

        foreach ($medicinas as $medicina) {
            DB::table('lotes_medicina')->insert([
                'inventario_medicina_id' => $medicina->id,
                'item_adquisicion_id'    => null,
                'codigo_lote'            => null,
                'fecha_caducidad'        => $medicina->fecha_caducidad,
                'cantidad_ingresada'     => $medicina->stock_actual,
                'stock_actual'           => $medicina->stock_actual,
                'created_at'             => $ahora,
                'updated_at'             => $ahora,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('lotes_medicina')
            ->whereNull('item_adquisicion_id')
            ->whereNull('codigo_lote')
            ->delete();
    }
};
