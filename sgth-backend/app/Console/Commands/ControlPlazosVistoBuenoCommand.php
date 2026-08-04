<?php

namespace App\Console\Commands;

use App\Services\Disciplinario\VistoBuenoService;
use Illuminate\Console\Command;

class ControlPlazosVistoBuenoCommand extends Command
{
    protected $signature = 'sgth:visto-bueno:control-plazos';

    protected $description = 'Alerta sobre trámites de visto bueno que excedieron los plazos del Art. 183 del Código del Trabajo.';

    public function handle(VistoBuenoService $vistoBuenoService): int
    {
        $this->info('Controlando plazos de vistos buenos...');

        $alertas = $vistoBuenoService->controlarPlazosLegales();

        if ($alertas === []) {
            $this->info('Sin trámites fuera de plazo.');

            return self::SUCCESS;
        }

        $this->warn(count($alertas).' trámite(s) fuera de plazo:');
        $this->table(
            ['Visto bueno', 'Servidor', 'Plazo', 'Fecha límite', 'Días vencido'],
            array_map(fn (array $a) => [
                $a['visto_bueno_id'],
                $a['servidor_id'],
                $a['plazo'],
                $a['fecha_limite'],
                $a['dias_vencido'],
            ], $alertas)
        );

        return self::SUCCESS;
    }
}
