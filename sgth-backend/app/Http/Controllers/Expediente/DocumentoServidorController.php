<?php

namespace App\Http\Controllers\Expediente;

use App\Contracts\Expediente\ExpedienteServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Expediente\StoreDocumentoServidorRequest;
use App\Http\Resources\Expediente\DocumentoServidorResource;
use App\Http\Responses\ApiResponse;
use App\Models\Expediente\Servidor;
use Illuminate\Http\JsonResponse;

class DocumentoServidorController extends Controller
{
    private ExpedienteServiceInterface $expedienteService;

    public function __construct(ExpedienteServiceInterface $expedienteService)
    {
        $this->expedienteService = $expedienteService;
    }

    public function store(StoreDocumentoServidorRequest $request, int $servidorId): JsonResponse
    {
        $servidor = Servidor::findOrFail($servidorId);
        
        // Autorización: solo UATH o el mismo servidor (según definamos, por ahora usamos 'actualizar')
        $this->authorize('actualizar', $servidor);

        $documento = $this->expedienteService->subirDocumento(
            $servidorId,
            $request->validated(),
            $request->file('archivo')
        );

        return ApiResponse::created(new DocumentoServidorResource($documento), 'Documento anexado al expediente correctamente.');
    }
}
