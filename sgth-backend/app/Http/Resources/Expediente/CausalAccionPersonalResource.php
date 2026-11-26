<?php

namespace App\Http\Resources\Expediente;

use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una causal de una clase del catálogo. Hoy solo la cesación de funciones tiene
 * causales, y son sus subtipos: renuncia, destitución, jubilación…
 *
 * @mixin \App\Enums\SubtipoMovimientoPersonal
 * @property SubtipoMovimientoPersonal $resource
 */
class CausalAccionPersonalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $causal = $this->resource;

        return [
            'codigo'                      => $causal,
            'etiqueta'                    => $causal->etiqueta(),
            // La del régimen LOSEP, que es el caso general, y las de los
            // regímenes que citan otra norma por la misma causal (fase 4.1c):
            // el formulario elige con el nombramiento del servidor buscado.
            //
            // Va como lista y no como mapa porque Scramble no sabe leer un
            // arreglo con claves de cadena: lo tipa como una tupla posicional
            // y el tipo que llega al frontend queda mintiendo. Y dentro de un
            // `array_map` tampoco ve la forma, así que va anotada.
            'base_legal'                  => $causal->baseLegal(),
            /** @var list<array{nombramiento: TipoNombramiento, base_legal: string}> */
            'base_legal_por_nombramiento' => array_map(
                fn (string $nombramiento, string $base) => [
                    'nombramiento' => $nombramiento,
                    'base_legal'   => $base,
                ],
                array_keys($propias = $causal->baseLegalPorNombramiento()),
                array_values($propias),
            ),
            'nombramientos_elegibles'     => $causal->nombramientosElegibles(),
            // En la cesación es lo mismo que pregunta
            // ClaseAccionPersonal::dictamenMedicoPorDefecto($causal), que delega
            // en el subtipo: jubilación e incapacidad abren marcadas.
            'dictamen_medico_por_defecto' => $causal->requiereDictamenMedicoPorDefecto(),
        ];
    }
}
