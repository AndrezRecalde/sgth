<?php

namespace App\Console\Commands;

use App\Jobs\Disciplinario\ControlPlazosSumarioJob;
use Illuminate\Console\Command;

class ControlPlazosSumarioCommand extends Command
{
    protected $signature = 'sgth:disciplinario:control-plazos';
    protected $description = 'Ejecuta el job para controlar la caducidad de plazos en sumarios administrativos.';

    public function handle()
    {
        $this->info('Iniciando control de plazos procesales...');
        ControlPlazosSumarioJob::dispatchSync();
        $this->info('Control de plazos finalizado. Revise los logs de alertas a UATH.');
    }
}
