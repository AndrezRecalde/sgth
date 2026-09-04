<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * `tipo_movimiento` era texto libre y ya acumula cinco valores con
     * significado propio. Un typo en cualquiera de ellos rompería en silencio
     * el kardex, que es el libro que explica por qué el stock es el que es, y
     * nada lo detectaría. Mismo tratamiento que recibió `presentacion`.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE movimientos_inventario_med
            ADD CONSTRAINT check_tipo_movimiento_valido
            CHECK (tipo_movimiento IN ('ingreso','egreso','ajuste','anulacion','baja'))
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE movimientos_inventario_med
            DROP CONSTRAINT check_tipo_movimiento_valido
        ");
    }
};
