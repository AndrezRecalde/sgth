<?php

use App\Enums\RegimenLaboral;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Services\Asistencia\VacacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

/**
 * Un contrato civil no genera vacaciones, y el módulo tiene que decirlo.
 *
 * `obtenerMotor()` comprobaba solo si el servidor era del Código del Trabajo y
 * devolvía el motor LOSEP para todo lo demás. Con el régimen de servicios
 * profesionales —agregado el 2026-08-29— eso significaba calcularle vacaciones
 * con la escala LOSEP.
 *
 * Y lo hacía con consecuencia: `calcularSaldoActual()` cae a un cálculo legacy
 * cuando no hay saldo en los períodos, y ese camino multiplica los días del
 * motor por los años de antigüedad. Un servicios profesionales terminaba con
 * un saldo positivo inventado.
 */
beforeEach(function () {
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();

    $unidad = unidadDePrueba();
    $puesto = puestoDePrueba($unidad);

    $this->crearServidor = fn (RegimenLaboral $regimen, string $cedula) => Servidor::create([
        'cedula'                       => $cedula,
        'nombre'                       => 'Prueba',
        'apellido'                     => 'Régimen',
        'puesto_id'                    => $puesto->id,
        'unidad_administrativa_id'     => $unidad->id,
        'regimen_laboral'              => $regimen,
        'fecha_ingreso_institucion'    => now()->subYears(4),
        'fecha_ingreso_sector_publico' => now()->subYears(4),
        'estado'                       => true,
    ]);

    $this->servicio = new VacacionService();
});

test('un servicios profesionales no tiene saldo de vacaciones', function () {
    $servidor = ($this->crearServidor)(RegimenLaboral::SERVICIOS_PROFESIONALES, '0800000101');

    // Sin períodos generados, el cálculo legacy le habría dado 15 días por
    // cada uno de sus 4 años de antigüedad.
    expect($this->servicio->calcularSaldoActual($servidor->id))->toBe(0.0);
});

test('un LOSEP con la misma antigüedad sí tiene saldo', function () {
    // Control: si el saldo diera cero para todos, el test anterior no probaría
    // nada.
    $servidor = ($this->crearServidor)(RegimenLaboral::LOSEP, '0800000102');

    expect($this->servicio->calcularSaldoActual($servidor->id))->toBeGreaterThan(0.0);
});

test('pedir vacaciones con un contrato civil se rechaza por el régimen', function () {
    $servidor = ($this->crearServidor)(RegimenLaboral::SERVICIOS_PROFESIONALES, '0800000103');

    expect(fn () => $this->servicio->solicitar([
        'motivo'       => 'vacaciones',
        'fecha_inicio' => now()->addWeek()->toDateString(),
        'fecha_fin'    => now()->addWeek()->addDays(3)->toDateString(),
    ], $servidor->id))
        // El mensaje nombra el motivo real. Si se cortara por saldo, diría
        // «no tiene días suficientes» y nadie entendería por qué.
        ->toThrow(ReglaNegocioException::class, 'no genera vacaciones');
});

test('el motor no se elige por descarte', function () {
    $sp = ($this->crearServidor)(RegimenLaboral::SERVICIOS_PROFESIONALES, '0800000104');

    expect(fn () => $this->servicio->obtenerMotor($sp))
        ->toThrow(ReglaNegocioException::class);
});
