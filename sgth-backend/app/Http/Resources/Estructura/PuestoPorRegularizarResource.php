<?php

namespace App\Http\Resources\Estructura;

use App\Models\Expediente\ContratoServidor;
use App\Support\NombreDelServidor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un puesto de plantilla que parece de contrato: solo lo ocupan ocasionales o
 * profesionales que no reemplazan a nadie (P9, PR 4).
 *
 * @mixin \App\Models\Estructura\Puesto
 */
class PuestoPorRegularizarResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'cargo'           => $this->cargo?->nombre,
            'unidad'          => $this->unidadAdministrativa?->nombre,
            'regimen_laboral' => $this->regimen_laboral,
            // Las que hoy cuenta la Plantilla como vacantes.
            'plazas'          => $this->plazas,
            'ocupantes'       => $this->contratosVigentes
                ->filter(fn (ContratoServidor $c) => $c->servidor !== null)
                ->map(fn (ContratoServidor $c) => [
                    'contrato_id' => $c->id,
                    // Las columnas del nombre, y no `nombre_completo`: un
                    // servidor sin ellas cargadas traía el expediente entero.
                    'nombre'      => NombreDelServidor::de($c->servidor),
                    'modalidad'   => $c->tipo_nombramiento?->etiqueta(),
                ])
                ->values(),
        ];
    }
}
