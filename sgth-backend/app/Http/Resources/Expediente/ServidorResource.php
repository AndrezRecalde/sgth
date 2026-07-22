<?php

namespace App\Http\Resources\Expediente;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

class ServidorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $datos = parent::toArray($request);

        $datos['anios_servicio'] = $this->anios_servicio;

        // Nunca exponer rutas absolutas físicas directamente por seguridad
        if (!empty($this->carnet_conadis_ruta)) {
            $datos['carnet_conadis_ruta'] = URL::temporarySignedRoute(
                'documentos.descargar', now()->addMinutes(30), ['ruta' => basename($this->carnet_conadis_ruta)]
            );
        }

        if (!empty($this->enfermedad_catastrofica_certificado_ruta)) {
            $datos['enfermedad_catastrofica_certificado_ruta'] = URL::temporarySignedRoute(
                'documentos.descargar', now()->addMinutes(30), ['ruta' => basename($this->enfermedad_catastrofica_certificado_ruta)]
            );
        }

        // Incorporar relaciones si fueron cargadas
        $datos['unidad_administrativa'] = $this->whenLoaded('unidadAdministrativa');
        $datos['puesto'] = $this->whenLoaded('puesto');
        $datos['contrato_vigente'] = $this->whenLoaded('contratoVigente');
        $datos['user'] = $this->whenLoaded('user');
        $datos['documentos'] = DocumentoServidorResource::collection($this->whenLoaded('documentos'));
        $datos['movimientos'] = $this->whenLoaded('movimientos'); // Resource genérico o matriz directa

        return $datos;
    }
}
