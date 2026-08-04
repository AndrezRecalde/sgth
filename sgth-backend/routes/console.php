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

// Plazos del Art. 183 del Código del Trabajo en los trámites de visto bueno
Schedule::command('sgth:visto-bueno:control-plazos')->weekdays()->dailyAt('07:00');

// Vencimiento de contratos de Servicios Profesionales: genera la cesación en
// borrador para que Talento Humano la revise. Nada se da de baja sin aprobación.
Schedule::command('sgth:contratos:detectar-vencidos')
    ->dailyAt('05:00')
    ->onOneServer();

// Tarea 8: Backup Automático Diario
Schedule::command('backup:base-datos')
    ->dailyAt('02:00')
    ->onOneServer();

use App\Jobs\GenerarPeriodosAnualesJob;

Schedule::call(function () {
    GenerarPeriodosAnualesJob::dispatch(now()->year);
})->yearlyOn(1, 1, '00:00')
  ->name('generar-periodos-vacaciones')
  ->withoutOverlapping();

