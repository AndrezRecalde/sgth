<?php

/*
| La ventana de una campaña de tamizaje, sin base de datos.
|
| Son tres datos —`activa`, `fecha_apertura` y `fecha_cierre`— y cada lector
| miraba los que le parecía. Aquí quedan fijadas las nueve combinaciones que
| importan, incluidos los dos bordes que estaban mal:
|
| - la apertura futura, que nadie comprobaba;
| - el día de cierre, que se perdía entero porque la comparación era
|   `isPast()` sobre una columna casteada a `date`.
*/

use App\Enums\EstadoCampaniaSso;
use Illuminate\Support\Carbon;

$hoy = fn() => Carbon::parse('2026-10-15');

// ── Cerrada a mano ────────────────────────────────────────────────────

test('una campaña desactivada está cerrada, pase lo que pase con las fechas', function () use ($hoy) {
    expect(EstadoCampaniaSso::desde(false, Carbon::parse('2026-10-01'), null, $hoy()))
        ->toBe(EstadoCampaniaSso::CERRADA);

    // Incluso con la ventana por delante: cerrarla a mano manda.
    expect(EstadoCampaniaSso::desde(false, Carbon::parse('2026-10-01'), Carbon::parse('2026-12-31'), $hoy()))
        ->toBe(EstadoCampaniaSso::CERRADA);
});

// ── La apertura, que no se miraba ─────────────────────────────────────

test('una campaña con la apertura en el futuro está programada, no abierta', function () use ($hoy) {
    // Es el 1.8 de la revisión: creada con apertura el mes que viene, se
    // respondía hoy con su enlace.
    expect(EstadoCampaniaSso::desde(true, Carbon::parse('2026-11-01'), null, $hoy()))
        ->toBe(EstadoCampaniaSso::PROGRAMADA);
});

test('una campaña que abre hoy ya admite respuestas', function () use ($hoy) {
    // El borde de la apertura: el mismo día cuenta.
    expect(EstadoCampaniaSso::desde(true, Carbon::parse('2026-10-15'), null, $hoy()))
        ->toBe(EstadoCampaniaSso::ABIERTA);
});

test('una campaña que abrió ayer está abierta', function () use ($hoy) {
    expect(EstadoCampaniaSso::desde(true, Carbon::parse('2026-10-14'), null, $hoy()))
        ->toBe(EstadoCampaniaSso::ABIERTA);
});

// ── El cierre, que se comía el último día ─────────────────────────────

test('el día de cierre todavía admite respuestas', function () use ($hoy) {
    // El defecto: `fecha_cierre->isPast()` con la columna casteada a `date`
    // es verdadero a las 00:00 del propio día de cierre, así que una campaña
    // «abierta hasta el 15» perdía el 15 entero.
    expect(EstadoCampaniaSso::desde(true, Carbon::parse('2026-10-01'), Carbon::parse('2026-10-15'), $hoy()))
        ->toBe(EstadoCampaniaSso::ABIERTA);
});

test('el día siguiente al cierre ya está cerrada', function () use ($hoy) {
    expect(EstadoCampaniaSso::desde(true, Carbon::parse('2026-10-01'), Carbon::parse('2026-10-14'), $hoy()))
        ->toBe(EstadoCampaniaSso::CERRADA);
});

test('el cierre manda sobre la apertura si las dos ya pasaron', function () use ($hoy) {
    expect(EstadoCampaniaSso::desde(true, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'), $hoy()))
        ->toBe(EstadoCampaniaSso::CERRADA);
});

// ── Sin fechas ────────────────────────────────────────────────────────

test('sin fecha de cierre la campaña sigue abierta indefinidamente', function () use ($hoy) {
    expect(EstadoCampaniaSso::desde(true, Carbon::parse('2026-01-01'), null, $hoy()))
        ->toBe(EstadoCampaniaSso::ABIERTA);
});

test('sin ninguna de las dos fechas, activa significa abierta', function () use ($hoy) {
    expect(EstadoCampaniaSso::desde(true, null, null, $hoy()))
        ->toBe(EstadoCampaniaSso::ABIERTA);
});

// ── La hora del día no cambia nada ────────────────────────────────────

test('la hora del día no mueve la ventana', function () {
    // Las dos fechas son días, no instantes. Consultar a las 23:59 del día de
    // cierre tiene que dar lo mismo que a las 00:01.
    $cierre = Carbon::parse('2026-10-15');

    foreach (['2026-10-15 00:00:01', '2026-10-15 12:00:00', '2026-10-15 23:59:59'] as $momento) {
        expect(EstadoCampaniaSso::desde(true, Carbon::parse('2026-10-01'), $cierre, Carbon::parse($momento)))
            ->toBe(EstadoCampaniaSso::ABIERTA);
    }

    expect(EstadoCampaniaSso::desde(true, Carbon::parse('2026-10-01'), $cierre, Carbon::parse('2026-10-16 00:00:01')))
        ->toBe(EstadoCampaniaSso::CERRADA);
});

// ── Lo que consume el resto del módulo ────────────────────────────────

test('solo la abierta admite respuestas', function () {
    expect(EstadoCampaniaSso::ABIERTA->admiteRespuestas())->toBeTrue();
    expect(EstadoCampaniaSso::PROGRAMADA->admiteRespuestas())->toBeFalse();
    expect(EstadoCampaniaSso::CERRADA->admiteRespuestas())->toBeFalse();
});

test('los tres estados tienen etiqueta', function () {
    foreach (EstadoCampaniaSso::cases() as $estado) {
        expect($estado->etiqueta())->toBeString()->not->toBeEmpty();
    }
});
