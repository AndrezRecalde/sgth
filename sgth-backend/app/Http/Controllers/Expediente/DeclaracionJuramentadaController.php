<?php

namespace App\Http\Controllers\Expediente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expediente\StoreDeclaracionJuramentadaRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Expediente\DeclaracionJuramentada;
use App\Models\Expediente\Servidor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DeclaracionJuramentadaController extends Controller
{
    public function index(int $servidorId): JsonResponse
    {
        $servidor = Servidor::findOrFail($servidorId);
        $declaraciones = $servidor->declaracionesJuramentadas()
            ->orderByDesc('fecha_declaracion')
            ->get();
        return ApiResponse::ok($declaraciones, 'Declaraciones juramentadas.');
    }

    public function store(
        StoreDeclaracionJuramentadaRequest $request,
        int $servidorId
    ): JsonResponse {
        $servidor = Servidor::findOrFail($servidorId);
        $datos = $request->validated();

        if ($request->hasFile('documento')) {
            $archivo  = $request->file('documento');
            $ruta = $archivo->storeAs(
                "expedientes/{$servidor->cedula}/declaraciones",
                time() . '_' . $archivo->getClientOriginalName(),
                'local'
            );
            $datos['documento_ruta']           = $ruta;
            $datos['documento_nombre_archivo'] = $archivo->getClientOriginalName();
        }

        unset($datos['documento']);
        $declaracion = DeclaracionJuramentada::create(
            array_merge($datos, ['servidor_id' => $servidorId])
        );

        return ApiResponse::created($declaracion, 'Declaración juramentada registrada.');
    }

    public function destroy(int $servidorId, int $id): JsonResponse
    {
        $declaracion = DeclaracionJuramentada::where('servidor_id', $servidorId)
            ->findOrFail($id);

        if ($declaracion->documento_ruta) {
            Storage::disk('local')->delete($declaracion->documento_ruta);
        }

        $declaracion->delete();
        return ApiResponse::ok(null, 'Declaración eliminada.');
    }

    public function exportar(Request $request, int $servidorId): mixed
    {
        $request->validate([
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin'    => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'formato'      => ['required', 'in:txt,pdf'],
        ]);

        $servidor = Servidor::findOrFail($servidorId);
        $declaraciones = $servidor->declaracionesJuramentadas()
            ->whereBetween('fecha_declaracion', [
                $request->fecha_inicio,
                $request->fecha_fin,
            ])
            ->orderBy('fecha_declaracion')
            ->get();

        if ($declaraciones->isEmpty()) {
            return ApiResponse::ok([], 'No hay declaraciones en el rango indicado.');
        }

        $lineas = $declaraciones->map(fn($d) => $d->toLineaContraloria());
        $contenido = $lineas->implode("\n");
        $nombreArchivo = "declaraciones_{$servidor->cedula}_{$request->fecha_inicio}_{$request->fecha_fin}";

        if ($request->formato === 'txt') {
            return response($contenido, 200, [
                'Content-Type'        => 'text/plain; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$nombreArchivo}.txt\"",
            ]);
        }

        $html = view('exports.declaraciones-contraloria', [
            'servidor'     => $servidor,
            'declaraciones' => $declaraciones,
            'lineas'        => $lineas,
            'fechaInicio'   => $request->fecha_inicio,
            'fechaFin'      => $request->fecha_fin,
        ])->render();

        $pdf = app('dompdf.wrapper')->loadHTML($html);

        return $pdf->download("{$nombreArchivo}.pdf");
    }

    public function verDocumento(int $servidorId, int $id): mixed
    {
        $declaracion = DeclaracionJuramentada::where('servidor_id', $servidorId)
            ->findOrFail($id);

        if (!$declaracion->documento_ruta ||
            !Storage::disk('local')->exists($declaracion->documento_ruta)) {
            return ApiResponse::error('Documento no encontrado.', 404);
        }

        return Storage::disk('local')->response(
            $declaracion->documento_ruta,
            $declaracion->documento_nombre_archivo
        );
    }
}