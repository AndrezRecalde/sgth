<?php
namespace App\Http\Controllers\Viatico;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Viatico\TramoViatico;
use App\Models\Viatico\Viatico;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TramoViaticoController extends Controller
{
    public function index(int $viaticoId): JsonResponse
    {
        $tramos = TramoViatico::with([
            'empresa.catalogo',
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
            'empresa_transporte_id' => 'required|exists:empresas_transporte,id',
            'datetime_salida'       => 'required|date',
            'datetime_llegada'      => 'required|date|after:datetime_salida',
            'orden'                 => 'nullable|integer|min:1',
        ]);

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
                    422
                );
            }
        }

        // ── Validar último tramo ──────────────────────
        // Si la llegada de este tramo = llegada del viático,
        // validamos que coincida exactamente
        $llegadaViatico = Carbon::parse(
            $viatico->datetime_llegada
        );
        $llegadaTramo = Carbon::parse(
            $data['datetime_llegada']
        );

        // Si no hay tramos (es el único) o si la llegada
        // de este tramo coincide con la del viático,
        // validar que sean exactas
        $esUltimoTramo = $llegadaTramo->eq($llegadaViatico)
            || $llegadaTramo->gt($llegadaViatico);

        if ($llegadaTramo->gt($llegadaViatico)) {
            return ApiResponse::error(
                'La fecha y hora de llegada del tramo ' .
                '(' . $llegadaTramo->format('d/m/Y H:i') . ') ' .
                'no puede ser posterior a la llegada del viático ' .
                '(' . $llegadaViatico->format('d/m/Y H:i') . ').',
                422
            );
        }

        $data['viatico_id'] = $viaticoId;
        $tramo = TramoViatico::create($data);

        return ApiResponse::created(
            $tramo->load([
                'empresa.catalogo',
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
            'empresa_transporte_id' => 'sometimes|exists:empresas_transporte,id',
            'datetime_salida'       => 'sometimes|date',
            'datetime_llegada'      => 'sometimes|date',
            'orden'                 => 'sometimes|integer|min:1',
        ]);

        $viatico = Viatico::findOrFail($viaticoId);
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
                    422
                );
            }
        }

        $tramo->update($data);

        return ApiResponse::ok(
            $tramo->fresh([
                'empresa.catalogo',
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
        $tramo->delete();

        return ApiResponse::ok(
            null, 'Tramo eliminado.'
        );
    }
}
