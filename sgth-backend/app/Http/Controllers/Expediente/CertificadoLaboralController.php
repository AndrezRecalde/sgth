<?php

namespace App\Http\Controllers\Expediente;

use App\Http\Controllers\Controller;
use App\Models\Expediente\Servidor;
use App\Services\Expediente\CertificadoLaboralService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Emite el certificado laboral. Lo entrega Talento Humano, no el interesado:
 * la ruta está cerrada a `admin-uath|asistente-uath` (ver routes/api.php).
 */
class CertificadoLaboralController extends Controller
{
    public function __construct(
        private readonly CertificadoLaboralService $certificados,
    ) {
    }

    /**
     * El PDF sale en la misma respuesta, sin pasar por disco.
     *
     * Antes se guardaba en storage con un nombre con marca de tiempo y se
     * devolvía una URL firmada. Nada borraba esos archivos, así que la carpeta
     * crecía sola con copias de documentos que llevan cédula y remuneración.
     * La constancia de que el certificado se emitió vive ahora en la bitácora,
     * que es lo que de verdad hacía falta guardar.
     */
    public function generar(Request $request, int $servidorId): Response
    {
        $datos = $request->validate([
            'con_remuneracion' => ['sometimes', 'boolean'],
        ]);

        $servidor = Servidor::findOrFail($servidorId);

        $emision = $this->certificados->emitir(
            $servidor,
            (bool) ($datos['con_remuneracion'] ?? false),
            $request->user()->id,
        );

        return response($this->certificados->pdf($emision), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'
                .$this->certificados->nombreArchivo($emision).'"',
            // Para que el frontend pueda mostrar el código sin volver a pedirlo.
            'X-Codigo-Certificado' => $emision->codigo,
        ]);
    }
}
