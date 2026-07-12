<?php

namespace App\Http\Controllers\Dispensario;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Dispensario\SolicitudCertificacionMedica;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SolicitudCertificacionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SolicitudCertificacionMedica::with([
            'servidor:id,nombre,apellido,cedula',
            'postulante:id,nombres,apellidos,cedula,correo',
            'convocatoria:id,codigo,titulo',
            'solicitadoPor:id,usuario_ti,servidor_id',
            'solicitadoPor.servidor:id,nombre,apellido',
        ])->orderBy('created_at', 'desc');

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        if ($request->filled('tipo_evento')) {
            $query->where('tipo_evento', $request->input('tipo_evento'));
        }

        return ApiResponse::ok($query->paginate(
            $request->integer('per_page', 20)
        ));
    }

    public function show(int $id): JsonResponse
    {
        $solicitud = SolicitudCertificacionMedica::with([
            'servidor',
            'postulante',
            'convocatoria.puesto.cargo',
            'solicitadoPor.servidor:id,nombre,apellido',
        ])->findOrFail($id);

        return ApiResponse::ok($solicitud);
    }

    public function iniciarProceso(
        Request $request,
        int $id
    ): JsonResponse {
        $solicitud = SolicitudCertificacionMedica::findOrFail($id);

        if ($solicitud->estado !== 'pendiente') {
            return ApiResponse::error(
                'La solicitud no está en estado pendiente.', null, 422
            );
        }

        $solicitud->update(['estado' => 'en_proceso']);

        return ApiResponse::ok(
            $solicitud, 'Proceso iniciado correctamente.'
        );
    }

    public function completar(
        Request $request,
        int $id
    ): JsonResponse {
        $request->validate([
            'ficha_femo_id' => ['nullable', 'integer',
                'exists:fichas_salud_ocupacional,id'],
        ]);

        $solicitud = SolicitudCertificacionMedica::findOrFail($id);

        $solicitud->update([
            'estado'       => 'completada',
            'ficha_femo_id'=> $request->integer('ficha_femo_id') ?: null,
        ]);

        return ApiResponse::ok(
            $solicitud, 'Solicitud completada correctamente.'
        );
    }
}
