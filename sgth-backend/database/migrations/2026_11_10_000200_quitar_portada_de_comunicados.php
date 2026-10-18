<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Sin portada en las noticias y comunicados (decisión del 2026-10-18,
 * docs/diseno-noticias-comunicados.md, D10): Talento Humano no publica
 * imágenes, y el Portal se diseña con tarjetas de solo texto.
 *
 * Va aparte de `2026_11_10_000000_crear_comunicados`, que ya corrió en
 * desarrollo. Se llevan también los archivos de las portadas que se hubieran
 * subido mientras existió, para no dejar imágenes huérfanas en el disco.
 */
return new class extends Migration
{
    public function up(): void
    {
        $disco = Storage::disk('local');

        DB::table('comunicados')->whereNotNull('portada_path')->orderBy('id')
            ->pluck('portada_path')
            ->each(fn (string $ruta) => $disco->delete($ruta));

        Schema::table('comunicados', function (Blueprint $table) {
            $table->dropColumn('portada_path');
        });
    }

    public function down(): void
    {
        Schema::table('comunicados', function (Blueprint $table) {
            $table->string('portada_path')->nullable()->after('referencia');
        });
    }
};
