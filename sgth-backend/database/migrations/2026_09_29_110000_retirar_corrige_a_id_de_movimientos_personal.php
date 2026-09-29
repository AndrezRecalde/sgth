<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Se retira `corrige_a_id`.
 *
 * Existía para enmendar una acción de personal ya registrada: en vez de
 * editarla —imposible, hay guarda de inmutabilidad— se creaba otra que la
 * referenciaba. Nunca tuvo pantalla, y al analizarlo resultó que tampoco
 * funcionaba: en un ingreso y en una cesación la corrección moría al
 * registrarse, y en las demás dejaba DOS documentos oficiales vigentes con
 * correlativos distintos, sin que nada dijera que el primero había quedado
 * enmendado.
 *
 * Preguntado, Talento Humano (2026-09-29): «Lo correcto es anularla, para
 * emitir uno nuevo.» Esa vía es la que abre esta misma entrega, así que la
 * columna queda sin uso.
 *
 * No hay datos que migrar: ninguna fila la tenía poblada, porque el endpoint
 * no se llamaba desde ninguna parte. `down()` la devuelve vacía.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $tabla) {
            $tabla->dropConstrainedForeignId('corrige_a_id');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $tabla) {
            $tabla->foreignId('corrige_a_id')
                ->nullable()
                ->constrained('movimientos_personal')
                ->nullOnDelete();
        });
    }
};
