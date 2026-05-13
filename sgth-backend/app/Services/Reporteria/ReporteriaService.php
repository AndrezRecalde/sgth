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
                ->whereMonth('mes', now()->month)
                ->whereYear('anio', now()->year)
                ->sum('total_neto'),
            'variacion_nomina' => 2.5, // Porcentaje mock simulado
            'descuentos_iess_mes' => DB::table('roles_pago')
                ->join('nominas', 'roles_pago.nomina_id', '=', 'nominas.id')
                ->whereMonth('nominas.mes', now()->month)
                ->whereYear('nominas.anio', now()->year)
                ->sum('total_descuentos'), // Simplificado
            'handoffs_pendientes' => DB::table('handoffs_erp')
                ->where('estado', 'pendiente')
                ->count()
        ];
    }

    private function calcularKpisAsistencia(): array
    {
        return [
            'faltas_injustificadas_mes' => DB::table('permisos')
                ->where('estado', 'falta_injustificada')
                ->whereMonth('fecha_inicio', now()->month)
                ->whereYear('fecha_inicio', now()->year)
                ->count(),
            'permisos_pendientes' => DB::table('permisos')
                ->where('estado', 'pendiente')
                ->count(),
            'vacaciones_proximas_vencer' => DB::table('saldos_vacaciones')
                ->where('dias_disponibles', '>', 45)
                ->count(),
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
                ->avg('calificacion_general') ?? 0
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
}
