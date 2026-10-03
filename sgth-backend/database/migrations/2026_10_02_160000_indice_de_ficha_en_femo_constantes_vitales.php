<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `femo_constantes_vitales.ficha_id` sin índice.
 *
 * En PostgreSQL una clave foránea no crea índice. Las demás tablas hijas de la
 * ficha lo tienen; esta no, y se consulta cada vez que se abre una ficha o se
 * genera su PDF.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('femo_constantes_vitales', function (Blueprint $table) {
            $table->index('ficha_id');
        });
    }

    public function down(): void
    {
        Schema::table('femo_constantes_vitales', function (Blueprint $table) {
            $table->dropIndex(['ficha_id']);
        });
    }
};
