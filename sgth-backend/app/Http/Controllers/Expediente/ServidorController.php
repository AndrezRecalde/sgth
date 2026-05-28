<?php

namespace App\Http\Controllers\Expediente;

use App\Contracts\Expediente\ExpedienteServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Expediente\StoreServidorBasicoRequest;
use App\Http\Requests\Expediente\StoreServidorRequest;
use App\Http\Requests\Expediente\UpdateServidorRequest;
use App\Http\Resources\Expediente\ServidorResource;
use App\Http\Responses\ApiResponse;
use App\Models\Expediente\Servidor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServidorController extends Controller
{
    private ExpedienteServiceInterface $expedienteService;

    public function __construct(ExpedienteServiceInterface $expedienteService)
    {
        $this->expedienteService = $expedienteService;
    }

    public function storeBasico(StoreServidorBasicoRequest $request): JsonResponse
    {
        $this->authorize('crear', Servidor::class);
        $servidor = $this->expedienteService->crearServidorBasico(
            $request->validated()
        );
        return ApiResponse::created(
            new ServidorResource($servidor),
            'Datos básicos del servidor registrados.'
        );
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('verAny', Servidor::class);

        $paginador = $this->expedienteService->listarServidores(
            $request->all()
        );

        return response()->json([
            'exito'   => true,
            'mensaje' => 'Listado de servidores.',
            'datos'   => ServidorResource::collection($paginador->items()),
            'meta'    => [
                'pagina_actual' => $paginador->currentPage(),
                'por_pagina'    => $paginador->perPage(),
                'total'         => $paginador->total(),
                'ultima_pagina' => $paginador->lastPage(),
            ],
        ]);
    }

    public function store(StoreServidorRequest $request): JsonResponse
    {
        $this->authorize('crear', Servidor::class);

        $servidor = $this->expedienteService->crearServidor($request->validated());

        return ApiResponse::created(new ServidorResource($servidor), 'Expediente del servidor creado con éxito.');
    }

    public function show(int $id): JsonResponse
    {
        $servidor = $this->expedienteService->obtenerExpedienteCompleto($id);

        $this->authorize('ver', $servidor);

        return ApiResponse::ok(new ServidorResource($servidor), 'Expediente detallado');
    }

    public function update(UpdateServidorRequest $request, int $id): JsonResponse
    {
        $servidorModel = Servidor::findOrFail($id);
        $this->authorize('actualizar', $servidorModel);

        $servidor = $this->expedienteService->actualizarServidor($id, $request->validated());

        return ApiResponse::ok(new ServidorResource($servidor), 'Expediente actualizado exitosamente.');
    }

    public function sinUsuario(Request $request): JsonResponse
    {
        $this->authorize('verAny', Servidor::class);

        $servidores = Servidor::whereDoesntHave('usuario')
            ->when(
                $request->filled('search'),
                fn($q) => $q->where(function ($q) use ($request) {
                    $q->where('cedula', 'ilike', "%{$request->search}%")
                      ->orWhere('nombre', 'ilike', "%{$request->search}%")
                      ->orWhere('apellido', 'ilike', "%{$request->search}%");
                })
            )
            ->select('id', 'cedula', 'nombre', 'segundo_nombre',
                     'apellido', 'segundo_apellido')
            ->orderBy('apellido')
            ->limit(20)
            ->get()
            ->map(fn($s) => [
                'id'     => $s->id,
                'cedula' => $s->cedula,
                'nombre_completo' => trim(
                    "{$s->apellido} {$s->segundo_apellido} {$s->nombre} {$s->segundo_nombre}"
                ),
            ]);

        return ApiResponse::ok($servidores, 'Servidores sin usuario del sistema.');
    }
}
