<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;
use App\Jobs\Dispensario\VerificarAlertasInventarioJob;

// Tarea 8: Alertas Dispensario
Schedule::job(new VerificarAlertasInventarioJob)->dailyAt('06:00');

use App\Jobs\Helpdesk\EnviarAlertaSlaJob;

// Tarea 4 (Sprint 10): Alertas de SLA Helpdesk cada 15 minutos
Schedule::job(new EnviarAlertaSlaJob)->everyFifteenMinutes();

// Sprint 12: Generación automática de reportes LOTAIP Art. 7
Schedule::command('lotaip:generar-reportes')->dailyAt('01:00');

// Tarea 8: Backup Automático Diario
Schedule::command('backup:base-datos')
    ->dailyAt('02:00')
    ->onOneServer();
