<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La nómina del SGTH sobre el vínculo (diseño de Acciones de Personal, 9.8;
 * fase 7.3c).
 *
 * - `nominas.referencial`: calculada en paralelo con la de Financiero, que es
 *   la que paga. Se cierra para congelarla, sin salir al ERP ni a los
 *   servidores.
 * - `detalle_nomina.descuento_recurrente_id`: la cuota de un préstamo se cuenta
 *   como pagada al cerrar, no al calcular. Hasta aquí cada recálculo del
 *   borrador sumaba otra.
 * - `roles_pago.dias` y `roles_pago.remuneracion`: los días del mes y la
 *   remuneración con que se pagó, para compararlo con Financiero.
 * - Los conceptos que traen las novedades y no estaban en el catálogo.
 */
return new class extends Migration
{
    private const CONCEPTOS = [
        ['codigo' => 'ENCARGO', 'nombre' => 'Diferencia por Encargo', 'tipo' => 'ingreso'],
        ['codigo' => 'RETROACTIVO', 'nombre' => 'Retroactivo de remuneración', 'tipo' => 'ingreso'],
        ['codigo' => 'REMUNERACIONES_PENDIENTES', 'nombre' => 'Remuneraciones pendientes (restitución o reintegro)', 'tipo' => 'ingreso'],
        ['codigo' => 'DIAS_SIN_REMUNERACION', 'nombre' => 'Días sin remuneración (licencia, comisión o suspensión)', 'tipo' => 'descuento'],
        ['codigo' => 'MULTA_DISCIPLINARIA', 'nombre' => 'Multa disciplinaria', 'tipo' => 'descuento'],
    ];

    public function up(): void
    {
        Schema::table('nominas', function (Blueprint $table) {
            $table->boolean('referencial')->default(true)->after('estado');
        });

        Schema::table('detalle_nomina', function (Blueprint $table) {
            $table->foreignId('descuento_recurrente_id')->nullable()->after('concepto_nomina_id')
                ->constrained('descuentos_recurrentes')->nullOnDelete();
        });

        Schema::table('roles_pago', function (Blueprint $table) {
            $table->smallInteger('dias')->nullable()->after('servidor_id');
            $table->decimal('remuneracion', 12, 2)->nullable()->after('dias');
        });

        // En la tabla y no por el modelo, que se retiró con la nómina
        // (2026-10-20).
        foreach (self::CONCEPTOS as $concepto) {
            if (! DB::table('conceptos_nomina')->where('codigo', $concepto['codigo'])->exists()) {
                DB::table('conceptos_nomina')->insert([...$concepto, 'activo' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        DB::table('conceptos_nomina')->whereIn('codigo', array_column(self::CONCEPTOS, 'codigo'))->delete();

        Schema::table('roles_pago', function (Blueprint $table) {
            $table->dropColumn(['dias', 'remuneracion']);
        });

        Schema::table('detalle_nomina', function (Blueprint $table) {
            $table->dropConstrainedForeignId('descuento_recurrente_id');
        });

        Schema::table('nominas', function (Blueprint $table) {
            $table->dropColumn('referencial');
        });
    }
};
