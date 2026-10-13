<?php

namespace App\Http\Controllers\InventarioTi;

use App\Contracts\InventarioTi\BajaServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\InventarioTi\AprobarBajaRequest;
use App\Http\Requests\InventarioTi\ProponerBajaRequest;
use App\Http\Resources\InventarioTi\BajaBienResource;
use App\Http\Responses\ApiResponse;
use App\Models\InventarioTi\BajaBien;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * La baja de los equipos (fase 1.6): el técnico la propone con su informe y el
 * jefe de TIC la aprueba con el memorando o la rechaza.
 *
 * Antes `store` cambiaba el estado del equipo y respondía que el acta quedaba
 * pendiente: no se guardaba ni el motivo.
 */
final class BajaController extends Controller
{
    public function __construct(private readonly BajaServiceInterface $bajas) {}

    public function index(Request $request): JsonResponse
    {
        $pagina = $this->bajas->listar($request->query());

        return ApiResponse::paginadoDe(
            $pagina,
            BajaBienResource::collection($pagina->getCollection())->resolve($request),
            'Bajas de equipos.'
        );
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::ok(new BajaBienResource($this->bajas->obtener($id)), 'Baja del equipo.');
    }

    public function store(ProponerBajaRequest $request): JsonResponse
    {
        return ApiResponse::created(
            new BajaBienResource($this->bajas->proponer($request->validated())),
            'Baja propuesta. Queda esperando la aprobación del jefe de TIC.'
        );
    }

    public function aprobar(AprobarBajaRequest $request, BajaBien $baja): JsonResponse
    {
        return ApiResponse::ok(
            new BajaBienResource($this->bajas->aprobar($baja, $request->validated())),
            'Baja aprobada: el equipo salió del servicio.'
        );
    }

    public function rechazar(Request $request, BajaBien $baja): JsonResponse
    {
        $datos = $request->validate(
            ['motivo' => ['required', 'string', 'min:5', 'max:500']],
            [],
            ['motivo' => 'motivo del rechazo'],
        );

        return ApiResponse::ok(
            new BajaBienResource($this->bajas->rechazar($baja, $datos['motivo'])),
            'Baja rechazada. El equipo sigue en el inventario.'
        );
    }

    /** El PDF del memorando de Quipux que aprueba la baja. */
    public function adjuntarMemorando(Request $request, BajaBien $baja): JsonResponse
    {
        $request->validate(
            ['archivo' => ['required', 'file', 'mimes:pdf', 'max:10240']],
            [],
            ['archivo' => 'memorando en PDF'],
        );

        return ApiResponse::ok(
            new BajaBienResource($this->bajas->adjuntarMemorando($baja, $request->file('archivo'))),
            'Memorando adjuntado.'
        );
    }

    public function memorando(BajaBien $baja): StreamedResponse|JsonResponse
    {
        if (! $baja->memorando_archivo || ! Storage::disk('local')->exists($baja->memorando_archivo)) {
            return ApiResponse::noEncontrado('Esta baja no tiene el memorando adjunto.');
        }

        return Storage::disk('local')->download($baja->memorando_archivo, $baja->memorando_nombre ?? 'memorando.pdf');
    }

    public function entregaActivos(Request $request, BajaBien $baja): JsonResponse
    {
        $datos = $request->validate(
            ['fecha' => ['required', 'date', 'before_or_equal:today']],
            [],
            ['fecha' => 'fecha de entrega'],
        );

        return ApiResponse::ok(
            new BajaBienResource($this->bajas->registrarEntregaActivos($baja, $datos['fecha'])),
            'Entrega a Activos Fijos registrada.'
        );
    }

    /** El informe técnico de baja, `inline`: se mira antes de imprimirlo para la firma. */
    public function informe(int $id): Response
    {
        $informe = $this->bajas->generarInforme($id);

        return response($informe['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $informe['filename'] . '"',
        ]);
    }
}
