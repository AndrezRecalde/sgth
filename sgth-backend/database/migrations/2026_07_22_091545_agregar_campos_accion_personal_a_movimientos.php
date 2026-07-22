<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            // Campos exclusivos del documento "Acción de Personal" (PDF),
            // opcionales: el historial de movimientos sigue funcionando sin
            // ellos, solo se completan cuando RRHH necesita emitir el PDF
            // formal.
            $table->string('codigo', 30)->nullable()->after('tipo_movimiento');
            $table->string('lugar_trabajo')->nullable()->after('observacion');
            $table->boolean('caucionado')->nullable()->after('lugar_trabajo');
            $table->string('caucion_numero')->nullable()->after('caucionado');
            $table->date('caucion_fecha')->nullable()->after('caucion_numero');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->dropColumn([
                'codigo', 'lugar_trabajo', 'caucionado', 'caucion_numero', 'caucion_fecha',
            ]);
        });
    }
};
