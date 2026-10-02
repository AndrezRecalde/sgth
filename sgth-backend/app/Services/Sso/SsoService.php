<?php

namespace App\Services\Sso;

use App\Contracts\Sso\SsoServiceInterface;
use App\Enums\AlcanceIndicadorSso;
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
use App\Services\Sso\Indicadores\HorasTrabajadas;
use App\Services\Sso\Indicadores\IndicesReactivos;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

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
     *
     * Los tres niveles se resuelven con `tryFrom` y no con `from`: las columnas
     * nacieron nullable (la migración que trajo NTP 330 reemplazó el esquema
     * anterior sin rellenar datos), así que un riesgo identificado antes de la
     * matriz las tiene en NULL. Un PATCH que solo toque la descripción de esa
     * fila llegaba aquí con null y `from` lanzaba un TypeError, que sale como
     * 500 y no dice nada. Ahora sale un 422 que pide completar la valoración,
     * que es lo único que puede hacer quien lo edita.
     */
    private function calcularNtp330(array $datos, ?RiesgoLaboral $actual = null): array
    {
        $nivelDeficiencia = NivelDeficienciaRiesgo::tryFrom(
            $datos['nivel_deficiencia'] ?? $actual?->nivel_deficiencia?->value ?? ''
        );
        $nivelExposicion = NivelExposicionRiesgo::tryFrom(
            $datos['nivel_exposicion'] ?? $actual?->nivel_exposicion?->value ?? ''
        );
        $nivelConsecuencias = NivelConsecuenciasRiesgo::tryFrom(
            $datos['nivel_consecuencias'] ?? $actual?->nivel_consecuencias?->value ?? ''
        );

        if (! $nivelDeficiencia || ! $nivelExposicion || ! $nivelConsecuencias) {
            throw new ReglaNegocioException(
                'Este riesgo no tiene valoración NTP 330 (fue identificado antes de la matriz). '
                . 'Para guardarlo hay que indicar los tres niveles: deficiencia, exposición y consecuencias.'
            );
        }

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

    /**
     * El catálogo de equipos activos, sin paginar, para los desplegables.
     *
     * Existe porque los tres formularios que eligen un equipo —asignar EPP a
     * un puesto, registrar un movimiento y entregar el kit— pedían
     * `listarEquiposProteccion(['estado' => true])`, que PAGINA de 15 en 15.
     * Leían `datos` y se quedaban con los quince primeros por nombre: con
     * dieciséis equipos activos, el decimosexto no se podía asignar ni
     * entregar, y el desplegable decía «Sin equipos en el catálogo».
     *
     * Subir el tope de `por_pagina` habría sido el mismo defecto aplazado a
     * los 101 equipos. Esto es lo que ya hace `FactorRiesgoCatalogoController`
     * para el catálogo de factores: un listado completo, de solo lectura.
     *
     * Devuelve las cuatro columnas que un desplegable necesita y no la fila
     * entera: lo que se pinta es «EPP-014 — Respirador de media cara».
     *
     * @return Collection<int, EquipoProteccion>
     */
    public function catalogoEquiposProteccion(): Collection
    {
        return EquipoProteccion::query()
            ->where('estado', true)
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre', 'tipo']);
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

    /**
     * Carga las horas trabajadas de un período.
     *
     * Rechaza el duplicado en vez de sobrescribirlo. Era un `updateOrCreate`:
     * volver a cargar un período que ya estaba pisaba `total_horas` sin decir
     * nada y respondía «registradas». Ese número es el denominador de los tres
     * índices del CD 513 que se reportan al IESS, así que cambiarlo por
     * accidente —un mes tecleado dos veces, un copiar y pegar— movía los tres
     * sin dejar rastro. Para corregir un período cargado está el borrado, que
     * sí es una decisión deliberada.
     *
     * Esta comprobación es la que da el mensaje al campo; la garantía de
     * verdad la pone la base, con el `unique(periodo, unidad_administrativa_id)`
     * de la tabla y el índice parcial que cubre el total institucional —dos
     * NULL no son iguales entre sí, así que el primero no lo alcanzaba—. Entre
     * el SELECT y el INSERT de aquí hay un hueco que solo el índice cierra.
     */
    public function registrarHorasTrabajadas(array $datos): HorasTrabajadasPeriodo
    {
        $unidadId = $datos['unidad_administrativa_id'] ?? null;

        $yaCargado = HorasTrabajadasPeriodo::query()
            ->where('periodo', $datos['periodo'])
            // `whereNull` explícito: el total institucional va con la unidad en
            // NULL, y en SQL `= NULL` no es cierto nunca.
            ->when($unidadId, fn($q) => $q->where('unidad_administrativa_id', $unidadId))
            ->when(! $unidadId, fn($q) => $q->whereNull('unidad_administrativa_id'))
            ->exists();

        if ($yaCargado) {
            throw ValidationException::withMessages([
                'periodo' => $unidadId
                    ? "El período {$datos['periodo']} ya tiene horas cargadas para esa unidad. Elimine el registro existente para cargarlo de nuevo."
                    : "El período {$datos['periodo']} ya tiene horas cargadas como total institucional. Elimine el registro existente para cargarlo de nuevo.",
            ]);
        }

        return HorasTrabajadasPeriodo::create([
            'periodo' => $datos['periodo'],
            'unidad_administrativa_id' => $unidadId,
            'total_horas' => $datos['total_horas'],
            'registrado_por' => auth()->id(),
        ]);
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
     * Las horas trabajadas de un período: trae las filas candidatas —la del
     * período pedido y, si es un año, las de sus meses— y deja que
     * HorasTrabajadas decida cuál manda. La decisión vive ahí porque tiene dos
     * ejes con precedencia y se prueba sin base de datos.
     */
    private function resolverHorasTrabajadas(string $periodo, ?int $unidadAdministrativaId): HorasTrabajadas
    {
        $filas = HorasTrabajadasPeriodo::query()
            ->where(fn($q) => $q
                ->where('periodo', $periodo)
                ->when(
                    PeriodoSso::esAnio($periodo),
                    fn($sq) => $sq->orWhere('periodo', 'like', "{$periodo}-%"),
                ))
            ->when(
                $unidadAdministrativaId !== null,
                fn($q) => $q->where('unidad_administrativa_id', $unidadAdministrativaId),
            )
            ->get(['periodo', 'unidad_administrativa_id', 'total_horas']);

        return HorasTrabajadas::desde($filas, $periodo, $unidadAdministrativaId);
    }

    /**
     * Índices reactivos CD 513: cuenta las lesiones y los días perdidos del
     * período, resuelve el denominador y deja las fórmulas —y la nota sobre su
     * verificación legal— en `Indicadores\IndicesReactivos`.
     */
    public function calcularIndicadoresMrl(string $periodo, ?int $unidadAdministrativaId = null): array
    {
        [$inicio, $fin] = PeriodoSso::rango($periodo);

        $resolucionHoras = $this->resolverHorasTrabajadas($periodo, $unidadAdministrativaId);
        $horasTrabajadas = $resolucionHoras->horas;

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
            // Decir qué se buscó, porque en un período anual se buscan trece
            // cosas: el año y sus doce meses.
            $donde = PeriodoSso::esAnio($periodo)
                ? "para {$periodo} ni para sus meses ({$periodo}-01 a {$periodo}-12)"
                : "para {$periodo}";

            return [
                'periodo' => $periodo,
                'sin_datos' => true,
                'mensaje' => "No hay horas trabajadas registradas {$donde}. Cargue el dato en \"Horas trabajadas\" antes de calcular los índices.",
                'numero_lesiones' => $numeroLesiones,
                'dias_perdidos' => $diasPerdidos,
                'horas_trabajadas' => 0,
                'horas_trabajadas_origen' => null,
                'horas_trabajadas_alcance' => null,
                'horas_trabajadas_detalle' => null,
                'indice_frecuencia' => null,
                'indice_gravedad' => null,
                'tasa_riesgo' => null,
            ];
        }

        $indices = IndicesReactivos::desde($numeroLesiones, $diasPerdidos, $horasTrabajadas);

        return [
            'periodo' => $periodo,
            'sin_datos' => false,
            'numero_lesiones' => $numeroLesiones,
            'dias_perdidos' => $diasPerdidos,
            'horas_trabajadas' => $horasTrabajadas,
            'horas_trabajadas_origen' => $resolucionHoras->origen,
            'horas_trabajadas_alcance' => $resolucionHoras->alcance,
            'horas_trabajadas_detalle' => $resolucionHoras->detalle(),
            'indice_frecuencia' => $indices->indiceFrecuencia,
            'indice_gravedad' => $indices->indiceGravedad,
            'tasa_riesgo' => $indices->tasaRiesgo,
        ];
    }

    /**
     * Índices proactivos: agregaciones simples sobre datos ya existentes (sin tabla nueva).
     * No existe en el sistema una distinción entre actividades "planificadas" y "realizadas"
     * para inspecciones/capacitaciones, por lo que se reportan los conteos reales del período.
     *
     * Cada indicador declara su ALCANCE, y ahí está el arreglo. El método
     * aceptaba una unidad y solo `inspecciones_realizadas` la usaba: las
     * capacitaciones y la cobertura de EPP salían institucionales y la
     * respuesta se titulaba con la unidad igual. Un número que dice una cosa y
     * vale otra, y que acaba en un informe al Ministerio.
     *
     * De los tres, dos sí pueden filtrar y ahora lo hacen —las inspecciones
     * tienen unidad propia, y la cobertura de EPP llega a ella por
     * `puestos.unidad_administrativa_id`—. Las capacitaciones NO pueden:
     * `capacitaciones_sso` no tiene columna de unidad. Eso no se inventa; se
     * declara, y la pantalla lo dice.
     */
    public function calcularIndicadoresProactivos(string $periodo, ?int $unidadAdministrativaId = null): array
    {
        [$inicio, $fin] = PeriodoSso::rango($periodo);

        $inspecciones = InspeccionSso::query()
            ->whereBetween('fecha_inspeccion', [$inicio, $fin])
            ->when($unidadAdministrativaId, fn($q) => $q->where('unidad_administrativa_id', $unidadAdministrativaId))
            ->count();

        // Sin filtro de unidad, y es a propósito: ver el alcance declarado
        // abajo. `capacitaciones_sso` no sabe de unidades.
        $capacitaciones = CapacitacionSso::query()
            ->whereBetween('fecha', [$inicio, $fin])
            ->get();

        $cobertura = $this->coberturaEpp($inicio, $fin, $unidadAdministrativaId);

        return [
            'periodo' => $periodo,
            'unidad_administrativa_id' => $unidadAdministrativaId,
            'inspecciones_realizadas' => $inspecciones,
            'capacitaciones_realizadas' => $capacitaciones->count(),
            'horas_capacitacion_total' => (float) $capacitaciones->sum('duracion_horas'),
            'cobertura_epp' => $cobertura,
            'alcances' => [
                'inspecciones' => AlcanceIndicadorSso::segunUnidad($unidadAdministrativaId)
                    ->comoRespuesta(),
                'capacitaciones' => AlcanceIndicadorSso::INSTITUCIONAL->comoRespuesta(
                    $unidadAdministrativaId !== null
                        ? 'Las capacitaciones no se registran por unidad administrativa, '
                            . 'así que esta cifra es de toda la institución.'
                        : null,
                ),
                'cobertura_epp' => AlcanceIndicadorSso::segunUnidad($unidadAdministrativaId)
                    ->comoRespuesta(),
            ],
        ];
    }

    /**
     * Cobertura de EPP del período: qué proporción de los puestos que requieren
     * equipo recibió al menos una entrega.
     *
     * Filtra por unidad a través de `puestos.unidad_administrativa_id`, en el
     * numerador Y en el denominador. Filtrar solo uno de los dos daría un
     * porcentaje sin sentido —los puestos de una unidad sobre los puestos de
     * toda la institución— y es justo el error que invita a cometer una
     * cobertura calculada a medias.
     *
     * @return array{total_puestos_con_epp_requerido: int, puestos_con_entrega_en_periodo: int, porcentaje: ?float}
     */
    private function coberturaEpp(
        CarbonInterface $inicio,
        CarbonInterface $fin,
        ?int $unidadAdministrativaId,
    ): array {
        $puestosConEppIds = PuestoEpp::query()
            ->when(
                $unidadAdministrativaId,
                fn($q) => $q->whereHas(
                    'puesto',
                    fn($sq) => $sq->where('unidad_administrativa_id', $unidadAdministrativaId),
                ),
            )
            ->distinct()
            ->pluck('puesto_id');

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

        return [
            'total_puestos_con_epp_requerido' => $totalPuestosConEpp,
            'puestos_con_entrega_en_periodo' => $puestosConEntregaEnPeriodo,
            'porcentaje' => $totalPuestosConEpp > 0
                ? round(($puestosConEntregaEnPeriodo / $totalPuestosConEpp) * 100, 1)
                : null,
        ];
    }
}
