<?php

namespace App\Services\Reporteria;

use App\Contracts\Reporteria\ReporteriaServiceInterface;
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
            'servidores_por_regimen' => [
                'losep' => DB::table('servidores')->where('regimen_laboral', 'LOSEP')->count(),
                'codigo_trabajo' => DB::table('servidores')->where('regimen_laboral', 'CODIGO_TRABAJO')->count()
            ],
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
            'medicamentos_stock_critico' => DB::table('inventario_medicinas')
                ->whereRaw('stock_actual <= stock_minimo')
                ->count(),
            'medicamentos_por_caducar' => DB::table('inventario_medicinas')
                ->where('fecha_caducidad', '<', now()->addDays(60))
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

    public function generarDistributivoSueldos(array $filtros): array
    {
        return $this->cacheReporte('distributivo_sueldos', $filtros, function () {
            return [
                'metadata' => ['reporte' => 'Distributivo de Sueldos', 'fecha' => now()->toDateString()],
                'datos' => DB::table('servidores')
                    ->join('unidades_administrativas', 'servidores.unidad_administrativa_id', '=', 'unidades_administrativas.id')
                    ->join('puestos', 'servidores.puesto_id', '=', 'puestos.id')
                    ->leftJoin('cargos', 'puestos.cargo_id', '=', 'cargos.id')
                    ->select('servidores.nombres', 'servidores.apellidos', 'unidades_administrativas.nombre as unidad', 'cargos.nombre as denominacion', 'servidores.rmu')
                    ->whereNull('servidores.deleted_at')
                    ->limit(10) // Simulado
                    ->get()->toArray()
            ];
        });
    }

    public function generarNominaConsolidada(array $filtros): array
    {
        return $this->cacheReporte('nomina_consolidada', $filtros, function () use ($filtros) {
            return [
                'metadata' => ['reporte' => 'Nómina Consolidada', 'periodo' => $filtros['mes'] ?? now()->month],
                'datos' => [] 
            ];
        });
    }

    public function generarPlanillaIess(array $filtros): array
    {
        return $this->cacheReporte('planilla_iess', $filtros, function () {
            return [
                'metadata' => ['reporte' => 'Planilla IESS', 'formato' => 'Excel Estricto IESS'],
                'datos' => [] 
            ];
        });
    }

    public function generarFormulario107(array $filtros): array
    {
        return $this->cacheReporte('formulario_107_sri', $filtros, function () use ($filtros) {
            return [
                'metadata' => ['reporte' => 'Formulario 107 SRI', 'anio_fiscal' => $filtros['anio'] ?? now()->year],
                'datos' => [] 
            ];
        });
    }

    public function generarInformePac(array $filtros): array
    {
        return $this->cacheReporte('informe_pac', $filtros, function () use ($filtros) {
            return [
                'metadata' => ['reporte' => 'Avance PAC', 'anio' => $filtros['anio'] ?? now()->year],
                'datos' => [] 
            ];
        });
    }

    public function generarReporteAsistencia(array $filtros): array
    {
        return $this->cacheReporte('reporte_asistencia', $filtros, function () use ($filtros) {
            return [
                'metadata' => ['reporte' => 'Reporte de Asistencia y Permisos', 'desde' => $filtros['desde'] ?? null, 'hasta' => $filtros['hasta'] ?? null],
                'datos' => [] 
            ];
        });
    }

    public function generarReporteViaticos(array $filtros): array
    {
        return $this->cacheReporte('reporte_viaticos', $filtros, function () {
            return [
                'metadata' => ['reporte' => 'Reporte Consolidado de Viáticos'],
                'datos' => [] 
            ];
        });
    }

    public function generarReporteAccidentabilidad(array $filtros): array
    {
        return $this->cacheReporte('reporte_accidentabilidad', $filtros, function () {
            return [
                'metadata' => ['reporte' => 'Indicadores SSO y Accidentabilidad'],
                'datos' => [] 
            ];
        });
    }
}
