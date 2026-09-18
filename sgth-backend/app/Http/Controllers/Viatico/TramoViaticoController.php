<?php
namespace App\Http\Controllers\Viatico;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Viatico\TramoViatico;
use App\Models\Viatico\Viatico;
use App\Services\Viatico\ItinerarioViaticoService;
use App\Services\Viatico\ViaticoEstadoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/*
| Los tramos del itinerario. Aquí se autoriza y se valida la forma de los
| datos; las reglas del itinerario —orden, fechas, regreso, transporte— viven
| en ItinerarioViaticoService.
*/
class TramoViaticoController extends Controller
{
    private const RELACIONES = [
        'catalogo', 'empresa.catalogo',
        'origenProvincia', 'origenCanton',
        'destinoProvincia', 'destinoCanton',
        'autorizacionVuelo',
    ];

    public function __construct(
        private ViaticoEstadoService $estados,
        private ItinerarioViaticoService $itinerario,
    ) {}

    public function index(int $viaticoId): JsonResponse
    {
        $this->authorize('ver', Viatico::findOrFail($viaticoId));

        $tramos = TramoViatico::with(self::RELACIONES)
            ->where('viatico_id', $viaticoId)
            ->orderBy('orden')
            ->get();

        return ApiResponse::ok($tramos, 'Tramos del viático.');
    }

    public function store(Request $request, int $viaticoId): JsonResponse
    {
        $viatico = $this->editable($request, $viaticoId);

        $tramo = $this->itinerario->agregar($viatico, $request->validate($this->reglas(obligatorio: true)));

        return ApiResponse::created($tramo->load(self::RELACIONES), 'Tramo registrado correctamente.');
    }

    public function update(Request $request, int $viaticoId, TramoViatico $tramo): JsonResponse
    {
        abort_if($tramo->viatico_id !== $viaticoId, 404);
        $this->editable($request, $viaticoId);

        $tramo = $this->itinerario->actualizar($tramo, $request->validate($this->reglas(obligatorio: false)));

        return ApiResponse::ok($tramo->load(self::RELACIONES), 'Tramo actualizado.');
    }

    public function destroy(Request $request, int $viaticoId, TramoViatico $tramo): JsonResponse
    {
        abort_if($tramo->viatico_id !== $viaticoId, 404);
        $this->editable($request, $viaticoId);

        $this->itinerario->eliminar($tramo);

        return ApiResponse::ok(null, 'Tramo eliminado.');
    }

    /**
     * El itinerario lo arma el titular o quien opera: antes cualquiera
     * agregaba, cambiaba o borraba tramos de un viático ajeno.
     */
    private function editable(Request $request, int $viaticoId): Viatico
    {
        $viatico = Viatico::findOrFail($viaticoId);
        $this->authorize('editar', $viatico);
        $this->estados->asegurarEditable($viatico, $request->user());

        return $viatico;
    }

    /** La forma de los datos: al crear, lo básico es obligatorio; al editar, no. */
    private function reglas(bool $obligatorio): array
    {
        $base = $obligatorio ? 'required' : 'sometimes';

        return [
            'origen_tipo'            => "{$base}|in:nacional,internacional",
            'origen_provincia_id'    => 'nullable|exists:provincias,id',
            'origen_canton_id'       => 'nullable|exists:cantones,id',
            'origen_pais'            => 'nullable|string|max:100',
            'origen_ciudad'          => "{$base}|string|max:150",
            'destino_tipo'           => "{$base}|in:nacional,internacional",
            'destino_provincia_id'   => 'nullable|exists:provincias,id',
            'destino_canton_id'      => 'nullable|exists:cantones,id',
            'destino_pais'           => 'nullable|string|max:100',
            'destino_ciudad'         => "{$base}|string|max:150",
            'catalogo_transporte_id' => [$obligatorio ? 'nullable' : 'sometimes', Rule::exists('catalogo_transportes', 'id')->where('activo', true)],
            'empresa_transporte_id'  => ['sometimes', 'nullable', Rule::exists('empresas_transporte', 'id')->where('activo', true)],
            'datetime_salida'        => "{$base}|date",
            'datetime_llegada'       => "{$base}|date",
            'tipo_tramo'             => 'nullable|in:ida,destino,escala,regreso',
        ];
    }
}
