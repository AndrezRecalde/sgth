<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El inicio del período de una autoridad, con su causal (TH, 2026-10-19):
 * por elección, con la credencial del CNE, o por designación del Consejo
 * Provincial, de una terna del Prefecto, cuando falta el Viceprefecto (COOTAD
 * Art. 47 n). La designación lleva la resolución del Consejo y termina con el
 * período del Prefecto.
 *
 * Los inicios ya registrados fueron todos por elección: se les pone esa causal,
 * por la base, sin pasar por la guarda de inmutabilidad del modelo, que no
 * dejaría tocar un acto registrado.
 */
return new class extends Migration
{
    /** Los subtipos de cada tipo que los tiene, como estaban (2026_10_12_110000). */
    private const SUBTIPOS = [
        'cambio_administrativo' => ['traslado_administrativo', 'traspaso', 'comision_con_remuneracion', 'comision_sin_remuneracion'],
        'regimen_disciplinario' => ['amonestacion_escrita', 'sancion_pecuniaria', 'suspension_sin_remuneracion', 'sancion_disciplinaria'],
        'cesacion_funciones'    => [
            'renuncia', 'destitucion', 'jubilacion', 'incapacidad', 'contrato_finalizado', 'visto_bueno', 'remocion',
            'periodo_prueba_no_superado', 'fin_del_plazo', 'terminacion_unilateral', 'mutuo_acuerdo',
            'evaluacion_insuficiente', 'retiro_voluntario', 'perdida_derechos_ciudadania', 'fallecimiento',
            'fin_del_periodo', 'revocatoria_del_mandato', 'remocion_por_el_consejo', 'terminacion_mutuo_acuerdo',
            'terminacion_anticipada', 'desahucio', 'acuerdo_de_partes', 'conclusion_de_obra', 'prueba_no_superada_ct',
            'despido_intempestivo', 'jubilacion_ct', 'retiro_voluntario_ct', 'muerte_o_incapacidad', 'caso_fortuito',
        ],
        'licencia_sin_remuneracion' => [
            'asuntos_particulares', 'estudios_posgrado', 'servicio_militar', 'reemplazo_dignatario', 'candidatura',
            'cuidado_hijos', 'segun_su_regimen',
        ],
        'revision_clasificacion' => ['revalorizacion', 'reclasificacion', 'ubicacion'],
    ];

    private const DEL_INICIO = ['eleccion', 'designacion_del_consejo'];

    public function up(): void
    {
        $this->coherencia([...self::SUBTIPOS, 'inicio_periodo' => self::DEL_INICIO]);

        DB::table('movimientos_personal')
            ->where('tipo_movimiento', 'inicio_periodo')
            ->whereNull('subtipo_movimiento')
            ->update(['subtipo_movimiento' => 'eleccion']);
    }

    public function down(): void
    {
        DB::table('movimientos_personal')
            ->where('tipo_movimiento', 'inicio_periodo')
            ->where('subtipo_movimiento', 'eleccion')
            ->update(['subtipo_movimiento' => null]);

        // Con designaciones registradas, esto falla a propósito: no se borran actos.
        $this->coherencia(self::SUBTIPOS);
    }

    /** @param  array<string, list<string>>  $subtipos */
    private function coherencia(array $subtipos): void
    {
        $lista = fn (array $v) => implode(', ', array_map(fn (string $x) => "'{$x}'", $v));

        $coherentes = implode(' OR ', array_map(
            fn (string $tipo, array $suyos) => "(tipo_movimiento = '{$tipo}' AND subtipo_movimiento IN ({$lista($suyos)}))",
            array_keys($subtipos),
            $subtipos,
        ));

        DB::statement('ALTER TABLE movimientos_personal DROP CONSTRAINT IF EXISTS movimientos_personal_subtipo_coherente_check');
        DB::statement('ALTER TABLE movimientos_personal ADD CONSTRAINT movimientos_personal_subtipo_coherente_check '
            ."CHECK (subtipo_movimiento IS NULL OR {$coherentes})");
    }
};
