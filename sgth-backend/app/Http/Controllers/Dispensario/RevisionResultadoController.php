<?php

namespace App\Http\Controllers\Dispensario;

use App\Enums\EstadoRevisionResultado;
use App\Enums\InterpretacionResultado;
use App\Exceptions\ReglaNegocioException;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Dispensario\AlcanceClinico;
use App\Models\Dispensario\ResultadoMedico;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * La revisión del médico sobre un resultado (fase 4 de
 * docs/diseno-examenes-medicos.md, D3): lo lee y lo marca como normal,
 * alterado o crítico, con un comentario.
 *
 * Lo revisa quien pidió el examen o, si no está, cualquier otro médico. Un
 * crítico que revisó otro queda fijo en «Mi jornada» de quien lo pidió hasta
 * que lo ve; no avisa a nadie más.
 */
final class RevisionResultadoController extends Controller
{
    public function revisar(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'interpretacion'    => ['required', 'string', Rule::in(InterpretacionResultado::valores())],
            // Un alterado o un crítico sin decir qué se vio no le sirve a quien
            // lo lea después.
            'comentario_medico' => ['nullable', 'string', 'max:2000',
                Rule::requiredIf(fn () => $request->input('interpretacion') !== InterpretacionResultado::NORMAL->value),
            ],
        ], [
            'comentario_medico.required' => 'Explique qué está alterado: lo leerá quien vea el resultado después.',
        ]);

        $usuario = $request->user();

        // Como la bandeja: cada profesional, los de su especialidad
        // (docs/plan-roles-y-accesos.md, PR 5).
        if (! AlcanceClinico::veResultado($usuario, $id)) {
            return ApiResponse::error('Este resultado es de otra especialidad.', null, 403);
        }

        $resultado = DB::transaction(function () use ($request, $id, $usuario) {
            $resultado = ResultadoMedico::with(['ordenExamen', 'consultaMedica', 'revisor.servidor'])
                ->lockForUpdate()
                ->findOrFail($id);

            // Una revisión ajena no se pisa: la corrige quien la hizo. Las que
            // llegaron revisadas con la migración, sin revisor, sí se pueden
            // completar.
            if ($resultado->revisado_por !== null && $resultado->revisado_por !== $usuario->id) {
                throw new ReglaNegocioException(
                    'Este resultado ya lo revisó ' . ($resultado->revisor?->nombre_completo ?? 'otro médico')
                    . ' el ' . $resultado->revisado_en?->format('d/m/Y')
                    . '. Solo quien lo revisó puede corregir su lectura.'
                );
            }

            $interpretacion = InterpretacionResultado::from($request->string('interpretacion')->value());
            $comentario = trim((string) $request->input('comentario_medico', ''));

            $resultado->update([
                'estado_revision'   => EstadoRevisionResultado::REVISADO,
                'interpretacion'    => $interpretacion,
                'comentario_medico' => $comentario !== '' ? $comentario : null,
                'revisado_por'      => $usuario->id,
                'revisado_en'       => now(),
                // Quien lo pidió y lo revisa él mismo ya vio el crítico.
                'critico_visto_en'  => $interpretacion === InterpretacionResultado::CRITICO
                    && $resultado->responsableId() === $usuario->id ? now() : null,
            ]);

            return $resultado;
        });

        return ApiResponse::ok(
            $resultado->refresh()->load(ResultadoMedicoController::RELACIONES),
            'Resultado revisado.'
        );
    }

    /**
     * Quien pidió el examen da por visto un crítico que revisó otro médico:
     * deja de estar fijo en su «Mi jornada».
     */
    public function enterado(Request $request, int $id): JsonResponse
    {
        $resultado = ResultadoMedico::with(['ordenExamen', 'consultaMedica'])->findOrFail($id);

        abort_if(
            $resultado->responsableId() !== $request->user()->id,
            403,
            'Solo quien pidió el examen da por visto su resultado crítico.'
        );

        if ($resultado->interpretacion !== InterpretacionResultado::CRITICO || $resultado->critico_visto_en !== null) {
            throw new ReglaNegocioException('Este resultado no tiene un crítico pendiente de ver.');
        }

        $resultado->update(['critico_visto_en' => now()]);

        return ApiResponse::ok(
            $resultado->refresh()->load(ResultadoMedicoController::RELACIONES),
            'Resultado crítico visto.'
        );
    }
}
