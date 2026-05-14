<?php

namespace App\Http\Resources\Expediente;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

class DocumentoServidorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $datos = parent::toArray($request);

        // Proveer URL firmada para descarga
        if (!empty($this->ruta_archivo)) {
            $datos['url_descarga'] = URL::temporarySignedRoute(
                'documentos.descargar', now()->addMinutes(30), ['ruta' => basename($this->ruta_archivo)]
            );
            unset($datos['ruta_archivo']); // Ocultar la ruta física real
        }

        $datos['subido_por_usuario'] = $this->whenLoaded('subidoPor');

        return $datos;
    }
}
