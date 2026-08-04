<?php

namespace App\Http\Controllers\Disciplinario;

use App\Contracts\Disciplinario\DisciplinarioServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Disciplinario\AvanzarSumarioRequest;
use App\Http\Requests\Disciplinario\ResolverSumarioRequest;
use App\Http\Requests\Disciplinario\StoreSumarioRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Disciplinario\Sumario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DisciplinarioController extends Controller
{
    public function __construct(private DisciplinarioServiceInterface $disciplinarioService) {}

    public function index(Request $request): JsonResponse
    {
        $query = Sumario::with([
            'servidor:id,nombre,segundo_nombre,apellido,segundo_apellido,cedula',
            'sancion',
        ])
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->input('estado')))
            ->when($request->filled('servidor_id'), fn ($q) => $q->where('servidor_id', $request->integer('servidor_id')))
            ->when($request->filled('anio'), fn ($q) => $q->whereYear('fecha_apertura', $request->integer('anio')))
            ->orderByDesc('fecha_apertura');

        return ApiResponse::ok(
            $query->paginate($request->integer('per_page', 20)),
            'Sumarios administrativos.'
        );
    }

    public function store(StoreSumarioRequest $request): JsonResponse
    {
        $datos = $request->validated();

        $sumario = $this->disciplinarioService->abrirSumario(
            $datos['servidor_id'],
            $datos,
            $request->user()->id
        );

        return ApiResponse::created(
            $sumario->load('servidor'),
            'Sumario administrativo abierto.'
        );
    }

    public function show(Sumario $sumario): JsonResponse
    {
        return ApiResponse::ok(
            $sumario->load(['servidor', 'sancion', 'createdBy', 'updatedBy']),
            'Sumario administrativo.'
        );
    }

    public function avanzar(AvanzarSumarioRequest $request, Sumario $sumario): JsonResponse
    {
        $datos = $request->validated();

        $actualizado = $this->disciplinarioService->avanzarSumario(
            $sumario,
            $datos['estado'],
            $datos,
            $request->user()->id
        );

        return ApiResponse::ok($actualizado, 'Sumario actualizado.');
    }

    public function resolver(int $sumarioId, ResolverSumarioRequest $request): JsonResponse
    {
        $sumario = $this->disciplinarioService->resolverSumario(
            $sumarioId,
            $request->validated(),
            $request->user()->id
        );

        $mensaje = 'Sumario Administrativo resuelto exitosamente y sanción aplicada.';
        if ($request->validated('tipo_sancion') === 'destitucion') {
            $mensaje .= ' El servidor fue destituido: se generó la Cesación de Funciones en '
                .'borrador para revisión de Talento Humano.';
        }

        return ApiResponse::ok($sumario->load('sancion'), $mensaje);
    }
}
