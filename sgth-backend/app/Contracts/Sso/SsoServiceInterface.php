<?php

namespace App\Contracts\Sso;

use App\Models\Sso\AccidenteTrabajo;
use App\Models\Sso\CapacitacionSso;
use App\Models\Sso\EquipoProteccion;
use App\Models\Sso\HorasTrabajadasPeriodo;
use App\Models\Sso\InspeccionSso;
use App\Models\Sso\RiesgoLaboral;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SsoServiceInterface
{
    // ── Riesgos laborales ─────────────────────────────────────────
    public function listarRiesgosLaborales(array $filtros): LengthAwarePaginator;

    public function obtenerRiesgoLaboral(int $id): RiesgoLaboral;

    public function registrarRiesgoLaboral(array $datos): RiesgoLaboral;

    public function actualizarRiesgoLaboral(int $id, array $datos): RiesgoLaboral;

    public function eliminarRiesgoLaboral(int $id): void;

    // ── Accidentes de trabajo ─────────────────────────────────────
    public function listarAccidentes(array $filtros): LengthAwarePaginator;

    public function obtenerAccidente(int $id): AccidenteTrabajo;

    public function registrarAccidente(array $datos): AccidenteTrabajo;

    public function actualizarAccidente(int $id, array $datos): AccidenteTrabajo;

    public function cerrarInvestigacionAccidente(int $id, array $datos): AccidenteTrabajo;

    public function eliminarAccidente(int $id): void;

    // ── Equipos de protección personal ────────────────────────────
    public function listarEquiposProteccion(array $filtros): LengthAwarePaginator;

    public function obtenerEquipoProteccion(int $id): EquipoProteccion;

    public function registrarEquipoProteccion(array $datos): EquipoProteccion;

    public function actualizarEquipoProteccion(int $id, array $datos): EquipoProteccion;

    public function eliminarEquipoProteccion(int $id): void;

    // ── Inspecciones SSO ───────────────────────────────────────────
    public function listarInspecciones(array $filtros): LengthAwarePaginator;

    public function obtenerInspeccion(int $id): InspeccionSso;

    public function registrarInspeccion(array $datos): InspeccionSso;

    public function actualizarInspeccion(int $id, array $datos): InspeccionSso;

    public function eliminarInspeccion(int $id): void;

    // ── Capacitaciones SSO ─────────────────────────────────────────
    public function listarCapacitaciones(array $filtros): LengthAwarePaginator;

    public function obtenerCapacitacion(int $id): CapacitacionSso;

    public function registrarCapacitacion(array $datos): CapacitacionSso;

    public function actualizarCapacitacion(int $id, array $datos): CapacitacionSso;

    public function eliminarCapacitacion(int $id): void;

    // ── Horas trabajadas por período (carga manual, CD 513) ─────────
    public function listarHorasTrabajadas(array $filtros): LengthAwarePaginator;

    public function registrarHorasTrabajadas(array $datos): HorasTrabajadasPeriodo;

    public function actualizarHorasTrabajadas(int $id, array $datos): HorasTrabajadasPeriodo;

    public function eliminarHorasTrabajadas(int $id): void;

    // ── Indicadores SSO ──────────────────────────────────────────────
    public function calcularIndicadoresMrl(string $periodo, ?int $unidadAdministrativaId = null): array;

    public function calcularIndicadoresProactivos(string $periodo, ?int $unidadAdministrativaId = null): array;
}
