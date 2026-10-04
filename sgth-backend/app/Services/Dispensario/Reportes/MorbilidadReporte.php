<?php

namespace App\Services\Dispensario\Reportes;

/**
 * Las causas de consulta del período: cuántas veces salió cada diagnóstico
 * principal, por sexo y grupo de edad, y cuántas fueron primera vez.
 *
 * Es agregado —no lleva nombres—, así que la autoridad también lo ve. Cuenta
 * solo el diagnóstico principal: sumar los secundarios contaría dos veces a
 * quien llegó con dos dolencias.
 */
final class MorbilidadReporte implements ReporteDispensario
{
    private const GRUPOS = ['Menor de 15', '15 a 29', '30 a 44', '45 a 64', '65 o más', 'Sin dato'];

    public function clave(): string { return 'morbilidad'; }

    public function titulo(): string { return 'Morbilidad'; }

    public function descripcion(): string
    {
        return 'Diagnósticos principales más frecuentes, por sexo y grupo de edad.';
    }

    public function nominal(): bool { return false; }

    public function perfiles(): array
    {
        return [AlcanceReporte::ADMINISTRACION, AlcanceReporte::AUTORIDAD, AlcanceReporte::MEDICO];
    }

    public function filtros(): array
    {
        return ['profesional', 'especialidad', 'tipo_paciente', 'unidad'];
    }

    public function columnas(FiltrosReporte $filtros): array
    {
        $columnas = [
            ['clave' => 'cie10', 'titulo' => 'CIE-10'],
            ['clave' => 'diagnostico', 'titulo' => 'Diagnóstico'],
            ['clave' => 'total', 'titulo' => 'Total'],
            ['clave' => 'porcentaje', 'titulo' => '%'],
            ['clave' => 'primera_vez', 'titulo' => 'Primera vez'],
            ['clave' => 'hombres', 'titulo' => 'Hombres'],
            ['clave' => 'mujeres', 'titulo' => 'Mujeres'],
            ['clave' => 'sexo_sin_dato', 'titulo' => 'Sexo sin dato'],
        ];

        foreach (self::GRUPOS as $grupo) {
            $columnas[] = ['clave' => self::claveGrupo($grupo), 'titulo' => $grupo];
        }

        return $columnas;
    }

    public function filas(FiltrosReporte $filtros, AlcanceReporte $alcance): array
    {
        $consultas = ConsultasConPaciente::query($filtros, $alcance)->get();
        $total = $consultas->count();

        return $consultas
            ->groupBy(fn ($c) => $c->cie10_codigo ?? 'sin_dx')
            ->map(function ($grupo) use ($total) {
                $primera = $grupo->first();
                $fila = [
                    'cie10'         => $primera->cie10_codigo ?? '—',
                    'diagnostico'   => $primera->cie10_descripcion ?? 'Sin diagnóstico registrado',
                    'total'         => $grupo->count(),
                    'porcentaje'    => $total ? round($grupo->count() * 100 / $total, 1) : 0,
                    'primera_vez'   => $grupo->where('tipo_atencion', 'primera_vez')->count(),
                    'hombres'       => $grupo->where('genero', 'masculino')->count(),
                    'mujeres'       => $grupo->where('genero', 'femenino')->count(),
                    'sexo_sin_dato' => $grupo->whereNotIn('genero', ['masculino', 'femenino'])->count(),
                ];

                $porGrupo = $grupo->countBy(fn ($c) => ConsultasConPaciente::grupoEdad(
                    ConsultasConPaciente::edad($c->fecha_nacimiento, (string) $c->fecha_consulta)
                ));
                foreach (self::GRUPOS as $g) {
                    $fila[self::claveGrupo($g)] = $porGrupo->get($g, 0);
                }

                return $fila;
            })
            ->sortBy([['total', 'desc'], ['cie10', 'asc']])
            ->values()
            ->all();
    }

    private static function claveGrupo(string $grupo): string
    {
        return 'edad_' . \Illuminate\Support\Str::slug($grupo, '_');
    }
}
