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
            'ficha_femo_id'     => ['nullable', 'integer',
                'exists:fichas_salud_ocupacional,id'],
            'dictamen'          => ['required',
                'in:apto,apto_con_restricciones,no_apto'],
            'observacion_medica'=> ['nullable', 'string'],
        ]);

        $solicitud = SolicitudCertificacionMedica::findOrFail($id);

        $solicitud->update([
            'estado'            => 'completada',
            'ficha_femo_id'     => $request->integer('ficha_femo_id') ?: null,
            'dictamen'          => $request->input('dictamen'),
            'observacion_medica'=> $request->input('observacion_medica'),
        ]);

        return ApiResponse::ok(
            $solicitud, 'Solicitud completada con dictamen médico.'
        );
    }

    public function confirmarIncorporacion(
        Request $request,
        int $id
    ): JsonResponse {
        $solicitud = SolicitudCertificacionMedica::with([
            'postulante.convocatoria.puesto',
        ])->findOrFail($id);

        if ($solicitud->dictamen !== 'apto' &&
            $solicitud->dictamen !== 'apto_con_restricciones') {
            return ApiResponse::error(
                'El candidato no tiene dictamen de aptitud médica.',
                null, 422
            );
        }

        if ($solicitud->estado !== 'completada') {
            return ApiResponse::error(
                'La solicitud debe estar completada con dictamen.',
                null, 422
            );
        }

        $postulante   = $solicitud->postulante;
        $convocatoria = $postulante->convocatoria;

        if (!$postulante || !$convocatoria) {
            return ApiResponse::error(
                'No se encontró el postulante o la convocatoria.',
                null, 422
            );
        }

        \DB::beginTransaction();
        try {
            $servidor = \App\Models\Expediente\Servidor::create([
                'cedula'                  => $postulante->cedula,
                'nombre'                  => $postulante->nombres,
                'segundo_nombre'          => $postulante->segundo_nombre,
                'apellido'                => $postulante->apellidos,
                'segundo_apellido'        => $postulante->segundo_apellido,
                'genero'                  => $postulante->genero,
                'estado_civil'            => $postulante->estado_civil,
                'fecha_nacimiento'        => $postulante->fecha_nacimiento?->toDateString(),
                'tipo_sangre'             => $postulante->tipo_sangre,
                'correo_personal'         => $postulante->correo,
                'telefono_celular'        => $postulante->telefono,
                'provincia_nacimiento_id' => $postulante->provincia_nacimiento_id,
                'canton_nacimiento_id'    => $postulante->canton_nacimiento_id,
                'puesto_id'               => $convocatoria->puesto_id,
                'estado'                  => true,
            ]);

            \App\Models\Seleccion\Onboarding::create([
                'postulante_id' => $postulante->id,
                'servidor_id'   => $servidor->id,
                'created_by'    => $request->user()->id,
            ]);

            \App\Models\Expediente\MovimientoPersonal::create([
                'servidor_id'     => $servidor->id,
                'tipo_movimiento' => 'ingreso',
                'descripcion'     => "Incorporación tras proceso de selección {$convocatoria->codigo}. Dictamen médico: {$solicitud->dictamen}.",
                'fecha_efectiva'  => now()->toDateString(),
                'autorizado_por'  => $request->user()->id,
            ]);

            $solicitud->update(['servidor_id' => $servidor->id]);

            $postulante->update([
                'estado' => \App\Enums\EstadoPostulante::INCORPORADO,
            ]);

            \DB::commit();

            return ApiResponse::ok(
                ['servidor_id' => $servidor->id],
                'Servidor incorporado correctamente al sistema.'
            );
        } catch (\Exception $e) {
            \DB::rollBack();
            throw $e;
        }
    }
}
