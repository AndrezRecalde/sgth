<?php

namespace App\Http\Controllers\Expediente;

use App\Contracts\Expediente\ExpedienteServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Expediente\StoreDocumentoServidorRequest;
use App\Http\Resources\Expediente\DocumentoServidorResource;
use App\Http\Responses\ApiResponse;
use App\Models\Expediente\DocumentoServidor;
use App\Models\Expediente\Servidor;
use Illuminate\Http\JsonResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class DocumentoServidorController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ExpedienteServiceInterface $expedienteService
    ) {}

    public function index(int $servidorId): JsonResponse
    {
        $servidor = Servidor::findOrFail($servidorId);
        $this->authorize('view', $servidor);

        $documentos = DocumentoServidor::where('servidor_id', $servidorId)
            ->with('subidoPor:id,usuario_ti,servidor_id')
            ->orderByDesc('created_at')
            ->get();

        return ApiResponse::ok(
            DocumentoServidorResource::collection($documentos),
            'Documentos del servidor.'
        );
    }

    public function store(
        StoreDocumentoServidorRequest $request,
        int $servidorId
    ): JsonResponse {
        $servidor = Servidor::findOrFail($servidorId);
        $this->authorize('actualizar', $servidor);

        $documento = $this->expedienteService->subirDocumento(
            $servidorId,
            $request->validated(),
            $request->file('archivo')
        );

        return ApiResponse::created(
            new DocumentoServidorResource($documento),
            'Documento anexado al expediente correctamente.'
        );
    }

    public function destroy(int $servidorId, int $documentoId): JsonResponse
    {
        $servidor = Servidor::findOrFail($servidorId);
        $this->authorize('actualizar', $servidor);

        $documento = DocumentoServidor::where('servidor_id', $servidorId)
            ->findOrFail($documentoId);

        $documento->delete();

        return ApiResponse::ok(null, 'Documento eliminado del expediente.');
    }

    public function descargar(int $servidorId, int $documentoId): mixed
    {
        $documento = DocumentoServidor::where('servidor_id', $servidorId)
            ->findOrFail($documentoId);

        if (!\Illuminate\Support\Facades\Storage::exists($documento->ruta_archivo)) {
            return ApiResponse::error('Archivo no encontrado.', null, 404);
        }

        return \Illuminate\Support\Facades\Storage::download(
            $documento->ruta_archivo,
            $documento->nombre_archivo
        );
    }
}
