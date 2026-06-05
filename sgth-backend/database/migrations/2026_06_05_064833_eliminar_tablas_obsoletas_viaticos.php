<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Eliminar FK comision_id en viaticos
        Schema::table('viaticos', function (Blueprint $table) {
            if (Schema::hasColumn('viaticos', 'comision_id')) {
                $table->dropForeign(['comision_id']);
                $table->dropColumn('comision_id');
            }
            if (Schema::hasColumn('viaticos', 'tipo')) {
                $table->dropColumn('tipo');
            }
            if (Schema::hasColumn('viaticos', 'fecha_inicio')) {
                $table->dropColumn('fecha_inicio');
            }
            if (Schema::hasColumn('viaticos', 'fecha_fin')) {
                $table->dropColumn('fecha_fin');
            }
        });

        // 2. Eliminar tablas que reemplaza tramos_viatico
        Schema::dropIfExists('autorizaciones_vuelo');
        Schema::dropIfExists('transportes_viatico');
        Schema::dropIfExists('destinos_viatico');
        Schema::dropIfExists('comisiones');
    }

    public function down(): void
    {
        // No reversible — refactorización intencional
    }
};
