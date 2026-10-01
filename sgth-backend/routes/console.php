<?php

use App\Jobs\Asistencia\VencerPermisosJob;
use App\Jobs\Dispensario\VerificarAlertasInventarioJob;
use App\Jobs\GenerarPeriodosAnualesJob;
use App\Jobs\Helpdesk\EnviarAlertaSlaJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Tarea 8: Alertas Dispensario
Schedule::job(new VerificarAlertasInventarioJob)->dailyAt('06:00');

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

// Plazos del Art. 183 del Código del Trabajo en los trámites de visto bueno.
// `onOneServer()` como sus cinco vecinos: solo escribe avisos en el log, pero
// con dos máquinas corriendo el planificador los escribía dos veces.
Schedule::command('sgth:visto-bueno:control-plazos')
    ->weekdays()
    ->dailyAt('07:00')
    ->onOneServer();

// Plazos procesales del sumario administrativo (LOSEP): notificación dentro de
// 3 días hábiles de la apertura, resolución dentro de 10 desde el informe.
//
// El comando existía desde el primer sprint del módulo y nunca se programó, así
// que el control solo corría cuando alguien lo lanzaba a mano: en producción
// ningún sumario avisaba jamás de haber caducado, y la caducidad de un sumario
// es la que deja sin efecto la sanción. Es el mismo caso de `VencerPermisosJob`
// anotado más abajo.
//
// En días laborables porque los plazos se cuentan en días hábiles: un sábado no
// vence nada y no hay nada que revisar. A las 07:15 para no pisarse con el de
// visto bueno.
Schedule::command('sgth:disciplinario:control-plazos')
    ->weekdays()
    ->dailyAt('07:15')
    ->onOneServer();

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

// Vacaciones aprobadas que ya terminaron pasan a gozada. Como las
// subrogaciones, no hay nada que revisar: las fechas venían aprobadas.
Schedule::command('sgth:vacaciones:marcar-gozadas')
    ->dailyAt('05:45')
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

// Viáticos: llegada la salida pasan a «en comisión»; llegado el regreso, a
// «pendiente de liquidación», que es cuando empieza el plazo de 4 días hábiles
// para liquidar. Dependía de un botón que nadie estaba obligado a pulsar. Cada
// hora, porque el viaje empieza y termina a una hora concreta y el servidor
// debe ver su liquidación abierta el mismo día que vuelve.
Schedule::command('sgth:viaticos:avanzar-estados')
    ->hourly()
    ->onOneServer()
    ->withoutOverlapping();

// Las 72 horas laborables del Art. 33 de la LOSEP: el permiso cuyo respaldo
// físico no llegó a Recepción dentro del plazo pasa a falta injustificada.
//
// El job existía desde el primer sprint del módulo y nunca se programó, así
// que la regla solo corría dentro de su propio test: en producción ningún
// permiso caducaba jamás y todos quedaban «pendientes» para siempre. Se corre
// en días laborables porque el plazo se cuenta en días hábiles; un sábado no
// vence nada y no hay nada que revisar.
Schedule::job(new VencerPermisosJob)
    ->weekdays()
    ->dailyAt('06:15')
    ->onOneServer();

Schedule::call(function () {
    GenerarPeriodosAnualesJob::dispatch(now()->year);
})->yearlyOn(1, 1, '00:00')
  ->name('generar-periodos-vacaciones')
  ->withoutOverlapping()
  // `withoutOverlapping()` solo impide que se pise consigo misma en la MISMA
  // máquina. Sin esto, con más de un servidor corriendo el planificador, cada
  // uno abriría los períodos de la plantilla entera el 1 de enero. El de
  // permisos ya lo lleva.
  ->onOneServer();

