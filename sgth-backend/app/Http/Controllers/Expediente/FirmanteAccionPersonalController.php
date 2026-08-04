<?php

namespace App\Http\Controllers\Expediente;

use App\Enums\RolFirmaAccionPersonal;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Expediente\FirmanteAccionPersonalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Consulta de quién firmará una Acción de Personal. Es solo lectura: los
 * firmantes se derivan del organigrama (jefe de la unidad anclada como Talento
 * Humano y de la anclada como máxima autoridad), no se designan a mano. Para
 * cambiarlos se cambia el organigrama.
 */
final class FirmanteAccionPersonalController extends Controller
{
    public function __construct(
        private readonly FirmanteAccionPersonalService $firmanteService,
    ) {
    }

    /**
     * Quiénes firman a una fecha dada. Lo consume el formulario para mostrar,
     * antes de suscribir, qué nombres quedarán sellados en el documento.
     */
    public function vigentes(Request $request): JsonResponse
    {
        $fecha = $request->input('fecha', now()->toDateString());

        $vigentes = [];

        foreach (RolFirmaAccionPersonal::cases() as $rol) {
            $unidad = $this->firmanteService->unidadDe($rol);
            $firma  = $this->firmanteService->resolver($rol, $fecha);
            $servidor = $firma['servidor'];

            $vigentes[] = [
                'rol_firma' => $rol->value,
                'etiqueta'  => $rol->etiqueta(),
                'unidad'    => $unidad?->only(['id', 'nombre']),
                'servidor'  => $servidor?->only(['id', 'nombre', 'segundo_nombre', 'apellido', 'segundo_apellido', 'cedula']),
                'cargo'     => $firma['cargo'],
                'subrogado' => $firma['subrogado'],
                'resuelto'  => $servidor !== null,
                // Diagnóstico para el formulario: distingue "falta anclar la
                // unidad" de "la unidad está anclada pero el puesto de jefatura
                // está vacante".
                'motivo_sin_resolver' => match (true) {
                    $servidor !== null => null,
                    $unidad === null   => 'No hay ninguna unidad marcada para este rol de firma.',
                    default            => 'La unidad no tiene un puesto de jefatura ocupado.',
                },
            ];
        }

        return ApiResponse::ok($vigentes, 'Firmantes vigentes según el organigrama.');
    }
}
