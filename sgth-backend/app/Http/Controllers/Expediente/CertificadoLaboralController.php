<?php

namespace App\Http\Controllers\Expediente;

use App\Http\Controllers\Controller;
use App\Models\Expediente\Servidor;
use App\Services\Expediente\CertificadoLaboralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class CertificadoLaboralController extends Controller
{
    public function __construct(private CertificadoLaboralService $certificadoService)
    {
    }

    public function generar(int $servidorId): JsonResponse
    {
        $servidor = Servidor::findOrFail($servidorId);

        $rutaArchivo = $this->certificadoService->generarCertificado($servidor);

        $urlDescarga = URL::temporarySignedRoute(
            'expediente.certificado.descargar',
            now()->addMinutes(30),
            ['archivo' => basename($rutaArchivo)]
        );

        return response()->json([
            'message' => 'Certificado generado con éxito',
            'url'     => $urlDescarga,
        ]);
    }

    public function descargar(string $archivo)
    {
        $rutaArchivo = "certificados-laborales/{$archivo}";

        if (!Storage::disk('local')->exists($rutaArchivo)) {
            abort(404, 'El certificado no existe o ha expirado.');
        }

        return Storage::disk('local')->download($rutaArchivo, $archivo, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
