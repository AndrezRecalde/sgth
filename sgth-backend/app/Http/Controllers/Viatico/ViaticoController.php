<?php

namespace App\Http\Controllers\Viatico;

use App\Contracts\Viatico\ViaticoServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Viatico\LiquidarViaticoRequest;
use App\Http\Requests\Viatico\SolicitarViaticoRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class ViaticoController extends Controller
{
    public function __construct(private ViaticoServiceInterface $viaticoService) {}

    public function index(): JsonResponse
    {
        $viaticos = \App\Models\Viatico\Viatico::with(['servidor'])
            ->orderByDesc('created_at')
            ->paginate(50);

        return ApiResponse::ok($viaticos, 'Viáticos listados.');
    }

    public function show(int $id): JsonResponse
    {
        $viatico = \App\Models\Viatico\Viatico::with([
            'servidor',
            'tramos.empresa.catalogo',
            'tramos.origenProvincia',
            'tramos.origenCanton',
            'tramos.destinoProvincia',
            'tramos.destinoCanton',
            'tramos.autorizacionVuelo',
            'liquidacion.detallesFactura.categoria',
            'servidoresAcompanantes.servidor',
        ])->findOrFail($id);

        return ApiResponse::ok($viatico, 'Detalle del viático.');
    }

    public function store(SolicitarViaticoRequest $request): JsonResponse
    {
        // El servidor es el usuario autenticado
        $servidorId = $request->user()->servidor?->id;

        if (!$servidorId) {
            return ApiResponse::error(
                'El usuario autenticado no tiene un expediente ' .
                'de servidor vinculado.',
                422
            );
        }

        $viatico = $this->viaticoService->solicitar(
            $servidorId,
            $request->validated(),
            $request->user()->id
        );

        return ApiResponse::created(
            $viatico,
            'Solicitud de viático creada con éxito.'
        );
    }

    public function solicitar(int $servidorId, SolicitarViaticoRequest $request): JsonResponse
    {
        $viatico = $this->viaticoService->solicitar(
            $servidorId,
            $request->validated(),
            $request->user()->id
        );

        return ApiResponse::created($viatico, 'Solicitud de viático creada con éxito. El monto ha sido calculado automáticamente basado en la normativa del MRL.');
    }

    public function liquidar(int $viaticoId, LiquidarViaticoRequest $request): JsonResponse
    {
        $liquidacion = $this->viaticoService->liquidar(
            $viaticoId,
            $request->validated(),
            $request->user()->id
        );

        $viatico = $liquidacion->viatico;

        return ApiResponse::ok([
            'liquidacion' => $liquidacion,
            'viatico' => $viatico
        ], 'Viático liquidado correctamente. Facturas procesadas considerando el 70/30 de la normativa del MRL.');
    }

    public function aprobar(int $id): JsonResponse
    {
        $viatico = \App\Models\Viatico\Viatico::findOrFail($id);
        $this->authorize('gestionar-viaticos');

        if ($viatico->estado->value !== 'solicitado') {
            return ApiResponse::error(
                'Solo se pueden aprobar viáticos en estado solicitado.',
                422
            );
        }

        // Modalidad anticipo
        $montoAnticipo = match($viatico->modalidad_anticipo) {
            'total'        => $viatico->monto_calculado,
            'parcial'      => $viatico->monto_anticipo, // ya definido
            'sin_anticipo' => 0.00,
            default        => $viatico->monto_calculado,
        };

        $viatico->update([
            'estado'         => \App\Enums\EstadoViatico::APROBADO,
            'monto_anticipo' => $montoAnticipo,
        ]);

        return ApiResponse::ok(
            $viatico->fresh(),
            'Viático aprobado correctamente.'
        );
    }

    public function entregarAnticipo(int $id): JsonResponse
    {
        $viatico = \App\Models\Viatico\Viatico::findOrFail($id);
        $this->authorize('gestionar-viaticos');

        if ($viatico->estado->value !== 'aprobado') {
            return ApiResponse::error(
                'Solo se puede entregar anticipo a viáticos aprobados.',
                422
            );
        }

        $viatico->update([
            'estado'     => \App\Enums\EstadoViatico::CON_ANTICIPO,
            'updated_by' => request()->user()->id,
        ]);

        return ApiResponse::ok(
            $viatico->fresh(),
            'Anticipo entregado. El viático queda listo para la comisión.'
        );
    }

    public function marcarEnComision(int $id): JsonResponse
    {
        $viatico = \App\Models\Viatico\Viatico::findOrFail($id);
        $this->authorize('gestionar-viaticos');

        $estadosValidos = [
            \App\Enums\EstadoViatico::CON_ANTICIPO->value,
            \App\Enums\EstadoViatico::APROBADO->value,
        ];

        if (!in_array($viatico->estado->value, $estadosValidos)) {
            return ApiResponse::error(
                'El viático debe estar aprobado o con anticipo ' .
                'para marcarse en comisión.',
                422
            );
        }

        $viatico->update([
            'estado'     => \App\Enums\EstadoViatico::EN_COMISION,
            'updated_by' => request()->user()->id,
        ]);

        return ApiResponse::ok(
            $viatico->fresh(),
            'Viático marcado en comisión.'
        );
    }

    public function marcarPendienteLiquidacion(int $id): JsonResponse
    {
        $viatico = \App\Models\Viatico\Viatico::findOrFail($id);
        $this->authorize('gestionar-viaticos');

        if ($viatico->estado->value !== 'en_comision') {
            return ApiResponse::error(
                'El viático debe estar en comisión para ' .
                'marcarse pendiente de liquidación.',
                422
            );
        }

        $viatico->update([
            'estado'     => \App\Enums\EstadoViatico::PENDIENTE_LIQUIDACION,
            'updated_by' => request()->user()->id,
        ]);

        return ApiResponse::ok(
            $viatico->fresh(),
            'Viático marcado como pendiente de liquidación.'
        );
    }

    public function contabilizar(int $id): JsonResponse
    {
        $this->authorize('gestionar-viaticos');

        $liquidacion = $this->viaticoService->contabilizar(
            $id,
            request()->user()->id
        );

        return ApiResponse::ok(
            $liquidacion,
            'Viático contabilizado correctamente.'
        );
    }
}
