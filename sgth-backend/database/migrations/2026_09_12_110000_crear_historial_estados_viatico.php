<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de estados del viático: quién lo movió, cuándo y por qué.
 *
 * Hasta ahora el viático solo guardaba su estado actual y un `updated_by` que
 * la mitad de las transiciones no llenaba. No había forma de saber quién lo
 * aprobó, quién lo devolvió a corrección ni con qué motivo, y el motivo de un
 * rechazo tenía columna pero nunca se escribía.
 *
 * `motivo_rechazo` pasa a `text`: el motivo admite hasta 500 caracteres y la
 * columna era `varchar(255)`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('viatico_historial_estados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('viatico_id')->constrained('viaticos')->cascadeOnDelete();
            $table->string('estado_anterior', 30)->nullable();
            $table->string('estado_nuevo', 30);
            $table->text('motivo')->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['viatico_id', 'id']);
        });

        Schema::table('viaticos', function (Blueprint $table) {
            $table->text('motivo_rechazo')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('viatico_historial_estados');

        Schema::table('viaticos', function (Blueprint $table) {
            $table->string('motivo_rechazo')->nullable()->change();
        });
    }
};
