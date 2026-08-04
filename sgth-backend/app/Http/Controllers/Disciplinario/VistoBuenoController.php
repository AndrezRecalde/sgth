<?php

namespace App\Http\Controllers\Disciplinario;

use App\Enums\EstadoVistoBueno;
use App\Http\Controllers\Controller;
use App\Http\Requests\Disciplinario\StoreVistoBuenoRequest;
use App\Http\Requests\Disciplinario\TransicionarVistoBuenoRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Disciplinario\VistoBueno;
use App\Services\Disciplinario\VistoBuenoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class VistoBuenoController extends Controller
{
    public function __construct(private readonly VistoBuenoService $vistoBuenoService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = VistoBueno::with([
            'servidor:id,nombre,segundo_nombre,apellido,segundo_apellido,cedula',
            'movimientoPersonal:id,codigo_registro,estado',
        ])
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->input('estado')))
            ->when($request->filled('servidor_id'), fn ($q) => $q->where('servidor_id', $request->integer('servidor_id')))
            ->when($request->filled('anio'), fn ($q) => $q->whereYear('fecha_solicitud', $request->integer('anio')))
            ->orderByDesc('fecha_solicitud');

        return ApiResponse::ok(
            $query->paginate($request->integer('per_page', 20)),
            'Trámites de visto bueno.'
        );
    }

    public function store(StoreVistoBuenoRequest $request): JsonResponse
    {
        $datos = $request->validated();

        $vistoBueno = $this->vistoBuenoService->solicitar(
            $datos['servidor_id'],
            $datos,
            $request->user()->id
        );

        return ApiResponse::created(
            $vistoBueno->load('servidor'),
            'Solicitud de visto bueno registrada.'
        );
    }

    public function show(VistoBueno $vistoBueno): JsonResponse
    {
        return ApiResponse::ok(
            $vistoBueno->load(['servidor', 'movimientoPersonal', 'createdBy', 'updatedBy']),
            'Trámite de visto bueno.'
        );
    }

    public function transicionar(
        TransicionarVistoBuenoRequest $request,
        VistoBueno $vistoBueno
    ): JsonResponse {
        $datos   = $request->validated();
        $destino = EstadoVistoBueno::from($datos['estado']);

        $actualizado = $this->vistoBuenoService->transicionar(
            $vistoBueno,
            $destino,
            $datos,
            $request->user()->id
        );

        $mensaje = $destino === EstadoVistoBueno::CONCEDIDO
            ? 'Visto bueno concedido. Se generó la Cesación de Funciones en borrador para revisión de Talento Humano.'
            : "Trámite actualizado a '{$destino->etiqueta()}'.";

        return ApiResponse::ok($actualizado, $mensaje);
    }
}
