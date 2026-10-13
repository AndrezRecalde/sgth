<?php

namespace App\Http\Controllers\InventarioTi;

use App\Contracts\InventarioTi\CustodiaServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\InventarioTi\StoreAsignacionBienRequest;
use App\Http\Requests\InventarioTi\TraspasarAsignacionRequest;
use App\Http\Resources\InventarioTi\AsignacionBienResource;
use App\Http\Responses\ApiResponse;
use App\Models\InventarioTi\AsignacionBien;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * La custodia de los equipos (fase 1.3): entregar, traspasar, devolver, el
 * memorando y el acta. `index` y `show` eran muñones que devolvían `[]` y el
 * eco del id.
 */
final class AsignacionBienController extends Controller
{
    public function __construct(
        private readonly CustodiaServiceInterface $custodia
    ) {}

    public function index(Request $request): JsonResponse
    {
        $asignaciones = $this->custodia->listar($request->query());

        return ApiResponse::paginadoDe(
            $asignaciones,
            AsignacionBienResource::collection($asignaciones->getCollection())->resolve($request),
            'Custodias de equipos.'
        );
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::ok(new AsignacionBienResource($this->custodia->obtener($id)), 'Custodia del equipo.');
    }

    public function store(StoreAsignacionBienRequest $request): JsonResponse
    {
        $asignacion = $this->custodia->entregar($request->validated());

        return ApiResponse::created(new AsignacionBienResource($asignacion), 'Equipo entregado en custodia.');
    }

    public function traspasar(TraspasarAsignacionRequest $request, AsignacionBien $asignacion): JsonResponse
    {
        $nueva = $this->custodia->traspasar($asignacion, $request->validated());

        return ApiResponse::created(new AsignacionBienResource($nueva), 'Custodia traspasada.');
    }

    /** La devolución a TIC. La desvinculación la usa también (fase 7.4a). */
    public function devolver(Request $request, AsignacionBien $asignacion): JsonResponse
    {
        $datos = $request->validate([
            'fecha_devolucion' => ['required', 'date', 'before_or_equal:today'],
            'observaciones'    => ['nullable', 'string', 'max:1000'],
            'soporte_anterior' => ['nullable', 'string', 'max:30'],
        ], [], ['fecha_devolucion' => 'fecha de devolución', 'observaciones' => 'observaciones']);

        $asignacion = $this->custodia->devolver(
            $asignacion, $datos['fecha_devolucion'], $datos['observaciones'] ?? null,
            $this->custodia->aceptaSoporteAnterior() ? ($datos['soporte_anterior'] ?? null) : null,
        );

        return ApiResponse::ok(new AsignacionBienResource($asignacion), 'Devolución registrada.');
    }

    /** El PDF del memorando de Quipux que respalda la entrega o el traspaso. */
    public function adjuntarMemorando(Request $request, AsignacionBien $asignacion): JsonResponse
    {
        $request->validate(
            ['archivo' => ['required', 'file', 'mimes:pdf', 'max:10240']],
            [],
            ['archivo' => 'memorando en PDF'],
        );

        return ApiResponse::ok(
            new AsignacionBienResource($this->custodia->adjuntarMemorando($asignacion, $request->file('archivo'))),
            'Memorando adjuntado.',
        );
    }

    public function memorando(AsignacionBien $asignacion): StreamedResponse|JsonResponse
    {
        if (! $asignacion->memorando_archivo || ! Storage::disk('local')->exists($asignacion->memorando_archivo)) {
            return ApiResponse::noEncontrado('Esta custodia no tiene el memorando adjunto.');
        }

        return Storage::disk('local')->download($asignacion->memorando_archivo, $asignacion->memorando_nombre ?? 'memorando.pdf');
    }

    /** El acta de entrega-recepción, `inline`: se mira antes de imprimirla para la firma. */
    public function acta(int $id): Response
    {
        $acta = $this->custodia->generarActa($id);

        return response($acta['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $acta['filename'] . '"',
        ]);
    }
}
