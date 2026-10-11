<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La terminación del contrato de un obrero (diseño de Acciones de Personal,
 * 9.15; fase 4.5). Hasta aquí solo se le terminaba por visto bueno: ninguna
 * causal de la LOSEP le aplica, y no tenía las suyas.
 *
 * - Las causales del Código del Trabajo, con su base legal: desahucio, acuerdo
 *   de las partes, conclusión de la obra, período de prueba, despido
 *   intempestivo, jubilación, retiro voluntario, muerte o incapacidad, caso
 *   fortuito.
 * - `fecha_presentacion_renuncia`: el desahucio se da con 15 días de aviso
 *   (CT 184).
 * - `actas_finiquito`: el acta se genera y se legaliza en el sistema del
 *   Ministerio del Trabajo; aquí se registra su número, su fecha y lo pagado,
 *   enlazada a la terminación.
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
        'fin_del_periodo', 'revocatoria_del_mandato', 'remocion_por_el_consejo',
        'terminacion_mutuo_acuerdo', 'terminacion_anticipada',
    ];

    private const CESACION_CT = [
        'desahucio', 'acuerdo_de_partes', 'conclusion_de_obra', 'prueba_no_superada_ct', 'despido_intempestivo',
        'jubilacion_ct', 'retiro_voluntario_ct', 'muerte_o_incapacidad', 'caso_fortuito',
    ];

    private const LICENCIA = [
        'asuntos_particulares', 'estudios_posgrado', 'servicio_militar', 'reemplazo_dignatario',
        'candidatura', 'cuidado_hijos', 'segun_su_regimen',
    ];

    private const SANCION = [
        'amonestacion_escrita', 'sancion_pecuniaria', 'suspension_sin_remuneracion', 'sancion_disciplinaria',
    ];

    public function up(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->date('fecha_presentacion_renuncia')->nullable()->after('fecha_consentimiento');
        });

        $this->subtipos([...self::CESACION, ...self::CESACION_CT]);

        Schema::create('actas_finiquito', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movimiento_personal_id')->unique()
                ->constrained('movimientos_personal')->restrictOnDelete();
            $table->string('numero', 100);
            $table->date('fecha');
            $table->decimal('valor_pagado', 12, 2);
            $table->string('documento_ruta')->nullable();
            $table->string('documento_nombre')->nullable();
            $table->text('observacion')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE actas_finiquito ADD CONSTRAINT actas_finiquito_valor_check CHECK (valor_pagado >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('actas_finiquito');

        DB::table('movimientos_personal')->whereIn('subtipo_movimiento', self::CESACION_CT)->delete();
        $this->subtipos(self::CESACION);

        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->dropColumn('fecha_presentacion_renuncia');
        });
    }

    /** @param  list<string>  $cesacion */
    private function subtipos(array $cesacion): void
    {
        $lista = fn (array $v) => implode(', ', array_map(fn (string $x) => "'{$x}'", $v));

        DB::statement('ALTER TABLE movimientos_personal DROP CONSTRAINT IF EXISTS movimientos_personal_subtipo_coherente_check');
        DB::statement("
            ALTER TABLE movimientos_personal
            ADD CONSTRAINT movimientos_personal_subtipo_coherente_check
            CHECK (
                subtipo_movimiento IS NULL
                OR (tipo_movimiento = 'cambio_administrativo' AND subtipo_movimiento IN ({$lista(self::CAMBIO_ADMINISTRATIVO)}))
                OR (tipo_movimiento = 'regimen_disciplinario' AND subtipo_movimiento IN ({$lista(self::SANCION)}))
                OR (tipo_movimiento = 'cesacion_funciones' AND subtipo_movimiento IN ({$lista($cesacion)}))
                OR (tipo_movimiento = 'licencia_sin_remuneracion' AND subtipo_movimiento IN ({$lista(self::LICENCIA)}))
            )
        ");
    }
};
