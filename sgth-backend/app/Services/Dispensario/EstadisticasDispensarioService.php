<?php
namespace App\Services\Dispensario;

use App\Contracts\Dispensario\EstadisticasDispensarioServiceInterface;
use App\Enums\EspecialidadAtencion;
use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\InventarioMedicina;
use App\Models\Dispensario\LoteMedicina;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Las cifras del tablero del Dispensario.
 *
 * Eran siempre «del mes en curso»: el día 1 el tablero salía en ceros y no
 * había forma de mirar un mes cerrado. Ahora el período lo pide quien mira, y
 * cada cifra se lee contra el período anterior del mismo largo.
 */
final class EstadisticasDispensarioService implements EstadisticasDispensarioServiceInterface
{
    public function obtenerKpis(CarbonInterface $desde, CarbonInterface $hasta): array
    {
        $inicio = Carbon::parse($desde)->startOfDay();
        $fin = Carbon::parse($hasta)->endOfDay();

        // El período anterior: si se pidió un mes completo, el mes anterior
        // completo («septiembre contra agosto»); si no, el mismo número de días
        // justo antes.
        $anteriorHasta = $inicio->copy()->subDay()->endOfDay();
        $esMesCompleto = $inicio->isSameDay($inicio->copy()->startOfMonth())
            && $fin->isSameDay($inicio->copy()->endOfMonth());
        $anteriorDesde = $esMesCompleto
            ? $inicio->copy()->subMonthNoOverflow()->startOfMonth()
            : $inicio->copy()->subDays((int) $inicio->diffInDays($fin) + 1)->startOfDay();

        // 1. Atenciones del período, y de qué especialidad fueron
        $atenciones = ConsultaMedica::whereBetween('created_at', [$inicio, $fin])->count();
        $atencionesAnterior = ConsultaMedica::whereBetween('created_at', [$anteriorDesde, $anteriorHasta])->count();

        // El dato existe en la consulta desde que dejó de vivir solo en el
        // turno. Sin este desglose, medicina general y odontología iban en el
        // mismo número y ninguna de las dos podía justificar nada.
        $porEspecialidad = DB::table('consultas_medicas')
            ->whereBetween('created_at', [$inicio, $fin])
            ->select('especialidad', DB::raw('COUNT(*) as total'))
            ->groupBy('especialidad')
            ->pluck('total', 'especialidad');

        // 2. Pacientes DISTINTOS del período, por tipo.
        // «Pacientes atendidos» contaba consultas: quien iba tres veces contaba
        // tres. Y las dos condiciones no se excluían, así que la historia de un
        // candidato (sin servidor ni carga familiar) contaba como titular Y
        // como beneficiario.
        $pacientes = DB::table('consultas_medicas')
            ->join('historias_clinicas', 'consultas_medicas.historia_clinica_id', '=', 'historias_clinicas.id')
            ->whereBetween('consultas_medicas.created_at', [$inicio, $fin])
            ->selectRaw('
                COUNT(DISTINCT historias_clinicas.id) as distintos,
                COUNT(DISTINCT CASE WHEN historias_clinicas.servidor_id IS NOT NULL
                    AND historias_clinicas.carga_familiar_id IS NULL THEN historias_clinicas.id END) as titulares,
                COUNT(DISTINCT CASE WHEN historias_clinicas.carga_familiar_id IS NOT NULL
                    THEN historias_clinicas.id END) as carga_familiar,
                COUNT(DISTINCT CASE WHEN historias_clinicas.servidor_id IS NULL
                    AND historias_clinicas.carga_familiar_id IS NULL THEN historias_clinicas.id END) as candidatos
            ')
            ->first();

        // 3. Top Diagnosticos CIE-10 (mes actual), con su especialidad
        $topDiagnosticos = DB::table('consultas_medicas')
            ->join('diagnosticos_cie10', 'consultas_medicas.diagnostico_cie10_id', '=', 'diagnosticos_cie10.id')
            ->whereBetween('consultas_medicas.created_at', [$inicio, $fin])
            ->select(
                'diagnosticos_cie10.codigo',
                'diagnosticos_cie10.descripcion',
                'consultas_medicas.especialidad',
                DB::raw('COUNT(*) as total')
            )
            ->groupBy(
                'diagnosticos_cie10.id',
                'diagnosticos_cie10.codigo',
                'diagnosticos_cie10.descripcion',
                'consultas_medicas.especialidad'
            )
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        // 4. Medicamentos más despachados (mes actual)
        $medicamentosDespachados = DB::table('items_receta')
            ->join('inventario_medicinas', 'items_receta.inventario_medicina_id', '=', 'inventario_medicinas.id')
            ->whereBetween('items_receta.created_at', [$inicio, $fin])
            ->select('inventario_medicinas.nombre', DB::raw('SUM(items_receta.cantidad_despachada) as total_despachado'))
            ->groupBy('inventario_medicinas.id', 'inventario_medicinas.nombre')
            ->having(DB::raw('SUM(items_receta.cantidad_despachada)'), '>', 0)
            ->orderByDesc('total_despachado')
            ->limit(10)
            ->get();

        // 5. Estado de las recetas del período. Se calculaba y la pantalla no
        // lo mostraba: las pendientes de despacho son el cuello de Farmacia.
        $recetasEstado = DB::table('recetas_medicas')
            ->whereBetween('created_at', [$inicio, $fin])
            ->select('estado', DB::raw('COUNT(*) as total'))
            ->groupBy('estado')
            ->pluck('total', 'estado');

        // 6. Consultas por Médico (mes actual), desglosadas por especialidad
        //
        // Se leía `users.name`, una columna que esta tabla no tiene: el nombre
        // del profesional está en `servidores`, y `users` solo guarda el correo
        // y el usuario de red. La consulta reventaba con «column users.name
        // does not exist», así que estos KPI devolvían un 500 entero — nadie lo
        // notó porque todavía no hay pantalla que los pida.
        $consultasPorMedico = DB::table('consultas_medicas')
            ->join('users', 'consultas_medicas.medico_id', '=', 'users.id')
            ->leftJoin('servidores', 'users.servidor_id', '=', 'servidores.id')
            ->whereBetween('consultas_medicas.created_at', [$inicio, $fin])
            ->select(
                DB::raw(
                    "COALESCE(
                        NULLIF(TRIM(CONCAT(servidores.nombre, ' ', servidores.apellido)), ''),
                        users.usuario_ti,
                        users.email
                     ) as medico"
                ),
                'consultas_medicas.especialidad',
                DB::raw('COUNT(*) as total_consultas')
            )
            ->groupBy(
                'users.id', 'servidores.nombre', 'servidores.apellido',
                'users.usuario_ti', 'users.email',
                'consultas_medicas.especialidad'
            )
            ->orderByDesc('total_consultas')
            ->get();

        // 7. Alertas de inventario
        // Bajo mínimo se mide sobre lo despachable: las unidades vencidas no
        // evitan una rotura de stock, solo la disimulan.
        $medicamentosBajoStock = InventarioMedicina::bajoMinimo()
            ->conResumenDeLotes()
            ->get()
            ->map(fn ($medicina) => [
                'nombre'       => $medicina->nombre,
                'stock_actual' => (int) $medicina->stock_despachable,
                'stock_minimo' => $medicina->stock_minimo,
            ]);

        // El aviso va por lote, que es lo que caduca. Antes salía una fila por
        // medicina con la fecha de la última entrada, así que un lote a punto
        // de vencer quedaba tapado por otro más reciente.
        //
        // Los vencidos van aparte: se mezclaban en «por caducar en 60 días»
        // («vencido hace 43 días»), y lo vencido no se vigila, se retira.
        $limiteCaducidad = Carbon::now()->addDays(60);
        $lotesPorCaducar = LoteMedicina::with('medicina:id,nombre')
            ->conStock()
            ->whereNotNull('fecha_caducidad')
            ->whereDate('fecha_caducidad', '<=', $limiteCaducidad)
            ->fefo()
            ->get()
            ->map(fn (LoteMedicina $lote) => [
                'nombre'          => $lote->medicina->nombre,
                'lote'            => $lote->etiqueta,
                'stock'           => $lote->stock_actual,
                'fecha_caducidad' => $lote->fecha_caducidad->format('Y-m-d'),
                'dias_restantes'  => (int) Carbon::now()->startOfDay()
                    ->diffInDays($lote->fecha_caducidad->startOfDay(), false),
            ]);

        [$medicamentosVencidos, $medicamentosPorCaducar] = $lotesPorCaducar
            ->partition(fn (array $lote) => $lote['dias_restantes'] < 0);

        return [
            'periodo' => [
                'desde' => $inicio->toDateString(),
                'hasta' => $fin->toDateString(),
            ],
            'periodo_anterior' => [
                'desde' => $anteriorDesde->toDateString(),
                'hasta' => $anteriorHasta->toDateString(),
            ],
            'atenciones' => $atenciones,
            'atenciones_periodo_anterior' => $atencionesAnterior,
            'atenciones_por_especialidad' => [
                'medicina_general' => (int) $porEspecialidad->get(
                    EspecialidadAtencion::MEDICINA_GENERAL->value, 0
                ),
                'odontologia' => (int) $porEspecialidad->get(
                    EspecialidadAtencion::ODONTOLOGIA->value, 0
                ),
            ],
            'pacientes' => [
                'distintos' => (int) ($pacientes->distintos ?? 0),
                'titulares' => (int) ($pacientes->titulares ?? 0),
                'carga_familiar' => (int) ($pacientes->carga_familiar ?? 0),
                'candidatos' => (int) ($pacientes->candidatos ?? 0),
            ],
            'top_diagnosticos' => $topDiagnosticos,
            'medicamentos_mas_despachados' => $medicamentosDespachados,
            'recetas_estado' => [
                'pendiente' => (int) $recetasEstado->get('pendiente', 0),
                'despachada_parcial' => (int) $recetasEstado->get('despachada_parcial', 0),
                'despachada_completa' => (int) $recetasEstado->get('despachada_completa', 0),
                'anulada' => (int) $recetasEstado->get('anulada', 0),
            ],
            'consultas_por_medico' => $consultasPorMedico,
            'alertas_inventario' => [
                'medicamentos_bajo_stock' => $medicamentosBajoStock,
                'medicamentos_por_caducar' => $medicamentosPorCaducar->values(),
                'medicamentos_vencidos' => $medicamentosVencidos->values(),
            ]
        ];
    }
}
