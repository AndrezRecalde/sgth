<?php

namespace App\Http\Controllers\Expediente;

use App\Enums\EstadoPermiso;
use App\Enums\TipoPermiso;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Asistencia\PermisoServidor;
use Illuminate\Http\JsonResponse;

/**
 * Cuántos permisos por enfermedad pidió un servidor en los últimos meses.
 *
 * Lo pidió la UATH como indicador de salud ocupacional. Dos aclaraciones que
 * conviene no perder:
 *
 * 1. **Son permisos, no días.** Un permiso guarda `fecha`, `hora_inicio` y
 *    `hora_fin`: es una porción de un día, no un rango. Pasar horas a días
 *    exigiría asumir una jornada y daría un número que la UATH no podría
 *    cuadrar con sus registros. Lo confirmaron el 2026-09-26.
 * 2. **Mide episodios, no tiempo fuera.** Alguien con una enfermedad de diez
 *    días seguidos puede haber presentado un solo permiso, y otro con tres
 *    molestias cortas, tres. La pantalla lo rotula como «permisos», nunca
 *    como «ausencias».
 *
 * El motivo de cada permiso NO viaja: `observacion` es texto libre y puede
 * llevar un diagnóstico escrito. La UATH eligió el resumen sin detalle.
 */
final class AusentismoSaludController extends Controller
{
    private const MESES = 12;

    /**
     * Estados que cuentan como ausencia real por enfermedad.
     *
     * Quedan fuera `pendiente` —puede no llegar a ocurrir—, `anulado` y
     * `rechazado` —no se concedieron— y `falta_injustificada`: ahí la persona
     * sí faltó, pero el sistema dice que NO por una enfermedad justificada, y
     * contarla mezclaría un asunto disciplinario con un indicador médico.
     *
     * Es criterio propio a falta de confirmación de la UATH: se les preguntó
     * el 2026-09-26 y contestaron sobre el tipo de permiso, no sobre el
     * estado. Si responden otra cosa, se cambia aquí y nada más.
     */
    private const ESTADOS_QUE_CUENTAN = [
        EstadoPermiso::ACTIVO,
        EstadoPermiso::VALIDADO_TRABAJO_SOCIAL,
    ];

    public function __invoke(int $servidorId): JsonResponse
    {
        $desde = now()->subMonths(self::MESES)->startOfDay();

        $permisos = PermisoServidor::where('servidor_id', $servidorId)
            ->where('tipo', TipoPermiso::ENFERMEDAD)
            ->whereIn('estado', array_map(
                fn (EstadoPermiso $e) => $e->value,
                self::ESTADOS_QUE_CUENTAN,
            ))
            // Los últimos doce meses HASTA HOY: sin el tope, un permiso con
            // fecha de la semana próxima ya contaba. Y sin `whereDate`, que
            // envolvía la columna en un cast y no usaba el índice.
            ->whereBetween('fecha', [$desde->toDateString(), now()->toDateString()])
            ->count();

        return ApiResponse::ok([
            'permisos' => $permisos,
            'meses'    => self::MESES,
            'desde'    => $desde->toDateString(),
        ], 'Ausentismo por salud del servidor.');
    }
}
