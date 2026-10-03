<?php

namespace App\Services\Dispensario\Reportes;

use Illuminate\Support\Facades\DB;

/**
 * El informe del Dispensario para la jefatura: lo de todos los reportes
 * agregados en una sola hoja, por secciones. Es el que se presenta firmado,
 * por eso sale también en PDF.
 *
 * Se arma con los demás reportes en vez de repetir sus consultas: así sus
 * cifras son las mismas que las de cada reporte por separado. Agregado.
 */
final class InformeDispensarioReporte extends ReporteBase
{
    /** Cuántos diagnósticos y medicamentos entran en sus rankings. */
    private const TOP_DIAGNOSTICOS = 10;
    private const TOP_MEDICAMENTOS = 5;

    public function __construct(
        private readonly ProduccionReporte $produccion,
        private readonly MorbilidadReporte $morbilidad,
        private readonly AusentismoReporte $ausentismo,
        private readonly EnfermeriaReporte $enfermeria,
        private readonly MovimientoMedicamentosReporte $farmacia,
        private readonly ExistenciasReporte $existencias,
        private readonly GestionTurnosReporte $turnos,
        private readonly SaludOcupacionalReporte $saludOcupacional,
    ) {}

    public function clave(): string { return 'informe'; }

    public function titulo(): string { return 'Informe del Dispensario'; }

    public function area(): string { return 'Gestión'; }

    public function descripcion(): string
    {
        return 'Todo el Dispensario en una hoja, para presentar a la jefatura. También en PDF.';
    }

    public function nominal(): bool { return false; }

    public function perfiles(): array
    {
        return [AlcanceReporte::ADMINISTRACION, AlcanceReporte::AUTORIDAD];
    }

    public function filtros(): array
    {
        return [];
    }

    public function formatos(): array
    {
        return ['excel', 'pdf'];
    }

    public function columnas(FiltrosReporte $filtros): array
    {
        return self::columnasDe([['seccion', 'Sección'], ['indicador', 'Indicador'], ['valor', 'Valor']]);
    }

    public function filas(FiltrosReporte $filtros, AlcanceReporte $alcance): array
    {
        $todo = new FiltrosReporte($filtros->desde, $filtros->hasta);
        $filas = [];
        $agregar = function (string $seccion, string $indicador, int|float|string|null $valor) use (&$filas) {
            $filas[] = ['seccion' => $seccion, 'indicador' => $indicador, 'valor' => $valor ?? '—'];
        };

        // Atención.
        $produccion = collect($this->produccion->filas($todo, $alcance));
        $pacientes = ConsultasConPaciente::query($todo, $alcance)->distinct()->count('cm.historia_clinica_id');
        $agregar('Atención', 'Consultas de medicina general', $produccion->sum('consultas_medicina'));
        $agregar('Atención', 'Consultas de odontología', $produccion->sum('consultas_odontologia'));
        $agregar('Atención', 'De ellas, primera vez', $produccion->sum('primera_vez'));
        $agregar('Atención', 'Pacientes distintos', $pacientes);
        $agregar('Atención', 'Procedimientos odontológicos', $produccion->sum('procedimientos'));
        $agregar('Atención', 'Recetas emitidas', $produccion->sum('recetas'));

        // Morbilidad.
        foreach (array_slice($this->morbilidad->filas($todo, $alcance), 0, self::TOP_DIAGNOSTICOS) as $dx) {
            $agregar('Principales diagnósticos', "{$dx['cie10']} {$dx['diagnostico']}", "{$dx['total']} ({$dx['porcentaje']} %)");
        }

        // Ausentismo.
        $ausentismo = collect($this->ausentismo->filas($todo, $alcance));
        $agregar('Ausentismo por enfermedad', 'Certificados médicos a servidores', $ausentismo->sum('certificados'));
        $agregar('Ausentismo por enfermedad', 'Días de reposo', $ausentismo->sum('dias_reposo'));

        // Enfermería.
        $enfermeria = collect($this->enfermeria->filas($todo, $alcance))->groupBy('grupo');
        $agregar('Enfermería', 'Servicios de enfermería', $enfermeria->get('Servicio de enfermería', collect())->sum('cantidad'));
        $agregar('Enfermería', 'Triajes', $enfermeria->get('Triaje', collect())->sum('cantidad'));
        $agregar('Enfermería', 'De ellos, críticos', $enfermeria->get('Triaje', collect())->firstWhere('detalle', 'Crítico')['cantidad'] ?? 0);

        // Farmacia.
        $farmacia = collect($this->farmacia->filas($todo, $alcance));
        $agregar('Farmacia', 'Unidades despachadas', $farmacia->sum('despachado'));
        foreach ($farmacia->where('despachado', '>', 0)->take(self::TOP_MEDICAMENTOS) as $m) {
            $agregar('Farmacia', "Más despachado: {$m['medicamento']}", $m['despachado']);
        }
        $lotes = collect($this->existencias->filas($todo, $alcance))->countBy('estado');
        $agregar('Farmacia', 'Lotes caducados con existencias (hoy)', $lotes->get('Caducado', 0));
        $agregar('Farmacia', 'Lotes por caducar (hoy)', $lotes->get('Por caducar', 0));

        // Turnos.
        $turnos = GestionTurnosReporte::resumir(
            DB::table('agendas_medicas as a')
                ->leftJoin('consultas_medicas as c', fn ($j) => $j->on('c.agenda_medica_id', '=', 'a.id')->whereNull('c.deleted_at'))
                ->whereNull('a.deleted_at')
                ->whereBetween('a.fecha', [$todo->desde->toDateString(), $todo->hasta->toDateString()])
                ->get(['a.id', 'a.estado', 'a.registrado_en', 'c.created_at as consulta_en'])
                ->unique('id')
        );
        $agregar('Turnos', 'Turnos dados', $turnos['turnos']);
        $agregar('Turnos', 'Atendidos', $turnos['atendidos']);
        $agregar('Turnos', 'No se presentaron', $turnos['no_presentados']);
        $agregar('Turnos', 'Asistencia (%)', $turnos['asistencia']);
        $agregar('Turnos', 'Espera promedio (min)', $turnos['espera_min']);

        // Salud ocupacional.
        $so = collect($this->saludOcupacional->filas($todo, $alcance));
        $agregar('Salud ocupacional', 'Evaluaciones FEMO cerradas', $so->sum('evaluaciones'));
        $agregar('Salud ocupacional', 'No aptos', $so->sum('no_apto'));
        $agregar('Salud ocupacional', 'Solicitudes sin atender (hoy)', $so->sum('solicitudes_pendientes'));
        $agregar('Salud ocupacional', 'De ellas, vencidas', $so->sum('solicitudes_vencidas'));

        return $filas;
    }
}
