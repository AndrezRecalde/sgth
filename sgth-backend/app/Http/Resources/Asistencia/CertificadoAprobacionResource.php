<?php

namespace App\Http\Resources\Asistencia;

use App\Models\Dispensario\CertificadoMedico;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un certificado médico como lo ven Talento Humano y Trabajo Social al
 * aprobarlo: quién, qué días, quién lo emitió y en qué quedó.
 *
 * Campo por campo a propósito: el certificado guarda el diagnóstico y las
 * observaciones del médico, y aquí no van (decisión del 2026-10-08). Tampoco
 * el motivo de anulación, que lo escribe el médico. Si agregas una columna a
 * `certificados_medicos`, decide si debe verse aquí antes de agregarla.
 *
 * @mixin \App\Models\Dispensario\CertificadoMedico
 */
class CertificadoAprobacionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $medico = $this->emisor?->servidor;
        $aprobador = $this->aprobadoPor?->servidor;

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
            'fecha_inicio'          => $this->fecha_inicio?->toDateString(),
            'fecha_fin'             => $this->fecha_fin?->toDateString(),
            'dias_reposo'           => $this->dias_reposo,
            'emitido_en'            => $this->created_at?->toIso8601String(),
            'medico'                => $medico
                ? trim("{$medico->apellido} {$medico->nombre}")
                : $this->emisor?->usuario_ti,
            'anulado_en'            => $this->anulado_en?->toIso8601String(),
            'aprobado_en'           => $this->aprobado_en?->toIso8601String(),
            'aprobado_por'          => $aprobador
                ? trim("{$aprobador->apellido} {$aprobador->nombre}")
                : $this->aprobadoPor?->usuario_ti,
            'registro_sirha7'       => $this->registro_sirha7,
            'nota_aprobacion'       => $this->nota_aprobacion,
            'sirha7_leave_nombre'   => $this->sirha7_leave_nombre,
            'sirha7_dias_omitidos'  => $this->sirha7_dias_omitidos,
            'pendiente'             => $this->estaPendienteDeAprobacion(),
            'registrable_en_sirha7' => $this->estaPendienteDeAprobacion() && $this->seRegistraDesdeElSgth(),
        ];
    }
}
