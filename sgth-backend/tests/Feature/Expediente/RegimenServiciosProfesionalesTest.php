<?php

use App\Enums\RegimenLaboral;
use App\Enums\TipoNombramiento;
use App\Services\Asistencia\PeriodoVacacionService;

/**
 * `servicios_profesionales` se agregó como tercer régimen el 2026-08-29.
 * Antes caía en `codigo_trabajo`, y de ahí heredaba prestaciones que a un
 * contrato civil no le corresponden.
 */
test('el enum tiene los tres regímenes con su etiqueta', function () {
    expect(RegimenLaboral::cases())->toHaveCount(3)
        ->and(RegimenLaboral::SERVICIOS_PROFESIONALES->value)->toBe('servicios_profesionales')
        ->and(RegimenLaboral::SERVICIOS_PROFESIONALES->etiqueta())->toBe('Servicios Profesionales');
});

test('servicios profesionales no es una relación laboral de dependencia', function () {
    expect(RegimenLaboral::SERVICIOS_PROFESIONALES->esRelacionLaboral())->toBeFalse()
        ->and(RegimenLaboral::LOSEP->esRelacionLaboral())->toBeTrue()
        ->and(RegimenLaboral::CODIGO_TRABAJO->esRelacionLaboral())->toBeTrue();
});

test('no genera vacaciones, aunque acumule antigüedad', function () {
    $servicio = app(PeriodoVacacionService::class);

    // Con la fórmula del Código del Trabajo, veinte años daban 30 días.
    expect($servicio->calcularDiasGenerados('servicios_profesionales', 20))->toBe(0.0)
        ->and($servicio->calcularDiasGenerados('servicios_profesionales', 0))->toBe(0.0);
});

test('los otros dos regímenes siguen generando lo de siempre', function () {
    $servicio = app(PeriodoVacacionService::class);

    expect($servicio->calcularDiasGenerados('losep', 20))->toBe(30.0)
        ->and($servicio->calcularDiasGenerados('losep', 0))->toBe(15.0)
        ->and($servicio->calcularDiasGenerados('codigo_trabajo', 1))->toBe(15.0)
        ->and($servicio->calcularDiasGenerados('codigo_trabajo', 20))->toBe(30.0);
});

test('solo LOSEP accede al módulo de permisos', function () {
    // La comprobación se hace en positivo justamente para que un régimen nuevo
    // no entre por omisión, que es lo que pasaba al descartar solo el CT.
    expect(RegimenLaboral::LOSEP->accedeAPermisos())->toBeTrue()
        ->and(RegimenLaboral::CODIGO_TRABAJO->accedeAPermisos())->toBeFalse()
        ->and(RegimenLaboral::SERVICIOS_PROFESIONALES->accedeAPermisos())->toBeFalse();
});

test('el nombramiento de servicios profesionales ya no se liquida como obrero', function () {
    // `esLosep()` sigue diciendo que no es LOSEP —eso no cambió—, pero el
    // régimen que se guarda en el servidor ya es el suyo propio.
    expect(TipoNombramiento::SERVICIOS_PROFESIONALES->esLosep())->toBeFalse();
});
