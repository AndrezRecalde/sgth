<?php

namespace App\Http\Resources\Expediente;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentoServidorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'tipo_documento'    => $this->tipo_documento,
            'nombre_archivo'    => $this->nombre_archivo,
            'tamanio_bytes'     => $this->tamanio_bytes,
            'mime_type'         => $this->mime_type,
            'fecha_vencimiento' => $this->fecha_vencimiento?->format('Y-m-d'),
            'descripcion'       => $this->descripcion,
            'estado'            => $this->estado,
            'subido_por'        => $this->whenLoaded('subidoPor', fn() => [
                'id'         => $this->subidoPor?->id,
                'usuario_ti' => $this->subidoPor?->usuario_ti,
            ]),
            'created_at'        => $this->created_at?->format('Y-m-d H:i'),
            'url_descarga'      => route('documentos.descargar',
                ['servidorId' => $this->servidor_id, 'documentoId' => $this->id],
                absolute: false
            ),
        ];
    }
}
