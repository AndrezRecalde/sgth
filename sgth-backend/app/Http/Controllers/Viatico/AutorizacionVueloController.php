<?php

namespace App\Http\Controllers\Viatico;

use App\Http\Controllers\Controller;
use App\Models\Viatico\AutorizacionVuelo;
use App\Http\Resources\Viatico\AutorizacionVueloResource;
use App\Http\Responses\ApiResponse;
use App\Models\Viatico\Viatico;
use App\Services\Viatico\ViaticoEstadoService;
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
    public function __construct(private ViaticoEstadoService $estados) {}

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

    /*
    | Solo una autorización pendiente, y nunca la de un viático en el que viaja
    | quien decide: lo comprueba el servicio de estados. La autorización y la
    | lectura de `observacion` se quedan en cada método público para que el
    | contrato OpenAPI siga documentando el 403 y el cuerpo.
    */

    public function aprobar(Request $request, string $id): JsonResponse
    {
        $this->authorize('autorizarVuelos', Viatico::class);

        $autorizacion = $this->estados->decidirVuelo(
            (int) $id, $request->user(), 'aprobada', $request->input('observacion'),
        );

        return ApiResponse::ok(new AutorizacionVueloResource($autorizacion));
    }

    public function rechazar(Request $request, string $id): JsonResponse
    {
        $this->authorize('autorizarVuelos', Viatico::class);

        // El rechazo dice por qué: la pantalla mandaba siempre el mismo texto
        // fijo y el servidor no sabía qué cambiar del vuelo.
        $datos = $request->validate([
            'observacion' => ['required', 'string', 'max:1000'],
        ], [
            'observacion.required' => 'Indique por qué se rechaza el vuelo.',
        ]);

        $autorizacion = $this->estados->decidirVuelo(
            (int) $id, $request->user(), 'rechazada', $datos['observacion'],
        );

        return ApiResponse::ok(new AutorizacionVueloResource($autorizacion));
    }

    public function subirDocumento(Request $request, string $id): JsonResponse
    {
        $autorizacion = AutorizacionVuelo::findOrFail($id);

        $viatico = $autorizacion->viatico()->firstOrFail();
        $this->authorize('editar', $viatico);
        $this->estados->asegurarEditable($viatico, $request->user());

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
