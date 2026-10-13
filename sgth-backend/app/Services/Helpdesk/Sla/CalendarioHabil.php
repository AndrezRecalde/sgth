<?php

namespace App\Services\Helpdesk\Sla;

use App\Helpers\DiasHabilesHelper;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Las horas hábiles de TIC (fase 3.2): de lunes a viernes, en la jornada de
 * `config('helpdesk.jornada')`, sin los feriados institucionales. Un ticket
 * abierto un viernes a las 16:30 con un SLA de 2 horas vence el lunes a las
 * 09:30, no el sábado.
 */
final class CalendarioHabil
{
    use DiasHabilesHelper;

    /** Límite de días que recorren los cálculos: un SLA no pasa de unas semanas. */
    private const MAXIMO_DIAS = 400;

    /** El momento en que se cumplen `$horas` hábiles contadas desde `$desde`. */
    public function sumarHoras(CarbonInterface $desde, float $horas): Carbon
    {
        $restantes = (int) round($horas * 60);
        $cursor = $this->aJornada(Carbon::instance($desde));

        for ($i = 0; $i < self::MAXIMO_DIAS; $i++) {
            $disponibles = (int) $cursor->diffInMinutes($this->fin($cursor), true);
            if ($restantes <= $disponibles) {
                return $cursor->copy()->addMinutes($restantes);
            }
            $restantes -= $disponibles;
            $cursor = $this->aJornada($cursor->copy()->addDay()->startOfDay());
        }

        return $cursor;
    }

    /** Las horas hábiles entre dos momentos (0 si `$hasta` es anterior). */
    public function horasEntre(CarbonInterface $desde, CarbonInterface $hasta): float
    {
        if ($hasta->lte($desde)) {
            return 0.0;
        }

        $minutos = 0;
        $cursor = $this->aJornada(Carbon::instance($desde));
        for ($i = 0; $i < self::MAXIMO_DIAS && $cursor->lt($hasta); $i++) {
            $corte = $this->fin($cursor)->min($hasta);
            if ($corte->gt($cursor)) {
                $minutos += (int) $cursor->diffInMinutes($corte, true);
            }
            $cursor = $this->aJornada($cursor->copy()->addDay()->startOfDay());
        }

        return round($minutos / 60, 2);
    }

    /** El primer momento hábil desde `$momento`: si cae fuera de la jornada, el inicio de la siguiente. */
    private function aJornada(Carbon $momento): Carbon
    {
        for ($i = 0; $i < self::MAXIMO_DIAS; $i++) {
            if ($this->esDiaHabil($momento)) {
                if ($momento->lt($this->inicio($momento))) {
                    return $this->inicio($momento);
                }
                if ($momento->lt($this->fin($momento))) {
                    return $momento;
                }
            }
            $momento = $this->inicio($momento->copy()->addDay());
        }

        return $momento;
    }

    private function esDiaHabil(Carbon $dia): bool
    {
        return ! $dia->isWeekend() && ! $this->esFeriado($dia, $this->feriados());
    }

    private function inicio(Carbon $dia): Carbon
    {
        return $dia->copy()->setTimeFromTimeString((string) config('helpdesk.jornada.inicio', '08:00'));
    }

    private function fin(Carbon $dia): Carbon
    {
        return $dia->copy()->setTimeFromTimeString((string) config('helpdesk.jornada.fin', '17:00'));
    }

    /** Pasados N días hábiles desde `$desde` (para el cierre automático). */
    public function sumarDias(CarbonInterface $desde, int $dias): Carbon
    {
        return $this->calcularDiasHabiles(Carbon::instance($desde), $dias);
    }
}
