<?php

namespace App\Services\Sso;

use App\Contracts\Sso\SsoServiceInterface;
use App\Models\Sso\AccidenteTrabajo;
use App\Models\Sso\RiesgoLaboral;
use App\Models\Sso\EquipoProteccion;
use App\Models\Sso\InspeccionSso;
use App\Models\Sso\CapacitacionSso;
use App\Models\Sso\HorasTrabajadasPeriodo;
use App\Models\Sso\PuestoEpp;
use App\Models\Sso\EppEntrega;
use App\Enums\NivelDeficienciaRiesgo;
use App\Enums\NivelExposicionRiesgo;
use App\Enums\NivelConsecuenciasRiesgo;
use App\Enums\NivelIntervencionRiesgo;
use App\Enums\TipoEventoAccidente;
use App\Exceptions\ReglaNegocioException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

final class SsoService implements SsoServiceInterface
{
    // ── Riesgos laborales ─────────────────────────────────────────

    public function listarRiesgosLaborales(array $filtros): LengthAwarePaginator
    {
        return RiesgoLaboral::query()
            ->with(['puesto.cargo', 'factorRiesgo'])
            ->when(isset($filtros['puesto_id']), fn($q) => $q->where('puesto_id', $filtros['puesto_id']))
            ->when(isset($filtros['estado']), fn($q) => $q->where('estado', $filtros['estado']))
            ->orderByDesc('id')
            ->paginate($filtros['por_pagina'] ?? 15);
    }

    public function obtenerRiesgoLaboral(int $id): RiesgoLaboral
    {
        return RiesgoLaboral::with(['puesto.cargo', 'factorRiesgo'])->findOrFail($id);
    }

    public function registrarRiesgoLaboral(array $datos): RiesgoLaboral
    {
        $datos['created_by'] = auth()->id();
        $datos = $this->calcularNtp330($datos);
        return RiesgoLaboral::create($datos);
    }

    public function actualizarRiesgoLaboral(int $id, array $datos): RiesgoLaboral
    {
        $riesgo = RiesgoLaboral::findOrFail($id);
        $datos['updated_by'] = auth()->id();
        $datos = $this->calcularNtp330($datos, $riesgo);
        $riesgo->update($datos);
        return $riesgo->fresh();
    }

    /**
     * Calcula NP = ND × NE, NR = NP × NC y el Nivel de Intervención según NTP 330 (INSHT).
     * En actualizaciones parciales, los niveles no enviados se toman del registro existente.
     */
    private function calcularNtp330(array $datos, ?RiesgoLaboral $actual = null): array
    {
        $nivelDeficiencia = NivelDeficienciaRiesgo::from(
            $datos['nivel_deficiencia'] ?? $actual?->nivel_deficiencia?->value
        );
        $nivelExposicion = NivelExposicionRiesgo::from(
            $datos['nivel_exposicion'] ?? $actual?->nivel_exposicion?->value
        );
        $nivelConsecuencias = NivelConsecuenciasRiesgo::from(
            $datos['nivel_consecuencias'] ?? $actual?->nivel_consecuencias?->value
        );

        $nivelProbabilidad = $nivelDeficiencia->valor() * $nivelExposicion->valor();
        $nivelRiesgoValor = $nivelProbabilidad * $nivelConsecuencias->valor();

        $datos['nivel_probabilidad'] = $nivelProbabilidad;
        $datos['nivel_riesgo_valor'] = $nivelRiesgoValor;
        $datos['nivel_intervencion'] = NivelIntervencionRiesgo::desdeNivelRiesgo($nivelRiesgoValor)->value;

        return $datos;
    }

    public function eliminarRiesgoLaboral(int $id): void
    {
        RiesgoLaboral::findOrFail($id)->delete();
    }

    // ── Accidentes de trabajo ─────────────────────────────────────

    public function listarAccidentes(array $filtros): LengthAwarePaginator
    {
        return AccidenteTrabajo::query()
            ->with(['servidor', 'investigador'])
            ->when(isset($filtros['servidor_id']), fn($q) => $q->where('servidor_id', $filtros['servidor_id']))
            ->when(isset($filtros['estado']), fn($q) => $q->where('estado', $filtros['estado']))
            ->orderByDesc('fecha_accidente')
            ->paginate($filtros['por_pagina'] ?? 15);
    }

    public function obtenerAccidente(int $id): AccidenteTrabajo
    {
        return AccidenteTrabajo::with(['servidor', 'investigador'])->findOrFail($id);
    }

    public function registrarAccidente(array $datos): AccidenteTrabajo
    {
        $datos['created_by'] = auth()->id();
        $accidente = AccidenteTrabajo::create($datos);

        // La ficha de salud ocupacional tipo accidente se generará automáticamente
        // interconectando con el módulo del dispensario

        return $accidente;
    }

    public function actualizarAccidente(int $id, array $datos): AccidenteTrabajo
    {
        $accidente = AccidenteTrabajo::findOrFail($id);
        $datos['updated_by'] = auth()->id();
        $accidente->update($datos);
        return $accidente->fresh();
    }

    public function cerrarInvestigacionAccidente(int $id, array $datos): AccidenteTrabajo
    {
        $accidente = AccidenteTrabajo::findOrFail($id);
        $datos['estado'] = false; // cerrado
        $datos['updated_by'] = auth()->id();
        $accidente->update($datos);
        return $accidente->fresh();
    }

    public function eliminarAccidente(int $id): void
    {
        AccidenteTrabajo::findOrFail($id)->delete();
    }

    // ── Equipos de protección personal ────────────────────────────

    public function listarEquiposProteccion(array $filtros): LengthAwarePaginator
    {
        return EquipoProteccion::query()
            ->when(isset($filtros['tipo']), fn($q) => $q->where('tipo', $filtros['tipo']))
            ->when(isset($filtros['estado']), fn($q) => $q->where('estado', $filtros['estado']))
            ->orderBy('nombre')
            ->paginate($filtros['por_pagina'] ?? 15);
    }

    public function obtenerEquipoProteccion(int $id): EquipoProteccion
    {
        return EquipoProteccion::findOrFail($id);
    }

    public function registrarEquipoProteccion(array $datos): EquipoProteccion
    {
        $datos['created_by'] = auth()->id();
        return EquipoProteccion::create($datos);
    }

    public function actualizarEquipoProteccion(int $id, array $datos): EquipoProteccion
    {
        $equipo = EquipoProteccion::findOrFail($id);
        $datos['updated_by'] = auth()->id();
        $equipo->update($datos);
        return $equipo->fresh();
    }

    public function eliminarEquipoProteccion(int $id): void
    {
        EquipoProteccion::findOrFail($id)->delete();
    }

    // ── Inspecciones SSO ───────────────────────────────────────────

    public function listarInspecciones(array $filtros): LengthAwarePaginator
    {
        return InspeccionSso::query()
            ->with(['unidadAdministrativa', 'inspector'])
            ->when(isset($filtros['unidad_administrativa_id']), fn($q) => $q->where('unidad_administrativa_id', $filtros['unidad_administrativa_id']))
            ->when(isset($filtros['estado']), fn($q) => $q->where('estado', $filtros['estado']))
            ->orderByDesc('fecha_inspeccion')
            ->paginate($filtros['por_pagina'] ?? 15);
    }

    public function obtenerInspeccion(int $id): InspeccionSso
    {
        return InspeccionSso::with(['unidadAdministrativa', 'inspector'])->findOrFail($id);
    }

    public function registrarInspeccion(array $datos): InspeccionSso
    {
        $datos['created_by'] = auth()->id();
        return InspeccionSso::create($datos);
    }

    public function actualizarInspeccion(int $id, array $datos): InspeccionSso
    {
        $inspeccion = InspeccionSso::findOrFail($id);
        $datos['updated_by'] = auth()->id();
        $inspeccion->update($datos);
        return $inspeccion->fresh();
    }

    public function eliminarInspeccion(int $id): void
    {
        InspeccionSso::findOrFail($id)->delete();
    }

    // ── Capacitaciones SSO ─────────────────────────────────────────

    public function listarCapacitaciones(array $filtros): LengthAwarePaginator
    {
        return CapacitacionSso::query()
            ->when(isset($filtros['estado']), fn($q) => $q->where('estado', $filtros['estado']))
            ->orderByDesc('fecha')
            ->paginate($filtros['por_pagina'] ?? 15);
    }

    public function obtenerCapacitacion(int $id): CapacitacionSso
    {
        return CapacitacionSso::findOrFail($id);
    }

    public function registrarCapacitacion(array $datos): CapacitacionSso
    {
        $datos['created_by'] = auth()->id();
        return CapacitacionSso::create($datos);
    }

    public function actualizarCapacitacion(int $id, array $datos): CapacitacionSso
    {
        $capacitacion = CapacitacionSso::findOrFail($id);
        $datos['updated_by'] = auth()->id();
        $capacitacion->update($datos);
        return $capacitacion->fresh();
    }

    public function eliminarCapacitacion(int $id): void
    {
        CapacitacionSso::findOrFail($id)->delete();
    }

    // ── Horas trabajadas por período (carga manual, CD 513) ─────────

    public function listarHorasTrabajadas(array $filtros): LengthAwarePaginator
    {
        return HorasTrabajadasPeriodo::query()
            ->with('unidadAdministrativa')
            ->when(isset($filtros['periodo']), fn($q) => $q->where('periodo', $filtros['periodo']))
            ->when(isset($filtros['unidad_administrativa_id']), fn($q) => $q->where('unidad_administrativa_id', $filtros['unidad_administrativa_id']))
            ->orderByDesc('periodo')
            ->paginate($filtros['por_pagina'] ?? 15);
    }

    public function registrarHorasTrabajadas(array $datos): HorasTrabajadasPeriodo
    {
        $datos['registrado_por'] = auth()->id();

        return HorasTrabajadasPeriodo::updateOrCreate(
            [
                'periodo' => $datos['periodo'],
                'unidad_administrativa_id' => $datos['unidad_administrativa_id'] ?? null,
            ],
            ['total_horas' => $datos['total_horas'], 'registrado_por' => $datos['registrado_por']]
        );
    }

    public function actualizarHorasTrabajadas(int $id, array $datos): HorasTrabajadasPeriodo
    {
        $registro = HorasTrabajadasPeriodo::findOrFail($id);
        $datos['registrado_por'] = auth()->id();
        $registro->update($datos);
        return $registro->fresh();
    }

    public function eliminarHorasTrabajadas(int $id): void
    {
        HorasTrabajadasPeriodo::findOrFail($id)->delete();
    }

    // ── Indicadores SSO ──────────────────────────────────────────────

    /**
     * Convierte un período 'YYYY' (año) o 'YYYY-MM' (mes) en su rango de fechas.
     */
    private function rangoPeriodo(string $periodo): array
    {
        if (preg_match('/^\d{4}$/', $periodo)) {
            $inicio = Carbon::createFromDate((int) $periodo, 1, 1)->startOfYear();
            return [$inicio, $inicio->copy()->endOfYear()];
        }

        if (preg_match('/^\d{4}-\d{2}$/', $periodo)) {
            $inicio = Carbon::createFromFormat('Y-m-d', "{$periodo}-01")->startOfMonth();
            return [$inicio, $inicio->copy()->endOfMonth()];
        }

        throw new ReglaNegocioException('El período debe tener el formato AAAA o AAAA-MM.');
    }

    /**
     * Índices reactivos CD 513 (Resolución IESS - Reglamento del Seguro General de Riesgos del Trabajo).
     * IF = (nº lesiones × 200000) / horas trabajadas; IG = (días perdidos × 200000) / horas trabajadas; TR = IG / IF.
     * NOTA: estas fórmulas fueron verificadas solo por fuentes secundarias (el reglamento oficial del IESS
     * no pudo confirmarse contra un PDF primario legible en el entorno de desarrollo) — Talento Humano
     * debe confirmarlas contra el reglamento antes de tratarlas como referencia legal definitiva.
     */
    public function calcularIndicadoresMrl(string $periodo, ?int $unidadAdministrativaId = null): array
    {
        [$inicio, $fin] = $this->rangoPeriodo($periodo);

        $horasTrabajadas = (int) HorasTrabajadasPeriodo::where('periodo', $periodo)
            ->where('unidad_administrativa_id', $unidadAdministrativaId)
            ->sum('total_horas');

        $accidentes = AccidenteTrabajo::query()
            ->where('tipo_evento', TipoEventoAccidente::ACCIDENTE->value)
            ->whereBetween('fecha_accidente', [$inicio, $fin])
            ->when(
                $unidadAdministrativaId,
                fn($q) => $q->whereHas('servidor', fn($sq) => $sq->where('unidad_administrativa_id', $unidadAdministrativaId))
            )
            ->get();

        $numeroLesiones = $accidentes->count();
        $diasPerdidos = (int) $accidentes->sum('dias_reposo_medico');

        if ($horasTrabajadas <= 0) {
            return [
                'periodo' => $periodo,
                'sin_datos' => true,
                'mensaje' => 'No hay horas trabajadas registradas para este período. Cargue el dato en "Horas trabajadas" antes de calcular los índices.',
                'numero_lesiones' => $numeroLesiones,
                'dias_perdidos' => $diasPerdidos,
                'horas_trabajadas' => 0,
                'indice_frecuencia' => null,
                'indice_gravedad' => null,
                'tasa_riesgo' => null,
            ];
        }

        $indiceFrecuencia = round(($numeroLesiones * 200000) / $horasTrabajadas, 2);
        $indiceGravedad = round(($diasPerdidos * 200000) / $horasTrabajadas, 2);
        $tasaRiesgo = $indiceFrecuencia > 0 ? round($indiceGravedad / $indiceFrecuencia, 2) : 0.0;

        return [
            'periodo' => $periodo,
            'sin_datos' => false,
            'numero_lesiones' => $numeroLesiones,
            'dias_perdidos' => $diasPerdidos,
            'horas_trabajadas' => $horasTrabajadas,
            'indice_frecuencia' => $indiceFrecuencia,
            'indice_gravedad' => $indiceGravedad,
            'tasa_riesgo' => $tasaRiesgo,
        ];
    }

    /**
     * Índices proactivos: agregaciones simples sobre datos ya existentes (sin tabla nueva).
     * No existe en el sistema una distinción entre actividades "planificadas" y "realizadas"
     * para inspecciones/capacitaciones, por lo que se reportan los conteos reales del período.
     */
    public function calcularIndicadoresProactivos(string $periodo, ?int $unidadAdministrativaId = null): array
    {
        [$inicio, $fin] = $this->rangoPeriodo($periodo);

        $inspecciones = InspeccionSso::query()
            ->whereBetween('fecha_inspeccion', [$inicio, $fin])
            ->when($unidadAdministrativaId, fn($q) => $q->where('unidad_administrativa_id', $unidadAdministrativaId))
            ->count();

        $capacitaciones = CapacitacionSso::query()
            ->whereBetween('fecha', [$inicio, $fin])
            ->get();

        $puestosConEppIds = PuestoEpp::query()->distinct()->pluck('puesto_id');
        $totalPuestosConEpp = $puestosConEppIds->count();

        $puestosConEntregaEnPeriodo = $totalPuestosConEpp > 0
            ? EppEntrega::query()
                ->join('servidores', 'servidores.id', '=', 'epp_entregas.servidor_id')
                ->whereIn('servidores.puesto_id', $puestosConEppIds)
                ->where('epp_entregas.motivo', 'entrega')
                ->whereBetween('epp_entregas.fecha_entrega', [$inicio, $fin])
                ->distinct('servidores.puesto_id')
                ->count('servidores.puesto_id')
            : 0;

        $coberturaEpp = $totalPuestosConEpp > 0
            ? round(($puestosConEntregaEnPeriodo / $totalPuestosConEpp) * 100, 1)
            : null;

        return [
            'periodo' => $periodo,
            'inspecciones_realizadas' => $inspecciones,
            'capacitaciones_realizadas' => $capacitaciones->count(),
            'horas_capacitacion_total' => (float) $capacitaciones->sum('duracion_horas'),
            'cobertura_epp' => [
                'total_puestos_con_epp_requerido' => $totalPuestosConEpp,
                'puestos_con_entrega_en_periodo' => $puestosConEntregaEnPeriodo,
                'porcentaje' => $coberturaEpp,
            ],
        ];
    }
}
