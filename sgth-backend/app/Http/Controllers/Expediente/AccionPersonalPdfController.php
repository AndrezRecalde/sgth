<?php

namespace App\Http\Controllers\Expediente;

use App\Http\Controllers\Controller;
use App\Models\Expediente\MovimientoPersonal;
use App\Services\Expediente\AccionPersonalPdfService;
use Illuminate\Http\Response;

final class AccionPersonalPdfController extends Controller
{
    public function __construct(
        private readonly AccionPersonalPdfService $service
    ) {}

    public function generar(int $movimientoId): Response
    {
        $movimiento = MovimientoPersonal::findOrFail($movimientoId);

        $this->authorize('ver', $movimiento->servidor);

        $result = $this->service->generarContent($movimientoId);

        return response($result['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$result['filename'].'"',
        ]);
    }
}
