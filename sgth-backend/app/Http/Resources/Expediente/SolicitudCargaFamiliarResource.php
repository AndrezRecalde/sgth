<?php

namespace App\Http\Resources\Expediente;

use App\Enums\EstadoSolicitudCarga;
use App\Services\Expediente\SolicitudCargaFamiliarService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una solicitud de carga familiar del Portal: qué pidió el servidor, sobre
 * quién y en qué quedó. Lo ven el servidor —las suyas— y Talento Humano, que
 * además recibe los avisos para decidir (diseño, 6.2). El documento no sale:
 * se descarga por su endpoint.
 *
 * @mixin \App\Models\Expediente\SolicitudCargaFamiliar
 */
class SolicitudCargaFamiliarResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $carga = $this->resource->relationLoaded('cargaFamiliar') ? $this->resource->cargaFamiliar : null;

        return [
            'id'                  => $this->id,
            'tipo'                => $this->tipo,
            'tipo_etiqueta'       => $this->tipo->etiqueta(),
            'estado'              => $this->estado,
            'estado_etiqueta'     => $this->estado->etiqueta(),
            'servidor'            => $this->whenLoaded('servidor', fn () => [
                'id'               => $this->servidor->id,
                'cedula'           => $this->servidor->cedula,
                'nombre'           => $this->servidor->nombre,
                'segundo_nombre'   => $this->servidor->segundo_nombre,
                'apellido'         => $this->servidor->apellido,
                'segundo_apellido' => $this->servidor->segundo_apellido,
                'unidad'           => $this->servidor->unidadAdministrativa?->nombre,
            ]),

            // Lo que propone. En una baja, vacío: se lee de `carga`.
            'cedula'              => $this->cedula ?? $carga?->cedula,
            'nombres'             => $this->nombres,
            'apellidos'           => $this->apellidos,
            'parentesco'          => $this->parentesco,
            'parentesco_etiqueta' => $this->parentesco?->etiqueta(),
            'fecha_nacimiento'    => $this->fecha_nacimiento?->toDateString(),
            'genero'              => $this->genero,
            'motivo'              => $this->motivo,

            // La carga como está hoy: el «antes» de una corrección, o a quién
            // da de baja.
            'carga'               => $this->whenLoaded('cargaFamiliar', fn () => $carga ? [
                'id'               => $carga->id,
                'cedula'           => $carga->cedula,
                'nombres'          => $carga->nombres,
                'apellidos'        => $carga->apellidos,
                'parentesco'       => $carga->parentesco,
                'fecha_nacimiento' => $carga->fecha_nacimiento?->toDateString(),
                'genero'           => $carga->genero,
                'estado'           => $carga->estado,
            ] : null),

            'tiene_documento'     => $this->documento_ruta !== null,
            'documento_nombre'    => $this->documento_nombre,
            'motivo_rechazo'      => $this->motivo_rechazo,
            'resuelto_en'         => $this->resuelto_en?->toIso8601String(),
            'created_at'          => $this->created_at?->toIso8601String(),
            'puede_retirar'       => (bool) ($this->estado === EstadoSolicitudCarga::PENDIENTE
                && $request->user()?->servidor_id === $this->servidor_id),

            // Solo en la bandeja de Talento Humano.
            'avisos'              => $this->when(
                $request->routeIs('cargas-por-validar.*'),
                fn () => $this->avisos(),
            ),
        ];
    }

    /**
     * En un método con su tipo, para que Scramble lo lea como lista de textos.
     *
     * @return list<string>
     */
    private function avisos(): array
    {
        return app(SolicitudCargaFamiliarService::class)->avisos($this->resource);
    }
}
