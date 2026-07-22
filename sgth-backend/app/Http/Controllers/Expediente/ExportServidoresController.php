<?php

namespace App\Http\Controllers\Expediente;

use App\Contracts\Expediente\ExpedienteServiceInterface;
use App\Exports\ServidoresNominaExport;
use App\Http\Controllers\Controller;
use App\Models\Expediente\Servidor;
use App\Services\Expediente\NominaServidoresPdfService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportServidoresController extends Controller
{
    public function __construct(
        private readonly ExpedienteServiceInterface $expedienteService,
        private readonly NominaServidoresPdfService $pdfService,
    ) {}

    public function excel(Request $request): BinaryFileResponse
    {
        $this->authorize('verAny', Servidor::class);

        $datos = $this->expedienteService->exportarServidores($request->all());

        return Excel::download(
            new ServidoresNominaExport($datos),
            'nomina_servidores_'.now()->format('Ymd_His').'.xlsx'
        );
    }

    public function pdf(Request $request): Response
    {
        $this->authorize('verAny', Servidor::class);

        $result = $this->pdfService->generarContent($request->all());

        return response($result['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$result['filename'].'"',
        ]);
    }
}
