<?php

/*
| Aprobar o rechazar una solicitud: una sola vez, y solo si está pendiente.
|
| `update()` aceptaba cualquier cambio de estado sobre cualquier solicitud. La
| única defensa contra el doble descuento era «el estado anterior no era
| aprobada», que no mira más atrás: aprobar → rechazar → aprobar descontaba dos
| veces, y rechazar una ya aprobada no devolvía nada. Tampoco había
| transacción ni bloqueo, así que un doble clic podía descontar dos veces sin
| pasar siquiera por el rechazo.
|
| Ahora una solicitud se resuelve desde PENDIENTE y en ningún otro caso. Deshacer
| una aprobada —con la devolución de sus días— es otra operación, la anulación,
| y todavía no existe.
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
        'estado'           => 'pendiente',
        'motivo'           => $motivo,
    ]);

    $this->resolver = fn (Vacacion $v, string $estado) =>
        $this->putJson("/api/v1/asistencia/vacaciones/{$v->id}", ['estado' => $estado]);
});

test('aprobar, rechazar y volver a aprobar ya no descuenta dos veces', function () {
    $periodo = ($this->periodo)(30, 0);
    $vacacion = ($this->vacacion)(5);

    ($this->resolver)($vacacion, 'aprobada')->assertOk();
    ($this->resolver)($vacacion, 'rechazada')->assertStatus(422);
    ($this->resolver)($vacacion, 'aprobada')->assertStatus(422);

    $periodo->refresh();
    expect((float) $periodo->dias_utilizados)->toBe(5.0)
        ->and((float) $periodo->dias_saldo)->toBe(25.0)
        ->and($vacacion->fresh()->estado)->toBe('aprobada');
});

test('una aprobación repetida se rechaza con el motivo', function () {
    ($this->periodo)(30, 0);
    $vacacion = ($this->vacacion)(5);

    ($this->resolver)($vacacion, 'aprobada')->assertOk();
    $respuesta = ($this->resolver)($vacacion, 'aprobada');

    $respuesta->assertStatus(422);
    expect($respuesta->json('mensaje'))->toContain('ya fue resuelta');
});

test('rechazar no descuenta y cierra la solicitud', function () {
    $periodo = ($this->periodo)(30, 0);
    $vacacion = ($this->vacacion)(5);

    ($this->resolver)($vacacion, 'rechazada')->assertOk();
    ($this->resolver)($vacacion, 'aprobada')->assertStatus(422);

    expect((float) $periodo->fresh()->dias_utilizados)->toBe(0.0)
        ->and($vacacion->fresh()->estado)->toBe('rechazada');
});

test('sin período abierto del año, aprobar se rechaza en vez de aprobar sin descontar', function () {
    $vacacion = ($this->vacacion)(5);

    $respuesta = ($this->resolver)($vacacion, 'aprobada');

    $respuesta->assertStatus(422);
    expect($respuesta->json('mensaje'))->toContain('período de vacaciones abierto')
        ->and($vacacion->fresh()->estado)->toBe('pendiente');
});

test('dos pendientes que juntas superan el saldo: la segunda no se aprueba', function () {
    // Una pendiente no reserva días. Las dos pasaron el control de saldo al
    // registrarse, cada una por su lado; el saldo se vuelve a mirar al aprobar.
    $periodo = ($this->periodo)(30, 22);
    $primera = ($this->vacacion)(5);
    $segunda = ($this->vacacion)(5);

    ($this->resolver)($primera, 'aprobada')->assertOk();
    $respuesta = ($this->resolver)($segunda, 'aprobada');

    $respuesta->assertStatus(422);
    expect($respuesta->json('mensaje'))->toContain('Saldo insuficiente')
        ->and((float) $periodo->fresh()->dias_utilizados)->toBe(27.0)
        ->and($segunda->fresh()->estado)->toBe('pendiente');
});

test('un motivo que no descuenta se aprueba sin período', function () {
    $vacacion = ($this->vacacion)(3, 'matrimonio');

    ($this->resolver)($vacacion, 'aprobada')->assertOk();

    expect($vacacion->fresh()->estado)->toBe('aprobada')
        ->and(PeriodoVacacion::count())->toBe(0);
});
