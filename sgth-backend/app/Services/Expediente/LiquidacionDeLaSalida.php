<?php

namespace App\Services\Expediente;

use App\Enums\TipoNombramiento;
use App\Models\Asistencia\PeriodoVacacion;
use App\Models\Expediente\Desvinculacion;
use App\Services\Asistencia\PeriodoVacacionService;
use App\Services\Asistencia\TopeAcumulacionService;
use Carbon\CarbonImmutable;

/**
 * Los días de vacaciones no gozadas de la salida (fase 7.4b; Reglamento de la
 * LOSEP, Art. 31; CT Art. 71): el saldo de los períodos abiertos de los años
 * anteriores, y del año de la salida solo lo ganado —los días del período por
 * la fracción del año trabajada—, menos lo ya gozado de él. Los períodos se
 * generan con el año entero: tomar su saldo tal cual contaría días que no se
 * ganaron. Con el tope de acumulación del régimen, el mismo que vence días en
 * Asistencia.
 *
 * Son días, no dinero: el SGTH es informativo desde el 2026-10-20, y su valor
 * lo calcula Financiero. Tampoco calcula ya el tope de la indemnización del
 * Mandato 2: la indemnización se registra como lo que pagó Financiero.
 */
class LiquidacionDeLaSalida
{
    public function __construct(
        private readonly PeriodoVacacionService $periodos,
        private readonly TopeAcumulacionService $topes,
    ) {
    }

    /**
     * @return array{periodos: list<array{anio: int, dias: float}>,
     *               proporcional: array{anio: int, generados: float, trabajados: int, dias_del_anio: int, ganados: float, utilizados: float, dias: float},
     *               total: float, tope: ?float, dias: float}
     */
    public function vacaciones(Desvinculacion $desvinculacion): array
    {
        $desvinculacion->loadMissing(['servidor', 'contrato']);
        $servidor = $desvinculacion->servidor;
        $salida = CarbonImmutable::parse($desvinculacion->fecha_salida->toDateString());
        $anio = $salida->year;

        $anteriores = PeriodoVacacion::where('servidor_id', $servidor->id)
            ->where('estado', 'abierto')
            ->where('anio', '<', $anio)
            ->orderBy('anio')
            ->get(['anio', 'dias_saldo'])
            ->map(fn (PeriodoVacacion $p) => ['anio' => (int) $p->anio, 'dias' => round((float) $p->dias_saldo, 2)])
            ->filter(fn (array $p) => $p['dias'] > 0)
            ->values()
            ->all();

        // Del año de la salida, lo ganado hasta ese día.
        $actual = PeriodoVacacion::where('servidor_id', $servidor->id)->where('anio', $anio)->first();
        $regimen = $desvinculacion->contrato?->tipo_nombramiento === TipoNombramiento::CODIGO_TRABAJO ? 'codigo_trabajo' : 'losep';
        $generados = $actual
            ? (float) $actual->dias_generados
            : $this->periodos->calcularDiasGenerados($regimen, $this->periodos->calcularAntiguedad($servidor, $regimen, $anio), $anio);

        $inicioDelAnio = CarbonImmutable::create($anio, 1, 1);
        $inicioDelServicio = $servidor->inicioDelServicioContinuo();
        $desde = $inicioDelServicio && CarbonImmutable::parse($inicioDelServicio)->gt($inicioDelAnio)
            ? CarbonImmutable::parse($inicioDelServicio)
            : $inicioDelAnio;
        $diasDelAnio = $inicioDelAnio->isLeapYear() ? 366 : 365;
        $trabajados = $salida->lt($desde) ? 0 : (int) $desde->diffInDays($salida) + 1;
        $ganados = round($generados * $trabajados / $diasDelAnio, 2);
        $utilizados = round((float) ($actual?->dias_utilizados ?? 0), 2);
        $proporcional = max(0.0, round($ganados - $utilizados, 2));

        $total = round(array_sum(array_column($anteriores, 'dias')) + $proporcional, 2);
        $tope = $this->topes->topePara($servidor);
        $dias = $tope !== null ? min($total, $tope) : $total;

        return [
            'periodos'     => $anteriores,
            'proporcional' => [
                'anio'          => $anio,
                'generados'     => round($generados, 2),
                'trabajados'    => $trabajados,
                'dias_del_anio' => $diasDelAnio,
                'ganados'       => $ganados,
                'utilizados'    => $utilizados,
                'dias'          => $proporcional,
            ],
            'total'        => $total,
            'tope'         => $tope,
            'dias'         => round($dias, 2),
        ];
    }
}
