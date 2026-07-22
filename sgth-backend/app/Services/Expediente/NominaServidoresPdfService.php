<?php

namespace App\Services\Expediente;

use Barryvdh\DomPDF\Facade\Pdf;

class NominaServidoresPdfService
{
    public function __construct(
        private readonly ExpedienteService $expedienteService
    ) {}

    public function generarContent(array $filtros): array
    {
        $servidores = $this->expedienteService->exportarServidores($filtros);

        $pdf = Pdf::loadView('pdf.expediente.nomina-servidores', [
            'servidores' => $servidores,
            'logo'       => public_path('images/logo-gadpe.png'),
            'fecha'      => now(),
        ])->setPaper('a4', 'landscape');

        return [
            'content'  => $pdf->output(),
            'filename' => 'nomina_servidores_'.now()->format('Ymd_His').'.pdf',
        ];
    }
}
