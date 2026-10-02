<?php

namespace App\Http\Controllers\Dispensario;

use App\Http\Controllers\Controller;
use App\Services\Dispensario\CertificadoAptitudService;
use Illuminate\Http\Response;

/**
 * El certificado de aptitud médica ocupacional de una evaluación.
 *
 * Lo abre Talento Humano, al revés que el PDF del FEMO: ese es el formulario
 * 028 del MSP completo y se queda en el Dispensario.
 */
final class CertificadoAptitudController extends Controller
{
    public function __construct(
        private readonly CertificadoAptitudService $service,
    ) {
    }

    public function generar(int $id): Response
    {
        $resultado = $this->service->generarContent($id);

        return response($resultado['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$resultado['filename'].'"',
        ]);
    }
}
