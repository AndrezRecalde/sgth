<?php
namespace App\Http\Controllers\Viatico;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Viatico\EmpresaTransporte;
use App\Models\Viatico\TramoViatico;
use App\Models\Viatico\Viatico;
use App\Services\Viatico\ViaticoEstadoService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TramoViaticoController extends Controller
{
    public function __construct(private ViaticoEstadoService $estados) {}

    public function index(int $viaticoId): JsonResponse
    {
        $this->authorize('ver', Viatico::findOrFail($viaticoId));

        $tramos = TramoViatico::with([
            'catalogo', 'empresa.catalogo',
            'origenProvincia', 'origenCanton',
            'destinoProvincia', 'destinoCanton',
            'autorizacionVuelo',
        ])->where('viatico_id', $viaticoId)
          ->orderBy('orden')
          ->get();

        return ApiResponse::ok(
            $tramos, 'Tramos del viático.'
        );
    }

    public function store(
        Request $request,
        int $viaticoId
    ): JsonResponse {
        $viatico = Viatico::findOrFail($viaticoId);

        // El itinerario lo arma el titular o quien opera: antes cualquiera
        // agregaba, cambiaba o borraba tramos de un viático ajeno.
        $this->authorize('editar', $viatico);
        $this->estados->asegurarEditable($viatico, $request->user());

        $data = $request->validate([
            'origen_tipo'           => 'required|in:nacional,internacional',
            'origen_provincia_id'   => 'nullable|exists:provincias,id',
            'origen_canton_id'      => 'nullable|exists:cantones,id',
            'origen_pais'           => 'nullable|string|max:100',
            'origen_ciudad'         => 'required|string|max:150',
            'destino_tipo'          => 'required|in:nacional,internacional',
            'destino_provincia_id'  => 'nullable|exists:provincias,id',
            'destino_canton_id'     => 'nullable|exists:cantones,id',
            'destino_pais'          => 'nullable|string|max:100',
            'destino_ciudad'        => 'required|string|max:150',
            'catalogo_transporte_id' => ['nullable', Rule::exists('catalogo_transportes', 'id')->where('activo', true)],
            'empresa_transporte_id'  => ['nullable', Rule::exists('empresas_transporte', 'id')->where('activo', true)],
            'datetime_salida'       => 'required|date',
            'datetime_llegada'      => 'required|date|after:datetime_salida',
            'orden'                 => 'nullable|integer|min:1',
            'tipo_tramo'            => 'nullable|in:ida,destino,escala,regreso',
        ]);

        $data = $this->conTransporte($data);

        // Orden automático
        if (empty($data['orden'])) {
            $data['orden'] = TramoViatico::where(
                'viatico_id', $viaticoId
            )->max('orden') + 1;
        }

        $tramosExistentes = TramoViatico::where(
            'viatico_id', $viaticoId
        )->orderBy('orden')->get();

        $esPrimerTramo = $tramosExistentes->isEmpty();

        // ── Tipo de tramo automático ──────────────────
        if ($esPrimerTramo) {
            // Primer tramo siempre es IDA
            $data['tipo_tramo'] = 'ida';
        } elseif (
            isset($data['tipo_tramo']) &&
            $data['tipo_tramo'] === 'regreso'
        ) {
            // El servidor marcó este como REGRESO
            // Validar que llegada = datetime_llegada del viático
            $llegadaViatico = Carbon::parse(
                $viatico->datetime_llegada
            );
            $llegadaTramo = Carbon::parse(
                $data['datetime_llegada']
            );
            if (!$llegadaViatico->eq($llegadaTramo)) {
                return ApiResponse::error(
                    'El tramo de REGRESO debe llegar exactamente el ' .
                    $llegadaViatico->format('d/m/Y H:i') .
                    ' (fecha de llegada del viático).',
                    null,
                    422
                );
            }
        } elseif (!isset($data['tipo_tramo'])) {
            // Si no viene tipo_tramo, asignar destino por defecto
            $data['tipo_tramo'] = 'destino';
        }

        // ── Validar primer tramo ──────────────────────
        if ($esPrimerTramo) {
            $salidaViatico = Carbon::parse(
                $viatico->datetime_salida
            );
            $salidaTramo = Carbon::parse(
                $data['datetime_salida']
            );

            if (!$salidaViatico->eq($salidaTramo)) {
                return ApiResponse::error(
                    'La fecha y hora de salida del primer tramo ' .
                    '(' . $salidaTramo->format('d/m/Y H:i') . ') ' .
                    'debe coincidir exactamente con la fecha de ' .
                    'salida del viático ' .
                    '(' . $salidaViatico->format('d/m/Y H:i') . '). ' .
                    'Ajusta las fechas del tramo o edita la ' .
                    'solicitud.',
                    null,
                    422
                );
            }
        }

        // ── Validar último tramo ──────────────────────
        // La llegada de un tramo no puede pasar la del viático.
        $llegadaViatico = Carbon::parse(
            $viatico->datetime_llegada
        );
        $llegadaTramo = Carbon::parse(
            $data['datetime_llegada']
        );


        if ($llegadaTramo->gt($llegadaViatico)) {
            return ApiResponse::error(
                'La fecha y hora de llegada del tramo ' .
                '(' . $llegadaTramo->format('d/m/Y H:i') . ') ' .
                'no puede ser posterior a la llegada del viático ' .
                '(' . $llegadaViatico->format('d/m/Y H:i') . ').',
                null,
                422
            );
        }

        $data['viatico_id'] = $viaticoId;
        $tramo = TramoViatico::create($data);

        return ApiResponse::created(
            $tramo->load([
                'catalogo', 'empresa.catalogo',
                'origenProvincia', 'destinoProvincia',
                'autorizacionVuelo',
            ]),
            'Tramo registrado correctamente.'
        );
    }

    public function update(
        Request $request,
        int $viaticoId,
        TramoViatico $tramo
    ): JsonResponse {
        if ($tramo->viatico_id !== $viaticoId) {
            abort(404);
        }

        $viatico = Viatico::findOrFail($viaticoId);
        $this->authorize('editar', $viatico);
        $this->estados->asegurarEditable($viatico, request()->user());

        $data = $request->validate([
            'origen_tipo'           => 'sometimes|in:nacional,internacional',
            'origen_provincia_id'   => 'nullable|exists:provincias,id',
            'origen_canton_id'      => 'nullable|exists:cantones,id',
            'origen_pais'           => 'nullable|string|max:100',
            'origen_ciudad'         => 'sometimes|string|max:150',
            'destino_tipo'          => 'sometimes|in:nacional,internacional',
            'destino_provincia_id'  => 'nullable|exists:provincias,id',
            'destino_canton_id'     => 'nullable|exists:cantones,id',
            'destino_pais'          => 'nullable|string|max:100',
            'destino_ciudad'        => 'sometimes|string|max:150',
            'catalogo_transporte_id' => ['sometimes', Rule::exists('catalogo_transportes', 'id')->where('activo', true)],
            'empresa_transporte_id'  => ['sometimes', 'nullable', Rule::exists('empresas_transporte', 'id')->where('activo', true)],
            'datetime_salida'       => 'sometimes|date',
            'datetime_llegada'      => 'sometimes|date',
            'orden'                 => 'sometimes|integer|min:1',
            'tipo_tramo'            => 'sometimes|in:ida,destino,escala,regreso',
        ]);

        if (array_key_exists('catalogo_transporte_id', $data) || array_key_exists('empresa_transporte_id', $data)) {
            $data = $this->conTransporte($data, $tramo);
        }

        $tramosExistentes = TramoViatico::where(
            'viatico_id', $viaticoId
        )->orderBy('orden')->get();

        $esPrimero = $tramosExistentes->first()?->id === $tramo->id;

        // Validar primer tramo si se cambia datetime_salida
        if ($esPrimero && isset($data['datetime_salida'])) {
            $salidaViatico = Carbon::parse(
                $viatico->datetime_salida
            );
            $salidaTramo   = Carbon::parse(
                $data['datetime_salida']
            );

            if (!$salidaViatico->eq($salidaTramo)) {
                return ApiResponse::error(
                    'La salida del primer tramo ' .
                    '(' . $salidaTramo->format('d/m/Y H:i') . ') ' .
                    'debe coincidir con la salida del viático ' .
                    '(' . $salidaViatico->format('d/m/Y H:i') . ').',
                    null,
                    422
                );
            }
        }

        // Validar que llegada no supere la del viático
        if (isset($data['datetime_llegada'])) {
            $llegadaViatico = Carbon::parse(
                $viatico->datetime_llegada
            );
            $llegadaTramo   = Carbon::parse(
                $data['datetime_llegada']
            );

            if ($llegadaTramo->gt($llegadaViatico)) {
                return ApiResponse::error(
                    'La llegada del tramo no puede ser posterior ' .
                    'a la llegada del viático ' .
                    '(' . $llegadaViatico->format('d/m/Y H:i') . ').',
                    null,
                    422
                );
            }
        }

        // No permitir cambiar tipo de IDA o REGRESO automático
        if (isset($data['tipo_tramo'])) {
            $esPrimeroCheck = $tramosExistentes->first()?->id
                === $tramo->id;
            if ($esPrimeroCheck) {
                $data['tipo_tramo'] = 'ida';
            }
        }

        $tramo->update($data);

        return ApiResponse::ok(
            $tramo->fresh([
                'catalogo', 'empresa.catalogo',
                'origenProvincia', 'destinoProvincia',
            ]),
            'Tramo actualizado.'
        );
    }

    public function destroy(
        int $viaticoId,
        TramoViatico $tramo
    ): JsonResponse {
        if ($tramo->viatico_id !== $viaticoId) {
            abort(404);
        }

        $viatico = Viatico::findOrFail($viaticoId);
        $this->authorize('editar', $viatico);
        $this->estados->asegurarEditable($viatico, request()->user());

        $tramo->delete();

        return ApiResponse::ok(
            null, 'Tramo eliminado.'
        );
    }

    /**
     * El tipo de transporte y la empresa del tramo, coherentes entre sí.
     *
     * - El tipo es obligatorio. Si solo llega la empresa, se toma el suyo.
     * - La empresa se exige solo si el tipo tiene empresas activas (bus,
     *   avión). Un vehículo institucional, un taxi o una lancha no las
     *   tienen, y antes no había forma de registrarlos.
     * - Una empresa tiene que ser de ese tipo.
     */
    private function conTransporte(array $data, ?TramoViatico $tramo = null): array
    {
        $empresaId = array_key_exists('empresa_transporte_id', $data)
            ? $data['empresa_transporte_id']
            : $tramo?->empresa_transporte_id;

        $catalogoId = $data['catalogo_transporte_id']
            ?? ($empresaId !== null && array_key_exists('empresa_transporte_id', $data)
                ? EmpresaTransporte::whereKey($empresaId)->value('catalogo_transporte_id')
                : $tramo?->catalogo_transporte_id);

        if ($catalogoId === null) {
            throw ValidationException::withMessages([
                'catalogo_transporte_id' => 'Elija el tipo de transporte.',
            ]);
        }

        if ($empresaId !== null) {
            $deEseTipo = EmpresaTransporte::whereKey($empresaId)
                ->where('catalogo_transporte_id', $catalogoId)
                ->exists();

            if (! $deEseTipo) {
                // Al cambiar solo el tipo, la empresa anterior ya no aplica.
                if (! array_key_exists('empresa_transporte_id', $data)) {
                    $empresaId = null;
                } else {
                    throw ValidationException::withMessages([
                        'empresa_transporte_id' => 'La empresa no corresponde a ese tipo de transporte.',
                    ]);
                }
            }
        }

        $conEmpresas = EmpresaTransporte::where('catalogo_transporte_id', $catalogoId)
            ->where('activo', true)
            ->exists();

        if ($empresaId === null && $conEmpresas) {
            throw ValidationException::withMessages([
                'empresa_transporte_id' => 'Elija la empresa de transporte.',
            ]);
        }

        return [
            ...$data,
            'catalogo_transporte_id' => (int) $catalogoId,
            'empresa_transporte_id'  => $empresaId,
        ];
    }
}
