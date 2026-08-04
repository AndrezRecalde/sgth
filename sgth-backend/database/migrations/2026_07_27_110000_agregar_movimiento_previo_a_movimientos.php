<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Encadena una acción de personal con la que la habilitó. El caso que motiva
 * la columna: Talento Humano no usa "ascenso" — cuando un servidor pasa a otro
 * puesto se registran dos acciones formales y separadas, primero la Cesación
 * de Funciones y después el Ingreso y Vinculación al puesto nuevo. Esta
 * columna deja constancia de cuál cesación habilitó cuál ingreso.
 *
 * Es distinta de 'corrige_a_id', que apunta al registro que se rectifica.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->foreignId('movimiento_previo_id')
                  ->nullable()
                  ->after('corrige_a_id')
                  ->constrained('movimientos_personal')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->dropConstrainedForeignId('movimiento_previo_id');
        });
    }
};
