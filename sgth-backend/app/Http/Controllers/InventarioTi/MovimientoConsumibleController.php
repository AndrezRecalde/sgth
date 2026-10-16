<?php

namespace App\Http\Controllers\InventarioTi;

use App\Contracts\InventarioTi\MovimientoConsumibleServiceInterface;
use App\Enums\MotivoAjusteConsumible;
use App\Http\Controllers\Controller;
use App\Http\Requests\InventarioTi\RegistrarEntregaConsumibleRequest;
use App\Http\Requests\InventarioTi\RegistrarIngresoConsumibleRequest;
use App\Http\Responses\ApiResponse;
use App\Models\InventarioTi\Consumible;
use App\Models\InventarioTi\MovimientoConsumible;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Los movimientos de los consumibles y su kárdex (fase 5.4a): apertura,
 * ingresos, entregas, ajustes y anulación con motivo.
 */
final class MovimientoConsumibleController extends Controller
{
    public function __construct(private readonly MovimientoConsumibleServiceInterface $movimientos) {}

    public function kardex(Request $request, Consumible $consumible): JsonResponse
    {
        $datos = $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        return ApiResponse::ok($this->movimientos->kardex($consumible, $datos['desde'] ?? null, $datos['hasta'] ?? null), 'Kárdex del consumible.');
    }

    public function apertura(Request $request, Consumible $consumible): JsonResponse
    {
        $datos = $request->validate([
            'fecha'       => ['required', 'date', 'before_or_equal:today'],
            'cantidad'    => ['required', 'integer', 'min:1', 'max:99999'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ], [], ['observacion' => 'observación']);
        $m = $this->movimientos->registrarApertura($consumible, $datos, $request->user());

        return ApiResponse::created($this->movimientos->presentar($m), 'Apertura registrada.');
    }

    public function ingresos(RegistrarIngresoConsumibleRequest $request): JsonResponse
    {
        $hechos = $this->movimientos->registrarIngreso($request->validated(), $request->user());

        return ApiResponse::created($hechos->map(fn (MovimientoConsumible $m) => $this->movimientos->presentar($m))->all(), 'Ingreso registrado.');
    }

    public function entregas(RegistrarEntregaConsumibleRequest $request): JsonResponse
    {
        $hechos = $this->movimientos->registrarEntrega($request->validated(), $request->user());

        return ApiResponse::created($hechos->map(fn (MovimientoConsumible $m) => $this->movimientos->presentar($m))->all(), 'Entrega registrada.');
    }

    public function ajuste(Request $request, Consumible $consumible): JsonResponse
    {
        $datos = $request->validate([
            'fecha'         => ['required', 'date', 'before_or_equal:today'],
            'cantidad'      => ['required', 'integer', 'not_in:0', 'min:-99999', 'max:99999'],
            'motivo_ajuste' => ['required', Rule::enum(MotivoAjusteConsumible::class)],
            'observacion'   => ['required', 'string', 'min:5', 'max:500'],
        ], ['cantidad.not_in' => 'El ajuste suma o resta: no puede ser cero.'], ['motivo_ajuste' => 'motivo', 'observacion' => 'detalle']);
        $m = $this->movimientos->registrarAjuste($consumible, $datos, $request->user());

        return ApiResponse::created($this->movimientos->presentar($m), 'Ajuste registrado.');
    }

    public function anular(Request $request, MovimientoConsumible $movimiento): JsonResponse
    {
        $datos = $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:300']]);
        $m = $this->movimientos->anular($movimiento, $datos['motivo'], $request->user());

        return ApiResponse::ok($this->movimientos->presentar($m), 'Movimiento anulado: deja de contar, pero sigue en el kárdex.');
    }
}
