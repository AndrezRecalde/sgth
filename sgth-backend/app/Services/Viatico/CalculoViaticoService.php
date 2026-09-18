<?php

namespace App\Services\Viatico;

use App\Exceptions\ReglaNegocioException;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\Viatico\LiquidacionViatico;
use App\Models\Viatico\TarifaViatico;
use App\Models\Viatico\Viatico;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * La fórmula del viático, en un solo lugar.
 *
 * Estaba repartida en seis: el servicio, el controlador, el de estados, tres
 * plantillas de PDF y cuatro componentes del frontend, cada uno con su propia
 * versión —y ninguna coincidía con lo que aplica Gestión Financiera—.
 *
 * Reglas confirmadas por Financiero el 2026-09-15:
 *
 * - Se pagan las noches de pernocte. El día de regreso no se paga, y un viaje
 *   que no obliga a dormir fuera no genera viático: no existe la subsistencia.
 * - El servidor justifica con comprobantes el 70 % de lo que le corresponde.
 *   Cuentan el hospedaje, la alimentación y la movilización, sin tope propio.
 * - El 30 % restante se le reconoce siempre, sin comprobante.
 * - Lo que exceda del 70 % corre por su cuenta.
 * - El saldo es uno solo: si sale positivo se le paga, y si sale negativo
 *   devuelve. Con anticipo o sin él, la cuenta es la misma.
 * - Es autoridad solo el Prefecto o Prefecta, y para el exterior, dignatario.
 */
final class CalculoViaticoService
{
    /** Parte del viático que se entrega como anticipo y que hay que justificar. */
    public const PORCENTAJE_JUSTIFICABLE = 0.70;

    /** Parte que se reconoce sin comprobante. */
    public const PORCENTAJE_LIBRE = 0.30;

    /**
     * Noches fuera: los días que el servidor duerme en comisión.
     *
     * Solo cuentan las fechas, no las horas. De un lunes a las 08:00 hasta el
     * miércoles a las 18:00 hay dos noches, así que son dos días de viático y
     * no tres.
     */
    public function noches(CarbonInterface $salida, CarbonInterface $llegada): int
    {
        return (int) $salida->copy()->startOfDay()
            ->diffInDays($llegada->copy()->startOfDay());
    }

    /**
     * Sin noches no hay viático que pagar, así que tampoco hay nada que
     * solicitar. Lo comprueban la solicitud y la edición de fechas.
     */
    public function asegurarPernocte(CarbonInterface $salida, CarbonInterface $llegada): void
    {
        if ($this->noches($salida, $llegada) < 1) {
            throw new ReglaNegocioException(
                'Una comisión que empieza y termina el mismo día no genera viático: '
                    .'debe haber al menos una noche fuera.'
            );
        }
    }

    /**
     * Autoridad es solo quien ocupa la jefatura de la unidad marcada como
     * máxima autoridad: el Prefecto o la Prefecta.
     *
     * Antes se decidía buscando «director», «coordinador» o «secretario» en el
     * nombre del cargo, de modo que una docena de cargos cobraba la tarifa de
     * autoridad. El organigrama ya sabe quién es quién.
     */
    public function nivel(?Servidor $servidor): string
    {
        $puesto = $servidor?->puesto;

        if (! $puesto?->es_jefe) {
            return 'servidor';
        }

        $unidadId = UnidadAdministrativa::where('es_maxima_autoridad', true)->value('id');

        return $unidadId && (int) $puesto->unidad_administrativa_id === (int) $unidadId
            ? 'autoridad'
            : 'servidor';
    }

    /** Tarifa diaria del catálogo, o null si esa combinación no está sembrada. */
    public function tarifaDiariaSiExiste(string $zona, string $nivel): ?float
    {
        $tarifa = TarifaViatico::where('zona', $zona)
            ->where('nivel', $nivel)
            ->where('tipo_tarifa', 'con_pernocte')
            ->value('valor_diario');

        return $tarifa === null ? null : (float) $tarifa;
    }

    /** Tarifa diaria del catálogo, la única fuente de los valores. */
    public function tarifaDiaria(string $zona, string $nivel): float
    {
        $tarifa = $this->tarifaDiariaSiExiste($zona, $nivel);

        if ($tarifa === null) {
            throw new ReglaNegocioException(
                "No hay tarifa registrada para zona {$zona} y nivel {$nivel}. "
                    .'Revise el catálogo de tarifas de viático.'
            );
        }

        return $tarifa;
    }

    /**
     * Lo que le corresponde al servidor por el viaje.
     *
     * En el exterior la tarifa se multiplica por el coeficiente del país, que
     * Financiero ingresa al aprobar; mientras no lo haga, el viático vale 0.
     */
    public function derecho(
        ?Servidor $servidor,
        string $zona,
        int $noches,
        ?float $coeficiente = null
    ): float {
        if ($noches < 1) {
            return 0.00;
        }

        $diaria = $this->tarifaDiaria($zona, $this->nivel($servidor));

        if ($zona === 'exterior') {
            return $coeficiente > 0
                ? round($diaria * $coeficiente * $noches, 2)
                : 0.00;
        }

        return round($diaria * $noches, 2);
    }

    /** El derecho de un viático ya registrado, recalculado con sus datos. */
    public function derechoDe(Viatico $viatico): float
    {
        return $this->derecho(
            $viatico->servidor()->with('puesto')->first(),
            $this->valor($viatico->zona),
            $this->noches(
                Carbon::parse($viatico->datetime_salida),
                Carbon::parse($viatico->datetime_llegada)
            ),
            $viatico->coeficiente_exterior !== null
                ? (float) $viatico->coeficiente_exterior
                : null
        );
    }

    /**
     * La cuenta completa del viático, tal como la lee el servidor, la imprime
     * el comprobante y la revisa Financiero.
     *
     * @return array{
     *     noches: int, tarifa_diaria: float|null, derecho: float,
     *     tope_justificable: float, reconocido_sin_comprobante: float,
     *     total_comprobantes: float, justificado: float, excedente: float,
     *     reconocido: float, anticipo: float, saldo: float
     * }
     */
    public function resumen(Viatico $viatico, ?LiquidacionViatico $liquidacion = null): array
    {
        $derecho = (float) ($viatico->monto_calculado ?? 0);
        $tope    = round($derecho * self::PORCENTAJE_JUSTIFICABLE, 2);
        $libre   = round($derecho - $tope, 2);

        $liquidacion ??= $viatico->liquidacion;

        $comprobantes = $liquidacion
            ? (float) $liquidacion->detallesFactura()->sum('monto')
            : 0.00;

        $justificado = round(min($comprobantes, $tope), 2);
        $reconocido  = round($justificado + $libre, 2);
        $anticipo    = (float) ($viatico->monto_anticipo ?? 0);

        return [
            'noches'                     => (int) $viatico->noches,
            // La usa el modal del exterior para anticipar el monto con el
            // coeficiente antes de aprobar; tenía las tarifas escritas a mano.
            'tarifa_diaria'              => $this->tarifaDiariaSiExiste(
                $this->valor($viatico->zona),
                $this->nivel($viatico->servidor)
            ),
            'derecho'                    => $derecho,
            'tope_justificable'          => $tope,
            'reconocido_sin_comprobante' => $libre,
            'total_comprobantes'         => round($comprobantes, 2),
            'justificado'                => $justificado,
            'excedente'                  => round(max($comprobantes - $tope, 0), 2),
            'reconocido'                 => $reconocido,
            'anticipo'                   => $anticipo,
            'saldo'                      => round($reconocido - $anticipo, 2),
        ];
    }

    /**
     * Guarda en la liquidación el resultado del cálculo. Lo llaman el registro
     * de comprobantes y la confirmación, para que el PDF y la bandeja lean lo
     * mismo que la pantalla.
     */
    public function guardarEn(LiquidacionViatico $liquidacion, ?Viatico $viatico = null): array
    {
        $viatico ??= $liquidacion->viatico;
        $resumen   = $this->resumen($viatico, $liquidacion);

        $liquidacion->update([
            'total_facturas'    => $resumen['total_comprobantes'],
            'total_justificado' => $resumen['justificado'],
            'monto_reconocido'  => $resumen['reconocido'],
            'saldo'             => $resumen['saldo'],
        ]);

        return $resumen;
    }

    private function valor(mixed $valor): string
    {
        return $valor instanceof \BackedEnum ? (string) $valor->value : (string) $valor;
    }
}
