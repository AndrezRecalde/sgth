<?php

namespace App\Http\Controllers\Expediente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expediente\GuardarActaFiniquitoRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Expediente\MovimientoPersonal;
use App\Services\Expediente\TerminacionObreroService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Las terminaciones de obreros y sus actas de finiquito (diseño de Acciones de
 * Personal, 9.15; fase 4.5).
 */
class TerminacionObreroController extends Controller
{
    public function __construct(private TerminacionObreroService $terminaciones)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::ok(
            $this->terminaciones->listar($request->boolean('sin_acta')),
            'Terminaciones de obreros.'
        );
    }

    /** Registra o corrige el acta de finiquito de una terminación registrada. */
    public function guardarActa(GuardarActaFiniquitoRequest $request, MovimientoPersonal $movimiento): JsonResponse
    {
        $acta = $this->terminaciones->guardarActa(
            $movimiento,
            $request->safe()->except('archivo'),
            $request->file('archivo'),
            $request->user()->id,
        );

        return ApiResponse::ok($acta, 'Acta de finiquito registrada.');
    }

    /** Descarga el PDF del acta: el archivo vive en el disco privado. */
    public function descargarActa(MovimientoPersonal $movimiento): mixed
    {
        $acta = $movimiento->actaFiniquito;

        if (! $acta?->documento_ruta || ! Storage::disk('local')->exists($acta->documento_ruta)) {
            return ApiResponse::error('Esta terminación no tiene el acta de finiquito adjunta.', null, 404);
        }

        return Storage::disk('local')->download(
            $acta->documento_ruta,
            $acta->documento_nombre ?: "acta-finiquito-{$movimiento->codigo_registro}.pdf"
        );
    }
}
