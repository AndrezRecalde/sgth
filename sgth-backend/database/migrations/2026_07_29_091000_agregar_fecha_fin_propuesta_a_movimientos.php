<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fecha de término del vínculo propuesto. La necesitan los contratos con plazo
 * —servicios ocasionales y servicios profesionales—, cuyo vencimiento se pacta
 * al contratar y hasta ahora no viajaba en la acción de personal: el contrato
 * nacía sin plazo, o con el derivado automáticamente.
 *
 * Nula para los nombramientos sin plazo (permanente, provisional, libre
 * nombramiento, elección popular).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->date('fecha_fin_propuesta')->nullable()->after('remuneracion_propuesta');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->dropColumn('fecha_fin_propuesta');
        });
    }
};
