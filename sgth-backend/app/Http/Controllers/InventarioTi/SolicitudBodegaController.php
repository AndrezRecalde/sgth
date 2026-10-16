<?php

namespace App\Http\Controllers\InventarioTi;

use App\Contracts\InventarioTi\SolicitudBodegaServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\InventarioTi\StoreSolicitudBodegaRequest;
use App\Http\Responses\ApiResponse;
use App\Models\InventarioTi\SolicitudBodega;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * La «Solicitud de materiales informáticos a bodega» (fase 5.4c): generarla
 * (desde un ticket «Consumibles» o sin él), imprimirla, anotar lo que despachó
 * Bodega —que es la entrega— o anularla.
 */
final class SolicitudBodegaController extends Controller
{
    public function __construct(private readonly SolicitudBodegaServiceInterface $solicitudes) {}

    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'estado'    => ['nullable', Rule::in([SolicitudBodega::PENDIENTE, SolicitudBodega::DESPACHADA, SolicitudBodega::ANULADA])],
            'ticket_id' => ['nullable', 'integer'],
            'search'    => ['nullable', 'string', 'max:100'],
            'per_page'  => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $pagina = $this->solicitudes->listar($filtros);

        return ApiResponse::paginadoDe($pagina, collect($pagina->items())->map(fn (SolicitudBodega $s) => $this->solicitudes->presentar($s))->all(), 'Solicitudes a bodega.');
    }

    public function store(StoreSolicitudBodegaRequest $request): JsonResponse
    {
        $s = $this->solicitudes->crear($request->validated(), $request->user());

        return ApiResponse::created($this->solicitudes->presentar($s), "Solicitud {$s->codigo()} generada: imprímala para las firmas.");
    }

    public function show(SolicitudBodega $solicitud): JsonResponse
    {
        return ApiResponse::ok($this->solicitudes->presentar($solicitud->load('items.consumible', 'ticket', 'servidor', 'unidad', 'bien.tipo', 'bien.marca', 'creadaPor.servidor', 'despachadaPor.servidor', 'anuladaPor.servidor')), 'Solicitud a bodega.');
    }

    public function pdf(SolicitudBodega $solicitud): Response
    {
        $doc = $this->solicitudes->pdf($solicitud);

        return response($doc['content'], 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$doc['filename']}\"",
        ]);
    }

    public function despachar(Request $request, SolicitudBodega $solicitud): JsonResponse
    {
        $datos = $request->validate([
            'fecha'                       => ['required', 'date', 'before_or_equal:today'],
            'items'                       => ['required', 'array', 'min:1', 'max:25'],
            'items.*.item_id'             => ['required', 'integer'],
            'items.*.cantidad_despachada' => ['required', 'integer', 'min:0', 'max:999'],
        ], [], ['items' => 'renglones', 'items.*.cantidad_despachada' => 'cantidad despachada']);
        $s = $this->solicitudes->despachar($solicitud, $datos, $request->user());

        return ApiResponse::ok($this->solicitudes->presentar($s), $s->ticket_id
            ? 'La entrega quedó en el kárdex y el ticket se cerró.'
            : 'La entrega quedó en el kárdex.');
    }

    public function anular(Request $request, SolicitudBodega $solicitud): JsonResponse
    {
        $datos = $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:300']]);

        return ApiResponse::ok($this->solicitudes->presentar($this->solicitudes->anular($solicitud, $datos['motivo'], $request->user())), 'Solicitud anulada.');
    }
}
