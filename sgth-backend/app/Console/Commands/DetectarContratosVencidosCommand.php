<?php

namespace App\Console\Commands;

use App\Services\Expediente\ContratoVencidoService;
use Illuminate\Console\Command;

class DetectarContratosVencidosCommand extends Command
{
    protected $signature = 'sgth:contratos:detectar-vencidos {--fecha= : Fecha de corte (Y-m-d), por defecto hoy}';

    protected $description = 'Genera en borrador la Cesación de Funciones de los contratos de Servicios Profesionales cuyo plazo venció.';

    public function handle(ContratoVencidoService $servicio): int
    {
        $fecha = $this->option('fecha');

        $this->info('Detectando contratos de Servicios Profesionales vencidos...');

        $resultado = $servicio->generarCesacionesPendientes($fecha);

        $this->info(count($resultado['generadas']).' cesación(es) generada(s) en borrador.');

        if ($resultado['generadas'] !== []) {
            $this->table(
                ['Contrato', 'Servidor', 'Acción de personal'],
                array_map(fn (array $g) => [
                    $g['contrato_id'], $g['servidor_id'], $g['movimiento_id'],
                ], $resultado['generadas'])
            );
        }

        if ($resultado['omitidas'] !== []) {
            $this->warn(count($resultado['omitidas']).' contrato(s) omitido(s):');
            $this->table(
                ['Contrato', 'Motivo'],
                array_map(fn (array $o) => [$o['contrato_id'], $o['motivo']], $resultado['omitidas'])
            );
        }

        return self::SUCCESS;
    }
}
