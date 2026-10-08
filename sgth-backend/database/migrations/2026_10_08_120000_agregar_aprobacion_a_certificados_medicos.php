<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Talento Humano y Trabajo Social aprueban el certificado médico, no un
 * permiso creado a partir de él (decisión del 2026-10-08).
 *
 * - `servidor_id`: el servidor del certificado, que hasta ahora solo se sabía
 *   pasando por la consulta y la historia clínica. Nulo en los de familiares,
 *   que no se aprueban: no tienen marcaciones que justificar.
 * - `aprobado_*`: la aprobación en el SGTH, que es lo que cuenta en el
 *   ausentismo. `registro_sirha7` dice cómo llegó a Sirha7: `sgth` si lo
 *   escribió el SGTH, `manual` si TH lo cargó a mano (las cédulas que Sirha7 no
 *   reconoce, o lo anterior a la fecha de corte); `nota_aprobacion` lo explica.
 * - `sirha7_*`: lo que escribió el SGTH. La referencia se guarda tal cual
 *   porque con ella se retira: no se deduce del folio.
 *
 * Las filas, una por día de reposo, en su tabla y con su ID de Sirha7, igual
 * que las de los permisos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificados_medicos', function (Blueprint $table) {
            $table->foreignId('servidor_id')
                ->nullable()
                ->after('consulta_medica_id')
                ->constrained('servidores');

            $table->foreignId('aprobado_por')->nullable()->after('motivo_anulacion')->constrained('users');
            $table->timestamp('aprobado_en')->nullable()->after('aprobado_por');
            $table->string('registro_sirha7', 10)->nullable()->after('aprobado_en');
            $table->string('nota_aprobacion', 500)->nullable()->after('registro_sirha7');

            $table->integer('sirha7_leave_id')->nullable()->after('nota_aprobacion');
            $table->string('sirha7_leave_nombre', 200)->nullable()->after('sirha7_leave_id');
            $table->integer('sirha7_userid')->nullable()->after('sirha7_leave_nombre');
            $table->string('sirha7_referencia', 60)->nullable()->after('sirha7_userid');
            $table->json('sirha7_dias_omitidos')->nullable()->after('sirha7_referencia');

            $table->index(['servidor_id', 'fecha_inicio']);
        });

        // Los ya emitidos: el servidor sale de la historia clínica de su consulta.
        DB::statement(<<<'SQL'
            UPDATE certificados_medicos AS c
            SET servidor_id = h.servidor_id
            FROM consultas_medicas AS cm
            JOIN historias_clinicas AS h ON h.id = cm.historia_clinica_id
            WHERE cm.id = c.consulta_medica_id
              AND h.servidor_id IS NOT NULL
        SQL);

        Schema::create('certificado_sirha7_filas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certificado_medico_id')
                ->constrained('certificados_medicos')
                ->cascadeOnDelete();
            // El ID de dbo.USER_SPEDAY en Sirha7.
            $table->integer('sirha7_id');
            $table->dateTime('inicio');
            $table->dateTime('fin');
            $table->timestamps();

            $table->unique(['certificado_medico_id', 'sirha7_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificado_sirha7_filas');

        Schema::table('certificados_medicos', function (Blueprint $table) {
            $table->dropIndex(['servidor_id', 'fecha_inicio']);
            $table->dropForeign(['servidor_id']);
            $table->dropForeign(['aprobado_por']);
            $table->dropColumn([
                'servidor_id', 'aprobado_por', 'aprobado_en', 'registro_sirha7', 'nota_aprobacion',
                'sirha7_leave_id', 'sirha7_leave_nombre', 'sirha7_userid', 'sirha7_referencia',
                'sirha7_dias_omitidos',
            ]);
        });
    }
};
