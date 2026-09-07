<?php

use App\Contracts\Reporteria\ReporteriaServiceInterface;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;

// Sin RefreshDatabase: aquí no se toca la base. Lo que se comprueba es qué
// hay programado y qué pasa cuando la generación falla.
uses(Tests\TestCase::class);

/**
 * Los reportes LOTAIP: la tarea apagada, y el fallo que ya no se pierde.
 *
 * Publicaban en `storage/app/public/lotaip/`, que se sirve por URL directa y
 * sin autenticación, y los tres reportes que arma el comando todavía no están
 * construidos: la nómina consolidada devuelve una lista vacía, la estructura
 * orgánica trae dos filas de ejemplo y el distributivo está limitado a diez
 * servidores.
 *
 * Hoy no llega a publicar porque el distributivo consulta columnas que no
 * existen. El riesgo no era lo que publicaba, sino que arreglar esa consulta
 * —un cambio de una línea— la habría hecho empezar a publicar.
 */
test('la_tarea_de_lotaip_no_esta_programada', function () {
    $programadas = collect(app(Schedule::class)->events())
        ->map(fn ($evento) => $evento->command ?? $evento->description ?? '');

    expect($programadas->filter(
        fn ($comando) => str_contains((string) $comando, 'lotaip:generar-reportes')
    ))->toBeEmpty();
});

test('el_resto_de_tareas_programadas_sigue_en_pie', function () {
    // Apagar una no debe llevarse por delante a las demás: la de plazos del
    // visto bueno y la de contratos vencidos están justo al lado en el archivo.
    $programadas = collect(app(Schedule::class)->events())
        ->map(fn ($evento) => (string) ($evento->command ?? ''))
        ->implode(' ');

    expect($programadas)->toContain('sgth:visto-bueno:control-plazos');
    expect($programadas)->toContain('sgth:contratos:detectar-vencidos');
});

test('si_la_generacion_falla_queda_en_el_registro_y_el_comando_falla', function () {
    // El fallo moría escrito solo en la consola: en una tarea de la una de la
    // madrugada eso equivale a no decir nada, y el planificador la daba por
    // buena. Lleva fallando desde siempre sin constar en ningún sitio.
    $this->mock(ReporteriaServiceInterface::class, function ($mock) {
        $mock->shouldReceive('generarDistributivoSueldos')
            ->andThrow(new RuntimeException('columna inexistente'));
    });

    Log::spy();

    $this->artisan('lotaip:generar-reportes', ['--force' => true])
        ->assertFailed();

    Log::shouldHaveReceived('error')
        ->withArgs(function (string $mensaje, array $contexto) {
            return str_contains($mensaje, 'reportes LOTAIP')
                && str_contains($contexto['excepcion'] ?? '', 'columna inexistente');
        })
        ->once();
});

test('el_comando_sigue_existiendo_para_ejecutarlo_a_mano', function () {
    // Apagar la tarea no es quitar el comando: cuando los reportes estén
    // hechos, encenderla otra vez debe poder probarse a mano primero.
    expect(array_keys(Illuminate\Support\Facades\Artisan::all()))
        ->toContain('lotaip:generar-reportes');
});
