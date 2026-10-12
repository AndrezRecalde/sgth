<?php

namespace App\Http\Resources\Asistencia;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una licencia con remuneración (fase 5.1): de quién, por qué, qué días, su
 * tope y en qué quedó. El respaldo no sale: se descarga por su endpoint.
 *
 * @mixin \App\Models\Asistencia\Licencia
 */
class LicenciaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $nombre = fn ($u) => $u?->servidor ? trim("{$u->servidor->apellido} {$u->servidor->nombre}") : $u?->usuario_ti;
        $causa = $this->causa;
        $usuario = $request->user();

        return [
            'id'                    => $this->id,
            'folio'                 => $this->folio,
            'servidor_id'           => $this->servidor_id,
            'servidor'              => $this->servidor ? [
                'id'       => $this->servidor->id,
                'nombre'   => $this->servidor->nombre,
                'apellido' => $this->servidor->apellido,
                'cedula'   => $this->servidor->cedula,
                'unidad'   => $this->servidor->unidadAdministrativa?->nombre,
            ] : null,
            'causa'                 => $causa->value,
            'causa_etiqueta'        => $causa->etiqueta(),
            'alcance'               => $this->alcance,
            'alcance_etiqueta'      => $this->alcance ? ($causa->alcances()[$this->alcance]['etiqueta'] ?? null) : null,
            'base_legal'            => $causa->baseLegal(),
            'tope'                  => $causa->tope($this->alcance),
            'fecha_inicio'          => $this->fecha_inicio?->toDateString(),
            'fecha_fin'             => $this->fecha_fin?->toDateString(),
            'dias'                  => $this->dias,
            'observacion'           => $this->observacion,
            'documento_nombre'      => $this->documento_nombre,
            'estado'                => $this->estado(),
            'registrada_en'         => $this->created_at?->toIso8601String(),
            'registrada_por'        => $nombre($this->creadoPor),
            'aprobado_en'           => $this->aprobado_en?->toIso8601String(),
            'aprobado_por'          => $nombre($this->aprobadoPor),
            'registro_sirha7'       => $this->registro_sirha7,
            'nota_aprobacion'       => $this->nota_aprobacion,
            'sirha7_leave_nombre'   => $this->sirha7_leave_nombre,
            'sirha7_dias_omitidos'  => $this->sirha7_dias_omitidos,
            'anulado_en'            => $this->anulado_en?->toIso8601String(),
            'motivo_anulacion'      => $this->motivo_anulacion,
            'registrable_en_sirha7' => $this->estaPendienteDeAprobacion() && $this->seRegistraDesdeElSgth(),
            // Lo que quien mira puede hacer con ella: el formulario no ofrece
            // lo que el backend rechazaría.
            'puede_aprobar'         => $this->estaPendienteDeAprobacion() && (bool) $usuario?->can('aprobar', $this->resource),
            'puede_anular'          => $this->anulado_en === null && (bool) $usuario?->can('anular', $this->resource),
        ];
    }
}
