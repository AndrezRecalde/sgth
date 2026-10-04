<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Lo que le faltaba al visto bueno (2026-10-04):
 *
 * - La resolución del Inspector del Trabajo como archivo. `documento_respaldo`
 *   existía como texto libre que ninguna pantalla llenaba; pasa a guardar la
 *   ruta del PDF en el disco privado —solo la escribe el sistema al subirlo—,
 *   y aquí se agrega el nombre original para devolverlo al descargar.
 * - La referencia de la impugnación: el número de juicio o de causa y la
 *   fecha. No había dónde ponerla, y `resolucion_detalle` es del Inspector.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vistos_buenos', function (Blueprint $table) {
            $table->string('documento_nombre', 255)->nullable()->after('documento_respaldo');
            $table->string('impugnacion_referencia', 200)->nullable()->after('resolucion_detalle');
            $table->date('fecha_impugnacion')->nullable()->after('impugnacion_referencia');
        });
    }

    public function down(): void
    {
        Schema::table('vistos_buenos', function (Blueprint $table) {
            $table->dropColumn(['documento_nombre', 'impugnacion_referencia', 'fecha_impugnacion']);
        });
    }
};
