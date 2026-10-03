<?php

namespace App\Services\Dispensario;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * «Mi jornada»: lo de cada profesional, y solo lo suyo.
 *
 * El tablero del Dispensario es de la jefatura y compara a unos con otros.
 * Esto es lo contrario: qué tengo que hacer hoy, qué dejé pendiente y cómo va
 * mi mes. Es operativo antes que estadístico: si fuera un tablero de análisis,
 * nadie lo abriría entre paciente y paciente.
 */
final class MiJornadaService
{
    /** El perfil de jornada de cada rol, en el orden en que se elige sin pedido. */
    private const ROLES = ['medico' => 'medico', 'odontologo' => 'odontologo', 'enfermeria' => 'enfermera'];

    /**
     * `$pedido` es el perfil de la pantalla desde la que se pregunta
     * (`medico`, `odontologo` o `enfermeria`): quien tiene varios roles ve en
     * Odontología su jornada de odontólogo, no la de médico. Se respeta solo
     * si tiene el rol; si no, se le da el primero que tenga.
     */
    public function resumen(User $usuario, ?string $pedido = null): array
    {
        $propios = array_keys(array_filter(self::ROLES, fn ($rol) => $usuario->hasRole($rol)));
        $perfil = in_array($pedido, $propios, true) ? $pedido : ($propios[0] ?? null);

        return match ($perfil) {
            'medico', 'odontologo' => ['perfil' => $perfil, ...$this->clinico($usuario, $perfil)],
            'enfermeria' => ['perfil' => $perfil, ...$this->enfermeria($usuario)],
            default => ['perfil' => null],
        };
    }

    private function inicioMes(): Carbon
    {
        return Carbon::now()->startOfMonth();
    }

    private function clinico(User $usuario, string $perfil): array
    {
        $turnos = DB::table('agendas_medicas as a')
            ->whereNull('a.deleted_at')
            ->where('a.medico_id', $usuario->id)
            ->whereDate('a.fecha', Carbon::today());

        $esperando = (clone $turnos)->whereIn('a.estado', ['en_espera', 'en_sala']);

        // Listo para pasar: no necesita triaje, o enfermería ya lo hizo.
        $listos = (clone $esperando)->where(fn ($q) => $q
            ->where('a.requiere_triaje', false)
            ->orWhereExists(fn ($t) => $t->from('triajes')->whereColumn('triajes.agenda_medica_id', 'a.id')));

        $porEstado = (clone $turnos)->select('a.estado', DB::raw('COUNT(*) as total'))
            ->groupBy('a.estado')->pluck('total', 'estado');

        // Un borrador vivo es una consulta empezada y sin cerrar: el turno
        // todavía no está atendido ni cancelado.
        $borradores = DB::table('borradores_consulta as b')
            ->join('agendas_medicas as a', 'a.id', '=', 'b.agenda_medica_id')
            ->where('b.medico_id', $usuario->id)
            ->whereNotIn('a.estado', ['atendido', 'cancelada'])
            ->count();

        $consultasMes = DB::table('consultas_medicas')
            ->where('consultas_medicas.medico_id', $usuario->id)
            ->where('consultas_medicas.created_at', '>=', $this->inicioMes());

        $reposos = DB::table('certificados_medicos')
            ->where('emitido_por', $usuario->id)
            ->whereNull('anulado_en')
            ->whereNull('deleted_at')
            ->where('fecha_inicio', '>=', $this->inicioMes()->toDateString())
            ->selectRaw('COUNT(*) as certificados, COALESCE(SUM(dias_reposo), 0) as dias')
            ->first();

        $diagnosticos = (clone $consultasMes)
            ->join('diagnosticos_cie10 as d', 'd.id', '=', 'consultas_medicas.diagnostico_cie10_id')
            ->select('d.codigo', 'd.descripcion', DB::raw('COUNT(*) as total'))
            ->groupBy('d.id', 'd.codigo', 'd.descripcion')
            ->orderByDesc('total')->orderBy('d.codigo')
            ->limit(3)
            ->get()
            ->map(fn ($d) => ['codigo' => $d->codigo, 'descripcion' => $d->descripcion, 'total' => (int) $d->total])
            ->all();

        return [
            'hoy' => [
                'esperando' => (clone $esperando)->count(),
                'listos' => $listos->count(),
                'en_consulta' => (int) $porEstado->get('en_consulta', 0),
                'atendidos' => (int) $porEstado->get('atendido', 0),
                'no_presentados' => (int) $porEstado->get('no_presentado', 0),
            ],
            'pendientes' => [
                'borradores' => $borradores,
                // Las fichas FEMO que este médico dejó como borrador: la
                // solicitud sigue en curso con su ficha enlazada.
                'fichas_femo' => $perfil === 'medico'
                    ? DB::table('solicitudes_certificacion_medica as s')
                        ->join('fichas_salud_ocupacional as f', 'f.id', '=', 's.ficha_femo_id')
                        ->where('s.estado', 'en_proceso')
                        ->where('f.evaluador_id', $usuario->id)
                        ->whereNull('f.deleted_at')
                        ->count()
                    : 0,
                // Evaluaciones ocupacionales con el triaje hecho, listas para
                // que cualquier médico las inicie.
                'evaluaciones_listas' => $perfil === 'medico'
                    ? DB::table('solicitudes_certificacion_medica as s')
                        ->where('s.estado', 'pendiente')
                        ->whereExists(fn ($q) => $q->from('solicitud_constantes_vitales as v')->whereColumn('v.solicitud_id', 's.id'))
                        ->count()
                    : 0,
            ],
            'mes' => [
                'consultas' => (clone $consultasMes)->count(),
                'pacientes' => (clone $consultasMes)->distinct()->count('consultas_medicas.historia_clinica_id'),
                'reposos' => (int) $reposos->certificados,
                'dias_reposo' => (int) $reposos->dias,
                'procedimientos' => $perfil === 'odontologo'
                    ? DB::table('odontograma_procedimientos')
                        ->where('realizado_por', $usuario->id)
                        ->whereNull('anulado_en')
                        ->whereNull('deleted_at')
                        ->where('fecha', '>=', $this->inicioMes()->toDateString())
                        ->count()
                    : 0,
                'diagnosticos' => $diagnosticos,
            ],
        ];
    }

    private function enfermeria(User $usuario): array
    {
        // La cola de triaje es de todo el equipo de enfermería, no de una.
        $porTriar = DB::table('agendas_medicas as a')
            ->whereNull('a.deleted_at')
            ->whereDate('a.fecha', Carbon::today())
            ->where('a.requiere_triaje', true)
            ->whereIn('a.estado', ['en_espera', 'en_sala'])
            ->whereNotExists(fn ($t) => $t->from('triajes')->whereColumn('triajes.agenda_medica_id', 'a.id'))
            ->count();

        $triajeSso = DB::table('solicitudes_certificacion_medica as s')
            ->where('s.estado', 'pendiente')
            ->whereNotExists(fn ($q) => $q->from('solicitud_constantes_vitales as v')->whereColumn('v.solicitud_id', 's.id'))
            ->count();

        $misAtenciones = DB::table('atenciones_enfermeria')
            ->where('enfermera_id', $usuario->id)
            ->whereNull('anulado_en')
            ->whereNull('deleted_at');

        $porServicio = (clone $misAtenciones)
            ->where('atenciones_enfermeria.atendido_en', '>=', $this->inicioMes())
            ->leftJoin('catalogo_servicios_enfermeria as c', 'c.id', '=', 'atenciones_enfermeria.catalogo_servicio_id')
            ->select(DB::raw("COALESCE(c.nombre, 'Sin servicio') as servicio"), DB::raw('COUNT(*) as total'))
            ->groupBy('c.nombre')
            ->orderByDesc('total')->orderBy('servicio')
            ->limit(5)
            ->get()
            ->map(fn ($f) => ['servicio' => $f->servicio, 'total' => (int) $f->total])
            ->all();

        return [
            'hoy' => [
                'por_triar' => $porTriar,
                'triaje_sso' => $triajeSso,
                'mis_atenciones' => (clone $misAtenciones)->whereDate('atendido_en', Carbon::today())->count(),
            ],
            'mes' => [
                'atenciones' => (clone $misAtenciones)->where('atendido_en', '>=', $this->inicioMes())->count(),
                'triajes' => DB::table('triajes')
                    ->where('enfermera_id', $usuario->id)
                    ->where('created_at', '>=', $this->inicioMes())
                    ->count(),
                'por_servicio' => $porServicio,
            ],
        ];
    }
}
