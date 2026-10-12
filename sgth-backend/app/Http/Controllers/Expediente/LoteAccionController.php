<?php

namespace App\Http\Controllers\Expediente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expediente\IncrementoEnLoteRequest;
use App\Http\Requests\Expediente\RevisionDeClasificacionRequest;
use App\Http\Requests\Expediente\TraspasoDePuestoRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Estructura\Puesto;
use App\Models\Expediente\LoteAccion;
use App\Services\Expediente\IncrementoEnLoteService;
use App\Services\Expediente\LoteEnteroService;
use App\Services\Expediente\RevisionDeClasificacionService;
use App\Services\Expediente\TraspasoDePuestoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Los actos que nacen juntos (diseño de Acciones de Personal, 8.5): el
 * incremento de remuneración de los obreros (fase 4.6) y, sobre puestos
 * ocupados, el traspaso a otra unidad (7.1a) y la revisión de la
 * clasificación (7.1b). Estos dos se registran y se anulan enteros.
 */
class LoteAccionController extends Controller
{
    public function __construct(
        private IncrementoEnLoteService $incrementos,
        private TraspasoDePuestoService $traspasos,
        private RevisionDeClasificacionService $revisiones,
        private LoteEnteroService $enteros,
    ) {
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

    /** Quiénes recibirían el acto del traspaso, y lo que lo impide (fase 7.1a). */
    public function previsualizarTraspaso(TraspasoDePuestoRequest $request): JsonResponse
    {
        $datos = $request->validated();

        return ApiResponse::ok(
            $this->traspasos->previsualizar(
                Puesto::findOrFail($datos['puesto_id']),
                isset($datos['unidad_destino_id']) ? (int) $datos['unidad_destino_id'] : null,
                $request->user(),
            ),
            'Vista previa del traspaso.'
        );
    }

    /** Prepara un borrador por ocupante, todos o ninguno (fase 7.1a). */
    public function traspasar(TraspasoDePuestoRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $lote = $this->traspasos->preparar(Puesto::findOrFail($datos['puesto_id']), $datos, $request->user());

        return ApiResponse::created($this->incrementos->detalle($lote), 'Traspaso de puesto preparado.');
    }

    /**
     * Lo que recibiría cada ocupante de la revisión, y lo que la impide
     * (fase 7.1b).
     */
    public function previsualizarRevision(RevisionDeClasificacionRequest $request): JsonResponse
    {
        return ApiResponse::ok(
            $this->revisiones->previsualizar($request->validated(), $request->user()),
            'Vista previa de la revisión de la clasificación.'
        );
    }

    /** Prepara un borrador por ocupante de cada puesto, todos o ninguno (fase 7.1b). */
    public function revisar(RevisionDeClasificacionRequest $request): JsonResponse
    {
        $lote = $this->revisiones->preparar($request->validated(), $request->user());

        return ApiResponse::created($this->incrementos->detalle($lote), 'Revisión de la clasificación preparada.');
    }

    /**
     * Suscribe y registra lo que del lote sigue en trámite. Pide los dos
     * permisos, como hacerlo acto por acto. El traspaso, todos o ninguno.
     */
    public function registrar(LoteAccion $lote): JsonResponse
    {
        $resultado = LoteEnteroService::esEntero($lote)
            ? $this->enteros->registrar($lote)
            : $this->incrementos->registrar($lote);

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

        $anulados = LoteEnteroService::esEntero($lote)
            ? $this->enteros->descartar($lote, trim($datos['motivo']))
            : $this->incrementos->descartar($lote, trim($datos['motivo']));

        return ApiResponse::ok(
            ['anulados' => $anulados, 'lote' => $this->incrementos->detalle($lote->fresh())],
            "Se anularon {$anulados} actos del lote."
        );
    }

    /**
     * Deja sin efecto el traspaso o la revisión registrados: anula todos sus
     * actos, y el puesto y sus ocupantes vuelven a como estaban (7.1a, 7.1b).
     */
    public function revertir(Request $request, LoteAccion $lote): JsonResponse
    {
        $datos = $request->validate(
            ['motivo' => ['required', 'string', 'min:5', 'max:300']],
            [],
            ['motivo' => 'motivo'],
        );

        abort_unless(LoteEnteroService::esEntero($lote), 404);

        $anulados = $this->enteros->revertir($lote, trim($datos['motivo']));

        return ApiResponse::ok(
            ['anulados' => $anulados, 'lote' => $this->incrementos->detalle($lote->fresh())],
            "Se revirtió el lote: {$anulados} actos anulados."
        );
    }
}
