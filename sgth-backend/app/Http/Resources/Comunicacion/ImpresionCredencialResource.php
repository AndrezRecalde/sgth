<?php

namespace App\Http\Resources\Comunicacion;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * Una impresión de la credencial: cuándo, quién, en qué formato y con qué
 * nombre, cargo y unidad salió. El tipo de sangre y el contacto de emergencia
 * que se imprimieron no se repiten aquí: son datos sensibles y para saber que
 * cambiaron basta `cambios_desde_impresion` de la credencial.
 *
 * @mixin \App\Models\Comunicacion\ImpresionCredencial
 */
class ImpresionCredencialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'formato'          => $this->formato->value,
            'formato_etiqueta' => $this->formato->etiqueta(),
            'lote'             => $this->lote,
            'impresa_en'       => $this->created_at?->toIso8601String(),
            'impresa_por'      => $this->whenLoaded('impresaPor', fn () => $this->impresaPor ? [
                'id'     => $this->impresaPor->id,
                'nombre' => $this->impresaPor->nombre_completo,
            ] : null),
            /** @var array{nombres?: string, apellidos?: string, cargo?: ?string, unidad?: ?string, valida_hasta?: ?string} */
            'datos_impresos'   => Arr::only($this->datos_impresos ?? [], ['nombres', 'apellidos', 'cargo', 'unidad', 'valida_hasta']),
        ];
    }
}
