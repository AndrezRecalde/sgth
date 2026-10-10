<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cuándo surtió efecto una acción de personal (diseño de Acciones de Personal,
 * 6.2 y 8.6 punto 5; fase 1.6).
 *
 * Hasta aquí el efecto se aplicaba al registrar, rigiera cuando rigiera: una
 * cesación registrada hoy que rige el mes próximo cerraba el vínculo hoy y
 * —desde la fase 1.5— sacaba al servidor de la nómina antes de tiempo. Ahora
 * una acción registrada con fecha futura queda pendiente de vigencia, y el
 * comando diario `sgth:acciones:aplicar-vigentes` aplica su efecto el día en
 * que rige. Registrada y con esta columna en null es «pendiente de vigencia».
 *
 * Lo ya registrado surtió efecto al registrarse, que es como funcionaba: se
 * rellena con su fecha de registro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->timestamp('efecto_aplicado_en')->nullable()->after('fecha_registro');
            $table->index(['estado', 'efecto_aplicado_en']);
        });

        DB::statement("
            UPDATE movimientos_personal
               SET efecto_aplicado_en = COALESCE(fecha_registro::timestamp, updated_at)
             WHERE codigo_registro IS NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->dropIndex(['estado', 'efecto_aplicado_en']);
            $table->dropColumn('efecto_aplicado_en');
        });
    }
};
