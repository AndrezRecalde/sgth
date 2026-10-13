<?php

namespace App\Http\Controllers\InventarioTi;

use App\Contracts\InventarioTi\DisponibilidadPrestamoServiceInterface;
use App\Contracts\InventarioTi\PrestamoEstadoServiceInterface;
use App\Contracts\InventarioTi\PrestamoServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\InventarioTi\EntregarPrestamoRequest;
use App\Http\Requests\InventarioTi\PrestamoDirectoRequest;
use App\Http\Requests\InventarioTi\RecibirPrestamoRequest;
use App\Http\Resources\InventarioTi\PrestamoEquipoResource;
use App\Http\Responses\ApiResponse;
use App\Models\InventarioTi\PrestamoEquipo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * El préstamo de equipos visto por TIC (fase 2.1): la bandeja, el trámite, el
 * préstamo directo y la agenda con quién tiene cada equipo.
 */
final class PrestamoEquipoController extends Controller
{
    public function __construct(
        private readonly PrestamoServiceInterface $prestamos,
        private readonly PrestamoEstadoServiceInterface $estados,
        private readonly DisponibilidadPrestamoServiceInterface $disponibilidad,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $pagina = $this->prestamos->listar($request->query());

        return ApiResponse::paginadoDe(
            $pagina,
            PrestamoEquipoResource::collection($pagina->getCollection())->resolve($request),
            'Préstamos de equipos.'
        );
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::ok(new PrestamoEquipoResource($this->prestamos->obtener($id)), 'Préstamo de equipos.');
    }

    /** Los contadores de las pestañas de la bandeja. */
    public function resumen(): JsonResponse
    {
        return ApiResponse::ok($this->prestamos->resumen(), 'Préstamos que piden atención.');
    }

    /** El acta del préstamo, `inline`: se mira antes de imprimirla para la firma. */
    public function acta(int $id): Response
    {
        $acta = $this->prestamos->generarActa($id);

        return response($acta['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $acta['filename'] . '"',
        ]);
    }

    public function directo(PrestamoDirectoRequest $request): JsonResponse
    {
        $prestamo = $this->prestamos->registrarDirecto($request->validated());

        return ApiResponse::created(new PrestamoEquipoResource($prestamo), "Préstamo {$prestamo->folio} entregado.");
    }

    public function aprobar(PrestamoEquipo $prestamo): JsonResponse
    {
        return ApiResponse::ok(new PrestamoEquipoResource($this->estados->aprobar($prestamo)), 'Préstamo aprobado: el horario queda reservado.');
    }

    public function rechazar(Request $request, PrestamoEquipo $prestamo): JsonResponse
    {
        $motivo = $this->motivo($request, 'motivo del rechazo');

        return ApiResponse::ok(new PrestamoEquipoResource($this->estados->rechazar($prestamo, $motivo)), 'Préstamo rechazado.');
    }

    public function cancelar(Request $request, PrestamoEquipo $prestamo): JsonResponse
    {
        $motivo = $this->motivo($request, 'motivo de la cancelación');

        return ApiResponse::ok(new PrestamoEquipoResource($this->estados->cancelar($prestamo, $motivo)), 'Préstamo cancelado.');
    }

    public function entregar(EntregarPrestamoRequest $request, PrestamoEquipo $prestamo): JsonResponse
    {
        return ApiResponse::ok(new PrestamoEquipoResource($this->estados->entregar($prestamo, $request->validated())), 'Entrega registrada.');
    }

    public function recibir(RecibirPrestamoRequest $request, PrestamoEquipo $prestamo): JsonResponse
    {
        return ApiResponse::ok(new PrestamoEquipoResource($this->estados->recibir($prestamo, $request->validated())), 'Devolución registrada.');
    }

    /** La agenda de TIC: con el folio y quién tiene cada equipo. */
    public function disponibilidad(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'desde'        => ['required', 'date'],
            'hasta'        => ['required', 'date', 'after_or_equal:desde'],
            'tipo_bien_id' => ['nullable', 'integer'],
        ]);

        return ApiResponse::ok(
            $this->disponibilidad->equipos($datos['desde'], $datos['hasta'], true, $datos['tipo_bien_id'] ?? null),
            'Disponibilidad de los equipos de préstamo.'
        );
    }

    private function motivo(Request $request, string $nombre): string
    {
        return $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:500']], [], ['motivo' => $nombre])['motivo'];
    }
}
