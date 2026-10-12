<?php

namespace App\Console\Commands;

use App\Enums\EstadoAccionPersonal;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Services\Expediente\AutoridadElectaService;
use App\Services\Expediente\MovimientoPersonalStateService;
use App\Services\Expediente\PeriodoDePruebaService;
use App\Services\Expediente\PosesionService;
use App\Services\Expediente\ReintegroService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * El motor de efectos con fecha (diseño de Acciones de Personal, 6.2; fase 1.6).
 *
 * Una acción registrada que rige más tarde queda pendiente de vigencia: su
 * efecto —crear, mover o cerrar el vínculo, activar la subrogación— se aplica
 * aquí el día en que rige. Lo que falla queda pendiente y sale en la lista con
 * su motivo, para que Talento Humano lo resuelva; el resto sigue.
 *
 * Desde la fase 2.4 prepara también en borrador el reintegro de las ausencias
 * que llegaron a su fin. Y avisa de lo que vence en los próximos 30 días
 * —ausencias y contratos con plazo—, por ahora en esta salida. Desde la fase 4.1
 * cierra también los períodos de las autoridades electas que terminaron, antes
 * de aplicar nada: el de la autoridad reelegida empieza cuando ese se cierra.
 * Desde la fase 6.4 lista los ingresos cuyo plazo para presentarse venció sin
 * posesión: el nombramiento queda insubsistente y Talento Humano lo anula.
 */
class AplicarAccionesVigentesCommand extends Command
{
    protected $signature = 'sgth:acciones:aplicar-vigentes {--fecha= : Fecha de corte (Y-m-d), por defecto hoy}';

    protected $description = 'Aplica el efecto de las acciones de personal registradas que ya rigen, y avisa de lo que vence en 30 días.';

    public function handle(
        MovimientoPersonalStateService $estados,
        ReintegroService $reintegros,
        PeriodoDePruebaService $periodos,
        AutoridadElectaService $autoridades,
        PosesionService $posesiones,
    ): int {
        $fecha = $this->option('fecha') ?: now()->toDateString();

        $this->cerrarPeriodosDeAutoridades($autoridades, $fecha);

        $resultado = $estados->aplicarVigentes($fecha);

        $this->info(count($resultado['aplicadas']).' acción(es) surtieron efecto.');

        if ($resultado['aplicadas'] !== []) {
            $this->table(
                ['Acción', 'Código', 'Servidor'],
                array_map(fn (array $a) => [$a['id'], $a['codigo'], $a['servidor_id']], $resultado['aplicadas'])
            );
        }

        if ($resultado['fallidas'] !== []) {
            $this->warn(count($resultado['fallidas']).' acción(es) siguen pendientes de vigencia:');
            $this->table(
                ['Acción', 'Código', 'Servidor', 'Motivo'],
                array_map(fn (array $f) => [$f['id'], $f['codigo'], $f['servidor_id'], $f['motivo']], $resultado['fallidas'])
            );
        }

        $this->prepararReintegros($reintegros, $fecha);
        $this->prepararNombramientosDefinitivos($periodos, $fecha);
        $this->avisarVencimientos($fecha);
        $this->avisarReemplazosSinTitular();
        $this->avisarPresentacionesVencidas($posesiones, $fecha);

        // Lo que no se pudo aplicar no hace fallar el comando: el resto se
        // aplicó, y lo pendiente sigue a la vista en la lista de arriba.
        return self::SUCCESS;
    }

    /**
     * Los períodos de las autoridades electas que terminaron (fase 4.1): se
     * registra solo el «Fin del período», con la fecha en que terminó [TH 1].
     */
    private function cerrarPeriodosDeAutoridades(AutoridadElectaService $autoridades, string $fecha): void
    {
        $resultado = $autoridades->cerrarPeriodosTerminados($fecha);

        if ($resultado['cerrados'] !== []) {
            $this->info(count($resultado['cerrados']).' período(s) de autoridades electas cerrados.');
            $this->table(
                ['Vínculo', 'Acto', 'Servidor'],
                array_map(fn (array $c) => [$c['contrato_id'], $c['movimiento_id'], $c['servidor_id']], $resultado['cerrados'])
            );
        }

        if ($resultado['omitidos'] !== []) {
            $this->warn(count($resultado['omitidos']).' período(s) terminaron y no se pudieron cerrar:');
            $this->table(
                ['Vínculo', 'Servidor', 'Motivo'],
                array_map(fn (array $o) => [$o['contrato_id'], $o['servidor_id'], $o['motivo']], $resultado['omitidos'])
            );
        }
    }

    /**
     * Las ausencias que terminaron sin reintegro (fase 2.4): se les prepara en
     * borrador, con regreso al día siguiente del fin, para que Talento Humano
     * lo revise y lo registre.
     */
    private function prepararReintegros(ReintegroService $reintegros, string $fecha): void
    {
        $resultado = $reintegros->prepararLosDeAusenciasTerminadas($fecha);

        if ($resultado['preparados'] !== []) {
            $this->info(count($resultado['preparados']).' reintegro(s) preparados en borrador.');
            $this->table(
                ['Ausencia', 'Reintegro', 'Servidor'],
                array_map(fn (array $r) => [$r['ausencia_id'], $r['movimiento_id'], $r['servidor_id']], $resultado['preparados'])
            );
        }

        if ($resultado['omitidas'] !== []) {
            $this->warn(count($resultado['omitidas']).' ausencia(s) terminaron y no se les pudo preparar el reintegro:');
            $this->table(
                ['Ausencia', 'Servidor', 'Motivo'],
                array_map(fn (array $o) => [$o['ausencia_id'], $o['servidor_id'], $o['motivo']], $resultado['omitidas'])
            );
        }
    }

    /**
     * Los períodos de prueba que concluyeron (fase 3.2): se prepara en
     * borrador el nombramiento definitivo, que la ley da también si no se
     * practicó la evaluación (LOSEP 17 b.5).
     */
    private function prepararNombramientosDefinitivos(PeriodoDePruebaService $periodos, string $fecha): void
    {
        $resultado = $periodos->prepararLosConcluidos($fecha);

        if ($resultado['preparados'] !== []) {
            $this->info(count($resultado['preparados']).' nombramiento(s) definitivo(s) preparados en borrador.');
            $this->table(
                ['Contrato', 'Acción', 'Servidor'],
                array_map(fn (array $r) => [$r['contrato_id'], $r['movimiento_id'], $r['servidor_id']], $resultado['preparados'])
            );
        }

        if ($resultado['omitidos'] !== []) {
            $this->warn(count($resultado['omitidos']).' período(s) de prueba concluyeron sin poder preparar el nombramiento definitivo:');
            $this->table(
                ['Contrato', 'Servidor', 'Motivo'],
                array_map(fn (array $o) => [$o['contrato_id'], $o['servidor_id'], $o['motivo']], $resultado['omitidos'])
            );
        }
    }

    /**
     * Contratos de reemplazo cuyo titular ya cesó (fase 2.1): la ausencia que
     * cubrían terminó con la salida del titular. Salen aquí cada día hasta que
     * Talento Humano decida si siguen o terminan.
     */
    private function avisarReemplazosSinTitular(): void
    {
        $huerfanos = ContratoServidor::where('estado', 'vigente')
            ->whereNotNull('cubre_movimiento_id')
            ->whereHas('cubreMovimiento.servidor', fn ($q) => $q->whereDoesntHave(
                'contratos', fn ($c) => $c->where('estado', 'vigente')
            ))
            ->with('servidor:id,nombre,apellido', 'cubreMovimiento.servidor:id,nombre,apellido')
            ->orderBy('id')
            ->get();

        if ($huerfanos->isEmpty()) {
            return;
        }

        $this->warn($huerfanos->count().' reemplazo(s) cubren a alguien que ya cesó: decida si siguen o terminan.');
        $this->table(['Reemplazo', 'Cubría a', 'Contrato hasta'], $huerfanos->map(fn (ContratoServidor $c) => [
            trim(($c->servidor?->apellido ?? '').' '.($c->servidor?->nombre ?? '')),
            trim(($c->cubreMovimiento?->servidor?->apellido ?? '').' '.($c->cubreMovimiento?->servidor?->nombre ?? '')),
            $c->fecha_fin?->format('d/m/Y') ?? 'sin plazo',
        ])->all());
    }

    /**
     * Quien ingresó y no se presentó en su plazo (Reglamento a la LOSEP, Art.
     * 20; fase 6.4). Salen aquí cada día hasta que Talento Humano anule el
     * nombramiento o registre la posesión que faltaba.
     */
    private function avisarPresentacionesVencidas(PosesionService $posesiones, string $fecha): void
    {
        $vencidos = $posesiones->vencidos($fecha);

        if ($vencidos->isEmpty()) {
            return;
        }

        $this->warn($vencidos->count().' ingreso(s) sin posesión con el plazo para presentarse vencido: '
            .'el nombramiento queda insubsistente (Reglamento a la LOSEP, Art. 20).');
        $this->table(['Acción', 'Servidor', 'Venció el'], $vencidos->map(fn (MovimientoPersonal $m) => [
            $m->codigo_registro,
            trim(($m->servidor?->apellido ?? '').' '.($m->servidor?->nombre ?? '')),
            $m->presentacion_vence_el->format('d/m/Y'),
        ])->all());
    }

    private function avisarVencimientos(string $fecha): void
    {
        $hasta = Carbon::parse($fecha)->addDays(30)->toDateString();

        // Por query(): el modelo tiene también un esAusenciaTemporal() de
        // instancia, y la llamada estática caería en ese.
        $ausencias = MovimientoPersonal::query()->esAusenciaTemporal()
            ->whereIn('estado', [EstadoAccionPersonal::REGISTRADA->value, EstadoAccionPersonal::NOTIFICADA->value])
            ->whereBetween('fecha_fin', [$fecha, $hasta])
            // La que ya tiene reintegro, en trámite o emitido, no está por vencer:
            // Talento Humano ya se ocupó.
            ->whereDoesntHave('reintegro')
            ->with('servidor:id,nombre,apellido')
            ->orderBy('fecha_fin')
            ->orderBy('id')
            ->get()
            ->map(fn (MovimientoPersonal $m) => [
                $m->etiqueta(),
                trim(($m->servidor?->apellido ?? '').' '.($m->servidor?->nombre ?? '')),
                $m->fecha_fin->format('d/m/Y'),
            ]);

        $contratos = ContratoServidor::where('estado', 'vigente')
            ->whereBetween('fecha_fin', [$fecha, $hasta])
            ->with('servidor:id,nombre,apellido')
            ->orderBy('fecha_fin')
            ->orderBy('id')
            ->get()
            ->map(fn (ContratoServidor $c) => [
                'Contrato: '.($c->tipo_nombramiento?->etiqueta() ?? '—'),
                trim(($c->servidor?->apellido ?? '').' '.($c->servidor?->nombre ?? '')),
                $c->fecha_fin->format('d/m/Y'),
            ]);

        $porVencer = $ausencias->concat($contratos)->all();

        if ($porVencer === []) {
            $this->info('Nada vence en los próximos 30 días.');

            return;
        }

        $this->warn(count($porVencer).' vencimiento(s) en los próximos 30 días:');
        $this->table(['Qué', 'Servidor', 'Vence'], $porVencer);
    }
}
