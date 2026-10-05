<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * `cv_ruta` nunca se llenó (2026-10-05): la hoja de vida, como los demás
 * documentos del postulante, va en `documentos_postulante`, en el disco
 * privado. La columna quedó de un primer diseño y solo confundía.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postulantes', function (Blueprint $table) {
            $table->dropColumn('cv_ruta');
        });
    }

    public function down(): void
    {
        Schema::table('postulantes', function (Blueprint $table) {
            $table->string('cv_ruta')->nullable();
        });
    }
};
