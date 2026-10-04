<?php

namespace App\Http\Controllers\Expediente;

use App\Http\Controllers\Controller;
use App\Http\Resources\Expediente\EmisionCertificadoResource;
use App\Http\Responses\ApiResponse;
use App\Models\Expediente\EmisionCertificadoLaboral;
use App\Models\Expediente\Servidor;
use App\Services\Expediente\CertificadoLaboralService;
use Illuminate\Http\JsonResponse;
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
     * La bitácora de certificados de un servidor, del más reciente al más
     * antiguo.
     *
     * Sirve para dos cosas a la vez. Para Talento Humano, evitar emitir a
     * ciegas: ver que hace tres días ya se emitió uno sin remuneración ahorra
     * repetir el trabajo y explica por qué la persona vuelve a pedirlo. Y para
     * la institución, dejar registro de quién accedió a datos personales: el
     * certificado lleva cédula y, en una de sus variantes, la remuneración.
     *
     * No es un indicador de nada sobre el servidor. Un número alto no
     * significa nada malo —quien pide créditos o está en un concurso pide
     * varios—, y por eso no existe ningún listado que los ordene por cantidad.
     */
    public function emitidos(int $servidorId): JsonResponse
    {
        // `nombre_completo` del usuario sale de su servidor: sin la relación
        // anidada serían dos consultas por cada línea de la bitácora.
        $emisiones = EmisionCertificadoLaboral::with([
            'emitidoPor:id,usuario_ti,servidor_id',
            'emitidoPor.servidor:id,nombre,apellido',
        ])
            ->where('servidor_id', $servidorId)
            ->orderByDesc('emitido_en')->orderByDesc('id')
            ->get();

        return ApiResponse::ok(
            EmisionCertificadoResource::collection($emisiones),
            'Certificados laborales emitidos.',
        );
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
            'enviar'           => ['sometimes', 'boolean'],
        ]);

        $servidor = Servidor::findOrFail($servidorId);

        $emision = $this->certificados->emitir(
            $servidor,
            (bool) ($datos['con_remuneracion'] ?? false),
            $request->user()->id,
        );

        // El envío va aquí y no en cola: quien emite espera la respuesta y
        // necesita saber en ese momento si el correo salió o si le toca
        // mandarlo a mano.
        $envio = ($datos['enviar'] ?? false)
            ? $this->certificados->enviar($emision, $servidor)
            : 'no_solicitado';

        return response($this->certificados->pdf($emision), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'
                .$this->certificados->nombreArchivo($emision).'"',
            // El cuerpo es el PDF, así que el resultado viaja en cabeceras.
            // Van expuestas en config/cors.php; sin eso el navegador no las lee.
            'X-Codigo-Certificado' => $emision->codigo,
            'X-Envio-Certificado'  => $envio,
        ]);
    }
}
