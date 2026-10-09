<?php

namespace App\Http\Resources\Expediente;

use App\Enums\SubtipoMovimientoPersonal;
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
            'nombramientos_elegibles'     => $causal->nombramientosElegibles(),
            // En la cesación es lo mismo que pregunta
            // ClaseAccionPersonal::dictamenMedicoPorDefecto($causal), que delega
            // en el subtipo: jubilación e incapacidad abren marcadas.
            'dictamen_medico_por_defecto' => $causal->requiereDictamenMedicoPorDefecto(),
        ];
    }
}
