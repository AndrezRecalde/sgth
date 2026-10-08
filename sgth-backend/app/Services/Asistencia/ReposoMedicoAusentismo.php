<?php

namespace App\Services\Asistencia;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Los reposos médicos como ausencia por enfermedad, para los indicadores.
 *
 * Desde el 2026-10-08 el reposo del dispensario es un certificado médico y no
 * un permiso, así que el Consolidado, Riesgos Laborales › Ausentismo, el
 * tablero de SSO y el panel de salud del Expediente lo suman aparte de los
 * permisos por enfermedad. Decidido con el usuario:
 * - Solo cuentan los certificados aprobados por TH o Trabajo Social, en Sirha7
 *   o a mano, y no anulados.
 * - En días calendario: cada día del reposo que cae en el período cuenta, fin
 *   de semana incluido, y vale una jornada (`JornadaLaboral::MINUTOS`).
 */
final class ReposoMedicoAusentismo
{
    /**
     * Una fila por certificado con lo que aporta al período: `servidor_id`,
     * `fecha_inicio` y `minutos`. Pensada para sumarse a los permisos con un
     * `UNION ALL`, con las mismas columnas.
     */
    public static function filasEntre(string $desde, string $hasta): Builder
    {
        return DB::table('certificados_medicos')
            ->whereNull('certificados_medicos.deleted_at')
            ->whereNull('certificados_medicos.anulado_en')
            ->whereNotNull('certificados_medicos.aprobado_en')
            ->whereNotNull('certificados_medicos.servidor_id')
            ->whereDate('certificados_medicos.fecha_inicio', '<=', $hasta)
            ->whereDate('certificados_medicos.fecha_fin', '>=', $desde)
            ->selectRaw('certificados_medicos.servidor_id AS servidor_id')
            ->selectRaw('certificados_medicos.fecha_inicio AS fecha')
            // Días del reposo dentro del período, contados de uno en uno.
            ->selectRaw(
                '((LEAST(certificados_medicos.fecha_fin, ?::date) - GREATEST(certificados_medicos.fecha_inicio, ?::date) + 1) * ?) AS minutos',
                [$hasta, $desde, JornadaLaboral::MINUTOS]
            );
    }
}
