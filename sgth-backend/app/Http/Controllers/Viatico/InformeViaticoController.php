<?php
namespace App\Http\Controllers\Viatico;

use App\Http\Controllers\Controller;
use App\Services\Viatico\PdfInformeViaticoService;
use Illuminate\Http\Response;

class InformeViaticoController extends Controller
{
    public function __construct(
        private PdfInformeViaticoService $service
    ) {}

    /**
     * Descarga el PDF de solicitud de viático
     */
    public function generarSolicitud(
        string $identificador
    ): Response {
        $result = $this->service->generarSolicitudContent(
            $identificador
        );

        return response($result['content'], 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' .
                $result['filename'] . '"',
        ]);
    }

    /**
     * Descarga el PDF de informe de liquidación
     */
    public function generarEnlace(
        string $identificador
    ): Response {
        $result = $this->service->generarInformeContent(
            $identificador
        );

        return response($result['content'], 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' .
                $result['filename'] . '"',
        ]);
    }

    /**
     * Endpoint legacy — redirige al nuevo
     */
    public function descargar(string $archivo): Response
    {
        return response('Endpoint deprecado.', 410);
    }
}
