<?php

namespace App\Http\Controllers\InventarioTi;

use App\Contracts\InventarioTi\CatalogoInventarioServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\InventarioTi\StoreCatalogoInventarioRequest;
use App\Http\Requests\InventarioTi\UpdateCatalogoInventarioRequest;
use App\Http\Resources\InventarioTi\MarcaResource;
use App\Http\Resources\InventarioTi\SubtipoBienResource;
use App\Http\Resources\InventarioTi\TipoBienResource;
use App\Http\Resources\InventarioTi\UbicacionResource;
use App\Http\Responses\ApiResponse;
use App\Models\InventarioTi\Marca;
use App\Models\InventarioTi\SubtipoBien;
use App\Models\InventarioTi\TipoBien;
use App\Models\InventarioTi\Ubicacion;
use Illuminate\Http\JsonResponse;

/**
 * Los catálogos simples del inventario: tipos, subtipos, marcas y
 * ubicaciones, más lo que el formulario de un equipo necesita en una consulta.
 *
 * Los listados traen cuántos equipos usa cada registro: es lo que decide si se
 * puede borrar.
 */
final class CatalogoInventarioController extends Controller
{
    public function __construct(private readonly CatalogoInventarioServiceInterface $catalogos) {}

    public function formularios(): JsonResponse
    {
        return ApiResponse::ok($this->catalogos->paraFormularios(), 'Catálogos del inventario.');
    }

    // ── Tipos ──────────────────────────────────────────────────────

    public function tipos(): JsonResponse
    {
        return ApiResponse::ok(TipoBienResource::collection($this->catalogos->listarTipos()), 'Tipos de equipo.');
    }

    public function guardarTipo(StoreCatalogoInventarioRequest $request): JsonResponse
    {
        return ApiResponse::created(new TipoBienResource($this->catalogos->guardarTipo(null, $request->validated())), 'Tipo registrado.');
    }

    public function actualizarTipo(UpdateCatalogoInventarioRequest $request, TipoBien $tipo): JsonResponse
    {
        return ApiResponse::ok(new TipoBienResource($this->catalogos->guardarTipo($tipo, $request->validated())), 'Tipo actualizado.');
    }

    public function eliminarTipo(TipoBien $tipo): JsonResponse
    {
        $this->catalogos->eliminarTipo($tipo);

        return ApiResponse::ok(null, 'Tipo eliminado.');
    }

    // ── Subtipos ───────────────────────────────────────────────────

    public function guardarSubtipo(StoreCatalogoInventarioRequest $request): JsonResponse
    {
        return ApiResponse::created(new SubtipoBienResource($this->catalogos->guardarSubtipo(null, $request->validated())), 'Subtipo registrado.');
    }

    public function actualizarSubtipo(UpdateCatalogoInventarioRequest $request, SubtipoBien $subtipo): JsonResponse
    {
        return ApiResponse::ok(new SubtipoBienResource($this->catalogos->guardarSubtipo($subtipo, $request->validated())), 'Subtipo actualizado.');
    }

    public function eliminarSubtipo(SubtipoBien $subtipo): JsonResponse
    {
        $this->catalogos->eliminarSubtipo($subtipo);

        return ApiResponse::ok(null, 'Subtipo eliminado.');
    }

    // ── Marcas ─────────────────────────────────────────────────────

    public function marcas(): JsonResponse
    {
        return ApiResponse::ok(MarcaResource::collection($this->catalogos->listarMarcas()), 'Marcas.');
    }

    public function guardarMarca(StoreCatalogoInventarioRequest $request): JsonResponse
    {
        return ApiResponse::created(new MarcaResource($this->catalogos->guardarMarca(null, $request->validated())), 'Marca registrada.');
    }

    public function actualizarMarca(UpdateCatalogoInventarioRequest $request, Marca $marca): JsonResponse
    {
        return ApiResponse::ok(new MarcaResource($this->catalogos->guardarMarca($marca, $request->validated())), 'Marca actualizada.');
    }

    public function eliminarMarca(Marca $marca): JsonResponse
    {
        $this->catalogos->eliminarMarca($marca);

        return ApiResponse::ok(null, 'Marca eliminada.');
    }

    // ── Ubicaciones ────────────────────────────────────────────────

    public function ubicaciones(): JsonResponse
    {
        return ApiResponse::ok(UbicacionResource::collection($this->catalogos->listarUbicaciones()), 'Ubicaciones.');
    }

    public function guardarUbicacion(StoreCatalogoInventarioRequest $request): JsonResponse
    {
        return ApiResponse::created(new UbicacionResource($this->catalogos->guardarUbicacion(null, $request->validated())), 'Ubicación registrada.');
    }

    public function actualizarUbicacion(UpdateCatalogoInventarioRequest $request, Ubicacion $ubicacion): JsonResponse
    {
        return ApiResponse::ok(new UbicacionResource($this->catalogos->guardarUbicacion($ubicacion, $request->validated())), 'Ubicación actualizada.');
    }

    public function eliminarUbicacion(Ubicacion $ubicacion): JsonResponse
    {
        $this->catalogos->eliminarUbicacion($ubicacion);

        return ApiResponse::ok(null, 'Ubicación eliminada.');
    }
}
