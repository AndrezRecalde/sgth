<?php

namespace App\Services\Expediente;

use App\Enums\TipoNombramiento;
use App\Models\Asistencia\PeriodoVacacion;
use App\Models\Expediente\Desvinculacion;
use App\Models\Expediente\SalarioBasico;
use App\Services\Asistencia\PeriodoVacacionService;
use App\Services\Asistencia\TopeAcumulacionService;
use Carbon\CarbonImmutable;

/**
 * Lo que la salida paga, como referencia (fase 7.4b). El valor definitivo lo
 * establece Financiero.
 *
 * - Vacaciones no gozadas (LOSEP Art. 29; CT Art. 71): el saldo de los
 *   períodos abiertos de los años anteriores, y del año de la salida solo lo
 *   ganado —los días del período por la fracción del año trabajada—, menos lo
 *   ya gozado de él. Los períodos se generan con el año entero: tomar su saldo
 *   tal cual pagaría días que no se ganaron. Con el tope de acumulación del
 *   régimen, el mismo que vence días en Asistencia, y valoradas a la
 *   remuneración del vínculo ÷ 30.
 * - El tope de la indemnización (Mandato Constituyente 2, art. 8): 7 salarios
 *   básicos por año de servicio continuo, hasta 210, con el salario básico
 *   del año de la salida.
 *
 * [Pendiente: Talento Humano y Financiero confirman si se paga lo
 * proporcional y si la regla del obrero es esta.]
 */
class LiquidacionDeLaSalida
{
    public const SALARIOS_POR_ANIO = 7;
    public const SALARIOS_MAXIMOS = 210;

    public function __construct(
        private readonly PeriodoVacacionService $periodos,
        private readonly TopeAcumulacionService $topes,
    ) {
    }

    /**
     * @return array{periodos: list<array{anio: int, dias: float}>,
     *               proporcional: array{anio: int, generados: float, trabajados: int, dias_del_anio: int, ganados: float, utilizados: float, dias: float},
     *               total: float, tope: ?float, dias: float, remuneracion: ?float, monto: ?float}
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
        $remuneracion = $desvinculacion->contrato?->remuneracion !== null ? (float) $desvinculacion->contrato->remuneracion : null;

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
            'remuneracion' => $remuneracion,
            'monto'        => $remuneracion !== null ? round($dias * $remuneracion / 30, 2) : null,
        ];
    }

    /**
     * El tope del Mandato 2 para esta salida. Sin el salario básico del año no
     * hay tope que calcular: se carga en el contrato colectivo (fase 4.6).
     *
     * @return array{salario_basico: ?float, anios: ?float, tope: ?float}
     */
    public function topeDeLaIndemnizacion(Desvinculacion $desvinculacion): array
    {
        $desvinculacion->loadMissing('servidor');
        $salida = CarbonImmutable::parse($desvinculacion->fecha_salida->toDateString());
        $basico = SalarioBasico::delAnio($salida->year);
        $inicio = $desvinculacion->servidor?->inicioDelServicioContinuo();
        $anios = $inicio ? round(CarbonImmutable::parse($inicio)->diffInDays($salida->addDay()) / 365, 2) : null;

        return [
            'salario_basico' => $basico,
            'anios'          => $anios,
            'tope'           => $basico !== null && $anios !== null
                ? round(min(self::SALARIOS_POR_ANIO * $basico * $anios, self::SALARIOS_MAXIMOS * $basico), 2)
                : null,
        ];
    }
}
