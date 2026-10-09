<?php

namespace App\Http\Resources\Expediente;

use App\Enums\ClaseAccionPersonal;
use App\Enums\TipoNombramiento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una clase del catálogo de acciones de personal, con lo que el formulario
 * necesita saber de ella.
 *
 * Va como recurso y no como arreglo armado en el controlador para que Scramble
 * describa su forma: dentro de un `array_map` la ve como `unknown[]` y el tipo
 * llegaba inservible al frontend.
 *
 * @property ClaseAccionPersonal $resource
 */
class ClaseAccionPersonalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $clase = $this->resource;

        return [
            'codigo'                      => $clase,
            'etiqueta'                    => $clase->etiqueta(),
            'familia'                     => $clase->familia(),
            'se_crea_desde_formulario'    => $clase->seCreaDesdeElFormulario(),
            'requiere_vinculo'            => $clase->requiereVinculo(),
            /** @var list<TipoNombramiento> */
            'nombramientos_elegibles'     => $clase->nombramientosElegibles(),
            'causales'                    => CausalAccionPersonalResource::collection($clase->causales()),
            'pide_situacion_propuesta'    => $clase->pideSituacionPropuesta(),
            'pide_periodo'                => $clase->pidePeriodo(),
            'pide_contratacion'           => $clase->pideContratacion(),
            'dictamen_medico_por_defecto' => $clase->dictamenMedicoPorDefecto(),
            'aviso'                       => $clase->aviso(),
        ];
    }
}
