<?php

namespace App\Http\Controllers\Viatico;

use App\Contracts\Viatico\ViaticoServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Viatico\LiquidarViaticoRequest;
use App\Http\Requests\Viatico\SolicitarViaticoRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Viatico\Viatico;
use App\Services\Viatico\CalculoViaticoService;
use App\Services\Viatico\ComprobantesViaticoService;
use App\Services\Viatico\ViaticoEstadoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ViaticoController extends Controller
{
    public function __construct(
        private ViaticoServiceInterface $viaticoService,
        private ViaticoEstadoService $estados,
        private CalculoViaticoService $calculo,
    ) {}

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
        // `propios` es «Mis viáticos»: quien opera también tiene los suyos, y
        // los de todos los ve en la bandeja.
        if ($request->boolean('propios') || ! $request->user()->can('veTodos', Viatico::class)) {
            $servidorId = $request->user()->servidor_id;

            $query->where('servidor_id', $servidorId);
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
        $query = Viatico::with([
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
            'autorizacionesVuelo',
            'historial.usuario:id,usuario_ti,email,servidor_id',
            'historial.usuario.servidor:id,nombre,apellido',
            'firmantes',
            'partidaPresupuestaria',
        ]);

        $viatico = is_numeric($identificador)
            ? $query->findOrFail((int) $identificador)
            : $query->where('codigo_viatico', $identificador)->firstOrFail();

        $this->authorize('ver', $viatico);

        if ($viatico->liquidacion) {
            app(ComprobantesViaticoService::class)->conAlertas($viatico->liquidacion->detallesFactura, $viatico);
        }

        // La cuenta del viático viaja resuelta: el 70 % a justificar, lo
        // presentado, el 30 % que se reconoce sin comprobante y el saldo. El
        // frontend la rehacía por su cuenta, con otra fórmula.
        $viatico->setAttribute('calculo', $this->calculo->resumen($viatico));

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
            $this->estados->asegurarMontoAjeno($viatico, $request->user());
        }

        $this->estados->asegurarEditable($viatico, $request->user());

        $data = $request->validate([
            'zona'             => 'sometimes|in:dentro_provincia,fuera_provincia,exterior',
            'datetime_salida'  => 'sometimes|date',
            'datetime_llegada' => 'sometimes|date|after:datetime_salida',
            'justificacion'    => 'sometimes|string|min:10|max:2000',
            'modalidad_anticipo' => 'sometimes|in:sin_anticipo,total',
            'monto_calculado'  => 'sometimes|nullable|numeric|min:0',
            'tipo_viaje'       => 'sometimes|nullable|string|max:100',
            'pais_destino'     => 'sometimes|nullable|string|max:100',
        ]);

        // Al cambiar las fechas se rehace la cuenta: las noches y, con ellas,
        // lo que le corresponde al servidor. Lo resuelve el servicio de
        // cálculo, que es donde vive la fórmula.
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

            $this->calculo->asegurarPernocte($salida, $llegada);

            $data['noches'] = $this->calculo->noches($salida, $llegada);

            if (! isset($data['monto_calculado'])) {
                $servidor = \App\Models\Expediente\Servidor::with('puesto')
                    ->findOrFail($viatico->servidor_id);

                $data['monto_calculado'] = $this->calculo->derecho(
                    $servidor,
                    $data['zona'] ?? $this->valorZona($viatico),
                    $data['noches'],
                    $viatico->coeficiente_exterior !== null
                        ? (float) $viatico->coeficiente_exterior
                        : null
                );
            }
        }

        $data['updated_by'] = $request->user()->id;

        $viatico->update($data);

        return ApiResponse::ok(
            $viatico->fresh(),
            'Viático actualizado correctamente.'
        );
    }

    public function store(SolicitarViaticoRequest $request): JsonResponse
    {
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
        // La misma regla que al guardar los comprobantes por pasos: fuera de
        // las fechas del viaje no se reciben.
        app(ComprobantesViaticoService::class)->asegurarFechasDelViaje(
            Viatico::findOrFail($viaticoId),
            $request->validated('facturas', [])
        );

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

    public function aprobar(int $id, Request $request): JsonResponse
    {
        $this->authorize('aprobar', Viatico::findOrFail($id));

        $viatico = $this->estados->aprobar(
            $id,
            $request->user(),
            $request->only(['coeficiente_exterior', 'pais_destino'])
        );

        return ApiResponse::ok($viatico, 'Viático aprobado correctamente.');
    }

    public function entregarAnticipo(int $id, Request $request): JsonResponse
    {
        $this->authorize('operar', Viatico::findOrFail($id));

        // Financiero asigna la resolución y la partida al entregar el dinero
        // (decidido con ellos): sin eso no hay con qué respaldar el pago. La
        // partida sale del catálogo de Estructura, no de un campo de texto.
        $datos = $request->validate([
            'numero_resolucion'         => ['required', 'string', 'max:100'],
            'partida_presupuestaria_id' => ['required', 'integer', $this->partidaVigente()],
        ], $this->mensajesDelRespaldo());

        $viatico = $this->estados->entregarAnticipo($id, $request->user(), $datos);

        return ApiResponse::ok(
            $viatico,
            'Anticipo entregado. El viático queda listo para la comisión.'
        );
    }

    public function cancelar(int $id, Request $request): JsonResponse
    {
        $this->authorize('cancelar', Viatico::findOrFail($id));

        $viatico = $this->estados->cancelar($id, $request->user());

        return ApiResponse::ok($viatico, 'Viático cancelado correctamente.');
    }

    public function rechazar(int $id, Request $request): JsonResponse
    {
        $this->authorize('rechazar', Viatico::findOrFail($id));

        $viatico = $this->estados->rechazar($id, $request->user(), $this->motivo($request));

        return ApiResponse::ok($viatico, 'Viático rechazado correctamente.');
    }

    public function devolverCorreccion(int $id, Request $request): JsonResponse
    {
        $this->authorize('revisarLiquidacion', Viatico::findOrFail($id));

        $viatico = $this->estados->devolverCorreccion($id, $request->user(), $this->motivo($request));

        return ApiResponse::ok($viatico, 'Viático devuelto a corrección correctamente.');
    }

    public function marcarEnComision(int $id, Request $request): JsonResponse
    {
        $this->authorize('operar', Viatico::findOrFail($id));

        $viatico = $this->estados->marcarEnComision($id, $request->user());

        return ApiResponse::ok($viatico, 'Viático marcado en comisión.');
    }

    public function marcarPendienteLiquidacion(int $id, Request $request): JsonResponse
    {
        $this->authorize('operar', Viatico::findOrFail($id));

        $viatico = $this->estados->marcarPendienteLiquidacion($id, $request->user());

        return ApiResponse::ok($viatico, 'Viático marcado como pendiente de liquidación.');
    }

    public function contabilizar(int $id, Request $request): JsonResponse
    {
        $viatico = Viatico::findOrFail($id);

        $this->authorize('revisarLiquidacion', $viatico);

        // En un viático sin anticipo no hubo entrega, así que la resolución y
        // la partida se piden aquí. El que ya las tiene no las vuelve a pedir.
        $exigir = $viatico->numero_resolucion && $viatico->partida_presupuestaria_id
            ? 'nullable'
            : 'required';

        $datos = $request->validate([
            'numero_resolucion'         => [$exigir, 'string', 'max:100'],
            'partida_presupuestaria_id' => [$exigir, 'integer', $this->partidaVigente()],
        ], $this->mensajesDelRespaldo());

        $liquidacion = $this->estados->contabilizar($id, $request->user(), array_filter($datos));

        return ApiResponse::ok($liquidacion, 'Viático contabilizado correctamente.');
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

    /** Solo se imputa a una partida vigente del catálogo. */
    private function partidaVigente(): \Illuminate\Validation\Rules\Exists
    {
        return Rule::exists('partidas_presupuestarias', 'id')->where('activo', true);
    }

    /** @return array<string, string> */
    private function mensajesDelRespaldo(): array
    {
        return [
            'numero_resolucion.required'         => 'Indique el número de resolución.',
            'partida_presupuestaria_id.required' => 'Elija la partida presupuestaria.',
            'partida_presupuestaria_id.exists'   => 'Esa partida presupuestaria no está vigente.',
        ];
    }

    /** La zona como texto: el modelo la castea a enum. */
    private function valorZona(Viatico $viatico): string
    {
        return $viatico->zona instanceof \BackedEnum
            ? (string) $viatico->zona->value
            : (string) $viatico->zona;
    }

    /**
     * Rechazar y devolver a corrección le dicen al servidor que algo está mal:
     * sin el porqué no sabe qué corregir. La columna `motivo_rechazo` existía
     * y nunca se llenaba.
     */
    private function motivo(Request $request): string
    {
        return $request->validate([
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'motivo.required' => 'Indique el motivo.',
            'motivo.min'      => 'Explique el motivo: al menos 5 caracteres.',
        ])['motivo'];
    }
}
