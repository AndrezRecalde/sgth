<?php
namespace App\Http\Controllers\Viatico;

use App\Http\Controllers\Controller;
use App\Services\Viatico\PdfInformeViaticoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class InformeViaticoController extends Controller
{
    public function __construct(
        private PdfInformeViaticoService $service
    ) {}

    /**
     * Genera el PDF de solicitud de viático
     */
    public function generarSolicitud(
        string $identificador
    ): JsonResponse {
        $url = $this->service->generarSolicitud($identificador);
        return response()->json(['url' => $url]);
    }

    /**
     * Genera el PDF de informe de liquidación
     */
    public function generarEnlace(
        string $identificador
    ): JsonResponse {
        $url = $this->service->generarInformeLiquidacion(
            $identificador
        );
        return response()->json(['url' => $url]);
    }

    /**
     * Descarga el archivo PDF generado
     */
    public function descargar(string $archivo)
    {
        $path = "informes-viatico/{$archivo}";

        if (!Storage::exists($path)) {
            abort(404, 'El informe no existe o ha expirado.');
        }

        return Storage::download($path);
    }
}
