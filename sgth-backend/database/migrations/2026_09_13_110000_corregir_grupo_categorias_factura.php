<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Hospedaje y Alimentación justifican el 70 % del viático.
 *
 * La columna `grupo` nació con `movilizacion` por defecto y el seeder de
 * categorías nunca la llenó, así que en cualquier base todas las categorías
 * quedaron como movilización: ningún comprobante justificaba el 70 %, y la
 * devolución calculada era siempre el anticipo completo.
 *
 * Decidido con el usuario: Hospedaje y Alimentación pasan a `viatico`, y
 * «Viático diario» se desactiva —no es un gasto con factura propia—. Los
 * comprobantes que ya la usan conservan la categoría.
 *
 * Solo toca filas que existan, por código: en una base recién creada la tabla
 * está vacía aquí y los valores los pone el seeder.
 */
return new class extends Migration
{
    private const DEL_VIATICO = ['hospedaje', 'alimentacion'];

    private const DESACTIVADAS = ['viatico_diario'];

    public function up(): void
    {
        DB::table('categorias_factura')
            ->whereIn('codigo', self::DEL_VIATICO)
            ->update(['grupo' => 'viatico', 'updated_at' => now()]);

        DB::table('categorias_factura')
            ->whereIn('codigo', self::DESACTIVADAS)
            ->update(['activo' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('categorias_factura')
            ->whereIn('codigo', self::DEL_VIATICO)
            ->update(['grupo' => 'movilizacion', 'updated_at' => now()]);

        DB::table('categorias_factura')
            ->whereIn('codigo', self::DESACTIVADAS)
            ->update(['activo' => true, 'updated_at' => now()]);
    }
};
