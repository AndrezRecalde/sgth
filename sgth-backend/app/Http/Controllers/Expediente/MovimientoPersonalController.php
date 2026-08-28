<?php

namespace App\Http\Controllers\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Expediente\CorregirMovimientoRequest;
use App\Http\Requests\Expediente\StoreMovimientoPersonalRequest;
use App\Http\Requests\Expediente\TransicionarMovimientoRequest;
use App\Http\Requests\Expediente\UpdateMovimientoPersonalRequest;
use App\Http\Resources\Expediente\MovimientoPersonalResource;
use App\Http\Responses\ApiResponse;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Services\Expediente\MovimientoPersonalService;
use App\Services\Expediente\MovimientoPersonalStateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MovimientoPersonalController extends Controller
{
    public function __construct(
        private MovimientoPersonalService $movimientoService,
        private MovimientoPersonalStateService $stateService,
    ) {
    }

    public function index(int $servidorId): JsonResponse
    {
        $servidor = Servidor::findOrFail($servidorId);

        $this->authorize('ver', $servidor);

        $movimientos = MovimientoPersonal::with(['unidadOrigen', 'unidadDestino', 'puestoOrigen.cargo', 'puestoDestino.cargo', 'puestoDestino.grupoOcupacional:id,rmu', 'autorizadoPor'])
            ->where('servidor_id', $servidorId)
            // El id desempata: varias acciones del mismo día es lo normal
            // (una cesación y el ingreso que la sigue, por ejemplo), y sin
            // criterio secundario Postgres las devuelve en orden arbitrario —
            // el listado parecía barajarse entre recargas.
            ->orderBy('fecha_efectiva', 'desc')
            ->orderByDesc('id')
            ->get();

        return ApiResponse::ok(
            MovimientoPersonalResource::collection($movimientos),
            'Historial inmutable de movimientos'
        );
    }

    /**
     * Bandeja transversal de acciones de personal, para revisarlas y
     * aprobarlas sin tener que entrar servidor por servidor. A diferencia de
     * index(), no se ancla a un expediente: la autorización la da el rol de la
     * ruta, no la política sobre un Servidor concreto.
     */
    public function bandeja(Request $request): JsonResponse
    {
        $query = MovimientoPersonal::with([
            'servidor:id,nombre,segundo_nombre,apellido,segundo_apellido,cedula',
            'unidadDestino:id,nombre',
            'puestoDestino.cargo:id,nombre',
            'puestoDestino.grupoOcupacional:id,rmu',
            'partidaPresupuestaria:id,codigo,descripcion',
        ])
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->input('estado')))
            ->when($request->filled('tipo_movimiento'), fn ($q) => $q->where('tipo_movimiento', $request->input('tipo_movimiento')))
            ->when($request->filled('servidor_id'), fn ($q) => $q->where('servidor_id', $request->integer('servidor_id')))
            ->when($request->filled('anio'), fn ($q) => $q->whereYear('fecha_efectiva', $request->integer('anio')))
            // created_at empata cuando se crean varias en el mismo segundo
            // (seeders, cargas masivas): el id garantiza orden estable.
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        return ApiResponse::ok(
            $query->paginate($request->integer('per_page', 20)),
            'Bandeja de acciones de personal.'
        );
    }

    /**
     * Detalle completo de una acción, para revisarla antes de aprobarla o
     * editarla. Trae las relaciones que el listado omite por peso.
     */
    public function show(MovimientoPersonal $movimiento): JsonResponse
    {
        $movimiento->load([
            'servidor:id,nombre,segundo_nombre,apellido,segundo_apellido,cedula,numero_papeleta_votacion,puesto_id,unidad_administrativa_id',
            'servidor.puesto.cargo:id,nombre',
            'servidor.puesto.partidaPresupuestaria:id,codigo,descripcion',
            'servidor.unidadAdministrativa:id,nombre',
            'servidor.contratoVigente',
            'unidadOrigen:id,nombre',
            'unidadDestino:id,nombre',
            'puestoOrigen.cargo:id,nombre',
            'puestoDestino.cargo:id,nombre',
            'puestoDestino.partidaPresupuestaria:id,codigo,descripcion',
            'puestoDestino.grupoOcupacional:id,rmu',
            'partidaPresupuestaria:id,codigo,descripcion',
            'partidaOrigen:id,codigo,descripcion',
            'autorizadoPor',
            'movimientoPrevio:id,tipo_movimiento,subtipo_movimiento,codigo_registro,fecha_efectiva',
            'cubreMovimiento:id,servidor_id,tipo_movimiento,subtipo_movimiento,codigo_registro,fecha_inicio,fecha_fin',
            'cubreMovimiento.servidor:id,nombre,apellido,cedula',
            'solicitudCertificacion:id,estado,dictamen,fecha_limite',
        ]);

        $this->authorize('ver', $movimiento->servidor);

        return ApiResponse::ok(
            new MovimientoPersonalResource($movimiento),
            'Detalle de la acción de personal.'
        );
    }

    public function store(StoreMovimientoPersonalRequest $request, int $servidorId): JsonResponse
    {
        $servidor = Servidor::findOrFail($servidorId);

        $this->authorize('actualizar', $servidor);

        $movimiento = $this->movimientoService->registrar(
            $servidorId, $request->validated()
        );

        return ApiResponse::created(
            new MovimientoPersonalResource($movimiento),
            'Movimiento registrado con éxito.'
        );
    }

    public function update(
        UpdateMovimientoPersonalRequest $request,
        MovimientoPersonal $movimiento
    ): JsonResponse {
        $this->authorize('actualizar', $movimiento->servidor);

        $actualizado = $this->movimientoService->actualizarBorrador(
            $movimiento, $request->validated()
        );

        return ApiResponse::ok(
            new MovimientoPersonalResource($actualizado),
            'Borrador actualizado.'
        );
    }

    public function transicionar(
        TransicionarMovimientoRequest $request,
        MovimientoPersonal $movimiento
    ): JsonResponse {
        $this->authorize('actualizar', $movimiento->servidor);

        $destino = EstadoAccionPersonal::from($request->validated('estado'));
        $datos   = $request->safe()->except('estado');

        $actualizado = $this->stateService->transicionar($movimiento, $destino, $datos);

        return ApiResponse::ok(
            new MovimientoPersonalResource($actualizado),
            'Transición aplicada con éxito.'
        );
    }

    public function corregir(
        CorregirMovimientoRequest $request,
        MovimientoPersonal $movimiento
    ): JsonResponse {
        $this->authorize('actualizar', $movimiento->servidor);

        $corregido = $this->stateService->corregir($movimiento, $request->validated());

        return ApiResponse::created(
            new MovimientoPersonalResource($corregido),
            'Corrección registrada con éxito.'
        );
    }
}
