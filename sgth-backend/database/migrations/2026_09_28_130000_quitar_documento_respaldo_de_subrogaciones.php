<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `documento_respaldo` nunca se usó.
 *
 * Estaba en `$fillable` y el controlador la validaba como `nullable|string`,
 * pero ningún camino la escribe —no hay subida de archivo en el módulo— y
 * ninguna pantalla la lee. Una columna que el API acepta y nadie llena es una
 * promesa: quien la vea en el esquema va a suponer que ahí está el respaldo
 * escaneado, y no está.
 *
 * El respaldo documental de una subrogación es hoy su Acción de Personal, que sí
 * se imprime y se firma. Si algún día hace falta adjuntar el escaneado, entra
 * como funcionalidad con su subida, su validación de tipo y peso, y su
 * descarga — no como una columna suelta.
 *
 * `down()` la devuelve tal como estaba. En la base de desarrollo no hay nada que
 * recuperar —0 filas con valor, comprobado antes de escribir esto—, y como
 * ninguna ruta la escribe tampoco puede haberlo en producción.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subrogaciones', function (Blueprint $table) {
            $table->dropColumn('documento_respaldo');
        });
    }

    public function down(): void
    {
        Schema::table('subrogaciones', function (Blueprint $table) {
            $table->string('documento_respaldo')->nullable()->after('resolucion_numero');
        });
    }
};
