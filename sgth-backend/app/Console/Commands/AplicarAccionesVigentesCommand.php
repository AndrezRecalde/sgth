<?php

namespace App\Console\Commands;

use App\Enums\EstadoAccionPersonal;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Services\Expediente\MovimientoPersonalStateService;
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
 * —ausencias y contratos con plazo—, por ahora en esta salida. El cierre de los
 * períodos de las autoridades electas llega con su clase.
 */
class AplicarAccionesVigentesCommand extends Command
{
    protected $signature = 'sgth:acciones:aplicar-vigentes {--fecha= : Fecha de corte (Y-m-d), por defecto hoy}';

    protected $description = 'Aplica el efecto de las acciones de personal registradas que ya rigen, y avisa de lo que vence en 30 días.';

    public function handle(MovimientoPersonalStateService $estados, ReintegroService $reintegros): int
    {
        $fecha = $this->option('fecha') ?: now()->toDateString();

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
        $this->avisarVencimientos($fecha);
        $this->avisarReemplazosSinTitular();

        // Lo que no se pudo aplicar no hace fallar el comando: el resto se
        // aplicó, y lo pendiente sigue a la vista en la lista de arriba.
        return self::SUCCESS;
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
