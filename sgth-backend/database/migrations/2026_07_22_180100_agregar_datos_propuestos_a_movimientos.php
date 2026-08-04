<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Datos "propuestos" para los tipos que crean o modifican un vínculo
     * (creaVinculo() / modificaVinculo()): viven en el MovimientoPersonal
     * mientras está en borrador y solo se materializan en un
     * ContratoServidor al transicionar a REGISTRADA. No llevan CHECK
     * constraint (a diferencia de tipo_movimiento): son un borrador, no
     * un valor legalmente cerrado — la corrección de tipo la da el cast
     * a TipoNombramiento en el modelo y la validación de aplicación.
     */
    public function up(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->string('tipo_nombramiento_propuesto', 50)->nullable()->after('categoria');
            $table->decimal('remuneracion_propuesta', 10, 2)->nullable()->after('tipo_nombramiento_propuesto');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->dropColumn(['tipo_nombramiento_propuesto', 'remuneracion_propuesta']);
        });
    }
};
