<?php

namespace App\Http\Controllers\Dispensario;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Dispensario\BorradorConsulta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * El borrador de la consulta que se está escribiendo ahora mismo.
 *
 * Siempre acotado a quien pregunta: el borrador es de quien lo escribe, y no
 * hay ninguna pantalla —ni debe haberla— donde un profesional lea lo que otro
 * lleva a medias.
 */
final class BorradorConsultaController extends Controller
{
    /** Tope de lo que cabe en un borrador, en caracteres del JSON guardado. */
    private const MAXIMO = 40_000;

    public function show(Request $request): JsonResponse
    {
        $request->validate([
            'agenda_medica_id' => [
                'required', 'integer', 'exists:agendas_medicas,id',
            ],
        ]);

        $borrador = BorradorConsulta::where('medico_id', $request->user()->id)
            ->where('agenda_medica_id', $request->integer('agenda_medica_id'))
            ->first();

        return ApiResponse::ok($borrador);
    }

    public function guardar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'agenda_medica_id' => [
                'required', 'integer', 'exists:agendas_medicas,id',
            ],
            'contenido' => ['required', 'array'],
        ]);

        // El formulario tiene sus propios topes por campo cuando se guarda de
        // verdad; aquí basta con que un borrador no pueda crecer sin medida.
        if (strlen(json_encode($datos['contenido'])) > self::MAXIMO) {
            return ApiResponse::error(
                'El borrador es demasiado extenso para guardarse.', null, 422
            );
        }

        $borrador = BorradorConsulta::updateOrCreate(
            [
                'agenda_medica_id' => $datos['agenda_medica_id'],
                'medico_id'        => $request->user()->id,
            ],
            ['contenido' => $datos['contenido']],
        );

        return ApiResponse::ok($borrador, 'Borrador guardado.');
    }

    /** Descarta el borrador: lo pide el médico, o se limpia al guardar. */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'agenda_medica_id' => [
                'required', 'integer', 'exists:agendas_medicas,id',
            ],
        ]);

        BorradorConsulta::where('medico_id', $request->user()->id)
            ->where('agenda_medica_id', $request->integer('agenda_medica_id'))
            ->delete();

        return ApiResponse::ok(null, 'Borrador descartado.');
    }
}
