<?php

namespace App\Http\Controllers\InventarioTi;

use App\Contracts\InventarioTi\ConsumibleServiceInterface;
use App\Enums\TipoConsumible;
use App\Http\Controllers\Controller;
use App\Http\Requests\InventarioTi\StoreConsumibleRequest;
use App\Http\Responses\ApiResponse;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\Consumible;
use App\Models\InventarioTi\ConsumibleModelo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * El catálogo de consumibles de TIC y su compatibilidad por modelo (fase
 * 5.4a), y la sección «Consumibles» de la ficha de un equipo.
 */
final class ConsumibleController extends Controller
{
    public function __construct(private readonly ConsumibleServiceInterface $consumibles) {}

    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'search'              => ['nullable', 'string', 'max:100'],
            'tipo'                => ['nullable', Rule::enum(TipoConsumible::class)],
            'guarda_tic'          => ['nullable', 'boolean'],
            'incluir_inactivos'   => ['nullable', 'boolean'],
            'bajo_minimo'         => ['nullable', 'boolean'],
            'bien_informatico_id' => ['nullable', 'integer'],
            'marca_id'            => ['nullable', 'integer'],
            'modelo'              => ['nullable', 'string', 'max:100'],
        ]);
        foreach (['guarda_tic', 'incluir_inactivos', 'bajo_minimo'] as $bandera) {
            if (isset($filtros[$bandera])) {
                $filtros[$bandera] = $request->boolean($bandera);
            }
        }

        return ApiResponse::ok($this->consumibles->listar($filtros), 'Catálogo de consumibles.');
    }

    public function store(StoreConsumibleRequest $request): JsonResponse
    {
        $consumible = $this->consumibles->crear($request->validated(), $request->user());

        return ApiResponse::created($this->consumibles->detalle($consumible), 'Consumible registrado.');
    }

    public function show(Consumible $consumible): JsonResponse
    {
        return ApiResponse::ok($this->consumibles->detalle($consumible), 'Consumible.');
    }

    public function update(StoreConsumibleRequest $request, Consumible $consumible): JsonResponse
    {
        $consumible = $this->consumibles->actualizar($consumible, $request->validated(), $request->user());

        return ApiResponse::ok($this->consumibles->detalle($consumible), 'Consumible actualizado.');
    }

    /** Agregar un modelo compatible, también desde la entrega cuando falta. */
    public function agregarModelo(Request $request, Consumible $consumible): JsonResponse
    {
        $datos = $request->validate([
            'marca_id' => ['required', 'integer', Rule::exists('marcas', 'id')->whereNull('deleted_at')],
            'modelo'   => ['required', 'string', 'min:1', 'max:100'],
        ], [], ['marca_id' => 'marca']);
        $this->consumibles->agregarModelo($consumible, (int) $datos['marca_id'], $datos['modelo']);

        return ApiResponse::created($this->consumibles->detalle($consumible->fresh()), 'Modelo agregado a los compatibles.');
    }

    public function quitarModelo(Consumible $consumible, ConsumibleModelo $modelo): JsonResponse
    {
        $this->consumibles->quitarModelo($consumible, $modelo);

        return ApiResponse::ok($this->consumibles->detalle($consumible->fresh()), 'Modelo quitado de los compatibles.');
    }

    public function delEquipo(BienInformatico $bien): JsonResponse
    {
        return ApiResponse::ok($this->consumibles->delEquipo($bien), 'Consumibles del equipo.');
    }
}
