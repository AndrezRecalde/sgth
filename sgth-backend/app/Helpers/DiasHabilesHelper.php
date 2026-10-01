<?php

namespace App\Helpers;

use App\Models\Asistencia\FeriadoInstitucional;
use Carbon\Carbon;

trait DiasHabilesHelper
{
    /**
     * Los feriados, leídos una sola vez.
     *
     * Antes se consultaba la tabla UNA VEZ POR CADA DÍA que se sumaba, dentro
     * del bucle. Para un plazo de 10 días hábiles son once consultas, y el
     * control de plazos del sumario llama al método una vez por expediente:
     * con cincuenta sumarios con informe eran más de quinientas consultas para
     * leer una tabla de unas pocas docenas de filas.
     *
     * Se cargan enteros porque la tabla es pequeña y porque los feriados fijos
     * no tienen año: se guardan como mes y día, así que recortar por rango no
     * serviría de nada para ellos.
     *
     * @var array{fijos: array<string, true>, moviles: array<string, true>}|null
     */
    private ?array $feriadosEnMemoria = null;

    protected function calcularDiasHabiles(
        Carbon $fechaInicio,
        int $dias
    ): Carbon {
        $feriados = $this->feriados();

        $fecha = $fechaInicio->copy();
        $diasSumados = 0;

        while ($diasSumados < $dias) {
            $fecha->addDay();

            if ($fecha->isWeekend()) {
                continue;
            }

            if ($this->esFeriado($fecha, $feriados)) {
                continue;
            }

            $diasSumados++;
        }

        return $fecha;
    }

    /** @return array{fijos: array<string, true>, moviles: array<string, true>} */
    private function feriados(): array
    {
        if ($this->feriadosEnMemoria !== null) {
            return $this->feriadosEnMemoria;
        }

        $fijos = [];
        $moviles = [];

        FeriadoInstitucional::query()
            ->select(['mes', 'dia', 'fecha', 'es_movil'])
            ->get()
            ->each(function (FeriadoInstitucional $feriado) use (&$fijos, &$moviles) {
                if ($feriado->es_movil) {
                    // Un feriado móvil sin fecha no identifica ningún día.
                    if ($feriado->fecha) {
                        $moviles[$feriado->fecha->toDateString()] = true;
                    }

                    return;
                }

                $fijos[$feriado->mes.'-'.$feriado->dia] = true;
            });

        return $this->feriadosEnMemoria = ['fijos' => $fijos, 'moviles' => $moviles];
    }

    /**
     * La misma regla que el scope `FeriadoInstitucional::esFeriado()`: los
     * fijos valen todos los años y se comparan por mes y día; los móviles, por
     * la fecha exacta del año que les toca.
     *
     * @param  array{fijos: array<string, true>, moviles: array<string, true>}  $feriados
     */
    private function esFeriado(Carbon $fecha, array $feriados): bool
    {
        return isset($feriados['fijos'][$fecha->month.'-'.$fecha->day])
            || isset($feriados['moviles'][$fecha->toDateString()]);
    }
}
