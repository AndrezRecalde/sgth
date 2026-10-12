<?php

namespace App\Http\Controllers\Autoservicio;

use App\Exceptions\ReglaNegocioException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Autoservicio\MiDocumentoLaboralResource;
use App\Http\Responses\ApiResponse;
use App\Services\Autoservicio\MisDocumentosLaboralesService;
use App\Services\Expediente\AccionPersonalPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * «Mis documentos laborales» (diseño, 9.13; TH 26; fase 6.3a). Siempre sobre
 * el servidor de la cuenta: el de otro no existe para quien pregunta.
 */
class MisDocumentosLaboralesController extends Controller
{
    public function __construct(private readonly MisDocumentosLaboralesService $documentos) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::ok(
            MiDocumentoLaboralResource::collection($this->documentos->listar($this->servidorDe($request)))->resolve($request),
            'Mis documentos laborales.'
        );
    }

    public function pdf(Request $request, int $movimiento, AccionPersonalPdfService $pdf): Response
    {
        $documento = $this->documentos->obtener($this->servidorDe($request), $movimiento);
        $resultado = $pdf->generarContent($documento->id);

        return response($resultado['content'], 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$resultado['filename'].'"',
        ]);
    }

    public function acuse(Request $request, int $movimiento): JsonResponse
    {
        $documento = $this->documentos->acusarRecibo($this->servidorDe($request), $movimiento);

        return ApiResponse::ok(
            (new MiDocumentoLaboralResource($documento))->resolve($request),
            'Acusó recibo del documento.'
        );
    }

    /** La cuenta sin servidor —un usuario técnico— no tiene documentos. */
    private function servidorDe(Request $request): int
    {
        return (int) ($request->user()?->servidor_id
            ?? throw new ReglaNegocioException('Su cuenta no está vinculada a un servidor: no tiene documentos laborales.'));
    }
}
