<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La constancia de una historia clínica fusionada en otra (hallazgo del
 * 2026-10-23, `sgth:historias:fusionar`): a cuál y cuándo. La fusionada se
 * retira en blando y su contenido pasa a la que se queda; con esto se sabe
 * de dónde vino.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('historias_clinicas', function (Blueprint $table) {
            $table->foreignId('fusionada_con_id')
                  ->nullable()
                  ->constrained('historias_clinicas')
                  ->nullOnDelete();
            $table->timestamp('fusionada_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('historias_clinicas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fusionada_con_id');
            $table->dropColumn('fusionada_en');
        });
    }
};
