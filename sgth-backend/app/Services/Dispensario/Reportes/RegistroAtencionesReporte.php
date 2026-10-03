<?php

namespace App\Services\Dispensario\Reportes;

use Illuminate\Support\Facades\DB;

/**
 * Una fila por consulta, con lo que pide un registro de atenciones del estilo
 * del RDACAA del MSP: quién, cuándo, de qué edad y sexo, de qué unidad, si es
 * primera vez o subsecuente, con qué diagnósticos y quién lo atendió.
 *
 * Es nominal: nombres con diagnóstico. La autoridad no lo ve.
 */
final class RegistroAtencionesReporte extends ReporteBase
{
    private const TIPO_ATENCION = ['primera_vez' => 'Primera vez', 'subsecuente' => 'Subsecuente'];
    private const TIPO_DIAGNOSTICO = ['presuntivo' => 'Presuntivo', 'definitivo' => 'Definitivo'];
    private const ESPECIALIDAD = ['medicina_general' => 'Medicina general', 'odontologia' => 'Odontología'];
    private const TIPO_PACIENTE = ['servidor' => 'Servidor', 'familiar' => 'Familiar', 'candidato' => 'Candidato'];

    public function clave(): string { return 'atenciones'; }

    public function titulo(): string { return 'Registro de atenciones'; }

    public function area(): string { return 'Atención clínica'; }

    public function descripcion(): string
    {
        return 'Cada consulta del período con su paciente, diagnósticos CIE-10 y profesional.';
    }

    public function nominal(): bool { return true; }

    public function perfiles(): array
    {
        return [AlcanceReporte::ADMINISTRACION, AlcanceReporte::MEDICO, AlcanceReporte::ODONTOLOGO];
    }

    public function filtros(): array
    {
        return ['profesional', 'especialidad', 'tipo_paciente', 'unidad'];
    }

    public function columnas(FiltrosReporte $filtros): array
    {
        return array_map(fn ($c) => ['clave' => $c[0], 'titulo' => $c[1]], [
            ['fecha', 'Fecha'], ['hora', 'Hora'], ['cedula', 'Cédula'], ['paciente', 'Paciente'],
            ['tipo_paciente', 'Tipo'], ['sexo', 'Sexo'], ['edad', 'Edad'], ['unidad', 'Unidad administrativa'],
            ['especialidad', 'Especialidad'], ['tipo_atencion', 'Atención'], ['cie10', 'CIE-10'],
            ['diagnostico', 'Diagnóstico principal'], ['tipo_diagnostico', 'Tipo de diagnóstico'],
            ['secundarios', 'Diagnósticos secundarios'], ['profesional', 'Profesional'],
        ]);
    }

    public function filas(FiltrosReporte $filtros, AlcanceReporte $alcance): array
    {
        $consultas = ConsultasConPaciente::query($filtros, $alcance)
            ->orderBy('cm.fecha_consulta')->orderBy('cm.hora_consulta')->orderBy('cm.id')
            ->get();

        // Los secundarios de todas en una consulta, no en una por fila.
        $secundarios = DB::table('diagnosticos_secundarios_consulta as ds')
            ->join('diagnosticos_cie10 as d', 'd.id', '=', 'ds.diagnostico_cie10_id')
            ->whereIn('ds.consulta_medica_id', $consultas->pluck('id'))
            ->orderBy('ds.id')
            ->get(['ds.consulta_medica_id', 'd.codigo'])
            ->groupBy('consulta_medica_id')
            ->map(fn ($grupo) => $grupo->pluck('codigo')->implode(', '));

        return $consultas->map(fn ($c) => [
            'fecha'            => substr((string) $c->fecha_consulta, 0, 10),
            'hora'             => $c->hora_consulta ? substr((string) $c->hora_consulta, 0, 5) : null,
            'cedula'           => $c->cedula_paciente,
            'paciente'         => $c->paciente ?: null,
            'tipo_paciente'    => self::TIPO_PACIENTE[$c->tipo_paciente] ?? $c->tipo_paciente,
            'sexo'             => ConsultasConPaciente::sexo($c->genero),
            'edad'             => ConsultasConPaciente::edad($c->fecha_nacimiento, (string) $c->fecha_consulta),
            'unidad'           => $c->unidad,
            'especialidad'     => self::ESPECIALIDAD[$c->especialidad] ?? $c->especialidad,
            'tipo_atencion'    => self::TIPO_ATENCION[$c->tipo_atencion] ?? $c->tipo_atencion,
            'cie10'            => $c->cie10_codigo,
            'diagnostico'      => $c->cie10_descripcion,
            'tipo_diagnostico' => self::TIPO_DIAGNOSTICO[$c->tipo_diagnostico] ?? $c->tipo_diagnostico,
            'secundarios'      => $secundarios->get($c->id),
            'profesional'      => $c->profesional,
        ])->values()->all();
    }
}
