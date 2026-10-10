<?php

namespace App\Console\Commands;

use App\Enums\EstadoAccionPersonal;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Services\Expediente\MovimientoPersonalStateService;
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
 * También avisa de lo que vence en los próximos 30 días —ausencias y contratos
 * con plazo—, por ahora en esta salida. El reintegro en borrador al vencer una
 * ausencia y el cierre de los períodos de las autoridades electas llegan con
 * sus clases, en la fase 2.
 */
class AplicarAccionesVigentesCommand extends Command
{
    protected $signature = 'sgth:acciones:aplicar-vigentes {--fecha= : Fecha de corte (Y-m-d), por defecto hoy}';

    protected $description = 'Aplica el efecto de las acciones de personal registradas que ya rigen, y avisa de lo que vence en 30 días.';

    public function handle(MovimientoPersonalStateService $estados): int
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

        $this->avisarVencimientos($fecha);

        // Lo que no se pudo aplicar no hace fallar el comando: el resto se
        // aplicó, y lo pendiente sigue a la vista en la lista de arriba.
        return self::SUCCESS;
    }

    private function avisarVencimientos(string $fecha): void
    {
        $hasta = Carbon::parse($fecha)->addDays(30)->toDateString();

        // Por query(): el modelo tiene también un esAusenciaTemporal() de
        // instancia, y la llamada estática caería en ese.
        $ausencias = MovimientoPersonal::query()->esAusenciaTemporal()
            ->whereIn('estado', [EstadoAccionPersonal::REGISTRADA->value, EstadoAccionPersonal::NOTIFICADA->value])
            ->whereBetween('fecha_fin', [$fecha, $hasta])
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
