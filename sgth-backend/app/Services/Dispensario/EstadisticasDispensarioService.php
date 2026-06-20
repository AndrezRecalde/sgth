<?php
namespace App\Services\Dispensario;

use App\Contracts\Dispensario\EstadisticasDispensarioServiceInterface;
use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\InventarioMedicina;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

final class EstadisticasDispensarioService implements EstadisticasDispensarioServiceInterface
{
    public function obtenerKpisMensuales(): array
    {
        $inicioMes = Carbon::now()->startOfMonth();
        $finMes = Carbon::now()->endOfMonth();

        // 1. Atenciones mes actual
        $atencionesMesActual = ConsultaMedica::whereBetween('created_at', [$inicioMes, $finMes])->count();

        // 2. Pacientes por tipo
        $pacientes = DB::table('consultas_medicas')
            ->join('historias_clinicas', 'consultas_medicas.historia_clinica_id', '=', 'historias_clinicas.id')
            ->selectRaw("
                SUM(CASE WHEN historias_clinicas.carga_familiar_id IS NULL THEN 1 ELSE 0 END) as titulares,
                SUM(CASE WHEN historias_clinicas.servidor_id IS NULL THEN 1 ELSE 0 END) as beneficiarios
            ")
            ->first();

        // 3. Top Diagnosticos CIE-10 (mes actual)
        $topDiagnosticos = DB::table('consultas_medicas')
            ->join('diagnosticos_cie10', 'consultas_medicas.diagnostico_cie10_id', '=', 'diagnosticos_cie10.id')
            ->whereBetween('consultas_medicas.created_at', [$inicioMes, $finMes])
            ->select('diagnosticos_cie10.codigo', 'diagnosticos_cie10.descripcion', DB::raw('COUNT(*) as total'))
            ->groupBy('diagnosticos_cie10.id', 'diagnosticos_cie10.codigo', 'diagnosticos_cie10.descripcion')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        // 4. Medicamentos más despachados (mes actual)
        $medicamentosDespachados = DB::table('items_receta')
            ->join('inventario_medicinas', 'items_receta.inventario_medicina_id', '=', 'inventario_medicinas.id')
            ->whereBetween('items_receta.created_at', [$inicioMes, $finMes])
            ->select('inventario_medicinas.nombre', DB::raw('SUM(items_receta.cantidad_despachada) as total_despachado'))
            ->groupBy('inventario_medicinas.id', 'inventario_medicinas.nombre')
            ->having(DB::raw('SUM(items_receta.cantidad_despachada)'), '>', 0)
            ->orderByDesc('total_despachado')
            ->limit(10)
            ->get();

        // 5. Estado de Recetas
        $recetasEstado = DB::table('recetas_medicas')
            ->whereBetween('created_at', [$inicioMes, $finMes])
            ->select('estado', DB::raw('COUNT(*) as total'))
            ->groupBy('estado')
            ->pluck('total', 'estado');

        // 6. Consultas por Médico (mes actual)
        $consultasPorMedico = DB::table('consultas_medicas')
            ->join('users', 'consultas_medicas.medico_id', '=', 'users.id')
            ->whereBetween('consultas_medicas.created_at', [$inicioMes, $finMes])
            ->select('users.name as medico', DB::raw('COUNT(*) as total_consultas'))
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_consultas')
            ->get();

        // 7. Alertas de inventario
        $medicamentosBajoStock = InventarioMedicina::whereRaw('stock_actual <= stock_minimo')
            ->select('nombre', 'stock_actual', 'stock_minimo')
            ->get();

        $limiteCaducidad = Carbon::now()->addDays(60);
        $medicamentosPorCaducar = InventarioMedicina::whereNotNull('fecha_caducidad')
            ->where('fecha_caducidad', '<=', $limiteCaducidad)
            ->select('nombre', 'fecha_caducidad', 'lote')
            ->get()
            ->map(function ($medicina) {
                return [
                    'nombre' => $medicina->nombre,
                    'lote' => $medicina->lote,
                    'fecha_caducidad' => Carbon::parse($medicina->fecha_caducidad)->format('Y-m-d'),
                    'dias_restantes' => Carbon::now()->startOfDay()->diffInDays(Carbon::parse($medicina->fecha_caducidad)->startOfDay(), false),
                ];
            });

        return [
            'atenciones_mes_actual' => $atencionesMesActual,
            'pacientes_por_tipo' => [
                'titulares' => (int) ($pacientes->titulares ?? 0),
                'beneficiarios' => (int) ($pacientes->beneficiarios ?? 0),
            ],
            'top_diagnosticos' => $topDiagnosticos,
            'medicamentos_mas_despachados' => $medicamentosDespachados,
            'recetas_estado' => [
                'pendiente' => $recetasEstado->get('pendiente', 0),
                'despachada_parcial' => $recetasEstado->get('despachada_parcial', 0),
                'despachada_completa' => $recetasEstado->get('despachada_completa', 0),
                'anulada' => $recetasEstado->get('anulada', 0),
            ],
            'consultas_por_medico' => $consultasPorMedico,
            'alertas_inventario' => [
                'medicamentos_bajo_stock' => $medicamentosBajoStock,
                'medicamentos_por_caducar' => $medicamentosPorCaducar,
            ]
        ];
    }
}
