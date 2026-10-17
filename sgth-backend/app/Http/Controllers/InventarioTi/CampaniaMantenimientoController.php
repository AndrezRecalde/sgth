<?php

namespace App\Http\Controllers\InventarioTi;

use App\Contracts\InventarioTi\MantenimientoServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\InventarioTi\CampaniaEquipo;
use App\Models\InventarioTi\CampaniaMantenimiento;
use App\Models\InventarioTi\MantenimientoBien;
use App\Services\InventarioTi\CampaniaMantenimientoService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * El plan de mantenimiento preventivo por campaña (fase 5.1): crearla para un
 * lugar con su técnico y su período, ajustar los equipos, marcar lo hecho (cada
 * uno con su mantenimiento en la ficha) u omitido, y cerrarla.
 */
final class CampaniaMantenimientoController extends Controller
{
    private const ATRIBUTOS = [
        'ubicacion_id' => 'ubicación', 'unidad_administrativa_id' => 'unidad', 'tecnico_id' => 'técnico',
        'realizado_por' => 'quién la hace', 'contrato_vigencia_id' => 'contrato de vigencia',
        'fecha_inicio' => 'fecha de inicio', 'fecha_fin' => 'fecha de fin', 'descripcion' => 'qué se hace',
    ];

    public function __construct(
        private readonly CampaniaMantenimientoService $campanias,
        private readonly MantenimientoServiceInterface $mantenimientos,
    ) {}

    public function index(): JsonResponse
    {
        return ApiResponse::ok($this->campanias->listar()->map(fn (CampaniaMantenimiento $c) => $this->campanias->cabecera($c)), 'Campañas de mantenimiento preventivo.');
    }

    public function store(Request $request): JsonResponse
    {
        // La del proveedor (5.1b) lleva su contrato; el lugar y el técnico de TIC son opcionales.
        $delProveedor = $request->input('realizado_por') === MantenimientoBien::POR_PROVEEDOR;
        $datos = $request->validate([
            ...$this->reglasCabecera($delProveedor),
            'realizado_por'            => ['sometimes', Rule::in([MantenimientoBien::POR_TIC, MantenimientoBien::POR_PROVEEDOR])],
            'contrato_vigencia_id'     => [$delProveedor ? 'required' : 'prohibited', 'nullable', 'integer', Rule::exists('contratos_vigencia', 'id')->whereNull('deleted_at')],
            'ubicacion_id'             => [$delProveedor ? 'nullable' : 'required', 'integer', Rule::exists('ubicaciones', 'id')->whereNull('deleted_at')],
            'unidad_administrativa_id' => ['nullable', 'integer', Rule::exists('unidades_administrativas', 'id')],
        ], [], self::ATRIBUTOS);

        $c = $this->campanias->crear($datos, $request->user());

        return ApiResponse::created($this->cargada($c), 'Campaña creada con los equipos del lugar.');
    }

    public function show(CampaniaMantenimiento $campania): JsonResponse
    {
        return ApiResponse::ok([...$this->cargada($campania), ...$this->campanias->detalle($campania)], 'Campaña de mantenimiento preventivo.');
    }

    public function update(Request $request, CampaniaMantenimiento $campania): JsonResponse
    {
        // Quién la hace, su contrato y su lugar quedan como se crearon.
        $datos = $request->validate($this->reglasCabecera($campania->esDelProveedor()), [], self::ATRIBUTOS);

        return ApiResponse::ok($this->cargada($this->campanias->actualizar($campania, $datos)), 'Campaña actualizada.');
    }

    public function agregar(Request $request, CampaniaMantenimiento $campania): JsonResponse
    {
        $datos = $request->validate([
            'bienes'   => ['required', 'array', 'min:1', 'max:200'],
            'bienes.*' => ['integer', Rule::exists('bienes_informaticos', 'id')->whereNull('deleted_at')],
        ], [], ['bienes' => 'equipos']);
        $n = $this->campanias->agregar($campania, $datos['bienes'], $request->user());

        return ApiResponse::ok(['agregados' => $n], $n === 0 ? 'Esos equipos ya estaban en la campaña.' : 'Equipos agregados a la campaña.');
    }

    public function quitar(CampaniaMantenimiento $campania, CampaniaEquipo $equipo): JsonResponse
    {
        $this->campanias->quitar($campania, $equipo);

        return ApiResponse::ok(null, 'Equipo quitado de la campaña.');
    }

    public function realizar(Request $request, CampaniaMantenimiento $campania): JsonResponse
    {
        $datos = $request->validate([
            'items'       => ['required', 'array', 'min:1', 'max:200'],
            'items.*'     => ['integer'],
            'fecha'       => ['required', 'date', 'before_or_equal:today'],
            'tecnico_id'  => ['nullable', 'integer', $this->tecnicoValido()],
            'observacion' => ['nullable', 'string', 'max:500'],
        ], [], ['items' => 'equipos', 'tecnico_id' => 'técnico', 'observacion' => 'observación']);
        $n = $this->campanias->realizar($campania, $datos['items'], $datos, $request->user());

        return ApiResponse::ok(['hechos' => $n], 'Mantenimiento registrado en la ficha de cada equipo.');
    }

    public function omitir(Request $request, CampaniaMantenimiento $campania): JsonResponse
    {
        $datos = $request->validate([
            'items'   => ['required', 'array', 'min:1', 'max:200'],
            'items.*' => ['integer'],
            'motivo'  => ['required', 'string', 'min:5', 'max:300'],
        ], [], ['items' => 'equipos']);
        $n = $this->campanias->omitir($campania, $datos['items'], $datos['motivo'], $request->user());

        return ApiResponse::ok(['omitidos' => $n], 'Equipos omitidos, con su motivo.');
    }

    public function cerrar(Request $request, CampaniaMantenimiento $campania): JsonResponse
    {
        $c = $this->campanias->cerrar($campania, $request->user());

        return ApiResponse::ok($this->cargada($c), 'Campaña cerrada: lo pendiente quedó como no realizado.');
    }

    /**
     * En la del proveedor, el técnico de TIC es opcional: acompaña o hace el
     * seguimiento, pero el mantenimiento lo hace la empresa.
     *
     * @return array<string, mixed>
     */
    private function reglasCabecera(bool $delProveedor = false): array
    {
        return [
            'nombre'       => ['required', 'string', 'min:3', 'max:150'],
            'descripcion'  => ['required', 'string', 'min:5', 'max:500'],
            'tecnico_id'   => [$delProveedor ? 'nullable' : 'required', 'integer', $this->tecnicoValido()],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin'    => ['required', 'date', 'after_or_equal:fecha_inicio'],
        ];
    }

    /** El técnico es alguien que registra mantenimientos (`registrar-mantenimiento`). */
    private function tecnicoValido(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $fallar) {
            if ($valor !== null && ! $this->mantenimientos->tecnicos()->contains('id', (int) $valor)) {
                $fallar('El técnico debe ser alguien de TIC que registra mantenimientos.');
            }
        };
    }

    /** @return array<string, mixed> */
    private function cargada(CampaniaMantenimiento $c): array
    {
        return $this->campanias->cabecera($c->load('ubicacion', 'unidad', 'tecnico.servidor', 'cerradaPor.servidor', 'contrato'));
    }
}
