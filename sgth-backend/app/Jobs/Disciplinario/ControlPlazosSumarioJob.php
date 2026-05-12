<?php

namespace App\Jobs\Disciplinario;

use App\Contracts\Disciplinario\DisciplinarioServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ControlPlazosSumarioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct() {}

    public function handle(DisciplinarioServiceInterface $disciplinarioService): void
    {
        $disciplinarioService->controlarPlazosLegales();
    }
}
