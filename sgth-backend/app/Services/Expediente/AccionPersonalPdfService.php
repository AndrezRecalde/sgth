<?php

namespace App\Services\Expediente;

use App\Models\Estructura\Puesto;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use Barryvdh\DomPDF\Facade\Pdf;

class AccionPersonalPdfService
{
    public function generarContent(int $movimientoId): array
    {
        $movimiento = MovimientoPersonal::with([
            'servidor',
            'unidadOrigen',
            'unidadDestino',
            'puestoOrigen.cargo',
            'puestoOrigen.grupoOcupacional',
            'puestoOrigen.partidaPresupuestaria',
            'puestoDestino.cargo',
            'puestoDestino.grupoOcupacional',
            'puestoDestino.partidaPresupuestaria',
            'autorizadoPor',
        ])->findOrFail($movimientoId);

        $pdf = Pdf::loadView('pdf.expediente.accion-personal', [
            'movimiento'          => $movimiento,
            'servidor'            => $movimiento->servidor,
            'autoridadNominadora' => $this->obtenerPrefecto(),
            'responsableTalentoHumano' => $this->obtenerDirectorTalentoHumano(),
            'logo'                => public_path('images/logo-gadpe.png'),
        ])->setPaper('a4', 'portrait');

        return [
            'content'  => $pdf->output(),
            'filename' => 'accion_personal_'.($movimiento->codigo ?: $movimiento->id).'.pdf',
        ];
    }

    private function obtenerPrefecto(): ?Servidor
    {
        return Servidor::whereHas('puesto', function ($q) {
            $q->whereHas('cargo', function ($q2) {
                $q2->where('nombre', 'like', '%Prefect%');
            });
        })->with('puesto.cargo')->first();
    }

    private function obtenerDirectorTalentoHumano(): ?Servidor
    {
        return Servidor::whereHas('puesto', function ($q) {
            $q->whereHas('cargo', function ($q2) {
                $q2->where('nombre', 'like', '%Talento Humano%');
            });
        })->with('puesto.cargo')->first();
    }
}
