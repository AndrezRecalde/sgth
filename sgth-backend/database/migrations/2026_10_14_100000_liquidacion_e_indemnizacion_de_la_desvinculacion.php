<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La liquidación de vacaciones y la indemnización de la desvinculación (fase
 * 7.4b).
 *
 * - La liquidación la calcula el sistema y el Director la confirma: queda
 *   congelada aquí, con su detalle, y llega a Financiero en las novedades del
 *   mes de la salida.
 * - La indemnización la registra el Director, dentro del tope del Mandato 2
 *   (7 salarios básicos por año de servicio, hasta 210), que se guarda con
 *   ella.
 * - Quien cobró una indemnización no reingresa mientras no la devuelva
 *   (Mandato 2, art. 8): la devolución se registra aquí y levanta la
 *   prohibición.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('desvinculaciones', function (Blueprint $table) {
            $table->decimal('vacaciones_dias', 6, 2)->nullable();
            $table->decimal('vacaciones_remuneracion', 12, 2)->nullable();
            $table->decimal('vacaciones_monto', 12, 2)->nullable();
            $table->json('vacaciones_detalle')->nullable();
            $table->foreignId('vacaciones_confirmada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('vacaciones_confirmada_en')->nullable();

            $table->string('indemnizacion_concepto', 30)->nullable();
            $table->decimal('indemnizacion_monto', 12, 2)->nullable();
            $table->string('indemnizacion_resolucion', 150)->nullable();
            $table->decimal('indemnizacion_tope', 12, 2)->nullable();
            $table->foreignId('indemnizacion_registrada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('indemnizacion_registrada_en')->nullable();

            $table->date('indemnizacion_devuelta_en')->nullable();
            $table->string('indemnizacion_devolucion_documento', 150)->nullable();
            $table->foreignId('indemnizacion_devolucion_registrada_por')->nullable()->constrained('users')->nullOnDelete();
        });

        // Los conceptos de la nómina del SGTH para lo que paga la salida. En la
        // tabla y no por el modelo, que se retiró con la nómina (2026-10-20).
        foreach ([
            ['codigo' => 'VACACIONES_PAGADAS', 'nombre' => 'Vacaciones Compensadas al Retiro'],
            ['codigo' => 'INDEMNIZACION', 'nombre' => 'Indemnización o compensación por la salida'],
        ] as $concepto) {
            if (! DB::table('conceptos_nomina')->where('codigo', $concepto['codigo'])->exists()) {
                DB::table('conceptos_nomina')->insert([
                    ...$concepto, 'tipo' => 'ingreso', 'activo' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('conceptos_nomina')->where('codigo', 'INDEMNIZACION')->delete();

        Schema::table('desvinculaciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('indemnizacion_devolucion_registrada_por');
            $table->dropColumn(['indemnizacion_devuelta_en', 'indemnizacion_devolucion_documento']);
            $table->dropConstrainedForeignId('indemnizacion_registrada_por');
            $table->dropColumn(['indemnizacion_concepto', 'indemnizacion_monto', 'indemnizacion_resolucion', 'indemnizacion_tope', 'indemnizacion_registrada_en']);
            $table->dropConstrainedForeignId('vacaciones_confirmada_por');
            $table->dropColumn(['vacaciones_dias', 'vacaciones_remuneracion', 'vacaciones_monto', 'vacaciones_detalle', 'vacaciones_confirmada_en']);
        });
    }
};
