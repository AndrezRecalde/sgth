<?php

/*
| Confirmar con el saldo que hay de verdad, y que dos operaciones sobre el mismo
| permiso no se pisen.
|
| - Un permiso pendiente no reserva horas. Dos que pasaron el control de saldo
|   al registrarse, cada uno por su lado, se confirmaban los dos aunque juntos
|   lo superaran: `descontarDias()` recorta a cero y el período quedaba con más
|   días usados que generados (reproducido en la auditoría del módulo).
| - Un permiso con el plazo vencido se podía confirmar mientras el job no lo
|   hubiera marcado todavía.
| - Anular y el job de vencimiento leían y guardaban sin bloquear la fila, así
|   que podían escribir encima de una confirmación simultánea.
|
| La simultaneidad en sí no se puede reproducir en un test de un solo proceso.
| Lo que se comprueba aquí es la regla que la hace inofensiva: cada operación
| mira el estado real antes de escribir.
*/

use App\Enums\EstadoPermiso;
use App\Enums\RegimenLaboral;
use App\Jobs\Asistencia\VencerPermisosJob;
use App\Models\Asistencia\PeriodoVacacion;
use App\Models\Asistencia\PermisoServidor;
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
    PermisoServidor::unguard();

    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $unidad = unidadDePrueba();

    $this->servidor = Servidor::create([
        'cedula'                   => '0800004001',
        'nombre'                   => 'Carla',
        'apellido'                 => 'Confirma',
        'puesto_id'                => puestoDePrueba($unidad)->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral'          => RegimenLaboral::LOSEP,
        'estado'                   => true,
    ]);

    $this->usuario = function (string $rol) {
        $usuario = User::create([
            'email'        => uniqid('conf').'@example.com',
            'usuario_ti'   => uniqid('conf'),
            'password'     => bcrypt('123456'),
            'primer_login' => false,
        ]);
        $usuario->assignRole($rol);

        return $usuario;
    };

    $this->uath      = ($this->usuario)('admin-uath');
    $this->recepcion = ($this->usuario)('recepcion');

    $this->periodo = fn (float $generados, float $utilizados) => PeriodoVacacion::create([
        'servidor_id'          => $this->servidor->id,
        'anio'                 => now()->year,
        'fecha_inicio_periodo' => Carbon::create(now()->year, 1, 1),
        'fecha_fin_periodo'    => Carbon::create(now()->year, 12, 31),
        'regimen'              => 'losep',
        'anios_antiguedad'     => 3,
        'dias_generados'       => $generados,
        'dias_utilizados'      => $utilizados,
        'dias_saldo'           => $generados - $utilizados,
        'saldo_acumulado'      => $generados - $utilizados,
        'estado'               => 'abierto',
    ]);

    // Días hábiles próximos, uno por permiso: el tope diario de 4 horas
    // impediría poner dos permisos de 4 horas el mismo día.
    $this->diaHabil = function (int $cual): Carbon {
        $fecha = Carbon::today();
        $vistos = 0;
        while ($vistos < $cual) {
            $fecha->addDay();
            if (! $fecha->isWeekend()) {
                $vistos++;
            }
        }

        return $fecha;
    };

    $this->registrar = fn (int $diaHabil, array $extra = []) => $this->actingAs($this->uath, 'sanctum')
        ->postJson('/api/v1/asistencia/permisos', array_merge([
            'servidor_id' => $this->servidor->id,
            'tipo'        => 'personal',
            'fecha'       => ($this->diaHabil)($diaHabil)->toDateString(),
            'hora_inicio' => '08:00',
            'hora_fin'    => '12:00',
        ], $extra))->assertCreated()->json('datos');

    $this->confirmar = fn (array $permiso) => $this->actingAs($this->recepcion, 'sanctum')
        ->postJson("/api/v1/asistencia/permisos/confirmar/{$permiso['folio']}");
});

test('dos pendientes que juntas superan el saldo: la segunda confirmación se rechaza', function () {
    $periodo = ($this->periodo)(10, 9.4); // quedan 0,6 días

    // 4 horas son 0,5 días: cada uno cabe solo, los dos juntos no.
    $primero = ($this->registrar)(1);
    $segundo = ($this->registrar)(2);

    ($this->confirmar)($primero)->assertOk();
    $respuesta = ($this->confirmar)($segundo);

    $respuesta->assertStatus(422);
    expect($respuesta->json('mensaje'))->toContain('Saldo de vacaciones insuficiente')
        ->toContain($segundo['folio']);

    $periodo->refresh();
    expect((float) $periodo->dias_utilizados)->toBe(9.9)
        ->and((float) $periodo->dias_utilizados)->toBeLessThanOrEqual((float) $periodo->dias_generados)
        ->and(PermisoServidor::find($segundo['id'])->estado)->toBe(EstadoPermiso::PENDIENTE);
});

test('un permiso con el plazo vencido ya no se confirma, aunque el job no lo haya marcado', function () {
    $periodo = ($this->periodo)(15, 0);
    $permiso = ($this->registrar)(1);

    // Vencido hace una hora; el job de las 06:15 todavía no pasó.
    PermisoServidor::whereKey($permiso['id'])->update(['vence_en' => now()->subHour()]);

    $respuesta = ($this->confirmar)($permiso);

    $respuesta->assertStatus(422);
    expect($respuesta->json('mensaje'))->toContain('venció')
        ->and((float) $periodo->fresh()->dias_utilizados)->toBe(0.0);

    // Y el job, cuando pase, hace lo que corresponde.
    (new VencerPermisosJob)->handle();
    expect(PermisoServidor::find($permiso['id'])->estado)->toBe(EstadoPermiso::FALTA_INJUSTIFICADA);
});

test('anular un pendiente lo anula y deja constancia de quién', function () {
    ($this->periodo)(15, 0);
    $permiso = ($this->registrar)(1);

    $this->actingAs($this->uath, 'sanctum')
        ->putJson("/api/v1/asistencia/permisos/{$permiso['id']}/anular")
        ->assertOk();

    $anulado = PermisoServidor::find($permiso['id']);
    expect($anulado->estado)->toBe(EstadoPermiso::ANULADO)
        ->and($anulado->anulado_por)->toBe($this->uath->id)
        ->and($anulado->anulado_en)->not->toBeNull();
});

test('un permiso ya confirmado no se anula, y el saldo descontado se queda donde está', function () {
    $periodo = ($this->periodo)(15, 0);
    $permiso = ($this->registrar)(1);
    ($this->confirmar)($permiso)->assertOk();

    $respuesta = $this->actingAs($this->uath, 'sanctum')
        ->putJson("/api/v1/asistencia/permisos/{$permiso['id']}/anular");

    // 422 como el resto de reglas de negocio; antes respondía 400.
    $respuesta->assertStatus(422);
    expect($respuesta->json('mensaje'))->toContain('PENDIENTE')
        ->and(PermisoServidor::find($permiso['id'])->estado)->toBe(EstadoPermiso::ACTIVO)
        ->and((float) $periodo->fresh()->dias_utilizados)->toBe(0.5);
});

test('el job solo marca los pendientes vencidos', function () {
    ($this->periodo)(15, 0);
    $vencido   = ($this->registrar)(1, ['tipo' => 'oficial', 'observacion' => 'Comisión']);
    $vigente   = ($this->registrar)(2, ['tipo' => 'oficial', 'observacion' => 'Comisión']);
    $confirmado = ($this->registrar)(3, ['tipo' => 'oficial', 'observacion' => 'Comisión']);
    ($this->confirmar)($confirmado)->assertOk();

    // Los dos con el plazo pasado; solo uno sigue pendiente.
    PermisoServidor::whereKey([$vencido['id'], $confirmado['id']])
        ->update(['vence_en' => now()->subDay()]);

    (new VencerPermisosJob)->handle();

    expect(PermisoServidor::find($vencido['id'])->estado)->toBe(EstadoPermiso::FALTA_INJUSTIFICADA)
        ->and(PermisoServidor::find($vigente['id'])->estado)->toBe(EstadoPermiso::PENDIENTE)
        ->and(PermisoServidor::find($confirmado['id'])->estado)->toBe(EstadoPermiso::ACTIVO);
});
