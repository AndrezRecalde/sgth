<?php

namespace App\Http\Controllers\Capacitacion;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Capacitacion\CertificadoCapacitacion;
use Illuminate\Http\Request;

class CertificadoCapacitacionController extends Controller
{
    public function show(int $servidorId)
    {
        // Buscar todos los certificados de las inscripciones donde el servidorId coincida
        $certificados = CertificadoCapacitacion::whereHas('inscripcion', function ($query) use ($servidorId) {
            $query->where('servidor_id', $servidorId);
        })->with('inscripcion.curso')->get();

        return ApiResponse::ok($certificados, 'Certificados del servidor listados correctamente.');
    }

    public function generar(Request $request, int $inscripcionId)
    {
        // En una implementación real, aquí se llamaría al PdfService si se necesita 
        // forzar la regeneración manual de un certificado que ya fue aprobado.
        // Dado que la generación automática ocurre en el CapacitacionService al aprobar,
        // este endpoint puede servir para reimpresiones o descargas.
        
        $certificado = CertificadoCapacitacion::where('inscripcion_id', $inscripcionId)->firstOrFail();
        
        return ApiResponse::ok($certificado, 'Certificado PDF generado/recuperado exitosamente.');
    }
}
