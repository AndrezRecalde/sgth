<?php

namespace Tests\Feature;

use App\Helpers\DiasHabilesHelper;
use App\Models\Asistencia\FeriadoInstitucional;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * El contador de días hábiles lo comparten Permisos, Viáticos, el régimen
 * disciplinario y el comando de LOTAIP. Consultaba la tabla de feriados una
 * vez por cada día que sumaba; ahora la lee entera una sola vez.
 *
 * Estas pruebas fijan el resultado —que no cambia— y el número de consultas,
 * que es lo que se arregló.
 */
beforeEach(function () {
    $this->contador = new class
    {
        use DiasHabilesHelper;

        public function sumar(string $desde, int $dias): string
        {
            return $this->calcularDiasHabiles(Carbon::parse($desde), $dias)->toDateString();
        }
    };
});

test('cuenta de lunes a viernes sin tocar el fin de semana', function () {
    // 2026-03-02 es lunes. Tres días hábiles caen el jueves 5.
    expect($this->contador->sumar('2026-03-02', 3))->toBe('2026-03-05');
});

test('el fin de semana no cuenta', function () {
    // Jueves 2026-03-05 + 3 hábiles: viernes, lunes, martes 10.
    expect($this->contador->sumar('2026-03-05', 3))->toBe('2026-03-10');
});

test('un feriado móvil corre el plazo un día', function () {
    FeriadoInstitucional::create([
        'fecha'       => '2026-03-04',
        'descripcion' => 'Feriado móvil de prueba',
        'es_nacional' => true,
        'es_movil'    => true,
    ]);

    // Sin el feriado del miércoles serían 3 días hábiles al jueves 5.
    expect($this->contador->sumar('2026-03-02', 3))->toBe('2026-03-06');
});

test('un feriado fijo vale en cualquier año', function () {
    FeriadoInstitucional::create([
        'mes'         => 3,
        'dia'         => 4,
        'descripcion' => 'Feriado fijo de prueba',
        'es_nacional' => true,
        'es_movil'    => false,
    ]);

    expect($this->contador->sumar('2026-03-02', 3))->toBe('2026-03-06')
        ->and($this->contador->sumar('2031-03-03', 1))->toBe('2031-03-05');
});

test('la tabla de feriados se lee una sola vez, no una por día', function () {
    FeriadoInstitucional::create([
        'fecha'       => '2026-03-04',
        'descripcion' => 'Feriado móvil de prueba',
        'es_nacional' => true,
        'es_movil'    => true,
    ]);

    DB::enableQueryLog();
    DB::flushQueryLog();

    // Dos plazos largos sobre la misma instancia: antes eran 11 + 21
    // consultas; ahora es una, y la segunda llamada no vuelve a preguntar.
    $this->contador->sumar('2026-03-02', 10);
    $this->contador->sumar('2026-03-02', 20);

    $consultas = collect(DB::getRawQueryLog())
        ->filter(fn (array $q) => str_contains($q['raw_query'], 'feriados_institucionales'))
        ->count();

    DB::disableQueryLog();

    expect($consultas)->toBe(1);
});
