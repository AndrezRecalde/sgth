<?php

/*
| Talento Humano registra en ventanilla la solicitud que le llega firmada: una
| sola vez, y solo si está AUTORIZADA. Es el momento en que los días salen del
| saldo.
|
| `update()` aceptaba cualquier cambio de estado sobre cualquier solicitud. La
| única defensa contra el doble descuento era «el estado anterior no era
| aprobada», que no mira más atrás: aprobar → rechazar → aprobar descontaba dos
| veces, y rechazar una ya aprobada no devolvía nada. Tampoco había
| transacción ni bloqueo, así que un doble clic podía descontar dos veces sin
| pasar siquiera por el rechazo.
|
| Desde el 2026-11-24 ya no hay «rechazar»: la única salida hacia atrás es
| anular, siempre con motivo, y devolviendo los días si hiciera falta.
*/

use App\Enums\RegimenLaboral;
use App\Models\Asistencia\PeriodoVacacion;
use App\Models\Asistencia\Vacacion;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();
    Vacacion::unguard();

    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $unidad = unidadDePrueba();

    $this->servidor = Servidor::create([
        'cedula'                       => '0800000401',
        'nombre'                       => 'Pablo',
        'apellido'                     => 'Resolución',
        'puesto_id'                    => puestoDePrueba($unidad)->id,
        'unidad_administrativa_id'     => $unidad->id,
        'regimen_laboral'              => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion'    => now()->subYears(8),
        'fecha_ingreso_sector_publico' => now()->subYears(8),
        'estado'                       => true,
    ]);

    $uath = User::create([
        'email'        => 'uath-resolucion@example.com',
        'usuario_ti'   => 'uathres',
        'password'     => bcrypt('123456'),
        'primer_login' => false,
    ]);
    $uath->assignRole('admin-uath');
    $this->actingAs($uath, 'sanctum');

    // El año del período se toma de la fecha de la vacación, no de hoy: a
    // finales de diciembre, «dentro de una semana» ya es el año siguiente.
    $this->inicio = now()->addDays(7)->startOfDay();

    $this->periodo = fn (float $generados, float $utilizados) => PeriodoVacacion::create([
        'servidor_id'          => $this->servidor->id,
        'anio'                 => $this->inicio->year,
        'fecha_inicio_periodo' => Carbon::create($this->inicio->year, 1, 1),
        'fecha_fin_periodo'    => Carbon::create($this->inicio->year, 12, 31),
        'regimen'              => 'losep',
        'anios_antiguedad'     => 8,
        'dias_generados'       => $generados,
        'dias_utilizados'      => $utilizados,
        'dias_saldo'           => $generados - $utilizados,
        'saldo_acumulado'      => $generados - $utilizados,
        'estado'               => 'abierto',
    ]);

    $this->vacacion = fn (float $dias, string $motivo = 'vacaciones_anuales') => Vacacion::create([
        'servidor_id'      => $this->servidor->id,
        'fecha_inicio'     => $this->inicio->toDateString(),
        'fecha_fin'        => $this->inicio->copy()->addDays((int) $dias - 1)->toDateString(),
        'dias_solicitados' => $dias,
        'tipo_dias'        => 'habiles',
        'estado'           => 'autorizada',
        'motivo'           => $motivo,
    ]);

    $this->registrar = fn (Vacacion $v) =>
        $this->postJson("/api/v1/asistencia/vacaciones/{$v->id}/registrar");

    $this->anular = fn (Vacacion $v) => $this->postJson(
        "/api/v1/asistencia/vacaciones/{$v->id}/anular",
        ['motivo' => 'El papel llegó sin la firma del jefe inmediato.'],
    );
});

test('registrar dos veces no descuenta dos veces', function () {
    $periodo = ($this->periodo)(30, 0);
    $vacacion = ($this->vacacion)(5);

    ($this->registrar)($vacacion)->assertOk();
    ($this->registrar)($vacacion)->assertStatus(422);

    $periodo->refresh();
    expect((float) $periodo->dias_utilizados)->toBe(5.0)
        ->and((float) $periodo->dias_saldo)->toBe(25.0)
        ->and($vacacion->fresh()->estado)->toBe('registrada');
});

test('un registro repetido se rechaza diciendo en qué estado está', function () {
    ($this->periodo)(30, 0);
    $vacacion = ($this->vacacion)(5);

    ($this->registrar)($vacacion)->assertOk();
    $respuesta = ($this->registrar)($vacacion);

    $respuesta->assertStatus(422);
    expect($respuesta->json('mensaje'))->toContain('registrada');
});

test('una anulada ya no se registra', function () {
    // Anular es la única salida hacia atrás: sustituye al rechazo, y una
    // solicitud anulada no vuelve a entrar al circuito.
    $periodo = ($this->periodo)(30, 0);
    $vacacion = ($this->vacacion)(5);

    ($this->anular)($vacacion)->assertOk();
    ($this->registrar)($vacacion)->assertStatus(422);

    expect((float) $periodo->fresh()->dias_utilizados)->toBe(0.0)
        ->and($vacacion->fresh()->estado)->toBe('anulada');
});

test('sin ningún período abierto se rechaza, en vez de registrar sin descontar', function () {
    $vacacion = ($this->vacacion)(5);

    $respuesta = ($this->registrar)($vacacion);

    $respuesta->assertStatus(422);
    expect($respuesta->json('mensaje'))->toContain('período de vacaciones abierto')
        ->and($vacacion->fresh()->estado)->toBe('autorizada');
});

test('dos autorizadas que juntas superan el saldo: la segunda no se registra', function () {
    // Una autorizada no reserva días: el jefe las concedió, pero hasta la
    // ventanilla no sale nada del saldo. Las dos pasaron el control al
    // registrarse la solicitud, cada una por su lado; se vuelve a mirar aquí.
    $periodo = ($this->periodo)(30, 22);
    $primera = ($this->vacacion)(5);
    $segunda = ($this->vacacion)(5);

    ($this->registrar)($primera)->assertOk();
    $respuesta = ($this->registrar)($segunda);

    $respuesta->assertStatus(422);
    expect($respuesta->json('mensaje'))->toContain('Saldo insuficiente')
        ->and((float) $periodo->fresh()->dias_utilizados)->toBe(27.0)
        ->and($segunda->fresh()->estado)->toBe('autorizada');
});

test('un motivo que no descuenta se registra sin período', function () {
    $vacacion = ($this->vacacion)(3, 'matrimonio');

    ($this->registrar)($vacacion)->assertOk();

    expect($vacacion->fresh()->estado)->toBe('registrada')
        ->and(PeriodoVacacion::count())->toBe(0);
});
