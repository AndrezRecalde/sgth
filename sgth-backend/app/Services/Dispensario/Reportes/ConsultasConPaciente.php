<?php

namespace App\Services\Dispensario\Reportes;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Las consultas del período con su paciente y su profesional, ya filtradas y
 * acotadas al alcance de quien pide el reporte. La comparten el registro de
 * atenciones y la morbilidad.
 *
 * Cada consulta guardada en `consultas_medicas` está cerrada: los borradores
 * viven en `borradores_consulta`, así que aquí no hay nada a medias.
 *
 * El paciente sale de su historia clínica: un servidor, un familiar (su unidad
 * es la del servidor titular) o un candidato del preocupacional, que solo
 * consta por la cédula en `postulantes`. Quien no tiene el sexo registrado
 * —los familiares anteriores a ese campo— sale como «Sin dato», sin adivinar.
 */
final class ConsultasConPaciente
{
    public static function query(FiltrosReporte $filtros, AlcanceReporte $alcance): Builder
    {
        // Un candidato puede estar inscrito en varias convocatorias con la
        // misma cédula: se toma una sola fila por cédula para no duplicar.
        $candidatos = DB::table('postulantes')
            ->selectRaw('DISTINCT ON (cedula) cedula, nombres, apellidos, genero, fecha_nacimiento')
            ->orderBy('cedula')->orderByDesc('id');

        $query = DB::table('consultas_medicas as cm')
            ->join('historias_clinicas as hc', 'hc.id', '=', 'cm.historia_clinica_id')
            ->leftJoin('servidores as s', 's.id', '=', 'hc.servidor_id')
            ->leftJoin('cargas_familiares as cf', 'cf.id', '=', 'hc.carga_familiar_id')
            ->leftJoin('servidores as tit', 'tit.id', '=', 'cf.servidor_id')
            ->leftJoinSub($candidatos, 'p', 'p.cedula', '=', 'hc.cedula_paciente')
            ->leftJoin('unidades_administrativas as ua', 'ua.id', '=',
                DB::raw('COALESCE(s.unidad_administrativa_id, tit.unidad_administrativa_id)'))
            ->leftJoin('diagnosticos_cie10 as d', 'd.id', '=', 'cm.diagnostico_cie10_id')
            ->leftJoin('users as u', 'u.id', '=', 'cm.medico_id')
            ->leftJoin('servidores as ps', 'ps.id', '=', 'u.servidor_id')
            ->whereNull('cm.deleted_at')
            ->whereBetween('cm.fecha_consulta', [$filtros->desde->toDateString(), $filtros->hasta->toDateString()])
            ->select([
                'cm.id', 'cm.fecha_consulta', 'cm.hora_consulta', 'cm.especialidad',
                'cm.tipo_atencion', 'cm.tipo_diagnostico', 'cm.medico_id',
                'hc.tipo_paciente',
                DB::raw('COALESCE(s.cedula, cf.cedula, hc.cedula_paciente) as cedula_paciente'),
                DB::raw("TRIM(COALESCE(s.nombre || ' ' || s.apellido, cf.nombres || ' ' || cf.apellidos, p.nombres || ' ' || p.apellidos, '')) as paciente"),
                DB::raw('COALESCE(s.fecha_nacimiento, cf.fecha_nacimiento, p.fecha_nacimiento) as fecha_nacimiento'),
                DB::raw('COALESCE(s.genero, cf.genero, p.genero) as genero'),
                'ua.nombre as unidad',
                'd.codigo as cie10_codigo', 'd.descripcion as cie10_descripcion',
                DB::raw("COALESCE(NULLIF(TRIM(COALESCE(ps.nombre, '') || ' ' || COALESCE(ps.apellido, '')), ''), u.usuario_ti) as profesional"),
            ]);

        if ($filtros->especialidad) {
            $query->where('cm.especialidad', $filtros->especialidad);
        }

        match ($filtros->tipoPaciente) {
            'servidor' => $query->whereNotNull('hc.servidor_id'),
            'familiar' => $query->whereNotNull('hc.carga_familiar_id'),
            default    => null,
        };

        if ($filtros->unidadId) {
            $query->where('ua.id', $filtros->unidadId);
        }

        // Lo propio manda sobre el filtro: un médico no puede pedir las
        // consultas de otro poniendo su id en la URL.
        $profesional = $alcance->profesionalId ?? $filtros->profesionalId;
        if ($profesional) {
            $query->where('cm.medico_id', $profesional);
        }

        return $query;
    }

    /** Años cumplidos el día de la consulta; `null` sin fecha de nacimiento. */
    public static function edad(?string $nacimiento, string $fechaConsulta): ?int
    {
        return $nacimiento
            ? (int) CarbonImmutable::parse($nacimiento)->diffInYears(CarbonImmutable::parse($fechaConsulta))
            : null;
    }

    /**
     * Grupos de edad de los informes de morbilidad. Los menores van aparte:
     * llegan como cargas familiares y no son la población laboral.
     */
    public static function grupoEdad(?int $edad): string
    {
        return match (true) {
            $edad === null => 'Sin dato',
            $edad < 15     => 'Menor de 15',
            $edad < 30     => '15 a 29',
            $edad < 45     => '30 a 44',
            $edad < 65     => '45 a 64',
            default        => '65 o más',
        };
    }

    public static function sexo(?string $genero): string
    {
        return match ($genero) {
            'masculino' => 'Hombre',
            'femenino'  => 'Mujer',
            'otro'      => 'Otro',
            default     => 'Sin dato',
        };
    }
}
