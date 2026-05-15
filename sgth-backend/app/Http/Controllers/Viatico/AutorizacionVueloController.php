<?php

namespace App\Http\Controllers\Viatico;

use App\Http\Controllers\Controller;
use App\Models\Viatico\AutorizacionVuelo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AutorizacionVueloController extends Controller
{
    public function index(): JsonResponse
    {
        $autorizaciones = AutorizacionVuelo::with(['transporte', 'viatico.servidor'])
            ->where('estado', 'pendiente')
            ->get();
            
        return response()->json($autorizaciones);
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

        return response()->json($autorizacion);
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

        return response()->json($autorizacion);
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

        return response()->json($autorizacion);
    }
}
