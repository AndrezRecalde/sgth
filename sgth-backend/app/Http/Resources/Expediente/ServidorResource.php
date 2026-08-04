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
        // Derivado, no una columna: true si el servidor no tiene ningún
        // ContratoServidor vigente. No confundir con Servidor.estado (activo/
        // inactivo) — son conceptos independientes. Cálculo directo (no
        // whenLoaded con closure) para no depender de su resolución interna.
        $datos['pendiente_vinculacion'] = $this->resource->relationLoaded('contratoVigente')
            ? is_null($this->resource->contratoVigente)
            : null;
        $datos['user'] = $this->whenLoaded('usuario');
        $datos['documentos'] = DocumentoServidorResource::collection($this->whenLoaded('documentos'));
        $datos['movimientos'] = $this->whenLoaded('movimientos'); // Resource genérico o matriz directa

        return $datos;
    }
}
