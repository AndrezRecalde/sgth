<?php

namespace App\Http\Controllers\Expediente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expediente\GuardarClausulaDeLicenciaRequest;
use App\Http\Requests\Expediente\GuardarContratoColectivoRequest;
use App\Http\Requests\Expediente\GuardarSalarioBasicoRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Expediente\ClausulaDeLicencia;
use App\Models\Expediente\ContratoColectivo;
use App\Services\Expediente\ContratoColectivoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

/**
 * El contrato colectivo de los obreros y el salario básico de cada año
 * (diseño de Acciones de Personal, 9.15; fase 4.6).
 */
class ContratoColectivoController extends Controller
{
    public function __construct(private ContratoColectivoService $contratos)
    {
    }

    /** Los contratos, el que rige hoy con dictamen, y los salarios básicos. */
    public function index(): JsonResponse
    {
        return ApiResponse::ok([
            'vigente'          => $this->contratos->vigente()?->load('clausulasDeLicencia'),
            'contratos'        => $this->contratos->listar(),
            'salarios_basicos' => $this->contratos->salariosBasicos(),
        ], 'Contrato colectivo.');
    }

    public function store(GuardarContratoColectivoRequest $request): JsonResponse
    {
        $contrato = $this->contratos->guardar(
            $request->safe()->except('archivo'),
            $request->file('archivo'),
            null,
            $request->user()->id,
        );

        return ApiResponse::ok($contrato, 'Contrato colectivo registrado.', 201);
    }

    /** Corrige el contrato. Va por POST: lleva el PDF, y PUT no admite multipart. */
    public function update(GuardarContratoColectivoRequest $request, ContratoColectivo $contratoColectivo): JsonResponse
    {
        $contrato = $this->contratos->guardar(
            $request->safe()->except('archivo'),
            $request->file('archivo'),
            $contratoColectivo,
            $request->user()->id,
        );

        return ApiResponse::ok($contrato, 'Contrato colectivo actualizado.');
    }

    /** Descarga el PDF: el archivo vive en el disco privado. */
    public function descargar(ContratoColectivo $contratoColectivo): mixed
    {
        if (! $contratoColectivo->documento_ruta || ! Storage::disk('local')->exists($contratoColectivo->documento_ruta)) {
            return ApiResponse::error('Este contrato colectivo no tiene el PDF adjunto.', null, 404);
        }

        return Storage::disk('local')->download(
            $contratoColectivo->documento_ruta,
            $contratoColectivo->documento_nombre ?: "contrato-colectivo-{$contratoColectivo->numero}.pdf"
        );
    }

    /** Añade una cláusula de licencia al contrato (fase 5.3). */
    public function guardarClausulaDeLicencia(GuardarClausulaDeLicenciaRequest $request, ContratoColectivo $contratoColectivo): JsonResponse
    {
        $clausula = $this->contratos->guardarClausulaDeLicencia(
            $contratoColectivo, $request->validated(), null, $request->user()->id,
        );

        return ApiResponse::ok($clausula, 'Cláusula de licencia registrada.', 201);
    }

    public function actualizarClausulaDeLicencia(
        GuardarClausulaDeLicenciaRequest $request, ContratoColectivo $contratoColectivo, ClausulaDeLicencia $clausula,
    ): JsonResponse {
        $this->exigirQueSeaDelContrato($contratoColectivo, $clausula);

        $clausula = $this->contratos->guardarClausulaDeLicencia(
            $contratoColectivo, $request->validated(), $clausula, $request->user()->id,
        );

        return ApiResponse::ok($clausula, 'Cláusula de licencia actualizada.');
    }

    public function eliminarClausulaDeLicencia(ContratoColectivo $contratoColectivo, ClausulaDeLicencia $clausula): JsonResponse
    {
        $this->exigirQueSeaDelContrato($contratoColectivo, $clausula);

        $this->contratos->eliminarClausulaDeLicencia($clausula);

        return ApiResponse::ok(null, 'Cláusula de licencia eliminada.');
    }

    /** Registra o corrige el salario básico de un año. */
    public function guardarSalarioBasico(GuardarSalarioBasicoRequest $request): JsonResponse
    {
        return ApiResponse::ok(
            $this->contratos->guardarSalarioBasico($request->validated(), $request->user()->id),
            'Salario básico guardado.'
        );
    }

    /** La cláusula de otro contrato no se toca por la ruta de este. */
    private function exigirQueSeaDelContrato(ContratoColectivo $contrato, ClausulaDeLicencia $clausula): void
    {
        abort_unless($clausula->contrato_colectivo_id === $contrato->id, 404);
    }
}
