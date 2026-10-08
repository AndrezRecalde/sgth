<?php

namespace App\Http\Resources\Autoservicio;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un reposo médico como lo ve el propio servidor en el portal: días y estado.
 *
 * Campo por campo: ni diagnóstico ni observaciones del médico (decisión del
 * 2026-10-08), ni la nota interna de quien lo aprobó.
 *
 * @mixin \App\Models\Dispensario\CertificadoMedico
 */
class MiCertificadoMedicoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'folio'               => $this->folio,
            'fecha_inicio'        => $this->fecha_inicio?->toDateString(),
            'fecha_fin'           => $this->fecha_fin?->toDateString(),
            'dias_reposo'         => $this->dias_reposo,
            'emitido_en'          => $this->created_at?->toIso8601String(),
            'estado'              => match (true) {
                $this->anulado_en !== null  => 'anulado',
                $this->aprobado_en !== null => 'aprobado',
                default                     => 'pendiente',
            },
            'registro_sirha7'     => $this->registro_sirha7,
            'sirha7_leave_nombre' => $this->sirha7_leave_nombre,
        ];
    }
}
