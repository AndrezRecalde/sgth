<?php

namespace App\Services\Reporteria;

use App\Contracts\Reporteria\ReporteriaServiceInterface;
use App\Enums\RegimenLaboral;
use App\Exceptions\ReporteNoDisponibleException;
use App\Models\Dispensario\InventarioMedicina;
use App\Models\Dispensario\LoteMedicina;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ReporteriaService implements ReporteriaServiceInterface
{
    public function obtenerKpisDashboard(): array
    {
        // TTL de 5 minutos como indica el requerimiento
        return Cache::remember('sgth:dashboard:kpis', 300, function () {
            return [
                'personal' => $this->calcularKpisPersonal(),
                'nomina' => $this->calcularKpisNomina(),
                'asistencia' => $this->calcularKpisAsistencia(),
                'helpdesk' => $this->calcularKpisHelpdesk(),
                'dispensario' => $this->calcularKpisDispensario()
            ];
        });
    }

    private function calcularKpisPersonal(): array
    {
        // Mock de datos calculados
        // Aquí irían las consultas Eloquent como: Servidor::where('estado', true)->count()
        return [
            'total_servidores_activos' => DB::table('servidores')->whereNull('deleted_at')->count(),
            // Se arma desde el enum en vez de enumerar dos claves a mano.
            // Estaban escritas en MAYÚSCULAS contra una columna que guarda
            // minúsculas, así que ambos contadores devolvían cero desde
            // siempre; y al agregarse el tercer régimen en 2026 nadie se
            // acordó de este archivo. Recorriendo los casos, un régimen nuevo
            // aparece solo.
            'servidores_por_regimen' => collect(RegimenLaboral::cases())
                ->mapWithKeys(fn (RegimenLaboral $regimen) => [
                    $regimen->value => DB::table('servidores')
                        ->whereNull('deleted_at')
                        ->where('regimen_laboral', $regimen->value)
                        ->count(),
                ])
                ->all(),
            'servidores_por_unidad' => DB::table('servidores')
                ->join('unidades_administrativas', 'servidores.unidad_administrativa_id', '=', 'unidades_administrativas.id')
                ->select('unidades_administrativas.nombre', DB::raw('count(*) as total'))
                ->groupBy('unidades_administrativas.nombre')
                ->pluck('total', 'nombre')
                ->toArray(),
            'nuevos_ingresos_mes' => DB::table('movimientos_personal')
                ->where('tipo_movimiento', 'ingreso')
                ->whereMonth('fecha_efectiva', now()->month)
                ->whereYear('fecha_efectiva', now()->year)
                ->count()
        ];
    }

    private function calcularKpisNomina(): array
    {
        // En un entorno real se extraería de la tabla nominas del mes actual
        return [
            'costo_nomina_mes_actual' => DB::table('nominas')
                ->where('periodo', now()->format('Y-m'))
                ->sum('total_neto'),
            'variacion_nomina' => 2.5, // Porcentaje mock simulado
            'descuentos_iess_mes' => DB::table('roles_pago')
                ->join('nominas', 'roles_pago.nomina_id', '=', 'nominas.id')
                ->where('nominas.periodo', now()->format('Y-m'))
                ->sum('roles_pago.total_descuentos'), // Simplificado
            'handoffs_pendientes' => DB::table('handoffs_erp')
                ->whereNull('importado_erp_en')
                ->count()
        ];
    }

    private function calcularKpisAsistencia(): array
    {
        return [
            'faltas_injustificadas_mes' => DB::table('permisos_servidor')
                ->where('estado', 'falta_injustificada')
                ->whereMonth('fecha', now()->month)
                ->whereYear('fecha', now()->year)
                ->count(),
            'permisos_pendientes' => DB::table('permisos_servidor')
                ->where('estado', 'pendiente')
                ->count(),
            'vacaciones_proximas_vencer' => \App\Models\Expediente\Servidor::where('estado', true)
                ->get()
                ->filter(function($serv) {
                    try {
                        $service = app(\App\Contracts\Asistencia\VacacionServiceInterface::class);
                        return $service->calcularSaldoActual($serv->id) >= 45;
                    } catch (\Exception $e) {
                        return false;
                    }
                })->map(fn($s) => ['servidor_id' => $s->id])->values()->toArray(),
            'servidores_en_comision' => DB::table('viaticos')
                ->where('estado', 'en_comision')
                ->count()
        ];
    }

    private function calcularKpisHelpdesk(): array
    {
        return [
            'tickets_abiertos' => DB::table('tickets')
                ->whereNotIn('estado', ['cerrado', 'cancelado'])
                ->count(),
            'tickets_vencidos_sla' => DB::table('tickets')
                ->whereNotIn('estado', ['cerrado', 'cancelado'])
                ->where('fecha_vencimiento_sla', '<', now())
                ->count(),
            'tiempo_promedio_resolucion' => 3.5, // Horas
            'satisfaccion_promedio' => DB::table('encuestas_satisfaccion')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->avg('calificacion') ?? 0
        ];
    }

    private function calcularKpisDispensario(): array
    {
        return [
            'citas_hoy' => DB::table('agendas_medicas')
                ->whereDate('fecha', now()->toDateString())
                ->count(),
            // Los dos se miden sobre los lotes, que es donde están ahora las
            // existencias y su caducidad. El crítico cuenta lo entregable: unas
            // unidades vencidas no evitan una rotura de stock, la disimulan.
            'medicamentos_stock_critico' => InventarioMedicina::bajoMinimo()
                ->count(),
            'medicamentos_por_caducar' => LoteMedicina::conStock()
                ->whereNotNull('fecha_caducidad')
                ->whereDate('fecha_caducidad', '<', now()->addDays(60))
                ->count()
        ];
    }

    private function cacheReporte(string $tipo, array $parametros, \Closure $callback): array
    {
        $hash = md5(json_encode($parametros));
        $key = "sgth:reporte:{$tipo}:{$hash}";

        // TTL 30 minutos (1800 segundos). Usamos tags para poder invalidar masivamente por los Observers
        return Cache::tags(['reporteria', "modulo_{$tipo}"])->remember($key, 1800, $callback);
    }

    public function generarReporteAdHoc(array $configuracion): array
    {
        $modulo = $configuracion['modulo'] ?? 'general';
        return $this->cacheReporte($modulo, $configuracion, function () use ($modulo) {
            return [
                'metadata' => [
                    'modulo' => $modulo,
                    'total_registros' => 100,
                    'generado_en' => now()->toDateTimeString()
                ],
                'datos' => [
                    ['id' => 1, 'dato' => 'Fila de ejemplo 1'],
                    ['id' => 2, 'dato' => 'Fila de ejemplo 2'],
                ]
            ];
        });
    }


    /**
     * El distributivo: quién ocupa cada puesto y con qué remuneración.
     *
     * Consultaba `servidores.nombres`, `servidores.apellidos` y `servidores.rmu`,
     * y ninguna de las tres existe: las de nombre son singulares y la
     * remuneración vive en el grupo ocupacional del puesto, no en la persona.
     * Reventaba con un error de Postgres, que es lo que tenía parada la tarea
     * de LOTAIP.
     *
     * Y traía `limit(10) // Simulado`. Un distributivo recortado a diez
     * servidores no es un distributivo: es la lista completa o no es nada.
     */
    public function generarDistributivoSueldos(array $filtros): array
    {
        return $this->cacheReporte('distributivo_sueldos', $filtros, function () {
            $datos = DB::table('servidores')
                ->join('unidades_administrativas', 'servidores.unidad_administrativa_id', '=', 'unidades_administrativas.id')
                ->join('puestos', 'servidores.puesto_id', '=', 'puestos.id')
                ->leftJoin('cargos', 'puestos.cargo_id', '=', 'cargos.id')
                ->leftJoin('grupos_ocupacionales', 'puestos.grupo_ocupacional_id', '=', 'grupos_ocupacionales.id')
                ->whereNull('servidores.deleted_at')
                ->where('servidores.estado', true)
                ->select(
                    'servidores.cedula',
                    'servidores.nombre',
                    'servidores.apellido',
                    'unidades_administrativas.nombre as unidad',
                    'cargos.nombre as denominacion',
                    'grupos_ocupacionales.grado_codigo as grado',
                    'grupos_ocupacionales.rmu',
                    'servidores.regimen_laboral',
                )
                ->orderBy('unidades_administrativas.nombre')
                ->orderBy('servidores.apellido')
                ->get();

            // Un servidor sin puesto no sale en el distributivo —no ocupa
            // ninguno—, pero que no salga no puede ser silencioso: si son
            // muchos, lo que falla es la asignación de puestos, no el reporte.
            $sinPuesto = DB::table('servidores')
                ->whereNull('deleted_at')
                ->where('estado', true)
                ->whereNull('puesto_id')
                ->count();

            return [
                'metadata' => [
                    'reporte' => 'Distributivo de Sueldos',
                    'fecha'   => now()->toDateString(),
                    'total_servidores' => $datos->count(),
                    'servidores_sin_puesto' => $sinPuesto,
                ],
                'datos' => $datos->toArray(),
            ];
        });
    }

    /**
     * Lo pagado en un periodo, servidor por servidor.
     *
     * Sale de `roles_pago`, que guarda los totales de cada servidor en cada
     * nómina. El periodo se acota por la nómina y no por la fecha del rol:
     * una nómina de agosto liquidada en septiembre pertenece a agosto.
     */
    public function generarNominaConsolidada(array $filtros): array
    {
        return $this->cacheReporte('nomina_consolidada', $filtros, function () use ($filtros) {
            $consulta = DB::table('roles_pago')
                ->join('nominas', 'roles_pago.nomina_id', '=', 'nominas.id')
                ->join('servidores', 'roles_pago.servidor_id', '=', 'servidores.id')
                ->leftJoin('unidades_administrativas', 'servidores.unidad_administrativa_id', '=', 'unidades_administrativas.id')
                ->whereNull('roles_pago.deleted_at')
                ->whereNull('nominas.deleted_at');

            if (! empty($filtros['periodo'])) {
                $consulta->where('nominas.periodo', $filtros['periodo']);
            }

            if (! empty($filtros['anio'])) {
                $consulta->whereYear('nominas.fecha_inicio', $filtros['anio']);
            }

            if (! empty($filtros['mes'])) {
                $consulta->whereMonth('nominas.fecha_inicio', $filtros['mes']);
            }

            $datos = $consulta
                ->select(
                    'nominas.periodo',
                    'servidores.cedula',
                    'servidores.nombre',
                    'servidores.apellido',
                    'unidades_administrativas.nombre as unidad',
                    'roles_pago.total_ingresos',
                    'roles_pago.total_descuentos',
                    'roles_pago.total_neto',
                )
                ->orderBy('nominas.periodo')
                ->orderBy('servidores.apellido')
                ->get();

            return [
                'metadata' => [
                    'reporte' => 'Nómina Consolidada',
                    'periodo' => $filtros['periodo'] ?? ($filtros['mes'] ?? now()->month),
                    'total_roles'     => $datos->count(),
                    'suma_ingresos'   => round((float) $datos->sum('total_ingresos'), 2),
                    'suma_descuentos' => round((float) $datos->sum('total_descuentos'), 2),
                    'suma_neto'       => round((float) $datos->sum('total_neto'), 2),
                ],
                'datos' => $datos->toArray(),
            ];
        });
    }

    /**
     * La planilla del IESS no se puede armar con lo que hay.
     *
     * Necesita la materia gravada de cada servidor, y `roles_pago` solo guarda
     * los totales del rol: no hay tabla de detalle que diga qué rubros aportan
     * al IESS y cuáles no. Aplicar el 9,45 % y el 11,15 % sobre el total de
     * ingresos sería un supuesto, y produciría una planilla presentable ante el
     * IESS con cifras que nadie ha calculado.
     *
     * Antes devolvía una lista vacía, que se lee como «este mes no hubo
     * aportes».
     */
    public function generarPlanillaIess(array $filtros): array
    {
        throw new ReporteNoDisponibleException(
            'Planilla IESS',
            'hace falta el detalle por rubro del rol de pago para conocer la '
            . 'materia gravada de cada servidor; hoy solo se guardan los totales.'
        );
    }

    /**
     * El formulario 107 tampoco.
     *
     * Necesita los ingresos gravados acumulados del año y las retenciones
     * practicadas a cada servidor. Es el mismo hueco que la planilla del IESS:
     * sin el detalle por rubro, del rol de pago solo se sabe cuánto se pagó, no
     * qué parte era gravada ni cuánto se retuvo.
     */
    public function generarFormulario107(array $filtros): array
    {
        throw new ReporteNoDisponibleException(
            'Formulario 107 SRI',
            'hacen falta los ingresos gravados y las retenciones del año por '
            . 'servidor; el rol de pago solo guarda totales, sin desglose.'
        );
    }

    /**
     * El PAC no es información de este sistema.
     *
     * El Plan Anual de Contratación es de compras públicas: no hay tabla de PAC
     * en esta base, ni debería haberla en talento humano. El reporte se declaró
     * aquí por error.
     */
    public function generarInformePac(array $filtros): array
    {
        throw new ReporteNoDisponibleException(
            'Avance PAC',
            'el Plan Anual de Contratación pertenece a compras públicas y no '
            . 'existe en este sistema; el reporte está declarado en el módulo '
            . 'equivocado.'
        );
    }

    /**
     * Asistencia y permisos por servidor en un rango.
     *
     * Las marcaciones vienen del biométrico y los permisos del módulo de
     * Asistencia. Se cuentan por separado porque responden preguntas distintas:
     * cuántas veces marcó y cuánto tiempo estuvo autorizado a no estar.
     */
    public function generarReporteAsistencia(array $filtros): array
    {
        return $this->cacheReporte('reporte_asistencia', $filtros, function () use ($filtros) {
            $desde = $filtros['desde'] ?? now()->startOfMonth()->toDateString();
            $hasta = $filtros['hasta'] ?? now()->endOfMonth()->toDateString();

            $marcaciones = DB::table('marcaciones')
                ->whereBetween(DB::raw('fecha_hora::date'), [$desde, $hasta])
                ->select('servidor_id', DB::raw('COUNT(*) as total_marcaciones'))
                ->groupBy('servidor_id');

            $permisos = DB::table('permisos_servidor')
                ->whereNull('deleted_at')
                ->whereNull('anulado_en')
                ->whereBetween('fecha', [$desde, $hasta])
                ->select('servidor_id', DB::raw('COUNT(*) as total_permisos'))
                ->groupBy('servidor_id');

            $datos = DB::table('servidores')
                ->leftJoinSub($marcaciones, 'm', 'm.servidor_id', '=', 'servidores.id')
                ->leftJoinSub($permisos, 'p', 'p.servidor_id', '=', 'servidores.id')
                ->leftJoin('unidades_administrativas', 'servidores.unidad_administrativa_id', '=', 'unidades_administrativas.id')
                ->whereNull('servidores.deleted_at')
                ->where('servidores.estado', true)
                ->select(
                    'servidores.cedula',
                    'servidores.nombre',
                    'servidores.apellido',
                    'unidades_administrativas.nombre as unidad',
                    DB::raw('COALESCE(m.total_marcaciones, 0) as total_marcaciones'),
                    DB::raw('COALESCE(p.total_permisos, 0) as total_permisos'),
                )
                ->orderBy('servidores.apellido')
                ->get();

            return [
                'metadata' => [
                    'reporte' => 'Reporte de Asistencia y Permisos',
                    'desde'   => $desde,
                    'hasta'   => $hasta,
                    'total_servidores' => $datos->count(),
                ],
                'datos' => $datos->toArray(),
            ];
        });
    }

    /**
     * Los viáticos del periodo, con lo anticipado y lo calculado.
     *
     * Se excluyen los rechazados y los cancelados: no representan gasto, y
     * mezclarlos falsea cualquier suma que se haga sobre esta lista.
     */
    public function generarReporteViaticos(array $filtros): array
    {
        return $this->cacheReporte('reporte_viaticos', $filtros, function () use ($filtros) {
            $consulta = DB::table('viaticos')
                ->join('servidores', 'viaticos.servidor_id', '=', 'servidores.id')
                ->leftJoin('unidades_administrativas', 'servidores.unidad_administrativa_id', '=', 'unidades_administrativas.id')
                ->whereNull('viaticos.deleted_at')
                ->whereNotIn('viaticos.estado', ['rechazado', 'cancelado']);

            if (! empty($filtros['desde'])) {
                $consulta->whereDate('viaticos.datetime_salida', '>=', $filtros['desde']);
            }

            if (! empty($filtros['hasta'])) {
                $consulta->whereDate('viaticos.datetime_salida', '<=', $filtros['hasta']);
            }

            $datos = $consulta
                ->select(
                    'viaticos.codigo_viatico',
                    'servidores.cedula',
                    'servidores.nombre',
                    'servidores.apellido',
                    'unidades_administrativas.nombre as unidad',
                    'viaticos.zona',
                    'viaticos.estado',
                    'viaticos.datetime_salida',
                    'viaticos.datetime_llegada',
                    'viaticos.total_dias',
                    'viaticos.monto_calculado',
                    'viaticos.monto_anticipo',
                    'viaticos.partida_presupuestaria',
                )
                ->orderByDesc('viaticos.datetime_salida')
                ->get();

            return [
                'metadata' => [
                    'reporte' => 'Reporte Consolidado de Viáticos',
                    'desde'   => $filtros['desde'] ?? null,
                    'hasta'   => $filtros['hasta'] ?? null,
                    'total_viaticos'  => $datos->count(),
                    'suma_calculado'  => round((float) $datos->sum('monto_calculado'), 2),
                    'suma_anticipos'  => round((float) $datos->sum('monto_anticipo'), 2),
                ],
                'datos' => $datos->toArray(),
            ];
        });
    }

    /**
     * Accidentes de trabajo del periodo, con el reposo que causaron.
     *
     * Los días de reposo se suman aparte porque son la magnitud que mide la
     * gravedad de lo ocurrido: diez accidentes leves y uno que costó un mes no
     * se parecen, y contando accidentes solamente parecerían lo mismo.
     */
    public function generarReporteAccidentabilidad(array $filtros): array
    {
        return $this->cacheReporte('reporte_accidentabilidad', $filtros, function () use ($filtros) {
            $consulta = DB::table('accidentes_trabajo')
                ->join('servidores', 'accidentes_trabajo.servidor_id', '=', 'servidores.id')
                ->leftJoin('unidades_administrativas', 'servidores.unidad_administrativa_id', '=', 'unidades_administrativas.id')
                ->whereNull('accidentes_trabajo.deleted_at');

            if (! empty($filtros['desde'])) {
                $consulta->whereDate('accidentes_trabajo.fecha_accidente', '>=', $filtros['desde']);
            }

            if (! empty($filtros['hasta'])) {
                $consulta->whereDate('accidentes_trabajo.fecha_accidente', '<=', $filtros['hasta']);
            }

            $datos = $consulta
                ->select(
                    'accidentes_trabajo.fecha_accidente',
                    'servidores.cedula',
                    'servidores.nombre',
                    'servidores.apellido',
                    'unidades_administrativas.nombre as unidad',
                    'accidentes_trabajo.tipo_evento',
                    'accidentes_trabajo.gravedad',
                    'accidentes_trabajo.lugar_accidente',
                    'accidentes_trabajo.requirio_atencion_medica',
                    'accidentes_trabajo.dias_reposo_medico',
                    'accidentes_trabajo.estado',
                )
                ->orderByDesc('accidentes_trabajo.fecha_accidente')
                ->get();

            return [
                'metadata' => [
                    'reporte' => 'Indicadores SSO y Accidentabilidad',
                    'desde'   => $filtros['desde'] ?? null,
                    'hasta'   => $filtros['hasta'] ?? null,
                    'total_accidentes'  => $datos->count(),
                    'dias_reposo_total' => (int) $datos->sum('dias_reposo_medico'),
                    'con_atencion_medica' => $datos
                        ->where('requirio_atencion_medica', true)->count(),
                    'por_gravedad' => $datos->groupBy('gravedad')
                        ->map->count()->all(),
                ],
                'datos' => $datos->toArray(),
            ];
        });
    }
}
