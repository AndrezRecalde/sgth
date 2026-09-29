<?php

use Tests\TestCase;

uses(TestCase::class);

/**
 * Lo que la suite necesita del entorno, no del código.
 *
 * `phpunit.xml` es fácil de tocar sin querer, y cuando se desajusta no falla
 * de frente: falla raro, en un archivo que no tiene nada que ver, y de forma
 * intermitente. Ya ha pasado dos veces —`CACHE_DRIVER` en lugar de
 * `CACHE_STORE`, y Telescope grabando durante las migraciones—, así que las
 * condiciones que importan se comprueban aquí.
 */
test('Telescope y Pulse están apagados durante las pruebas', function () {
    /*
    | Los dos traen `env('…_ENABLED', true)`, así que hay que apagarlos a mano.
    | Con ellos encendidos, Telescope consulta `telescope_monitoring` en mitad
    | del `migrate:fresh` que dispara RefreshDatabase —cuando esa tabla acaba
    | de ser eliminada y aún no existe—, el SELECT aborta la transacción de las
    | migraciones y PostgreSQL rechaza el resto con SQLSTATE[25P02]. El fallo
    | aterriza en el primer test de Feature de la suite, que no tiene culpa
    | ninguna.
    */
    expect(config('telescope.enabled'))->toBeFalse()
        ->and(config('pulse.enabled'))->toBeFalse();
});

test('la caché de pruebas es en memoria y soporta etiquetas', function () {
    // `CACHE_STORE`, no `CACHE_DRIVER`: ese es el nombre de Laravel 10 y desde
    // la 11 no lo lee nadie. Con el nombre viejo la línea no hacía nada y las
    // pruebas acababan usando el almacén del `.env` de cada máquina.
    expect(config('cache.default'))->toBe('array');
});

test('las pruebas corren contra la base de pruebas y no contra la de desarrollo', function () {
    expect(config('database.default'))->toBe('pgsql')
        ->and(config('database.connections.pgsql.database'))->toBe('sgth_testing');
});
