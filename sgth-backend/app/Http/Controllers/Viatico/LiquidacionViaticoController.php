<?php
namespace App\Http\Controllers\Viatico;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Viatico\ActividadLiquidacion;
use App\Models\Viatico\LiquidacionViatico;
use App\Models\Viatico\Viatico;
use App\Services\Viatico\CalculoViaticoService;
use App\Services\Viatico\ComprobantesViaticoService;
use App\Services\Viatico\ViaticoEstadoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LiquidacionViaticoController extends Controller
{
    public function __construct(
        private ViaticoEstadoService $estados,
        private ComprobantesViaticoService $comprobantes,
        private CalculoViaticoService $calculo,
    ) {}

    /**
     * Obtener o crear la liquidación del viático
     * Se crea vacía cuando el viático entra en
     * estado pendiente_liquidacion
     */
    public function obtenerOCrear(
        int $viaticoId
    ): JsonResponse {
        $viatico = $this->autorizar($viaticoId, 'ver');

        // Abrirla solo la crea quien la va a llenar, y con el viático pendiente
        // de liquidación. Quien solo consulta —Talento Humano, un acompañante—
        // ve la que haya, o ninguna.
        $liquidacion = request()->user()->can('editar', $viatico)
            && $viatico->estado === \App\Enums\EstadoViatico::PENDIENTE_LIQUIDACION
            ? $this->getLiquidacion($viaticoId)
            : LiquidacionViatico::where('viatico_id', $viaticoId)->first();

        $liquidacion?->load([
            'actividades',
            'detallesFactura.categoria',
        ]);

        if ($liquidacion) {
            $this->comprobantes->conAlertas($liquidacion->detallesFactura, $viatico);
            // La misma cuenta que muestra la ficha del viático, para que la
            // pantalla de liquidación no tenga que rehacerla.
            $liquidacion->setAttribute('calculo', $this->calculo->resumen($viatico, $liquidacion));
        }

        return ApiResponse::ok(
            $liquidacion,
            'Liquidación obtenida.'
        );
    }

    // ── ACTIVIDADES ────────────────────────────────

    public function listarActividades(
        int $viaticoId
    ): JsonResponse {
        $this->autorizar($viaticoId, 'ver');
        $liquidacion = $this->liquidacionExistente($viaticoId);

        return ApiResponse::ok(
            $liquidacion?->actividades ?? [],
            'Actividades listadas.'
        );
    }

    public function guardarActividades(
        Request $request,
        int $viaticoId
    ): JsonResponse {
        $this->estados->asegurarLiquidacionAbierta($this->autorizar($viaticoId, 'editar'));

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
        $viatico = $this->autorizar($viaticoId, 'ver');
        $liquidacion = $this->liquidacionExistente($viaticoId);

        return ApiResponse::ok(
            $liquidacion
                ? $this->comprobantes->conAlertas($liquidacion->detallesFactura()->with('categoria')->get(), $viatico)
                : [],
            'Facturas listadas.'
        );
    }

    public function guardarFacturas(
        Request $request,
        int $viaticoId
    ): JsonResponse {
        $this->estados->asegurarLiquidacionAbierta($this->autorizar($viaticoId, 'editar'));

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
                // El 422 va en su sitio: como tercer argumento. Puesto en el
                // segundo se colaba en el cuerpo como si fuera el detalle del
                // error, y el código de estado salía bien de pura casualidad,
                // porque 422 es el valor por defecto.
                return ApiResponse::error(
                    "La factura #{$i} requiere RUC del proveedor.",
                    null,
                    422
                );
            }
        }

        DB::transaction(function () use (
            $liquidacion, $data
        ) {
            // Se reemplazan todos, pero los que no cambiaron conservan la
            // revisión de Financiero: corregir un comprobante observado no
            // obliga a revisar de nuevo los que ya estaban aceptados.
            $this->comprobantes->reemplazar($liquidacion, array_map(fn (array $f) => [
                'categoria_factura_id' => $f['categoria_factura_id'],
                'tipo_comprobante'     => $f['tipo_comprobante'],
                'numero_factura'       => $f['numero_factura']  ?? null,
                'numero_ticket'        => $f['numero_ticket']   ?? null,
                'fecha_factura'        => $f['fecha_factura']   ?? null,
                'ruc_proveedor'        => $f['ruc_proveedor']   ?? null,
                'nombre_proveedor'     => $f['nombre_proveedor'],
                'detalle'              => $f['detalle']         ?? null,
                'monto'                => $f['monto'],
            ], $data['facturas']));

            // La cuenta se rehace con la fórmula única: cuentan todos los
            // comprobantes hasta el 70 %, y el 30 % se reconoce igual.
            $this->calculo->guardarEn($liquidacion);
        });

        $liquidacion->load('detallesFactura.categoria');

        return ApiResponse::ok(
            $liquidacion->detallesFactura,
            'Facturas guardadas correctamente.'
        );
    }

    /**
     * Financiero acepta u observa un comprobante. Observar pide motivo: es lo
     * que el servidor leerá para corregirlo.
     */
    public function revisarFactura(
        Request $request,
        int $viaticoId,
        int $factura
    ): JsonResponse {
        $viatico = $this->autorizar($viaticoId, 'revisarLiquidacion');

        $datos = $request->validate([
            'decision'    => ['required', 'in:aceptada,observada'],
            'observacion' => ['required_if:decision,observada', 'nullable', 'string', 'min:5', 'max:500'],
        ], [
            'observacion.required_if' => 'Indique qué está mal en el comprobante.',
        ]);

        $revisada = $this->comprobantes->revisar(
            $viatico, $factura, $request->user(), $datos['decision'], $datos['observacion'] ?? null
        );

        return ApiResponse::ok(
            $this->comprobantes->conAlertas(collect([$revisada->load('categoria')]), $viatico)->first(),
            $datos['decision'] === 'aceptada' ? 'Comprobante aceptado.' : 'Comprobante observado.'
        );
    }

    // ── CONFIRMAR LIQUIDACIÓN ──────────────────────

    public function confirmar(
        int $viaticoId,
        Request $request
    ): JsonResponse {
        $this->autorizar($viaticoId, 'editar');

        // Sin crearla: confirmar una liquidación que no existe se rechaza igual
        // que una vacía. Lo resuelve el servicio de estados, que además exige
        // que el viático esté pendiente de liquidación: antes se confirmaba
        // desde cualquier estado, incluso recién solicitado.
        $viatico = $this->estados->confirmarLiquidacion($viaticoId, $request->user());

        return ApiResponse::ok(
            $viatico,
            'Liquidación registrada correctamente.'
        );
    }

    /**
     * La liquidación del viático, abriéndola si aún no existe.
     *
     * Solo para lo que escribe. Antes la usaban también los dos listados, que
     * son GET: pedir la lista de actividades creaba la liquidación, con lo que
     * abrir la pantalla dejaba una fila aunque no se registrara nada. Un GET no
     * debe cambiar el estado del sistema.
     *
     * Comprueba primero que el viático exista. Sin eso, un id inventado no daba
     * un 404 sino una violación de clave foránea contra `liquidaciones_viatico`
     * —un 500 con la traza de Postgres en la cara—.
     */
    private function getLiquidacion(
        int $viaticoId
    ): LiquidacionViatico {
        Viatico::findOrFail($viaticoId);

        return LiquidacionViatico::firstOrCreate(
            ['viatico_id' => $viaticoId],
            [
                'total_facturas'    => 0,
                'fecha_liquidacion' => now()->toDateString(),
                'created_by'        => request()->user()->id,
            ]
        );
    }

    /**
     * La liquidación si la hay, y nada si el viático aún no tiene ninguna.
     *
     * Que un viático no esté liquidado no es un error: es el estado normal
     * hasta que alguien registra sus actividades y comprobantes. Lo que sí es
     * un error es preguntar por un viático que no existe.
     */
    private function liquidacionExistente(
        int $viaticoId
    ): ?LiquidacionViatico {
        Viatico::findOrFail($viaticoId);

        return LiquidacionViatico::where('viatico_id', $viaticoId)->first();
    }

    /**
     * El viático, si el usuario puede hacer con él lo que pide.
     *
     * La liquidación no comprobaba de quién era el viático: cualquiera leía,
     * reemplazaba y confirmaba la de otro. La presenta el titular o quien opera
     * los viáticos (`editar`); la lee además quien puede ver el viático.
     */
    private function autorizar(int $viaticoId, string $accion): Viatico
    {
        $viatico = Viatico::findOrFail($viaticoId);

        $this->authorize($accion, $viatico);

        return $viatico;
    }
}
