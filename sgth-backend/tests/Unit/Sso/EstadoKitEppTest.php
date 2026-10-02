<?php

/*
| Cuándo toca entregar un equipo del kit, sin base de datos.
|
| El modal premarcaba TODO el kit del puesto, siempre, así que entregarlo dos
| veces creaba filas duplicadas sin aviso. Desmarcar lo entregado alguna vez
| tampoco bastaba: unas botas de hace tres años se quedarían desmarcadas para
| siempre. El plazo sale de `frecuencia_reposicion_meses` del puesto y, si no
| la fijó, de la `vida_util_meses` del equipo.
*/

use App\Enums\EstadoKitEpp;
use Illuminate\Support\Carbon;

$hoy = fn() => Carbon::parse('2026-10-15');

// ── Nunca entregado ───────────────────────────────────────────────────

test('sin entrega previa el equipo está pendiente', function () use ($hoy) {
    expect(EstadoKitEpp::desde(null, 6, 12, $hoy()))->toBe(EstadoKitEpp::PENDIENTE);
    // Y sin plazos tampoco cambia: lo que falta, falta.
    expect(EstadoKitEpp::desde(null, null, null, $hoy()))->toBe(EstadoKitEpp::PENDIENTE);
});

// ── Con plazo del puesto ──────────────────────────────────────────────

test('dentro del plazo de reposición está vigente', function () use ($hoy) {
    // Entregado hace dos meses, se repone cada seis.
    expect(EstadoKitEpp::desde(Carbon::parse('2026-08-15'), 6, null, $hoy()))
        ->toBe(EstadoKitEpp::VIGENTE);
});

test('cumplido el plazo toca reponer', function () use ($hoy) {
    // Entregado hace siete meses, se repone cada seis.
    expect(EstadoKitEpp::desde(Carbon::parse('2026-03-15'), 6, null, $hoy()))
        ->toBe(EstadoKitEpp::POR_REPONER);
});

test('el día exacto del plazo ya toca reponer', function () use ($hoy) {
    // Entregado el 15 de abril, cada seis meses: el 15 de octubre toca.
    // Inclusivo: ese día se repone, no al siguiente.
    expect(EstadoKitEpp::desde(Carbon::parse('2026-04-15'), 6, null, $hoy()))
        ->toBe(EstadoKitEpp::POR_REPONER);
});

test('el día anterior al plazo todavía está vigente', function () use ($hoy) {
    expect(EstadoKitEpp::desde(Carbon::parse('2026-04-16'), 6, null, $hoy()))
        ->toBe(EstadoKitEpp::VIGENTE);
});

// ── El respaldo de la vida útil del equipo ────────────────────────────

test('sin frecuencia del puesto manda la vida útil del equipo', function () use ($hoy) {
    // El puesto no fijó frecuencia; el casco dura 12 meses y se entregó hace 13.
    expect(EstadoKitEpp::desde(Carbon::parse('2025-09-15'), null, 12, $hoy()))
        ->toBe(EstadoKitEpp::POR_REPONER);

    // Mismo equipo entregado hace 3 meses: vigente.
    expect(EstadoKitEpp::desde(Carbon::parse('2026-07-15'), null, 12, $hoy()))
        ->toBe(EstadoKitEpp::VIGENTE);
});

test('la frecuencia del puesto manda sobre la vida útil del equipo', function () use ($hoy) {
    // El equipo dura 24 meses, pero este puesto lo repone cada 3: entregado
    // hace 4, toca. Es el caso del puesto más exigente que el catálogo.
    expect(EstadoKitEpp::desde(Carbon::parse('2026-06-15'), 3, 24, $hoy()))
        ->toBe(EstadoKitEpp::POR_REPONER);
});

// ── Sin plazo por ningún lado ─────────────────────────────────────────

test('entregado y sin plazo fijado queda vigente, no por reponer', function () use ($hoy) {
    // Decir «por reponer» aquí sería inventar una periodicidad que nadie fijó.
    expect(EstadoKitEpp::desde(Carbon::parse('2020-01-01'), null, null, $hoy()))
        ->toBe(EstadoKitEpp::VIGENTE);
});

test('un plazo de cero o negativo se trata como si no hubiera plazo', function () use ($hoy) {
    expect(EstadoKitEpp::desde(Carbon::parse('2020-01-01'), 0, null, $hoy()))
        ->toBe(EstadoKitEpp::VIGENTE);
    expect(EstadoKitEpp::desde(Carbon::parse('2020-01-01'), -3, null, $hoy()))
        ->toBe(EstadoKitEpp::VIGENTE);
});

// ── La fecha que viaja a la pantalla ──────────────────────────────────

test('reponerDesde dice el día en que toca', function () {
    expect(EstadoKitEpp::reponerDesde(Carbon::parse('2026-04-15'), 6, null)->toDateString())
        ->toBe('2026-10-15');

    // Con el respaldo de la vida útil.
    expect(EstadoKitEpp::reponerDesde(Carbon::parse('2026-01-31'), null, 1)->toDateString())
        ->toBe('2026-02-28');
});

test('el plazo no desborda al mes siguiente cuando el día no existe', function () {
    // `addMonths` de Carbon desborda: 31 de enero más un mes daba el 3 de
    // marzo, y en una reposición mensual ese corrimiento se acumula. Con
    // `addMonthsNoOverflow` el plazo se queda en el último día del mes.
    expect(EstadoKitEpp::reponerDesde(Carbon::parse('2026-01-31'), 1, null)->toDateString())
        ->toBe('2026-02-28');
    expect(EstadoKitEpp::reponerDesde(Carbon::parse('2026-05-31'), 1, null)->toDateString())
        ->toBe('2026-06-30');
    // Y donde el día sí existe, se comporta como se espera.
    expect(EstadoKitEpp::reponerDesde(Carbon::parse('2026-01-15'), 1, null)->toDateString())
        ->toBe('2026-02-15');
});

test('reponerDesde es nulo si no hay con qué calcularlo', function () {
    expect(EstadoKitEpp::reponerDesde(null, 6, 12))->toBeNull();
    expect(EstadoKitEpp::reponerDesde(Carbon::parse('2026-04-15'), null, null))->toBeNull();
    expect(EstadoKitEpp::reponerDesde(Carbon::parse('2026-04-15'), 0, null))->toBeNull();
});

// ── Lo que consume el formulario ──────────────────────────────────────

test('se premarcan lo pendiente y lo que toca reponer, no lo vigente', function () {
    expect(EstadoKitEpp::PENDIENTE->toca())->toBeTrue();
    expect(EstadoKitEpp::POR_REPONER->toca())->toBeTrue();
    expect(EstadoKitEpp::VIGENTE->toca())->toBeFalse();
});

test('la hora del día no mueve el plazo', function () {
    // El plazo es un día, no un instante: consultar a las 23:59 del día en que
    // toca tiene que dar lo mismo que a las 00:01.
    foreach (['2026-10-15 00:00:01', '2026-10-15 23:59:59'] as $momento) {
        expect(EstadoKitEpp::desde(Carbon::parse('2026-04-15'), 6, null, Carbon::parse($momento)))
            ->toBe(EstadoKitEpp::POR_REPONER);
    }
});

test('los tres estados tienen etiqueta', function () {
    foreach (EstadoKitEpp::cases() as $estado) {
        expect($estado->etiqueta())->toBeString()->not->toBeEmpty();
    }
});
