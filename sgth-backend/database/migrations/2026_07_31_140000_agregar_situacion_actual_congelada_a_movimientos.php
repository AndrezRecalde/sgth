<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Completa la columna "situación actual" del documento de Acción de Personal.
 *
 * Ya se congelaban puesto y unidad de origen; faltaban la remuneración y la
 * partida. Derivarlas del puesto al momento de imprimir sería un error de
 * auditoría: el puesto cambia de grupo ocupacional y de partida con el tiempo,
 * así que un documento reimpreso años después mostraría cifras que nunca
 * estuvieron en el original. Es el mismo motivo por el que los firmantes se
 * sellan en vez de resolverse cada vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->decimal('remuneracion_origen', 10, 2)
                ->nullable()
                ->after('puesto_origen_id');

            $table->foreignId('partida_origen_id')
                ->nullable()
                ->after('remuneracion_origen')
                ->constrained('partidas_presupuestarias')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->dropConstrainedForeignId('partida_origen_id');
            $table->dropColumn('remuneracion_origen');
        });
    }
};
