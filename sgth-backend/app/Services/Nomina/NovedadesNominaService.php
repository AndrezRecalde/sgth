<?php

namespace App\Services\Nomina;

use App\Enums\ConceptoNovedadNomina;
use App\Exceptions\ReglaNegocioException;
use App\Exports\NovedadesNominaExport;
use App\Mail\Nomina\NovedadesNominaMail;
use App\Models\Nomina\EntregaNovedadesNomina;
use App\Models\Nomina\NovedadNomina;
use App\Models\User;
use App\Services\Disciplinario\AvisoFinancieroSancionService;
use App\Services\Estructura\JefeFinanciero;
use App\Services\Expediente\OcupantesDelPuesto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Excel as FormatoExcel;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Las novedades para nómina, mes por mes (diseño, 9.8; fase 7.3a).
 *
 * Financiero paga en su propio sistema (decisión de TH, 2026-10-04). Cada mes
 * Talento Humano revisa lo que el SGTH deriva de los actos y se lo entrega: la
 * entrega congela el mes y le envía el Excel al jefe de Gestión Financiera.
 *
 * Lo que cambie después en un mes ya entregado —un reintegro que acorta la
 * licencia, un acto que se anula, un incremento con fecha anterior— no
 * reescribe lo entregado: se entrega como ajuste en el mes siguiente, por la
 * diferencia entre lo que hoy resulta y lo que ya se dio. Los meses que nunca
 * se entregaron, como los anteriores a empezar a usarlo, no generan ajustes.
 *
 * @phpstan-import-type Novedad from NovedadesDelMes
 */
class NovedadesNominaService
{
    public function __construct(
        private readonly NovedadesDelMes $derivacion,
        private readonly JefeFinanciero $jefeFinanciero,
    ) {
    }

    /** El mes como lo ve la pantalla. */
    public function delMes(MesDeNomina $mes): array
    {
        $entrega = EntregaNovedadesNomina::with('entregadaPor:id,usuario_ti,servidor_id', 'entregadaPor.servidor:id,nombre,apellido')
            ->where('periodo', $mes->periodo)
            ->first();

        if ($entrega) {
            $filas = $entrega->filas()
                ->with('servidor:id,nombre,segundo_nombre,apellido,segundo_apellido,cedula')
                ->orderBy('id')
                ->get()
                ->map(fn (NovedadNomina $fila) => $this->deLaFila($fila));

            $novedades = $filas->where('es_ajuste', false)->values();
            $ajustes = $filas->where('es_ajuste', true)->values();
            // Lo que cambió en este mes desde que se entregó: va como ajuste en
            // el próximo mes que se entregue.
            $cambios = $this->diferencias($mes);
        } else {
            $novedades = $this->derivacion->de($mes);
            $ajustes = $this->ajustesPara($mes);
            $cambios = collect();
        }

        return [
            'periodo'                 => $mes->periodo,
            'etiqueta'                => $mes->etiqueta(),
            'entrega'                 => $entrega ? $this->resumenDeEntrega($entrega) : null,
            'puede_entregarse'        => ! $entrega && $mes->periodo <= MesDeNomina::actual()->periodo,
            'novedades'               => $novedades->map(fn (array $n) => $this->paraLaRespuesta($n))->all(),
            'ajustes'                 => $ajustes->map(fn (array $n) => $this->paraLaRespuesta($n))->all(),
            'cambios_tras_la_entrega' => $cambios->map(fn (array $n) => $this->paraLaRespuesta($n))->all(),
            'resumen'                 => $this->resumen($novedades->merge($ajustes)),
        ];
    }

    /**
     * Congela el mes y se lo envía a Financiero. Una sola vez: después, lo que
     * cambie va como ajuste en el mes siguiente.
     */
    public function entregar(MesDeNomina $mes, User $usuario): EntregaNovedadesNomina
    {
        if ($mes->periodo > MesDeNomina::actual()->periodo) {
            throw new ReglaNegocioException(
                "No se puede entregar {$mes->etiqueta()} todavía: los actos de un mes que no empieza aún pueden cambiar."
            );
        }

        $entrega = DB::transaction(function () use ($mes, $usuario) {
            if (EntregaNovedadesNomina::where('periodo', $mes->periodo)->lockForUpdate()->exists()) {
                throw new ReglaNegocioException(
                    "Las novedades de {$mes->etiqueta()} ya se entregaron: lo que cambie va como ajuste en el mes siguiente."
                );
            }

            $novedades = $this->derivacion->de($mes);
            $ajustes = $this->ajustesPara($mes);

            $entrega = EntregaNovedadesNomina::create([
                'periodo'       => $mes->periodo,
                'entregada_por' => $usuario->id,
                'entregada_en'  => now(),
                'novedades'     => $novedades->count(),
                'ajustes'       => $ajustes->count(),
            ]);

            foreach ($novedades->merge($ajustes) as $novedad) {
                NovedadNomina::create([
                    'entrega_id'     => $entrega->id,
                    'mes_afectado'   => $novedad['mes_afectado'],
                    'es_ajuste'      => $novedad['es_ajuste'],
                    'vigente'        => $novedad['vigente'] ?? true,
                    'clave'          => $novedad['clave'],
                    'concepto'       => $novedad['concepto'],
                    'servidor_id'    => $novedad['servidor_id'],
                    'contrato_id'    => $novedad['contrato_id'],
                    'movimiento_id'  => $novedad['movimiento_id'],
                    'subrogacion_id' => $novedad['subrogacion_id'],
                    'acto'           => $novedad['acto'],
                    'desde'          => $novedad['desde'],
                    'hasta'          => $novedad['hasta'],
                    'dias'           => $novedad['dias'],
                    'base'           => $novedad['base'],
                    'monto'          => $novedad['monto'],
                    'detalle'        => $novedad['detalle'],
                ]);
            }

            return $entrega;
        });

        $this->avisarAFinanciero($entrega, $mes);

        return $entrega->fresh();
    }

    /** Las filas del Excel: las novedades y los ajustes del mes. */
    public function filasDelExcel(MesDeNomina $mes): Collection
    {
        $vista = $this->delMes($mes);

        return collect($vista['novedades'])->merge($vista['ajustes'])
            ->map(fn (array $n) => [
                'MES'               => $mes->periodo,
                'TIPO'              => $n['es_ajuste'] ? 'Ajuste de '.MesDeNomina::de($n['mes_afectado'])->etiqueta() : 'Novedad',
                'CÉDULA'            => $n['cedula'],
                'SERVIDOR'          => $n['servidor'],
                'NOVEDAD'           => $n['concepto_etiqueta'],
                'DETALLE'           => $n['detalle'],
                'DESDE'             => $n['desde'],
                'HASTA'             => $n['hasta'],
                'DÍAS'              => $n['dias'],
                'BASE'              => $n['base'],
                'MONTO REFERENCIAL' => $n['monto'],
                'ACTO'              => $n['acto'],
            ]);
    }

    // ── Los ajustes ─────────────────────────────────────────────────

    /**
     * Lo que hay que corregir de los meses ya entregados antes de este.
     *
     * @return Collection<int, Novedad>
     */
    private function ajustesPara(MesDeNomina $mes): Collection
    {
        return EntregaNovedadesNomina::where('periodo', '<', $mes->periodo)
            ->orderBy('periodo')
            ->pluck('periodo')
            ->flatMap(fn (string $periodo) => $this->diferencias(MesDeNomina::de($periodo)))
            ->values();
    }

    /**
     * Lo que hoy resulta para un mes entregado, menos lo que ya se entregó de
     * él: en su entrega y en los ajustes que le siguieron. Cada fila guarda si
     * la novedad seguía en pie después de ella (`vigente`), para que la que ya
     * se revirtió no se revierta otra vez cada mes.
     *
     * @return Collection<int, Novedad>
     */
    private function diferencias(MesDeNomina $mes): Collection
    {
        $hoy = $this->derivacion->de($mes)->keyBy('clave');

        $entregado = NovedadNomina::where('mes_afectado', $mes->periodo)
            ->with('servidor:id,nombre,segundo_nombre,apellido,segundo_apellido,cedula')
            ->orderBy('id')
            ->get()
            ->groupBy('clave');

        $ajustes = collect();

        foreach ($hoy->keys()->merge($entregado->keys())->unique() as $clave) {
            $actual = $hoy->get($clave);
            $filas = $entregado->get($clave, collect());
            $enPie = (bool) $filas->last()?->vigente;

            if ($actual === null && ! $enPie) {
                continue;
            }

            $monto = $this->resta($actual['monto'] ?? null, $filas->pluck('monto'), 2);
            $dias = $this->resta($actual['dias'] ?? null, $filas->pluck('dias'), 0);

            // Sigue igual que como se entregó.
            if ($actual !== null && $enPie
                && ($monto === null || abs($monto) < 0.005) && ($dias === null || $dias == 0)
            ) {
                continue;
            }

            $referencia = $actual ?? $this->deLaFila($filas->last());

            $ajustes->push([
                ...$referencia,
                'mes_afectado' => $mes->periodo,
                'es_ajuste'    => true,
                'vigente'      => $actual !== null,
                'dias'         => $dias !== null ? (int) $dias : null,
                'monto'        => $monto,
                'detalle'      => "Ajuste de {$mes->etiqueta()}: ".($actual !== null
                    ? $actual['detalle']
                    : 'ya no corresponde, porque el acto se anuló o cambió. Se había entregado: '.$this->deLaFila($filas->first())['detalle']),
            ]);
        }

        return $ajustes->values();
    }

    /**
     * Lo de hoy menos lo entregado. Null si ninguno de los dos tiene la cifra:
     * una novedad sin monto —falta la remuneración en el acto— no se inventa uno.
     *
     * @param  Collection<int, mixed>  $entregado
     */
    private function resta(mixed $hoy, Collection $entregado, int $decimales): float|int|null
    {
        $cifras = $entregado->filter(fn ($v) => $v !== null);

        if ($hoy === null && $cifras->isEmpty()) {
            return null;
        }

        $resta = (float) ($hoy ?? 0) - (float) $cifras->sum(fn ($v) => (float) $v);

        return $decimales === 0 ? (int) round($resta) : round($resta, $decimales);
    }

    // ── El aviso ────────────────────────────────────────────────────

    /**
     * El correo al jefe de Gestión Financiera, con el Excel. Como el de la
     * multa: si no hay a quién o falla, la entrega queda hecha y la pantalla
     * dice que hay que enviarlo a mano.
     */
    private function avisarAFinanciero(EntregaNovedadesNomina $entrega, MesDeNomina $mes): void
    {
        $direccion = $this->jefeFinanciero->direccion();

        if (! $direccion) {
            Log::warning('Novedades para nómina sin destinatario: la unidad financiera no tiene jefe con usuario', [
                'periodo' => $mes->periodo,
            ]);
            $entrega->update(['aviso' => AvisoFinancieroSancionService::SIN_DESTINATARIO]);

            return;
        }

        try {
            $vista = $this->delMes($mes);
            $excel = Excel::raw(new NovedadesNominaExport($this->filasDelExcel($mes)), FormatoExcel::XLSX);

            Mail::to($direccion)->send(new NovedadesNominaMail(
                $mes->etiqueta(),
                $vista['resumen'],
                count($vista['novedades']),
                count($vista['ajustes']),
                $excel,
                "novedades_nomina_{$mes->periodo}.xlsx",
            ));

            $entrega->update(['destinatario' => $direccion, 'aviso' => AvisoFinancieroSancionService::ENVIADO]);
        } catch (\Throwable $e) {
            Log::error('No salió el correo de las novedades para nómina', [
                'periodo' => $mes->periodo,
                'motivo'  => $e->getMessage(),
            ]);
            $entrega->update(['destinatario' => $direccion, 'aviso' => AvisoFinancieroSancionService::FALLO_ENVIO]);
        }
    }

    // ── Las formas ──────────────────────────────────────────────────

    /** @return Novedad */
    private function deLaFila(NovedadNomina $fila): array
    {
        return [
            'clave'          => $fila->clave,
            'concepto'       => $fila->concepto,
            'mes_afectado'   => $fila->mes_afectado,
            'es_ajuste'      => $fila->es_ajuste,
            'vigente'        => $fila->vigente,
            'servidor_id'    => $fila->servidor_id,
            'servidor'       => OcupantesDelPuesto::nombre($fila->servidor),
            'cedula'         => $fila->servidor?->cedula,
            'contrato_id'    => $fila->contrato_id,
            'movimiento_id'  => $fila->movimiento_id,
            'subrogacion_id' => $fila->subrogacion_id,
            'acto'           => $fila->acto,
            'desde'          => $fila->desde?->toDateString(),
            'hasta'          => $fila->hasta?->toDateString(),
            'dias'           => $fila->dias,
            'base'           => $fila->base !== null ? (float) $fila->base : null,
            'monto'          => $fila->monto !== null ? (float) $fila->monto : null,
            'detalle'        => $fila->detalle,
        ];
    }

    /** @param Novedad $novedad */
    private function paraLaRespuesta(array $novedad): array
    {
        return [
            ...$novedad,
            'concepto'          => $novedad['concepto']->value,
            'concepto_etiqueta' => $novedad['concepto']->etiqueta(),
        ];
    }

    /**
     * Cuántas hay de cada concepto, y lo que suman.
     *
     * @param  Collection<int, Novedad>  $novedades
     * @return list<array{concepto: string, etiqueta: string, cantidad: int, monto: float}>
     */
    private function resumen(Collection $novedades): array
    {
        return collect(ConceptoNovedadNomina::cases())
            ->map(fn (ConceptoNovedadNomina $c) => [
                'concepto' => $c->value,
                'etiqueta' => $c->etiqueta(),
                'cantidad' => $novedades->where('concepto', $c)->count(),
                'monto'    => round((float) $novedades->where('concepto', $c)->sum(fn (array $n) => (float) ($n['monto'] ?? 0)), 2),
            ])
            ->filter(fn (array $r) => $r['cantidad'] > 0)
            ->values()
            ->all();
    }

    private function resumenDeEntrega(EntregaNovedadesNomina $entrega): array
    {
        $servidor = $entrega->entregadaPor?->servidor;

        return [
            'entregada_en' => $entrega->entregada_en?->toIso8601String(),
            'entregada_por' => $servidor ? trim("{$servidor->nombre} {$servidor->apellido}") : $entrega->entregadaPor?->usuario_ti,
            'novedades'    => $entrega->novedades,
            'ajustes'      => $entrega->ajustes,
            'destinatario' => $entrega->destinatario,
            'aviso'        => $entrega->aviso,
        ];
    }
}
