<?php

namespace App\Http\Controllers\Comunicacion;

use App\Contracts\Comunicacion\FotografiaServiceInterface;
use App\Enums\OrigenFotografia;
use App\Http\Controllers\Controller;
use App\Http\Requests\Comunicacion\ListarFotografiasRequest;
use App\Http\Requests\Comunicacion\SubirFotografiaRequest;
use App\Http\Resources\Comunicacion\FotografiaServidorResource;
use App\Http\Resources\Comunicacion\ServidorFotografiaResource;
use App\Http\Responses\ApiResponse;
use App\Models\Comunicacion\FotografiaServidor;
use App\Models\Expediente\Servidor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Las fotografías de los servidores (docs/diseno-comunicacion-social.md, 5.1;
 * fase 1.1): el listado con el estado de cada uno, subir, el historial,
 * restaurar, retirar y la imagen.
 */
final class FotografiaServidorController extends Controller
{
    private const RELACIONES = ['subidaPor.servidor', 'activadaPor.servidor'];

    public function __construct(private readonly FotografiaServiceInterface $fotografias) {}

    public function index(ListarFotografiasRequest $request): JsonResponse
    {
        $pagina = $this->fotografias->listar($request->validated());

        return ApiResponse::paginadoDe(
            $pagina,
            ServidorFotografiaResource::collection($pagina->getCollection())->resolve($request),
            'Fotografías de los servidores.',
        );
    }

    public function store(SubirFotografiaRequest $request, Servidor $servidor): JsonResponse
    {
        $fotografia = $this->fotografias->subir(
            $servidor,
            (string) $request->file('foto')->getRealPath(),
            $request->user(),
            OrigenFotografia::INDIVIDUAL,
        );

        return ApiResponse::created(
            new FotografiaServidorResource($fotografia->load(self::RELACIONES)),
            'Foto guardada. La anterior, si había, quedó en el historial.',
        );
    }

    public function historial(Servidor $servidor): JsonResponse
    {
        return ApiResponse::ok(
            FotografiaServidorResource::collection($this->fotografias->historial($servidor)),
            'Historial de fotos del servidor.',
        );
    }

    public function restaurar(Request $request, FotografiaServidor $fotografia): JsonResponse
    {
        $fotografia = $this->fotografias->restaurar($fotografia, $request->user());

        return ApiResponse::ok(
            new FotografiaServidorResource($fotografia->load(self::RELACIONES)),
            'Foto restaurada: vuelve a ser la vigente.',
        );
    }

    public function retirar(Servidor $servidor): JsonResponse
    {
        $fotografia = $this->fotografias->retirar($servidor);

        return ApiResponse::ok(
            new FotografiaServidorResource($fotografia->load(self::RELACIONES)),
            'Foto retirada. El servidor queda sin foto; se puede restaurar desde el historial.',
        );
    }

    /**
     * La imagen, para quien puede verla (FotografiaServidorPolicy). Se pide por
     * el id de la foto y no del servidor: ese archivo no cambia nunca, así que
     * el navegador lo guarda, y una foto nueva tiene otro id.
     */
    public function imagen(Request $request, FotografiaServidor $fotografia): BinaryFileResponse|JsonResponse
    {
        $this->authorize('verImagen', $fotografia);

        $datos = $request->validate(['tamano' => ['nullable', 'in:completa,miniatura']], [], ['tamano' => 'tamaño']);
        $ruta = $this->fotografias->rutaImagen($fotografia, ($datos['tamano'] ?? 'completa') === 'miniatura');

        if (! is_file($ruta)) {
            return ApiResponse::noEncontrado('El archivo de la foto no está en el servidor.');
        }

        return response()->file($ruta, [
            'Content-Type'  => 'image/jpeg',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
