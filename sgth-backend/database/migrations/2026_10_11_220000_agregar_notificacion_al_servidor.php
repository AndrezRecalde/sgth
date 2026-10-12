<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La notificación de la acción de personal al servidor (Reglamento a la LOSEP,
 * Art. 22; diseño, 6.4 y 9.13; TH 26; fase 6.3a).
 *
 * - `notificacion_medio`: por el portal —el documento aparece en «Mis
 *   documentos laborales» y le llega al correo— o en persona.
 * - En persona, `notificacion_resultado`: la recibió o se negó. La negativa
 *   lleva la razón y un testigo, con su nombre y su cédula.
 * - `acuse_en`: cuándo el servidor acusó recibo. En el portal lo deja él; en
 *   persona, es la fecha en que firmó el «Recibí».
 * - `correo_enviado_en` y `correo_destino`: si salió el correo y a cuál
 *   dirección —la institucional, la personal—, o por qué no salió.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->string('notificacion_medio', 10)->nullable()->after('fecha_notificacion');
            $table->string('notificacion_resultado', 10)->nullable()->after('notificacion_medio');
            $table->string('notificacion_razon', 500)->nullable()->after('notificacion_resultado');
            $table->string('notificacion_testigo_nombre', 150)->nullable()->after('notificacion_razon');
            $table->string('notificacion_testigo_cedula', 13)->nullable()->after('notificacion_testigo_nombre');
            $table->timestamp('acuse_en')->nullable()->after('notificacion_testigo_cedula');
            $table->timestamp('correo_enviado_en')->nullable()->after('acuse_en');
            $table->string('correo_destino', 15)->nullable()->after('correo_enviado_en');
        });

        DB::statement("ALTER TABLE movimientos_personal ADD CONSTRAINT movimientos_notificacion_medio_check
            CHECK (notificacion_medio IS NULL OR notificacion_medio IN ('portal', 'personal'))");
        // En persona dice si la recibió; por el portal, no hay resultado: lo
        // dice el acuse cuando llegue.
        DB::statement("ALTER TABLE movimientos_personal ADD CONSTRAINT movimientos_notificacion_resultado_check
            CHECK (
                (notificacion_medio = 'personal' AND notificacion_resultado IN ('recibida', 'negada'))
                OR (notificacion_medio IS DISTINCT FROM 'personal' AND notificacion_resultado IS NULL)
            )");
        // La negativa, con su razón y su testigo.
        DB::statement("ALTER TABLE movimientos_personal ADD CONSTRAINT movimientos_notificacion_negada_check
            CHECK (notificacion_resultado IS DISTINCT FROM 'negada' OR (
                notificacion_razon IS NOT NULL
                AND notificacion_testigo_nombre IS NOT NULL
                AND notificacion_testigo_cedula IS NOT NULL
            ))");
        DB::statement("ALTER TABLE movimientos_personal ADD CONSTRAINT movimientos_correo_destino_check
            CHECK (correo_destino IS NULL OR correo_destino IN ('institucional', 'personal', 'sin_direccion', 'fallo'))");
    }

    public function down(): void
    {
        foreach (['notificacion_medio', 'notificacion_resultado', 'notificacion_negada', 'correo_destino'] as $check) {
            DB::statement("ALTER TABLE movimientos_personal DROP CONSTRAINT IF EXISTS movimientos_{$check}_check");
        }

        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->dropColumn([
                'notificacion_medio', 'notificacion_resultado', 'notificacion_razon',
                'notificacion_testigo_nombre', 'notificacion_testigo_cedula',
                'acuse_en', 'correo_enviado_en', 'correo_destino',
            ]);
        });
    }
};
