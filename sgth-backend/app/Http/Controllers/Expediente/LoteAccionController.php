<?php

namespace App\Http\Controllers\Expediente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expediente\IncrementoEnLoteRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Expediente\LoteAccion;
use App\Services\Expediente\IncrementoEnLoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Los actos que nacen juntos (diseño de Acciones de Personal, 8.5; fase 4.6):
 * hoy, el incremento de remuneración de los obreros.
 */
class LoteAccionController extends Controller
{
    public function __construct(private IncrementoEnLoteService $incrementos)
    {
    }

    public function index(): JsonResponse
    {
        return ApiResponse::ok($this->incrementos->listar(), 'Lotes de acciones.');
    }

    public function show(LoteAccion $lote): JsonResponse
    {
        return ApiResponse::ok($this->incrementos->detalle($lote), 'Lote de acciones.');
    }

    /** Quiénes recibirían el incremento y cuánto, sin preparar nada. */
    public function previsualizar(IncrementoEnLoteRequest $request): JsonResponse
    {
        return ApiResponse::ok($this->incrementos->previsualizar($request->validated()), 'Vista previa del incremento.');
    }

    /** Prepara un borrador por obrero, todos o ninguno. */
    public function store(IncrementoEnLoteRequest $request): JsonResponse
    {
        $lote = $this->incrementos->preparar($request->validated(), $request->user()->id);

        return ApiResponse::created($this->incrementos->detalle($lote), 'Incremento en lote preparado.');
    }

    /**
     * Suscribe y registra lo que del lote sigue en trámite. Pide los dos
     * permisos, como hacerlo acto por acto.
     */
    public function registrar(LoteAccion $lote): JsonResponse
    {
        $resultado = $this->incrementos->registrar($lote);

        return ApiResponse::ok(
            [...$resultado, 'lote' => $this->incrementos->detalle($lote->fresh())],
            $resultado['fallidos'] === []
                ? "Se registraron {$resultado['registrados']} actos del lote."
                : "Se registraron {$resultado['registrados']} actos; ".count($resultado['fallidos']).' quedaron pendientes.'
        );
    }

    /** Anula lo que del lote sigue en trámite. */
    public function descartar(Request $request, LoteAccion $lote): JsonResponse
    {
        $datos = $request->validate(
            ['motivo' => ['required', 'string', 'min:5', 'max:300']],
            [],
            ['motivo' => 'motivo'],
        );

        $anulados = $this->incrementos->descartar($lote, trim($datos['motivo']));

        return ApiResponse::ok(
            ['anulados' => $anulados, 'lote' => $this->incrementos->detalle($lote->fresh())],
            "Se anularon {$anulados} actos del lote."
        );
    }
}
