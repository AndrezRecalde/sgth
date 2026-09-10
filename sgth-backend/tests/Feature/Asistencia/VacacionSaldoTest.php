<?php

/*
| El saldo de vacaciones sale de los períodos abiertos, y de nada más.
|
| `calcularSaldoActual()` tenía un respaldo legacy —días del motor por años de
| antigüedad, menos lo gozado— que entraba cuando la suma de los períodos era
| cero. Cero es también el saldo de quien ya gozó todo: a un LOSEP de ocho años
| que había agotado sus 20 días le devolvía 160, y podía volver a pedir. El
| mismo número inflado llegaba al KPI del dashboard y al autoservicio.
*/

use App\Enums\RegimenLaboral;
use App\Exceptions\ReglaNegocioException;
use App\Models\Asistencia\PeriodoVacacion;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Asistencia\VacacionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();

    $unidad = unidadDePrueba();

    $this->servidor = Servidor::create([
        'cedula'                       => '0800000301',
        'nombre'                       => 'Rosa',
        'apellido'                     => 'Saldo',
        'puesto_id'                    => puestoDePrueba($unidad)->id,
        'unidad_administrativa_id'     => $unidad->id,
        'regimen_laboral'              => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion'    => now()->subYears(8),
        'fecha_ingreso_sector_publico' => now()->subYears(8),
        'estado'                       => true,
    ]);

    $this->periodo = fn (int $anio, float $generados, float $utilizados, string $estado = 'abierto') => PeriodoVacacion::create([
        'servidor_id'          => $this->servidor->id,
        'anio'                 => $anio,
        'fecha_inicio_periodo' => Carbon::create($anio, 1, 1),
        'fecha_fin_periodo'    => Carbon::create($anio, 12, 31),
        'regimen'              => 'losep',
        'anios_antiguedad'     => 8,
        'dias_generados'       => $generados,
        'dias_utilizados'      => $utilizados,
        'dias_saldo'           => $generados - $utilizados,
        'saldo_acumulado'      => $generados - $utilizados,
        'estado'               => $estado,
    ]);

    $lunes = now()->next(Carbon::MONDAY);
    $this->semana = [
        'fecha_inicio' => $lunes->toDateString(),
        'fecha_fin'    => $lunes->copy()->addDays(4)->toDateString(),
    ];

    $this->servicio = new VacacionService();
});

test('quien agotó sus días tiene saldo cero, no el de toda su carrera', function () {
    ($this->periodo)(now()->year, 20, 20);

    expect($this->servicio->calcularSaldoActual($this->servidor->id))->toBe(0.0);
});

test('y con el saldo agotado no puede volver a pedir', function () {
    ($this->periodo)(now()->year, 20, 20);

    expect(fn () => $this->servicio->solicitar(
        $this->semana + ['motivo' => 'vacaciones_anuales'],
        $this->servidor->id
    ))->toThrow(ReglaNegocioException::class, 'Saldo insuficiente');
});

test('el saldo es la suma de los períodos abiertos', function () {
    ($this->periodo)(now()->year - 2, 20, 13, 'cerrado');
    ($this->periodo)(now()->year - 1, 20, 10);
    ($this->periodo)(now()->year, 20, 5);

    expect($this->servicio->calcularSaldoActual($this->servidor->id))->toBe(25.0);
});

test('sin período abierto, pedir vacaciones se rechaza diciendo por qué', function () {
    expect($this->servicio->calcularSaldoActual($this->servidor->id))->toBe(0.0);

    // No «saldo insuficiente»: lo que falta es generar el período, y el
    // mensaje tiene que mandar a quien lo lee al sitio donde se arregla.
    expect(fn () => $this->servicio->solicitar(
        $this->semana + ['motivo' => 'vacaciones_anuales'],
        $this->servidor->id
    ))->toThrow(ReglaNegocioException::class, 'período de vacaciones abierto');
});

test('un motivo que no descuenta no necesita período', function () {
    $vacacion = $this->servicio->solicitar(
        $this->semana + ['motivo' => 'matrimonio'],
        $this->servidor->id
    );

    expect($vacacion->estado)->toBe('pendiente');
});

test('el saldo insuficiente llega como 422 con el motivo, no como 500', function () {
    ($this->periodo)(now()->year, 20, 18);

    $uath = User::create([
        'email'        => 'uath-saldo@example.com',
        'usuario_ti'   => 'uathsaldo',
        'password'     => bcrypt('123456'),
        'primer_login' => false,
    ]);
    $this->seed(\Database\Seeders\RolPermisoSeeder::class);
    $uath->assignRole('admin-uath');

    $respuesta = $this->actingAs($uath, 'sanctum')->postJson('/api/v1/asistencia/vacaciones', $this->semana + [
        'servidor_id'      => $this->servidor->id,
        'motivo'           => 'vacaciones_anuales',
        'dias_solicitados' => 5,
        'tipo_dias'        => 'habiles',
    ]);

    $respuesta->assertStatus(422);
    expect($respuesta->json('mensaje'))->toContain('Saldo insuficiente');
});
