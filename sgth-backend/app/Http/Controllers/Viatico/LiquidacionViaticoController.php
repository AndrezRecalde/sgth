<?php
namespace App\Http\Controllers\Viatico;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Viatico\ActividadLiquidacion;
use App\Models\Viatico\FacturaViatico;
use App\Models\Viatico\LiquidacionViatico;
use App\Models\Viatico\Viatico;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LiquidacionViaticoController extends Controller
{
    /**
     * Obtener o crear la liquidación del viático
     * Se crea vacía cuando el viático entra en
     * estado pendiente_liquidacion
     */
    public function obtenerOCrear(
        int $viaticoId
    ): JsonResponse {
        $viatico = Viatico::findOrFail($viaticoId);

        $liquidacion = LiquidacionViatico::firstOrCreate(
            ['viatico_id' => $viaticoId],
            [
                'total_facturas'      => 0,
                'diferencia_devolver' => 0,
                'fecha_liquidacion'   => now()->toDateString(),
                'created_by'          => request()->user()->id,
            ]
        );

        $liquidacion->load([
            'actividades',
            'detallesFactura.categoria',
        ]);

        return ApiResponse::ok(
            $liquidacion,
            'Liquidación obtenida.'
        );
    }

    // ── ACTIVIDADES ────────────────────────────────

    public function listarActividades(
        int $viaticoId
    ): JsonResponse {
        $liquidacion = $this->getLiquidacion($viaticoId);
        return ApiResponse::ok(
            $liquidacion->actividades,
            'Actividades listadas.'
        );
    }

    public function guardarActividades(
        Request $request,
        int $viaticoId
    ): JsonResponse {
        $data = $request->validate([
            'actividades'               => ['required', 'array', 'min:1'],
            'actividades.*.fecha'       => ['required', 'date'],
            'actividades.*.hora_inicio' => ['nullable', 'string'],
            'actividades.*.hora_fin'    => ['nullable', 'string'],
            'actividades.*.descripcion' => ['required', 'string', 'min:3'],
            'actividades.*.lugar'       => ['required', 'string'],
        ]);

        $liquidacion = $this->getLiquidacion($viaticoId);

        DB::transaction(function () use (
            $liquidacion, $data
        ) {
            // Eliminar actividades anteriores y reemplazar
            $liquidacion->actividades()->delete();

            foreach ($data['actividades'] as $i => $act) {
                ActividadLiquidacion::create([
                    'liquidacion_viatico_id' => $liquidacion->id,
                    'fecha'       => $act['fecha'],
                    'hora_inicio' => $act['hora_inicio'] ?? null,
                    'hora_fin'    => $act['hora_fin']    ?? null,
                    'descripcion' => $act['descripcion'],
                    'lugar'       => $act['lugar'],
                    'orden'       => $i + 1,
                ]);
            }
        });

        $liquidacion->load('actividades');

        return ApiResponse::ok(
            $liquidacion->actividades,
            'Actividades guardadas correctamente.'
        );
    }

    // ── FACTURAS ───────────────────────────────────

    public function listarFacturas(
        int $viaticoId
    ): JsonResponse {
        $liquidacion = $this->getLiquidacion($viaticoId);
        return ApiResponse::ok(
            $liquidacion->detallesFactura()->with('categoria')->get(),
            'Facturas listadas.'
        );
    }

    public function guardarFacturas(
        Request $request,
        int $viaticoId
    ): JsonResponse {
        $data = $request->validate([
            'facturas'                        => ['required', 'array', 'min:1'],
            'facturas.*.categoria_factura_id' => ['required', 'integer'],
            'facturas.*.nombre_proveedor'     => ['required', 'string'],
            'facturas.*.monto'                => ['required', 'numeric', 'min:0.01'],
            'facturas.*.tipo_comprobante'     => ['required', 'in:factura,ticket,recibo,otro'],
            'facturas.*.numero_factura'       => ['nullable', 'string'],
            'facturas.*.numero_ticket'        => ['nullable', 'string'],
            'facturas.*.ruc_proveedor'        => ['nullable', 'string'],
            'facturas.*.fecha_factura'        => ['nullable', 'date'],
            'facturas.*.detalle'              => ['nullable', 'string'],
        ]);

        $liquidacion = $this->getLiquidacion($viaticoId);

        // Validar RUC para factura y recibo
        foreach ($data['facturas'] as $i => $f) {
            if (in_array($f['tipo_comprobante'], ['factura', 'recibo'])
                && empty($f['ruc_proveedor'])
            ) {
                return ApiResponse::error(
                    "La factura #{$i} requiere RUC del proveedor.",
                    422
                );
            }
        }

        DB::transaction(function () use (
            $liquidacion, $data
        ) {
            // Eliminar facturas anteriores y reemplazar
            $liquidacion->detallesFactura()->delete();

            foreach ($data['facturas'] as $f) {
                FacturaViatico::create([
                    'liquidacion_viatico_id' => $liquidacion->id,
                    'categoria_factura_id'   => $f['categoria_factura_id'],
                    'tipo_comprobante'       => $f['tipo_comprobante'],
                    'numero_factura'         => $f['numero_factura']  ?? null,
                    'numero_ticket'          => $f['numero_ticket']   ?? null,
                    'fecha_factura'          => $f['fecha_factura']   ?? null,
                    'ruc_proveedor'          => $f['ruc_proveedor']   ?? null,
                    'nombre_proveedor'       => $f['nombre_proveedor'],
                    'detalle'                => $f['detalle']         ?? null,
                    'monto'                  => $f['monto'],
                ]);
            }

            // Recalcular totales
            $liquidacion->load('detallesFactura.categoria');
            $total = $liquidacion->detallesFactura->sum('monto');

            $viatico     = $liquidacion->viatico;
            $montoAsig   = (float) ($viatico->monto_calculado ?? 0);
            $anticipo    = (float) ($viatico->monto_anticipo  ?? 0);
            $modalidad   = $viatico->modalidad_anticipo instanceof \BackedEnum
                ? $viatico->modalidad_anticipo->value
                : (string) $viatico->modalidad_anticipo;

            $idsViatico = \App\Models\Viatico\CategoriaFactura
                ::where('grupo', 'viatico')
                ->pluck('id')->toArray();

            $totalHA = $liquidacion->detallesFactura
                ->whereIn('categoria_factura_id', $idsViatico)
                ->sum('monto');

            $diferencia = $modalidad === 'sin_anticipo'
                ? 0
                : (($totalHA >= $anticipo) ? 0
                    : round($anticipo - $totalHA, 2));

            $liquidacion->update([
                'total_facturas'      => round($total, 2),
                'diferencia_devolver' => $diferencia,
            ]);
        });

        $liquidacion->load('detallesFactura.categoria');

        return ApiResponse::ok(
            $liquidacion->detallesFactura,
            'Facturas guardadas correctamente.'
        );
    }

    // ── CONFIRMAR LIQUIDACIÓN ──────────────────────

    public function confirmar(
        int $viaticoId,
        Request $request
    ): JsonResponse {
        $viatico     = Viatico::findOrFail($viaticoId);
        $liquidacion = $this->getLiquidacion($viaticoId);

        $liquidacion->load(['actividades', 'detallesFactura']);

        if ($liquidacion->actividades->isEmpty()) {
            return ApiResponse::error(
                'Debe registrar al menos una actividad.',
                422
            );
        }

        if ($liquidacion->detallesFactura->isEmpty()) {
            return ApiResponse::error(
                'Debe registrar al menos un comprobante.',
                422
            );
        }

        $liquidacion->update([
            'fecha_liquidacion' => now()->toDateString(),
            'updated_by'        => $request->user()->id,
        ]);

        $viatico->update([
            'estado' => \App\Enums\EstadoViatico::LIQUIDADO,
        ]);

        return ApiResponse::ok(
            $viatico->fresh(),
            'Liquidación registrada correctamente.'
        );
    }

    private function getLiquidacion(
        int $viaticoId
    ): LiquidacionViatico {
        return LiquidacionViatico::firstOrCreate(
            ['viatico_id' => $viaticoId],
            [
                'total_facturas'      => 0,
                'diferencia_devolver' => 0,
                'fecha_liquidacion'   => now()->toDateString(),
                'created_by'          => request()->user()->id,
            ]
        );
    }
}
