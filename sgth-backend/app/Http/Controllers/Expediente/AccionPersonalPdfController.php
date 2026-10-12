<?php

namespace App\Http\Controllers\Expediente;

use App\Http\Controllers\Controller;
use App\Models\Expediente\MovimientoPersonal;
use App\Services\Expediente\AccionPersonalPdfService;
use App\Services\Expediente\TramiteSobreSiMismo;
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

    /**
     * La vista previa de lo que todavía no se registra, con la marca BORRADOR
     * (fase 6.2; TH 27). La ruta la limita a quien prepara acciones: el
     * titular no ve sus borradores (diseño, 6.3), y nadie la pide sobre sí
     * mismo.
     */
    public function vistaPrevia(int $movimientoId): Response
    {
        $movimiento = MovimientoPersonal::findOrFail($movimientoId);

        TramiteSobreSiMismo::impedir((int) $movimiento->servidor_id);

        $result = $this->service->generarContent($movimientoId, vistaPrevia: true);

        return response($result['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$result['filename'].'"',
        ]);
    }
}
