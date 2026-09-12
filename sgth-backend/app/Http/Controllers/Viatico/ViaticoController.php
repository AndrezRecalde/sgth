<?php

namespace App\Http\Controllers\Viatico;

use App\Contracts\Viatico\ViaticoServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Viatico\LiquidarViaticoRequest;
use App\Http\Requests\Viatico\SolicitarViaticoRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Viatico\Viatico;
use Illuminate\Http\JsonResponse;

class ViaticoController extends Controller
{
    public function __construct(private ViaticoServiceInterface $viaticoService) {}

    public function index(
        \Illuminate\Http\Request $request
    ): JsonResponse {
        $this->authorize('verAny', Viatico::class);

        $query = Viatico::with(['servidor'])
            // created_at es timestamp(0): sin desempate por id, dos páginas
            // del mismo resultado pueden solaparse.
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        // Sin alcance sobre todos, solo los viáticos en los que viaja: como
        // titular o como acompañante. Antes el filtro `servidor_id` lo mandaba
        // el cliente y, sin él, cualquiera veía los de todos.
        if (! $request->user()->can('veTodos', Viatico::class)) {
            $servidorId = $request->user()->servidor_id;

            $query->where(function ($q) use ($servidorId) {
                $q->where('servidor_id', $servidorId)
                    ->orWhereHas('todosServidores', fn ($s) => $s->where('servidor_id', $servidorId));
            });
        }

        // Filtro por estado
        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        // Filtro por zona
        if ($request->filled('zona')) {
            $query->where('zona', $request->input('zona'));
        }

        // Filtro por servidor (para el servidor logueado)
        if ($request->filled('servidor_id')) {
            $query->where(
                'servidor_id',
                $request->input('servidor_id')
            );
        }

        // Búsqueda por código
        if ($request->filled('search')) {
            $query->where(
                'codigo_viatico',
                'like',
                '%' . $request->input('search') . '%'
            );
        }

        $perPage = (int) $request->input('per_page', 50);
        $viaticos = $query->paginate($perPage);

        return ApiResponse::ok(
            $viaticos,
            'Viáticos listados.'
        );
    }

    public function show(string $identificador): JsonResponse
    {
        // Acepta tanto id numérico como codigo_viatico
        $viatico = is_numeric($identificador)
            ? \App\Models\Viatico\Viatico::with([
                'servidor.puesto.cargo',
                'servidor.puesto.unidadAdministrativa',
                'tramos.empresa.catalogo',
                'tramos.origenProvincia',
                'tramos.origenCanton',
                'tramos.destinoProvincia',
                'tramos.destinoCanton',
                'tramos.autorizacionVuelo',
                'liquidacion.actividades',
                'liquidacion.detallesFactura.categoria',
                'liquidacion.jefeFinanciero',
                'liquidacion.contabilizadoPor',
                'todosServidores.servidor.puesto.cargo',
                'autorizacionesVuelo',
            ])->findOrFail((int) $identificador)
            : \App\Models\Viatico\Viatico::with([
                'servidor.puesto.cargo',
                'servidor.puesto.unidadAdministrativa',
                'tramos.empresa.catalogo',
                'tramos.origenProvincia',
                'tramos.origenCanton',
                'tramos.destinoProvincia',
                'tramos.destinoCanton',
                'tramos.autorizacionVuelo',
                'liquidacion.actividades',
                'liquidacion.detallesFactura.categoria',
                'liquidacion.jefeFinanciero',
                'liquidacion.contabilizadoPor',
                'todosServidores.servidor.puesto.cargo',
                'autorizacionesVuelo',
            ])->where('codigo_viatico', $identificador)
              ->firstOrFail();

        $this->authorize('ver', $viatico);

        return ApiResponse::ok(
            $viatico,
            'Detalle del viático.'
        );
    }

    public function update(
        \Illuminate\Http\Request $request,
        int $id
    ): JsonResponse {
        $viatico = Viatico::findOrFail($id);

        $this->authorize('editar', $viatico);

        // El monto lo calcula el sistema. Un `null` no borra nada —la columna
        // no lo admite y el formulario lo manda siempre—; un valor, solo lo
        // fija quien opera los viáticos.
        if ($request->input('monto_calculado') === null) {
            $request->offsetUnset('monto_calculado');
        } else {
            $this->authorize('cambiarMonto', $viatico);
        }

        // Solo editable si no está contabilizado
        if ($viatico->estado->value === 'contabilizado') {
            return ApiResponse::error(
                'No se puede editar un viático contabilizado.',
                422
            );
        }

        $data = $request->validate([
            'zona'             => 'sometimes|in:dentro_provincia,fuera_provincia,exterior',
            'datetime_salida'  => 'sometimes|date',
            'datetime_llegada' => 'sometimes|date|after:datetime_salida',
            'justificacion'    => 'sometimes|string|min:10|max:2000',
            'modalidad_anticipo' => 'sometimes|in:sin_anticipo,total,parcial',
            'monto_calculado'  => 'sometimes|nullable|numeric|min:0',
            'tipo_viaje'       => 'sometimes|nullable|string|max:100',
            'pais_destino'     => 'sometimes|nullable|string|max:100',
            'servidores_acompanantes'   => ['nullable', 'array'],
            'servidores_acompanantes.*' => ['integer', 'exists:servidores,id'],
        ]);

        // Recalcular total_dias si cambian las fechas
        if (
            isset($data['datetime_salida']) ||
            isset($data['datetime_llegada'])
        ) {
            $salida  = \Carbon\Carbon::parse(
                $data['datetime_salida'] ?? $viatico->datetime_salida
            );
            $llegada = \Carbon\Carbon::parse(
                $data['datetime_llegada'] ?? $viatico->datetime_llegada
            );
            $data['total_dias'] = (float) $salida
                ->copy()->startOfDay()
                ->diffInDays($llegada->copy()->startOfDay()) + 1;

            // Recalcular monto si es nacional
            if (
                ($data['zona'] ?? $viatico->zona) !== 'exterior' &&
                !isset($data['monto_calculado'])
            ) {
                $servidor = \App\Models\Expediente\Servidor::with('puesto.cargo')
                    ->findOrFail($viatico->servidor_id);

                $denominacion = strtolower(
                    $servidor->puesto?->cargo?->nombre ?? ''
                );
                $esAutoridad = str_contains($denominacion, 'director')
                            || str_contains($denominacion, 'prefecto')
                            || str_contains($denominacion, 'coordinador');
                $nivel = $esAutoridad ? 'autoridad' : 'servidor';
                $zona  = $data['zona'] ?? $viatico->zona;

                $tarifa = \App\Models\Viatico\TarifaViatico::where('zona', $zona)
                    ->where('nivel', $nivel)
                    ->where('tipo_tarifa', 'con_pernocte')
                    ->first();

                if ($tarifa) {
                    $data['monto_calculado'] = round(
                        (float) $tarifa->valor_diario *
                        $data['total_dias'],
                        2
                    );
                }
            }
        }

        $data['updated_by'] = $request->user()->id;
        $viatico->update($data);

        // Actualizar acompañantes si vienen en el request
        if ($request->has('servidores_acompanantes')) {
            // Eliminar acompañantes anteriores
            \App\Models\Viatico\ViaticoServidor::where(
                'viatico_id', $viatico->id
            )->where('es_titular', false)->delete();

            // Agregar los nuevos
            foreach (
                $request->input('servidores_acompanantes', [])
                as $servidorId
            ) {
                \App\Models\Viatico\ViaticoServidor::create([
                    'viatico_id'  => $viatico->id,
                    'servidor_id' => $servidorId,
                    'es_titular'  => false,
                ]);
            }
        }

        return ApiResponse::ok(
            $viatico->fresh(),
            'Viático actualizado correctamente.'
        );
    }

    public function store(SolicitarViaticoRequest $request): JsonResponse
    {
        \Illuminate\Support\Facades\Log::info(
            'ViaticoController@store - datos recibidos',
            $request->validated()
        );

        // El servidor es el usuario autenticado
        $servidor = $request->user()->servidor;

        if (!$servidor) {
            return ApiResponse::error(
                'El usuario autenticado no tiene un expediente ' .
                'de servidor vinculado.',
                null,
                422
            );
        }

        $viatico = $this->viaticoService->solicitar(
            $servidor->id,
            $this->datosDeSolicitud($request),
            $request->user()->id
        );

        return ApiResponse::created(
            $viatico,
            'Solicitud de viático creada con éxito.'
        );
    }

    /**
     * Registrar un viático a nombre de un servidor. Lo hace quien opera los
     * viáticos (lo comprueba la request); antes lo podía hacer cualquier
     * usuario con cualquier id.
     */
    public function solicitar(int $servidorId, SolicitarViaticoRequest $request): JsonResponse
    {
        $viatico = $this->viaticoService->solicitar(
            $servidorId,
            $this->datosDeSolicitud($request),
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

    public function aprobar(
        int $id,
        \Illuminate\Http\Request $request
    ): JsonResponse
    {
        $this->authorize('aprobar', Viatico::findOrFail($id));

        $viatico = $this->viaticoService->aprobar(
            $id,
            $request->only([
                'coeficiente_exterior',
                'pais_destino',
            ])
        );
        return ApiResponse::ok(
            $viatico,
            'Viático aprobado correctamente.'
        );
    }

    public function entregarAnticipo(int $id): JsonResponse
    {
        $this->authorize('operar', Viatico::findOrFail($id));

        $viatico = $this->viaticoService->entregarAnticipo($id);

        return ApiResponse::ok(
            $viatico,
            'Anticipo entregado. El viático queda listo para la comisión.'
        );
    }

    public function cancelar(
        int $id,
        \Illuminate\Http\Request $request
    ): JsonResponse
    {
        $this->authorize('cancelar', Viatico::findOrFail($id));

        $viatico = $this->viaticoService->cancelar(
            $id,
            $request->user()->id
        );
        return ApiResponse::ok(
            $viatico,
            'Viático cancelado correctamente.'
        );
    }

    public function rechazar(
        int $id,
        \Illuminate\Http\Request $request
    ): JsonResponse
    {
        $this->authorize('rechazar', Viatico::findOrFail($id));

        $viatico = $this->viaticoService->rechazar(
            $id,
            $request->user()->id
        );
        return ApiResponse::ok(
            $viatico,
            'Viático rechazado correctamente.'
        );
    }

    public function devolverCorreccion(
        int $id,
        \Illuminate\Http\Request $request
    ): JsonResponse {
        $this->authorize('revisarLiquidacion', Viatico::findOrFail($id));

        $viatico = $this->viaticoService->devolverCorreccion(
            $id,
            $request->user()->id
        );
        return ApiResponse::ok(
            $viatico,
            'Viático devuelto a corrección correctamente.'
        );
    }

    public function marcarEnComision(int $id): JsonResponse
    {
        $viatico = Viatico::findOrFail($id);
        $this->authorize('operar', $viatico);

        $estadosValidos = [
            \App\Enums\EstadoViatico::CON_ANTICIPO->value,
            \App\Enums\EstadoViatico::APROBADO->value,
        ];

        if (!in_array($viatico->estado->value, $estadosValidos)) {
            return ApiResponse::error(
                'El viático debe estar aprobado o con anticipo ' .
                'para marcarse en comisión.',
                null,
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
        $viatico = Viatico::findOrFail($id);
        $this->authorize('operar', $viatico);

        if ($viatico->estado->value !== 'en_comision') {
            return ApiResponse::error(
                'El viático debe estar en comisión para ' .
                'marcarse pendiente de liquidación.',
                null,
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
        $this->authorize('revisarLiquidacion', Viatico::findOrFail($id));

        $liquidacion = $this->viaticoService->contabilizar(
            $id,
            request()->user()->id
        );

        return ApiResponse::ok(
            $liquidacion,
            'Viático contabilizado correctamente.'
        );
    }

    /**
     * Lo validado, sin el monto si quien pide no opera los viáticos: el del
     * exterior lo fija Financiero al aprobar, no el servidor al solicitar.
     */
    private function datosDeSolicitud(SolicitarViaticoRequest $request): array
    {
        $datos = $request->validated();

        if (! $request->user()->can('cambiarMonto', Viatico::class)) {
            unset($datos['monto_calculado']);
        }

        return $datos;
    }
}
