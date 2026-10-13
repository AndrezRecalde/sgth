<?php

namespace App\Http\Controllers\Autoservicio;

use App\Contracts\InventarioTi\DisponibilidadPrestamoServiceInterface;
use App\Contracts\InventarioTi\PrestamoEstadoServiceInterface;
use App\Contracts\InventarioTi\PrestamoServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\InventarioTi\SolicitarPrestamoRequest;
use App\Http\Resources\InventarioTi\PrestamoEquipoResource;
use App\Http\Responses\ApiResponse;
use App\Models\InventarioTi\PrestamoEquipo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * El préstamo de equipos en el Portal (fase 2.1): la disponibilidad, pedir,
 * «Mis préstamos» y cancelar mientras no se haya entregado. Siempre los
 * propios; un préstamo ajeno responde como si no existiera.
 */
final class MisPrestamosController extends Controller
{
    public function __construct(
        private readonly PrestamoServiceInterface $prestamos,
        private readonly PrestamoEstadoServiceInterface $estados,
        private readonly DisponibilidadPrestamoServiceInterface $disponibilidad,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $servidorId = $request->user()->servidor_id;
        if (! $servidorId) {
            return ApiResponse::ok([], 'Su usuario no está vinculado a un expediente.');
        }

        $pagina = $this->prestamos->deServidor($servidorId, $request->query());

        return ApiResponse::paginadoDe(
            $pagina,
            PrestamoEquipoResource::collection($pagina->getCollection())->resolve($request),
            'Sus préstamos de equipos.'
        );
    }

    public function show(Request $request, PrestamoEquipo $prestamo): JsonResponse
    {
        if (! $this->esSuyo($request, $prestamo)) {
            return ApiResponse::noEncontrado('Ese préstamo no es suyo.');
        }

        return ApiResponse::ok(new PrestamoEquipoResource($this->prestamos->obtener($prestamo->id)), 'Su préstamo de equipos.');
    }

    public function store(SolicitarPrestamoRequest $request): JsonResponse
    {
        $servidorId = $request->user()->servidor_id;
        if (! $servidorId) {
            return ApiResponse::noEncontrado('Su usuario no está vinculado a un expediente: no puede pedir equipos.');
        }

        $prestamo = $this->prestamos->solicitar($servidorId, $request->validated());

        return ApiResponse::created(
            new PrestamoEquipoResource($prestamo),
            "Solicitud {$prestamo->folio} enviada. TIC le avisará cuando la apruebe."
        );
    }

    public function cancelar(Request $request, PrestamoEquipo $prestamo): JsonResponse
    {
        if (! $this->esSuyo($request, $prestamo)) {
            return ApiResponse::noEncontrado('Ese préstamo no es suyo.');
        }

        $motivo = $request->validate(['motivo' => ['nullable', 'string', 'max:500']])['motivo'] ?? null;

        return ApiResponse::ok(
            new PrestamoEquipoResource($this->estados->cancelar($prestamo, $motivo ?: 'Cancelado por el servidor.')),
            'Préstamo cancelado.'
        );
    }

    /** Ocupado y hasta cuándo, sin decir quién lo tiene (D15). */
    public function disponibilidad(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'desde'        => ['required', 'date'],
            'hasta'        => ['required', 'date', 'after_or_equal:desde'],
            'tipo_bien_id' => ['nullable', 'integer'],
        ]);

        return ApiResponse::ok(
            $this->disponibilidad->equipos($datos['desde'], $datos['hasta'], false, $datos['tipo_bien_id'] ?? null),
            'Disponibilidad de los equipos de préstamo.'
        );
    }

    private function esSuyo(Request $request, PrestamoEquipo $prestamo): bool
    {
        $servidorId = $request->user()->servidor_id;

        return $servidorId && (int) $prestamo->servidor_id === (int) $servidorId;
    }
}
