<?php

namespace App\Http\Resources\Actividades;

use App\Services\Actividades\MisActividadesService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una actividad del portal, para quien la anotó.
 *
 * @mixin \App\Models\Actividades\ActividadLaboral
 */
class ActividadLaboralResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'fecha'               => $this->fecha->toDateString(),
            'descripcion'         => $this->descripcion,
            'categoria'           => $this->categoria,
            'categoria_etiqueta'  => $this->categoria->etiqueta(),
            'producto_entregable' => $this->producto_entregable,
            // Solo las del mes en curso: las de antes quedan como se informaron.
            'puede_editar'        => MisActividadesService::esEditable($this->resource),
        ];
    }
}
