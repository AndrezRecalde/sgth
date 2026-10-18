<?php

namespace App\Http\Controllers\Comunicados;

use App\Contracts\Comunicados\ArchivosComunicadoServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Comunicados\SubirAdjuntoRequest;
use App\Http\Requests\Comunicados\SubirPortadaRequest;
use App\Http\Resources\Comunicados\ComunicadoAdjuntoResource;
use App\Http\Responses\ApiResponse;
use App\Models\Comunicados\Comunicado;
use App\Models\Comunicados\ComunicadoAdjunto;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * La portada y los PDF de una publicación, para Talento Humano
 * (docs/diseno-noticias-comunicados.md, regla 6). Cambiarlos pide redactar y
 * solo vale en un borrador; verlos, cualquiera de los tres permisos del
 * módulo, para la vista previa y el seguimiento. Los servidores los verán por
 * las rutas del Portal, que comprueban que sean destinatarios.
 */
final class ArchivosComunicadoController extends Controller
{
    private const TIPOS_DE_IMAGEN = [
        'jpg'  => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
    ];

    public function __construct(private readonly ArchivosComunicadoServiceInterface $archivos) {}

    public function subirPortada(SubirPortadaRequest $request, Comunicado $comunicado): JsonResponse
    {
        $this->archivos->subirPortada($comunicado, $request->file('portada'));

        return ApiResponse::ok(['tiene_portada' => true], 'Portada guardada.');
    }

    public function quitarPortada(Comunicado $comunicado): JsonResponse
    {
        $this->archivos->quitarPortada($comunicado);

        return ApiResponse::ok(['tiene_portada' => false], 'Portada quitada.');
    }

    public function portada(Comunicado $comunicado): BinaryFileResponse|JsonResponse
    {
        $ruta = $this->archivos->rutaPortada($comunicado);

        if ($ruta === null) {
            return ApiResponse::noEncontrado('Esta publicación no tiene portada.');
        }

        return response()->file($ruta, [
            'Content-Type'           => self::TIPOS_DE_IMAGEN[pathinfo($ruta, PATHINFO_EXTENSION)] ?? 'image/jpeg',
            'Cache-Control'          => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function agregarAdjunto(SubirAdjuntoRequest $request, Comunicado $comunicado): JsonResponse
    {
        $adjunto = $this->archivos->agregarAdjunto($comunicado, $request->file('archivo'));

        return ApiResponse::created(new ComunicadoAdjuntoResource($adjunto), 'Documento agregado.');
    }

    public function quitarAdjunto(Comunicado $comunicado, ComunicadoAdjunto $adjunto): JsonResponse
    {
        $this->archivos->quitarAdjunto($comunicado, $adjunto);

        return ApiResponse::noContent('Documento quitado.');
    }

    public function adjunto(Comunicado $comunicado, ComunicadoAdjunto $adjunto): BinaryFileResponse|JsonResponse
    {
        $ruta = $this->archivos->rutaAdjunto($adjunto);

        if ($ruta === null) {
            return ApiResponse::noEncontrado('El documento no está en el servidor.');
        }

        return response()->download($ruta, $adjunto->nombre_original, [
            'Content-Type'           => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
