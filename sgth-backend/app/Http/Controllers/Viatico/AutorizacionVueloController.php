<?php

namespace App\Http\Controllers\Viatico;

use App\Http\Controllers\Controller;
use App\Models\Viatico\AutorizacionVuelo;
use App\Http\Resources\Viatico\AutorizacionVueloResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AutorizacionVueloController extends Controller
{
    public function index(): JsonResponse
    {
        \Illuminate\Support\Facades\Log::info(
            'VuelosController@index - usuario: ' .
            request()->user()?->id .
            ' total: ' .
            AutorizacionVuelo::where('estado', 'pendiente')->count()
        );

        $autorizaciones = AutorizacionVuelo::with([
            'tramo.empresa.catalogo',
            'tramo.origenProvincia',
            'tramo.destinoProvincia',
            'viatico.servidor',
        ])->where('estado', 'pendiente')->get();

        return ApiResponse::ok(AutorizacionVueloResource::collection($autorizaciones));
    }

    public function aprobar(Request $request, string $id): JsonResponse
    {
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
        $request->validate([
            'documento' => 'required|file|mimes:pdf|max:5120',
        ]);

        $autorizacion = AutorizacionVuelo::findOrFail($id);
        $path = $request->file('documento')->store('viaticos/vuelos', 'public');
        
        $autorizacion->update([
            'documento_invitacion_ruta' => $path,
        ]);

        return ApiResponse::ok(new AutorizacionVueloResource($autorizacion));
    }
}
