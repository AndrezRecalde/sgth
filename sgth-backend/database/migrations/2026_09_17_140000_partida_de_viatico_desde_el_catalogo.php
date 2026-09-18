<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La partida presupuestaria del viático sale del catálogo, no de un campo de
 * texto.
 *
 * Se pedía escribiéndola a mano —«530303», «5303 03», «viáticos interior»— y
 * nada comprobaba que existiera. El catálogo `partidas_presupuestarias` ya vive
 * en Estructura, lo usan Puestos y las acciones de personal, y trae las dos
 * partidas del gasto: 530303 en el interior y 530304 en el exterior.
 *
 * Lo escrito antes se conserva cuando coincide con el código de una partida; lo
 * que no coincida queda sin partida y Financiero la elegirá al contabilizar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viaticos', function (Blueprint $table) {
            $table->foreignId('partida_presupuestaria_id')
                ->nullable()
                ->after('numero_resolucion')
                ->constrained('partidas_presupuestarias')
                ->nullOnDelete();
        });

        DB::statement("
            UPDATE viaticos AS v
            SET partida_presupuestaria_id = p.id
            FROM partidas_presupuestarias AS p
            WHERE p.codigo = trim(v.partida_presupuestaria)
        ");

        Schema::table('viaticos', function (Blueprint $table) {
            $table->dropColumn('partida_presupuestaria');
        });
    }

    public function down(): void
    {
        Schema::table('viaticos', function (Blueprint $table) {
            $table->string('partida_presupuestaria')->nullable()->after('numero_resolucion');
        });

        DB::statement('
            UPDATE viaticos AS v
            SET partida_presupuestaria = p.codigo
            FROM partidas_presupuestarias AS p
            WHERE p.id = v.partida_presupuestaria_id
        ');

        Schema::table('viaticos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('partida_presupuestaria_id');
        });
    }
};
