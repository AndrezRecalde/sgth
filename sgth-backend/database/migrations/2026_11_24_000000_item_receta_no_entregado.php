<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un medicamento del inventario que el mostrador no va a entregar: agotado,
 * vencido u otro motivo (decisión del Dispensario del 2026-10-24, opción A).
 *
 * Hasta ahora una receta solo se cerraba entregándolo todo o anulándola
 * entera, y anularla dejaba al paciente con un impreso «ANULADA» que no le
 * servía para comprar fuera lo que faltó. Ahora el ítem queda «no entregado»
 * con su motivo, quién lo marcó y cuándo; la receta se cierra cuando todo está
 * entregado o marcado, y el impreso dice qué adquirir fuera.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items_receta', function (Blueprint $table) {
            $table->string('motivo_no_entregado', 20)->nullable()->after('estado');
            $table->string('detalle_no_entregado')->nullable()->after('motivo_no_entregado');
            $table->foreignId('no_entregado_por')->nullable()->after('detalle_no_entregado')->constrained('users');
            $table->timestamp('no_entregado_en')->nullable()->after('no_entregado_por');
        });

        DB::statement('ALTER TABLE items_receta DROP CONSTRAINT items_receta_estado_check');
        DB::statement("
            ALTER TABLE items_receta
            ADD CONSTRAINT items_receta_estado_check
            CHECK (estado IN (
                'pendiente', 'despachado_parcial', 'despachado_completo',
                'no_disponible', 'no_entregado'
            ))
        ");
    }

    public function down(): void
    {
        // Lo marcado vuelve a esperar: el CHECK anterior no conoce el estado.
        DB::table('items_receta')->where('estado', 'no_entregado')->update([
            'estado' => DB::raw("CASE WHEN cantidad_despachada > 0 THEN 'despachado_parcial' ELSE 'pendiente' END"),
        ]);
        DB::table('recetas_medicas')->where('estado', 'cerrada_con_faltantes')->update(['estado' => 'despachada_parcial']);

        DB::statement('ALTER TABLE items_receta DROP CONSTRAINT items_receta_estado_check');
        DB::statement("
            ALTER TABLE items_receta
            ADD CONSTRAINT items_receta_estado_check
            CHECK (estado IN (
                'pendiente', 'despachado_parcial', 'despachado_completo',
                'no_disponible'
            ))
        ");

        Schema::table('items_receta', function (Blueprint $table) {
            $table->dropConstrainedForeignId('no_entregado_por');
            $table->dropColumn(['motivo_no_entregado', 'detalle_no_entregado', 'no_entregado_en']);
        });
    }
};
