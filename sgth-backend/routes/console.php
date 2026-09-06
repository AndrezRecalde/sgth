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

// Generación automática de reportes LOTAIP Art. 7 — APAGADA a propósito.
//
// Publica en `storage/app/public/lotaip/`, que se sirve por URL directa y sin
// autenticación, y los tres reportes que arma todavía no están construidos: la
// nómina consolidada devuelve una lista vacía, la estructura orgánica trae dos
// filas de ejemplo, y el distributivo de sueldos está limitado a diez
// servidores. Publicar eso como información de transparencia es peor que no
// publicar nada.
//
// Hoy no llega a publicar porque el distributivo consulta columnas que no
// existen y revienta, así que apagarla no cambia lo que hay: cambia que deje de
// depender de una avería. El día que los reportes estén hechos, volver a
// encenderla debe ser una decisión, no el efecto secundario de arreglar la
// consulta.
//
// Schedule::command('lotaip:generar-reportes')->dailyAt('01:00');

// Plazos del Art. 183 del Código del Trabajo en los trámites de visto bueno
Schedule::command('sgth:visto-bueno:control-plazos')->weekdays()->dailyAt('07:00');

// Vencimiento de contratos de Servicios Profesionales: genera la cesación en
// borrador para que Talento Humano la revise. Nada se da de baja sin aprobación.
Schedule::command('sgth:contratos:detectar-vencidos')
    ->dailyAt('05:00')
    ->onOneServer();

// Cierre de subrogaciones y encargos cuyo plazo venció. Aquí no se genera nada
// para revisar —a diferencia de los contratos vencidos—: la fecha de fin ya
// venía autorizada en la Acción de Personal.
Schedule::command('sgth:subrogaciones:caducar')
    ->dailyAt('05:30')
    ->onOneServer();

// Tarea 8: Backup Automático Diario
Schedule::command('backup:base-datos')
    ->dailyAt('02:00')
    ->onOneServer();

// Los tokens del API caducan a las 24 horas (config/sanctum.php), pero Sanctum
// solo los rechaza: siguen en `personal_access_tokens` hasta que alguien los
// borre. `--hours=24` deja un día de margen desde que caducaron, así que se van
// los emitidos hace más de dos días.
Schedule::command('sanctum:prune-expired --hours=24')
    ->dailyAt('03:30')
    ->onOneServer();

use App\Jobs\GenerarPeriodosAnualesJob;

Schedule::call(function () {
    GenerarPeriodosAnualesJob::dispatch(now()->year);
})->yearlyOn(1, 1, '00:00')
  ->name('generar-periodos-vacaciones')
  ->withoutOverlapping();

