<?php

namespace App\Http\Controllers\Sso;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Sso\DocumentoSsoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

final class DocumentoSsoController extends Controller
{
    public function __construct(
        private readonly DocumentoSsoService $documentoSsoService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'documentable_type' => ['required', 'string', 'in:' . implode(',', DocumentoSsoService::tiposPermitidos())],
            'documentable_id' => ['required', 'integer'],
        ]);

        $documentos = $this->documentoSsoService->listarDocumentos(
            $validated['documentable_type'],
            $validated['documentable_id']
        );

        return ApiResponse::ok($documentos, 'Documentos obtenidos exitosamente.');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'documentable_type' => ['required', 'string', 'in:' . implode(',', DocumentoSsoService::tiposPermitidos())],
            'documentable_id' => ['required', 'integer'],
            'nombre' => ['required', 'string', 'max:255'],
            'archivo' => ['required', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:10240'],
        ]);

        $documento = $this->documentoSsoService->subirDocumento(
            $validated['documentable_type'],
            $validated['documentable_id'],
            $request->file('archivo'),
            $validated['nombre'],
            $request->user()->id
        );

        return ApiResponse::created($documento, 'Documento subido exitosamente.');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->documentoSsoService->eliminarDocumento($id);
        return ApiResponse::ok(null, 'Documento eliminado exitosamente.');
    }

    public function generarEnlace(int $id): JsonResponse
    {
        $url = $this->documentoSsoService->generarUrlFirmada($id);
        return ApiResponse::ok(['url_firmada' => $url], 'Enlace seguro generado.');
    }

    public function descargar(Request $request, int $documento)
    {
        if (! $request->hasValidSignature()) {
            return ApiResponse::error('El enlace ha expirado o es inválido.', null, 403);
        }

        [$rutaArchivo, $nombre, $tipoMime] = $this->documentoSsoService->descargar($documento);

        return Storage::disk('local')->download($rutaArchivo, $nombre, ['Content-Type' => $tipoMime]);
    }
}
