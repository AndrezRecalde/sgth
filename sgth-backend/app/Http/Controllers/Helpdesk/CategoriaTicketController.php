<?php

namespace App\Http\Controllers\Helpdesk;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Helpdesk\CategoriaTicket;
use App\Rules\UnicoSinMayusculas;
use App\Services\Helpdesk\SubunidadesTic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Las categorías de los tickets (D7; fase 3.5): de qué se trata, si lleva el
 * equipo y qué subunidad la atiende por defecto. No se borran: una que ya
 * tiene tickets se desactiva y deja de ofrecerse.
 */
final class CategoriaTicketController extends Controller
{
    public function __construct(private readonly SubunidadesTic $subunidades) {}

    public function index(): JsonResponse
    {
        $categorias = CategoriaTicket::with('unidadTic:id,nombre')
            ->withCount('tickets')
            ->orderByDesc('activa')->orderBy('nombre')
            ->get()
            ->map(fn (CategoriaTicket $c) => [
                'id' => $c->id, 'nombre' => $c->nombre, 'descripcion' => $c->descripcion, 'es_hardware' => $c->es_hardware,
                'es_consumibles' => $c->es_consumibles,
                'activa' => $c->activa, 'unidad_tic_id' => $c->unidad_tic_id, 'unidad_tic' => $c->unidadTic?->nombre,
                'tickets_count' => $c->tickets_count,
            ]);

        return ApiResponse::ok($categorias, 'Categorías de los tickets.');
    }

    public function store(Request $request): JsonResponse
    {
        return ApiResponse::created(CategoriaTicket::create($this->validar($request)), 'Categoría registrada.');
    }

    public function update(Request $request, CategoriaTicket $categoria): JsonResponse
    {
        $categoria->update($this->validar($request, $categoria));

        return ApiResponse::ok($categoria, $categoria->activa ? 'Categoría actualizada.' : 'Categoría desactivada: ya no se ofrece al abrir tickets.');
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?CategoriaTicket $categoria = null): array
    {
        $requerido = $categoria ? 'sometimes' : 'required';

        return $request->validate([
            'nombre'        => [$requerido, 'string', 'min:3', 'max:100', new UnicoSinMayusculas('categorias_ticket', 'nombre', $categoria?->id)],
            'descripcion'   => ['nullable', 'string', 'max:300'],
            'es_hardware'   => ['sometimes', 'boolean'],
            'unidad_tic_id' => ['nullable', 'integer', Rule::in($this->subunidades->ids())],
            'activa'        => ['sometimes', 'boolean'],
        ], ['unidad_tic_id.in' => 'Elija una de las subunidades de TIC.'], [
            'nombre' => 'nombre', 'descripcion' => 'descripción', 'es_hardware' => 'lleva equipo', 'unidad_tic_id' => 'subunidad de TIC',
        ]);
    }
}
