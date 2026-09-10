<?php

use App\Enums\RegimenLaboral;
use App\Exceptions\ReglaNegocioException;
use App\Models\Asistencia\PeriodoVacacion;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Services\Asistencia\VacacionService;
use Carbon\Carbon;
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
 * Y lo hacía con consecuencia: `calcularSaldoActual()` caía entonces a un
 * cálculo legacy que multiplicaba los días del motor por los años de
 * antigüedad, y un servicios profesionales terminaba con un saldo inventado.
 * Ese cálculo ya se retiró —el saldo sale solo de los períodos—, pero la regla
 * del régimen se sigue comprobando: aunque el contratado civil arrastre un
 * período de cuando era de otro régimen, no tiene saldo que gozar.
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

    // Un período abierto con 15 días, igual para los dos regímenes: lo único
    // que cambia entre los tests es quién es el titular.
    $this->darPeriodo = fn (Servidor $servidor) => PeriodoVacacion::create([
        'servidor_id'          => $servidor->id,
        'anio'                 => now()->year,
        'fecha_inicio_periodo' => Carbon::create(now()->year, 1, 1),
        'fecha_fin_periodo'    => Carbon::create(now()->year, 12, 31),
        'regimen'              => 'losep',
        'anios_antiguedad'     => 4,
        'dias_generados'       => 15,
        'dias_utilizados'      => 0,
        'dias_saldo'           => 15,
        'saldo_acumulado'      => 15,
        'estado'               => 'abierto',
    ]);

    $this->servicio = new VacacionService();
});

test('un servicios profesionales no tiene saldo de vacaciones', function () {
    $servidor = ($this->crearServidor)(RegimenLaboral::SERVICIOS_PROFESIONALES, '0800000101');
    ($this->darPeriodo)($servidor);

    expect($this->servicio->calcularSaldoActual($servidor->id))->toBe(0.0);
});

test('un LOSEP con el mismo período sí tiene saldo', function () {
    // Control: si el saldo diera cero para todos, el test anterior no probaría
    // nada.
    $servidor = ($this->crearServidor)(RegimenLaboral::LOSEP, '0800000102');
    ($this->darPeriodo)($servidor);

    expect($this->servicio->calcularSaldoActual($servidor->id))->toBe(15.0);
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
