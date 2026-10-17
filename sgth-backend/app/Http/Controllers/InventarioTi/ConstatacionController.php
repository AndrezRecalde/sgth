<?php

namespace App\Http\Controllers\InventarioTi;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\InventarioTi\Constatacion;
use App\Models\InventarioTi\ConstatacionItem;
use App\Services\InventarioTi\ConstatacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * La constatación física de los equipos (fase 5.2): abrirla para una
 * ubicación, escanear las etiquetas desde el celular, mover aquí lo que
 * estaba registrado en otra parte, cerrarla y descargar el acta. Si participa
 * Activos Fijos y Bodega (5.2b), el acta lleva su firma.
 */
final class ConstatacionController extends Controller
{
    public function __construct(private readonly ConstatacionService $constataciones) {}

    public function index(): JsonResponse
    {
        return ApiResponse::ok($this->constataciones->listar()->map(fn (Constatacion $c) => $this->cabecera($c)), 'Constataciones físicas.');
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'nombre'                   => ['required', 'string', 'min:3', 'max:150'],
            'ubicacion_id'             => ['required', 'integer', Rule::exists('ubicaciones', 'id')->whereNull('deleted_at')],
            'unidad_administrativa_id' => ['nullable', 'integer', Rule::exists('unidades_administrativas', 'id')],
            'participa_activos_fijos'  => ['sometimes', 'boolean'],
            'observaciones'            => ['nullable', 'string', 'max:2000'],
        ], [], ['ubicacion_id' => 'ubicación', 'unidad_administrativa_id' => 'unidad', 'participa_activos_fijos' => 'participa Activos Fijos y Bodega']);

        $c = $this->constataciones->crear($datos, $request->user());

        return ApiResponse::created($this->cabecera($c->load('ubicacion', 'unidad', 'iniciadaPor.servidor')), 'Constatación abierta.');
    }

    public function show(Constatacion $constatacion): JsonResponse
    {
        $constatacion->load('ubicacion', 'unidad', 'iniciadaPor.servidor', 'cerradaPor.servidor');

        return ApiResponse::ok([
            ...$this->cabecera($constatacion),
            'firmante_activos_fijos' => $this->constataciones->firmanteActivosFijos($constatacion),
            ...$this->constataciones->detalle($constatacion),
        ], 'Constatación física.');
    }

    public function activosFijos(Request $request, Constatacion $constatacion): JsonResponse
    {
        $datos = $request->validate(['participa_activos_fijos' => ['required', 'boolean']], [], ['participa_activos_fijos' => 'participa Activos Fijos y Bodega']);
        $c = $this->constataciones->cambiarActivosFijos($constatacion, $datos['participa_activos_fijos']);

        return ApiResponse::ok(
            $this->cabecera($c->load('ubicacion', 'unidad', 'iniciadaPor.servidor')),
            $c->participa_activos_fijos ? 'Activos Fijos y Bodega firmará el acta.' : 'El acta queda sin la firma de Activos Fijos y Bodega.',
        );
    }

    public function escanear(Request $request, Constatacion $constatacion): JsonResponse
    {
        $datos = $request->validate(['codigo' => ['required', 'string', 'max:300']], [], ['codigo' => 'código']);
        $r = $this->constataciones->escanear($constatacion, $datos['codigo'], $request->user());

        return ApiResponse::ok($r, match (true) {
            ! $r['encontrado'] => "El código {$r['codigo']} no está en el inventario.",
            $r['repetido'] ?? false => "{$r['codigo']} ya estaba escaneado.",
            default => "{$r['codigo']}: " . mb_strtolower(ConstatacionItem::RESULTADOS[$r['fila']['resultado']]) . '.',
        });
    }

    public function mover(Constatacion $constatacion, ConstatacionItem $item): JsonResponse
    {
        $this->constataciones->mover($constatacion, $item);

        return ApiResponse::ok(null, 'El equipo quedó en esta ubicación, con sus componentes.');
    }

    public function cerrar(Request $request, Constatacion $constatacion): JsonResponse
    {
        $datos = $request->validate(['observaciones' => ['nullable', 'string', 'max:2000']]);
        $c = $this->constataciones->cerrar($constatacion, $request->user(), $datos['observaciones'] ?? null);

        return ApiResponse::ok($this->cabecera($c->load('ubicacion', 'unidad', 'iniciadaPor.servidor', 'cerradaPor.servidor')), 'Constatación cerrada: lo que no apareció quedó como no encontrado.');
    }

    public function acta(Constatacion $constatacion): Response
    {
        $acta = $this->constataciones->acta($constatacion);

        return response($acta['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $acta['filename'] . '"',
        ]);
    }

    /** @return array<string, mixed> */
    private function cabecera(Constatacion $c): array
    {
        return [
            'id'            => $c->id,
            'nombre'        => $c->nombre,
            'estado'        => $c->estado,
            'ubicacion_id'  => $c->ubicacion_id,
            'ubicacion'     => $c->ubicacion?->nombre,
            'unidad_id'     => $c->unidad_administrativa_id,
            'unidad'        => $c->unidad?->nombre,
            'observaciones' => $c->observaciones,
            'participa_activos_fijos' => $c->participa_activos_fijos,
            'iniciada_por'  => $c->iniciadaPor?->nombre_completo,
            'iniciada_en'   => $c->created_at?->toIso8601String(),
            'cerrada_por'   => $c->cerradaPor?->nombre_completo,
            'cerrada_en'    => $c->cerrada_en?->toIso8601String(),
            'escaneados'    => $c->escaneados ?? null,
            'no_encontrados' => $c->no_encontrados ?? null,
        ];
    }
}
