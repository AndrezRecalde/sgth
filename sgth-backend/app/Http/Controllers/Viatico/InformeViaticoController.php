<?php

namespace App\Http\Controllers\Viatico;

use App\Http\Controllers\Controller;
use App\Services\Viatico\PdfInformeViaticoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class InformeViaticoController extends Controller
{
    public function generarEnlace(int $id, PdfInformeViaticoService $service): JsonResponse
    {
        $url = $service->generarEnlaceTemporal($id);
        
        return response()->json([
            'url' => $url
        ]);
    }

    public function descargar(string $archivo)
    {
        $path = "informes-viatico/{$archivo}";
        
        if (!Storage::exists($path)) {
            abort(404, 'El informe no existe o ha expirado.');
        }

        return Storage::download($path);
    }
}
