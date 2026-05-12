<?php

namespace App\Http\Controllers\Sgd;

use App\Contracts\Sgd\SgdServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sgd\StoreDocumentoInstitucionalRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Sgd\DocumentoInstitucional;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentoInstitucionalController extends Controller
{
    public function __construct(private SgdServiceInterface $sgdService) {}

    public function store(StoreDocumentoInstitucionalRequest $request): JsonResponse
    {
        $documento = $this->sgdService->subirDocumento(
            $request->validated(),
            $request->file('archivo'),
            $request->user()->id
        );

        return ApiResponse::created($documento, 'Documento subido y registrado exitosamente.');
    }

    public function generarEnlace(int $id, Request $request): JsonResponse
    {
        $documento = DocumentoInstitucional::findOrFail($id);
        
        // Autorización estricta mediada por la Policy
        $this->authorize('view', $documento);

        $url = $this->sgdService->generarUrlFirmada($documento->id, $request->user()->id);

        return ApiResponse::ok(['url_firmada' => $url], 'Enlace seguro generado.');
    }

    public function descargar(Request $request, int $documentoId)
    {
        // Esta ruta debe protegerse con el middleware 'signed' en api.php
        if (!$request->hasValidSignature()) {
            return ApiResponse::error('El enlace ha expirado o es inválido.', null, 403);
        }

        $documento = DocumentoInstitucional::findOrFail($documentoId);

        if (!Storage::disk('local')->exists($documento->ruta_archivo)) {
            return ApiResponse::noEncontrado('El archivo físico no se encuentra en el servidor.');
        }

        return Storage::disk('local')->download(
            $documento->ruta_archivo,
            $documento->nombre,
            ['Content-Type' => $documento->tipo_mime]
        );
    }
}
