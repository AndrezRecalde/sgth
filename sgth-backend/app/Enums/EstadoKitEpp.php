<?php

namespace App\Enums;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * En qué punto está un equipo del kit que un puesto requiere, para el servidor
 * que lo ocupa.
 *
 * El modal «Entregar kit completo» pedía el kit del puesto y premarcaba TODO,
 * siempre. No sabía nada de lo ya entregado: entregar el mismo kit dos veces
 * creaba filas duplicadas en `epp_entregas` sin un aviso, y la comprobación
 * manual que `docs/pendientes-sso.md` dejó pendiente —«los equipos entregados
 * ya no deben aparecer pendientes»— no estaba implementada por ninguna parte.
 *
 * No basta con desmarcar lo entregado alguna vez: un par de botas de hace tres
 * años se quedaría desmarcado para siempre, y quien entrega tendría que
 * acordarse de volver a marcarlo. El EPP se repone, y cuándo toca reponerlo ya
 * estaba en la base y no lo leía nadie: `puesto_epp.frecuencia_reposicion_meses`
 * —que es justo lo que `EppService::asignarEquipoAPuesto` protege de perderse—
 * y, si el puesto no la fija, `equipos_proteccion.vida_util_meses`.
 *
 * El cálculo es puro y se prueba sin base de datos, como el resto de las
 * decisiones con consecuencia del módulo.
 */
enum EstadoKitEpp: string
{
    /** Nunca se le entregó: es lo que hay que entregar. */
    case PENDIENTE = 'pendiente';

    /** Se entregó, pero ya pasó su plazo de reposición. */
    case POR_REPONER = 'por_reponer';

    /** Entregado y dentro de su plazo. No hace falta volver a entregarlo. */
    case VIGENTE = 'vigente';

    public function etiqueta(): string
    {
        return match ($this) {
            self::PENDIENTE   => 'Pendiente',
            self::POR_REPONER => 'Por reponer',
            self::VIGENTE     => 'Vigente',
        };
    }

    /**
     * Lo que el formulario premarca: lo que falta y lo que ya toca reponer.
     *
     * Lo vigente se muestra desmarcado, no escondido. El backend no lo
     * prohíbe —un equipo se pierde o se rompe antes de su plazo, y para eso
     * está el motivo «reposición»—; lo que no hace es premarcarlo, que es lo
     * que generaba el duplicado sin que nadie lo pidiera.
     */
    public function toca(): bool
    {
        return $this !== self::VIGENTE;
    }

    /**
     * El estado de un equipo del kit.
     *
     * @param ?CarbonInterface $ultimaEntrega última entrega o reposición a este servidor
     * @param ?int $frecuenciaMeses `puesto_epp.frecuencia_reposicion_meses`, si el puesto la fijó
     * @param ?int $vidaUtilMeses   `equipos_proteccion.vida_util_meses`, como respaldo
     */
    public static function desde(
        ?CarbonInterface $ultimaEntrega,
        ?int $frecuenciaMeses = null,
        ?int $vidaUtilMeses = null,
        ?CarbonInterface $hoy = null,
    ): self {
        if ($ultimaEntrega === null) {
            return self::PENDIENTE;
        }

        $meses = $frecuenciaMeses ?? $vidaUtilMeses;

        // Sin plazo no hay reposición que calcular: se entregó y punto. Decir
        // «por reponer» aquí sería inventar una periodicidad que nadie fijó.
        if ($meses === null || $meses <= 0) {
            return self::VIGENTE;
        }

        return self::reponerDesde($ultimaEntrega, $frecuenciaMeses, $vidaUtilMeses)
            ->lessThanOrEqualTo(($hoy ?? Carbon::now())->copy()->startOfDay())
                ? self::POR_REPONER
                : self::VIGENTE;
    }

    /**
     * El día a partir del cual toca reponer, o `null` si no hay plazo fijado.
     *
     * Viaja a la pantalla junto al estado: «Vigente» sin decir hasta cuándo no
     * le sirve a quien está decidiendo si entrega o no.
     */
    public static function reponerDesde(
        ?CarbonInterface $ultimaEntrega,
        ?int $frecuenciaMeses = null,
        ?int $vidaUtilMeses = null,
    ): ?Carbon {
        $meses = $frecuenciaMeses ?? $vidaUtilMeses;

        if ($ultimaEntrega === null || $meses === null || $meses <= 0) {
            return null;
        }

        // `addMonthsNoOverflow` y no `addMonths`: Carbon desborda al mes
        // siguiente cuando el día de origen no existe en el mes destino, así
        // que una entrega del 31 de enero con plazo de un mes tocaba el 3 de
        // marzo en vez del 28 de febrero. En una reposición mensual ese
        // desbordamiento se acumula y el plazo se va corriendo solo.
        return $ultimaEntrega->copy()->startOfDay()->addMonthsNoOverflow($meses);
    }
}
