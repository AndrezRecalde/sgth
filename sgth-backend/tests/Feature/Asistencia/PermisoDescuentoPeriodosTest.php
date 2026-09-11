<?php

/*
| El permiso personal descuenta del período más antiguo al más nuevo.
|
| Antes salía solo del período del año del permiso, mientras que una vacación
| reparte entre períodos desde que se corrigió su descuento. Con el período del
| año vacío, el permiso se rechazaba aunque hubiera saldo de años anteriores, y
| esos días viejos seguían acercándose al tope mientras se gastaban los nuevos.
|
| Cada tramo queda en `permiso_descuentos`: revertir la confirmación lo devuelve
| a su período sin volver a calcular las horas.
*/

use App\Enums\EstadoPermiso;
use App\Enums\RegimenLaboral;
use App\Models\Asistencia\PeriodoVacacion;
use App\Models\Asistencia\PermisoDescuento;
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
        'cedula'                   => '0800005001',
        'nombre'                   => 'Paula',
        'apellido'                 => 'Periodos',
        'puesto_id'                => puestoDePrueba($unidad)->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral'          => RegimenLaboral::LOSEP,
        'estado'                   => true,
    ]);

    $this->usuario = function (string $rol) {
        $usuario = User::create([
            'email'        => uniqid('per').'@example.com',
            'usuario_ti'   => uniqid('per'),
            'password'     => bcrypt('123456'),
            'primer_login' => false,
        ]);
        $usuario->assignRole($rol);

        return $usuario;
    };

    $this->uath      = ($this->usuario)('admin-uath');
    $this->recepcion = ($this->usuario)('recepcion');

    $this->periodo = fn (int $anio, float $generados, float $utilizados) => PeriodoVacacion::create([
        'servidor_id'          => $this->servidor->id,
        'anio'                 => $anio,
        'fecha_inicio_periodo' => Carbon::create($anio, 1, 1),
        'fecha_fin_periodo'    => Carbon::create($anio, 12, 31),
        'regimen'              => 'losep',
        'anios_antiguedad'     => 3,
        'dias_generados'       => $generados,
        'dias_utilizados'      => $utilizados,
        'dias_saldo'           => $generados - $utilizados,
        'saldo_acumulado'      => $generados - $utilizados,
        'estado'               => 'abierto',
    ]);

    $this->anio = now()->year;

    // Un día hábil próximo: el permiso personal no se registra en fin de semana.
    $this->fecha = Carbon::today()->addDay();
    while ($this->fecha->isWeekend()) {
        $this->fecha->addDay();
    }

    // 4 horas son 0,5 días.
    $this->registrar = fn () => $this->actingAs($this->uath, 'sanctum')
        ->postJson('/api/v1/asistencia/permisos', [
            'servidor_id' => $this->servidor->id,
            'tipo'        => 'personal',
            'fecha'       => $this->fecha->toDateString(),
            'hora_inicio' => '08:00',
            'hora_fin'    => '12:00',
        ]);

    $this->confirmar = fn (string $folio) => $this->actingAs($this->recepcion, 'sanctum')
        ->postJson("/api/v1/asistencia/permisos/confirmar/{$folio}");

    $this->revertir = fn (int $id) => $this->actingAs($this->uath, 'sanctum')
        ->postJson("/api/v1/asistencia/permisos/{$id}/revertir-confirmacion", [
            'motivo' => 'Recepción confirmó el folio equivocado.',
        ]);
});

test('con el período del año agotado, el permiso sale del saldo de años anteriores', function () {
    $anterior = ($this->periodo)($this->anio - 1, 15, 10); // quedan 5
    $actual   = ($this->periodo)($this->anio, 15, 15);     // quedan 0

    $permiso = ($this->registrar)()->assertCreated()->json('datos');

    ($this->confirmar)($permiso['folio'])->assertOk();

    expect((float) $anterior->fresh()->dias_utilizados)->toBe(10.5)
        ->and((float) $actual->fresh()->dias_utilizados)->toBe(15.0);

    $tramos = PermisoDescuento::where('permiso_servidor_id', $permiso['id'])->get();
    expect($tramos)->toHaveCount(1)
        ->and($tramos->first()->periodo_vacacion_id)->toBe($anterior->id)
        ->and($tramos->first()->dias)->toBe(0.5);
});

test('se gasta primero el saldo más antiguo, aunque el del año alcance', function () {
    $anterior = ($this->periodo)($this->anio - 1, 15, 14.75); // quedan 0,25
    $actual   = ($this->periodo)($this->anio, 15, 0);

    $permiso = ($this->registrar)()->assertCreated()->json('datos');

    ($this->confirmar)($permiso['folio'])->assertOk();

    // Medio día: 0,25 del período anterior, que lo deja en cero, y 0,25 del actual.
    expect((float) $anterior->fresh()->dias_saldo)->toBe(0.0)
        ->and((float) $actual->fresh()->dias_saldo)->toBe(14.75)
        ->and(PermisoDescuento::where('permiso_servidor_id', $permiso['id'])->pluck('dias')->all())
        ->toEqualCanonicalizing([0.25, 0.25]);
});

test('revertir devuelve cada tramo al período del que salió', function () {
    $anterior = ($this->periodo)($this->anio - 1, 15, 14.75);
    $actual   = ($this->periodo)($this->anio, 15, 0);

    $permiso = ($this->registrar)()->assertCreated()->json('datos');
    ($this->confirmar)($permiso['folio'])->assertOk();

    ($this->revertir)($permiso['id'])->assertOk();

    expect((float) $anterior->fresh()->dias_utilizados)->toBe(14.75)
        ->and((float) $actual->fresh()->dias_utilizados)->toBe(0.0)
        ->and(PermisoDescuento::where('permiso_servidor_id', $permiso['id'])->whereNull('devuelto_en')->count())
        ->toBe(0)
        ->and(PermisoServidor::find($permiso['id'])->estado)->toBe(EstadoPermiso::PENDIENTE);

    // El acumulado de cada período refleja lo devuelto.
    expect((float) $actual->fresh()->saldo_acumulado)->toBe(15.25);
});

test('un período generado por adelantado para el año siguiente no se toca', function () {
    ($this->periodo)($this->anio, 15, 15);        // quedan 0
    ($this->periodo)($this->anio + 1, 15, 0);     // días que aún no se ganan

    $respuesta = ($this->registrar)();

    $respuesta->assertStatus(422);
    expect($respuesta->json('mensaje'))->toContain('Saldo de vacaciones insuficiente');
});

test('si al confirmar ya no alcanza ningún período, se rechaza y no queda ningún tramo', function () {
    $anterior = ($this->periodo)($this->anio - 1, 15, 14.75);
    $actual   = ($this->periodo)($this->anio, 15, 0);

    $permiso = ($this->registrar)()->assertCreated()->json('datos');

    // Entre el registro y la confirmación, otra cosa gastó el saldo.
    $anterior->update(['dias_utilizados' => 15, 'dias_saldo' => 0]);
    $actual->update(['dias_utilizados' => 15, 'dias_saldo' => 0]);

    $respuesta = ($this->confirmar)($permiso['folio']);

    $respuesta->assertStatus(422);
    expect($respuesta->json('mensaje'))->toContain('Saldo de vacaciones insuficiente')
        ->toContain($permiso['folio'])
        ->and(PermisoDescuento::count())->toBe(0)
        ->and(PermisoServidor::find($permiso['id'])->estado)->toBe(EstadoPermiso::PENDIENTE);
});

test('revertir un permiso confirmado antes de que se anotaran los tramos lo devuelve al período de su año', function () {
    // Así quedaba un permiso confirmado con el descuento anterior: medio día
    // usado en el período de su año, y ninguna fila en `permiso_descuentos`.
    $actual = ($this->periodo)($this->anio, 15, 0.5);

    $permiso = PermisoServidor::create([
        'servidor_id'              => $this->servidor->id,
        'unidad_administrativa_id' => $this->servidor->unidad_administrativa_id,
        'tipo'                     => 'personal',
        'fecha'                    => $this->fecha->toDateString(),
        'hora_inicio'              => '08:00',
        'hora_fin'                 => '12:00',
        'estado'                   => EstadoPermiso::ACTIVO->value,
        'vence_en'                 => $this->fecha->copy()->addDays(3),
        'folio'                    => "PER-{$this->anio}-00999",
        'confirmado_en'            => now(),
    ]);

    ($this->revertir)($permiso->id)->assertOk();

    expect((float) $actual->fresh()->dias_utilizados)->toBe(0.0)
        ->and((float) $actual->fresh()->dias_saldo)->toBe(15.0);
});
