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
            // `whereYear` envuelve la columna en una función y deja el índice
            // sin usar; el rango del año sí lo aprovecha.
            ->when($request->filled('anio'), function ($q) use ($request) {
                $anio = $request->integer('anio');
                $q->whereBetween('fecha_solicitud', ["{$anio}-01-01", "{$anio}-12-31"]);
            })
            // `fecha_solicitud` es una fecha sin hora: sin desempatar por `id`,
            // dos trámites del mismo día quedaban en orden indeterminado y al
            // paginar una fila podía repetirse o perderse.
            ->orderByDesc('fecha_solicitud')
            ->orderByDesc('id');

        return ApiResponse::ok(
            $query->paginate($request->integer('per_page', 15)),
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
