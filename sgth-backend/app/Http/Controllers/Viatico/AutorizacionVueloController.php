<?php

namespace App\Http\Controllers\Viatico;

use App\Http\Controllers\Controller;
use App\Models\Viatico\AutorizacionVuelo;
use App\Http\Resources\Viatico\AutorizacionVueloResource;
use App\Http\Responses\ApiResponse;
use App\Models\Viatico\Viatico;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Autorizaciones de vuelo de los tramos aéreos.
 *
 * Ninguna acción pedía permiso: cualquier usuario listaba las pendientes de
 * toda la institución y las aprobaba o rechazaba. Ahora deciden quienes
 * aprueban viáticos (`aprobar-viatico`); la invitación la sube quien puede
 * editar el viático.
 */
class AutorizacionVueloController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('autorizarVuelos', Viatico::class);

        $autorizaciones = AutorizacionVuelo::with([
            'viatico.servidor.puesto.cargo',
            'tramo.empresa',
            'tramo.origenProvincia',
            'tramo.origenCanton',
            'tramo.destinoProvincia',
            'tramo.destinoCanton',
        ])->where('estado', 'pendiente')->get();

        return ApiResponse::ok(
            AutorizacionVueloResource::collection($autorizaciones)
        );
    }

    public function aprobar(Request $request, string $id): JsonResponse
    {
        $this->authorize('autorizarVuelos', Viatico::class);

        $autorizacion = AutorizacionVuelo::findOrFail($id);

        $autorizacion->update([
            'estado'                => 'aprobada',
            'aprobado_por'          => $request->user()->id,
            'observacion_aprobador' => $request->input('observacion'),
            'aprobado_en'           => now(),
        ]);

        return ApiResponse::ok(new AutorizacionVueloResource($autorizacion));
    }

    public function rechazar(Request $request, string $id): JsonResponse
    {
        $this->authorize('autorizarVuelos', Viatico::class);

        $autorizacion = AutorizacionVuelo::findOrFail($id);

        $autorizacion->update([
            'estado'                => 'rechazada',
            'aprobado_por'          => $request->user()->id,
            'observacion_aprobador' => $request->input('observacion'),
            'aprobado_en'           => now(),
        ]);

        return ApiResponse::ok(new AutorizacionVueloResource($autorizacion));
    }

    public function subirDocumento(Request $request, string $id): JsonResponse
    {
        $autorizacion = AutorizacionVuelo::findOrFail($id);

        $this->authorize('editar', $autorizacion->viatico()->firstOrFail());

        $request->validate([
            'documento' => 'required|file|mimes:pdf|max:5120',
        ]);

        $path = $request->file('documento')->store('viaticos/vuelos', 'public');

        $autorizacion->update([
            'documento_invitacion_ruta' => $path,
        ]);

        return ApiResponse::ok(new AutorizacionVueloResource($autorizacion));
    }
}
