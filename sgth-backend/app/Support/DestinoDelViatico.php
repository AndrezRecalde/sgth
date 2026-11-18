<?php

namespace App\Support;

use App\Models\Viatico\TramoViatico;
use App\Models\Viatico\Viatico;
use Illuminate\Support\Collection;

/**
 * A dónde fue el servidor, según su itinerario.
 *
 * Los tres papeles del trámite —solicitud, informe y comprobante— nombran el
 * destino, y cada uno lo calculaba por su cuenta. El comprobante tomaba el
 * último tramo, que es el regreso, así que imprimía la ciudad de la que el
 * servidor salió: «se trasladó a la ciudad de ESMERALDAS» en un viaje a Quito.
 *
 * El destino es donde se realiza la comisión, no el último lugar al que se
 * llega. Son los tramos marcados `destino`; si nadie los marcó —un viaje de
 * ida y vuelta sin paradas— vale el de ida, y si tampoco, todos menos el
 * último, que es el regreso.
 */
final class DestinoDelViatico
{
    /**
     * Los destinos con su provincia: «Pichincha / Quito».
     *
     * @return Collection<int, string>
     */
    public static function lugares(Viatico $viatico): Collection
    {
        return self::tramosDeDestino($viatico)
            ->map(fn (TramoViatico $t) => self::lugarDe($t))
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * Solo la ciudad: «Quito». Es lo que pide una frase corrida —«se trasladó
     * a la ciudad de…»—, donde la provincia estorba.
     *
     * @return Collection<int, string>
     */
    public static function ciudades(Viatico $viatico): Collection
    {
        return self::tramosDeDestino($viatico)
            ->map(fn (TramoViatico $t) => self::ciudadDe($t))
            ->filter()
            ->unique()
            ->values();
    }

    /** Los destinos en una línea, o «—» si el itinerario todavía está vacío. */
    public static function texto(Viatico $viatico, string $separador = ' • '): string
    {
        $lugares = self::lugares($viatico);

        return $lugares->isNotEmpty()
            ? $lugares->join($separador)
            : self::sinItinerario($viatico);
    }

    /** Las ciudades en una línea, o «—» si el itinerario todavía está vacío. */
    public static function textoDeCiudades(Viatico $viatico, string $separador = ', '): string
    {
        $ciudades = self::ciudades($viatico);

        return $ciudades->isNotEmpty()
            ? $ciudades->join($separador)
            : self::sinItinerario($viatico);
    }

    /**
     * Los tramos que cuentan como destino.
     *
     * @return Collection<int, TramoViatico>
     */
    private static function tramosDeDestino(Viatico $viatico): Collection
    {
        // Con el mismo `orden` el desempate es el id: sin él, dos tramos
        // guardados a la vez salen en cualquier orden.
        $tramos = $viatico->tramos->sortBy(fn (TramoViatico $t) => [$t->orden, $t->id])->values();

        foreach (['destino', 'ida'] as $tipo) {
            $deEseTipo = $tramos->where('tipo_tramo', $tipo);

            if ($deEseTipo->isNotEmpty()) {
                return $deEseTipo->values();
            }
        }

        return $tramos->slice(0, -1)->values();
    }

    private static function lugarDe(TramoViatico $tramo): string
    {
        if ($tramo->destino_tipo === 'internacional') {
            return collect([$tramo->destino_pais, $tramo->destino_ciudad])
                ->filter()->unique()->join(' / ');
        }

        return collect([
            $tramo->destinoProvincia?->nombre,
            $tramo->destinoCanton?->nombre ?: $tramo->destino_ciudad,
        ])->filter()->unique()->join(' / ');
    }

    private static function ciudadDe(TramoViatico $tramo): string
    {
        if ($tramo->destino_tipo === 'internacional') {
            return (string) ($tramo->destino_ciudad ?: $tramo->destino_pais);
        }

        return (string) ($tramo->destinoCanton?->nombre ?: $tramo->destino_ciudad);
    }

    /**
     * Sin tramos todavía queda el país que se escribió al solicitar un viaje
     * al exterior; dentro del país no hay nada que decir hasta el itinerario.
     */
    private static function sinItinerario(Viatico $viatico): string
    {
        return $viatico->pais_destino ?: '—';
    }
}
