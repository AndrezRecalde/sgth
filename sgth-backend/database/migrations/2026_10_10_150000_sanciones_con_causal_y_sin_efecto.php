<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La sanción con sus datos, y anular ↔ sanción (diseño de Acciones de
 * Personal, 4.2 y 9.6; TH N13; fase 2.5).
 *
 * - Las causales de la acción de una sanción: amonestación escrita, sanción
 *   pecuniaria y suspensión, en la restricción del subtipo. «Sanción
 *   disciplinaria» a secas queda para el histórico.
 * - Las acciones ya emitidas desde un sumario toman la causal de la sanción
 *   enlazada, que se conoce con exactitud. Las hechas a mano, sin sanción
 *   detrás, se quedan como estaban: su causal es «no indicada (histórico)».
 * - `sanciones_disciplinarias` gana cuándo, por qué y quién la dejó sin
 *   efecto. Anular su acción ya no la deja «impuesta». Y el sumario admite una
 *   sanción vigente a la vez, no una para siempre: la que quedó sin efecto no
 *   ocupa el lugar de la que la corrige.
 */
return new class extends Migration
{
    private const CAMBIO_ADMINISTRATIVO = [
        'traslado_administrativo', 'traspaso', 'comision_con_remuneracion', 'comision_sin_remuneracion',
    ];

    private const CESACION = [
        'renuncia', 'destitucion', 'jubilacion', 'incapacidad', 'contrato_finalizado', 'visto_bueno',
        'remocion', 'periodo_prueba_no_superado', 'fin_del_plazo', 'terminacion_unilateral',
        'mutuo_acuerdo', 'evaluacion_insuficiente', 'retiro_voluntario',
        'perdida_derechos_ciudadania', 'fallecimiento',
    ];

    private const LICENCIA = [
        'asuntos_particulares', 'estudios_posgrado', 'servicio_militar', 'reemplazo_dignatario',
        'candidatura', 'cuidado_hijos', 'segun_su_regimen',
    ];

    private const SANCION_ANTES = ['sancion_disciplinaria'];

    private const SANCION = [
        'amonestacion_escrita', 'sancion_pecuniaria', 'suspension_sin_remuneracion', 'sancion_disciplinaria',
    ];

    public function up(): void
    {
        $this->reemplazarConstraint(self::SANCION);

        DB::statement("
            UPDATE movimientos_personal AS m
            SET subtipo_movimiento = CASE s.tipo_sancion
                WHEN 'amonestacion_escrita' THEN 'amonestacion_escrita'
                WHEN 'multa'                THEN 'sancion_pecuniaria'
                WHEN 'suspension'           THEN 'suspension_sin_remuneracion'
            END
            FROM sanciones_disciplinarias AS s
            WHERE s.movimiento_personal_id = m.id
              AND m.tipo_movimiento = 'regimen_disciplinario'
              AND m.subtipo_movimiento = 'sancion_disciplinaria'
              AND s.tipo_sancion IN ('amonestacion_escrita', 'multa', 'suspension')
        ");

        Schema::table('sanciones_disciplinarias', function (Blueprint $table) {
            $table->timestamp('anulada_en')->nullable()->after('observaciones');
            $table->string('motivo_anulacion', 500)->nullable()->after('anulada_en');
            $table->foreignId('anulada_por')->nullable()->after('motivo_anulacion')
                ->constrained('users')->nullOnDelete();

            $table->dropUnique('sanciones_disciplinarias_sumario_id_unique');
        });

        // Una vigente por sumario. La borrada lógicamente sigue ocupando el
        // lugar, como hasta ahora: no se borra para corregirla, se deja sin
        // efecto.
        DB::statement('
            CREATE UNIQUE INDEX sanciones_disciplinarias_sumario_vigente_unique
            ON sanciones_disciplinarias (sumario_id)
            WHERE anulada_en IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS sanciones_disciplinarias_sumario_vigente_unique');

        // Las sin efecto no caben en un índice único por sumario.
        DB::table('sanciones_disciplinarias')->whereNotNull('anulada_en')->delete();

        Schema::table('sanciones_disciplinarias', function (Blueprint $table) {
            $table->dropConstrainedForeignId('anulada_por');
            $table->dropColumn(['anulada_en', 'motivo_anulacion']);
            $table->unique('sumario_id');
        });

        DB::table('movimientos_personal')
            ->where('tipo_movimiento', 'regimen_disciplinario')
            ->whereIn('subtipo_movimiento', ['amonestacion_escrita', 'sancion_pecuniaria', 'suspension_sin_remuneracion'])
            ->update(['subtipo_movimiento' => 'sancion_disciplinaria']);

        $this->reemplazarConstraint(self::SANCION_ANTES);
    }

    /** @param  list<string>  $sancion */
    private function reemplazarConstraint(array $sancion): void
    {
        DB::statement('ALTER TABLE movimientos_personal DROP CONSTRAINT IF EXISTS movimientos_personal_subtipo_coherente_check');

        DB::statement("
            ALTER TABLE movimientos_personal
            ADD CONSTRAINT movimientos_personal_subtipo_coherente_check
            CHECK (
                subtipo_movimiento IS NULL
                OR (tipo_movimiento = 'cambio_administrativo' AND subtipo_movimiento IN ({$this->lista(self::CAMBIO_ADMINISTRATIVO)}))
                OR (tipo_movimiento = 'regimen_disciplinario' AND subtipo_movimiento IN ({$this->lista($sancion)}))
                OR (tipo_movimiento = 'cesacion_funciones' AND subtipo_movimiento IN ({$this->lista(self::CESACION)}))
                OR (tipo_movimiento = 'licencia_sin_remuneracion' AND subtipo_movimiento IN ({$this->lista(self::LICENCIA)}))
            )
        ");
    }

    /** @param  list<string>  $valores */
    private function lista(array $valores): string
    {
        return implode(', ', array_map(fn ($v) => "'{$v}'", $valores));
    }
};
