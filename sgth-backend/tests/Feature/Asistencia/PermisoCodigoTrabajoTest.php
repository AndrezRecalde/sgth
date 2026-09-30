<?php

/*
| El Código del Trabajo accede al módulo de permisos.
|
| Estuvo fuera desde el principio: el control existía como «rechazar si es
| Código del Trabajo» y en agosto de 2026 solo se reformuló en positivo, para
| que el régimen nuevo de servicios profesionales no entrara por omisión.
|
| Talento Humano confirmó el 2026-09-30 que sí debe entrar: los obreros piden
| permisos igual que el resto, y su permiso PERSONAL les descuenta de vacaciones
| como a un LOSEP —es la misma bolsa, que el Código del Trabajo también genera—.
|
| Servicios profesionales sigue fuera: contrato civil, sin jornada que permisar.
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

    $this->crearServidor = fn (string $cedula, RegimenLaboral $regimen) => Servidor::create([
        'cedula'                   => $cedula,
        'nombre'                   => 'Obrero',
        'apellido'                 => 'Del Trabajo',
        'puesto_id'                => puestoDePrueba($unidad)->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral'          => $regimen,
        'estado'                   => true,
    ]);

    $this->obrero = ($this->crearServidor)('0800009001', RegimenLaboral::CODIGO_TRABAJO);
    $this->jefe   = ($this->crearServidor)('0800009099', RegimenLaboral::LOSEP);

    $usuarioCon = function (string $rol) {
        $usuario = User::create([
            'email'        => uniqid('ct').'@example.com',
            'usuario_ti'   => uniqid('ct'),
            'password'     => bcrypt('123456'),
            'primer_login' => false,
        ]);
        $usuario->assignRole($rol);

        return $usuario;
    };

    $this->uath      = $usuarioCon('admin-uath');
    $this->recepcion = $usuarioCon('recepcion');

    $this->periodo = fn (Servidor $servidor, float $generados, float $utilizados) => PeriodoVacacion::create([
        'servidor_id'          => $servidor->id,
        'anio'                 => now()->year,
        'fecha_inicio_periodo' => Carbon::create(now()->year, 1, 1),
        'fecha_fin_periodo'    => Carbon::create(now()->year, 12, 31),
        'regimen'              => 'codigo_trabajo',
        'anios_antiguedad'     => 3,
        'dias_generados'       => $generados,
        'dias_utilizados'      => $utilizados,
        'dias_saldo'           => $generados - $utilizados,
        'saldo_acumulado'      => $generados - $utilizados,
        'estado'               => 'abierto',
    ]);

    // Un día hábil: el permiso personal no se registra en fin de semana.
    $this->fecha = Carbon::today()->addDay();
    while ($this->fecha->isWeekend()) {
        $this->fecha->addDay();
    }

    // 4 horas son 0,5 días.
    $this->registrar = fn (Servidor $servidor, string $horaFin = '12:00') =>
        $this->actingAs($this->uath, 'sanctum')->postJson('/api/v1/asistencia/permisos', [
            'servidor_id' => $servidor->id,
            'jefe_id'     => $this->jefe->id,
            'tipo'        => 'personal',
            'fecha'       => $this->fecha->toDateString(),
            'hora_inicio' => '08:00',
            'hora_fin'    => $horaFin,
        ]);
});

test('un obrero del Código del Trabajo puede registrar un permiso', function () {
    ($this->periodo)($this->obrero, 15, 0);

    $permiso = ($this->registrar)($this->obrero)->assertCreated()->json('datos');

    expect($permiso['tipo'])->toBe('personal')
        ->and($permiso['estado'])->toBe(EstadoPermiso::PENDIENTE->value);
});

test('y al confirmarlo le descuenta de sus vacaciones, como a un LOSEP', function () {
    $periodo = ($this->periodo)($this->obrero, 15, 0);

    $permiso = ($this->registrar)($this->obrero)->assertCreated()->json('datos');

    $this->actingAs($this->recepcion, 'sanctum')
        ->postJson("/api/v1/asistencia/permisos/confirmar/{$permiso['folio']}")
        ->assertOk();

    // 4 horas = 0,5 días, que salen del período y quedan anotados en su tramo.
    expect((float) $periodo->fresh()->dias_utilizados)->toBe(0.5)
        ->and((float) $periodo->fresh()->dias_saldo)->toBe(14.5);

    $tramos = PermisoDescuento::where('permiso_servidor_id', $permiso['id'])->get();

    expect($tramos)->toHaveCount(1)
        ->and($tramos->first()->dias)->toBe(0.5);
});

test('sin período abierto no se le deja registrar, igual que a un LOSEP', function () {
    // Sin período: el permiso personal no tendría de dónde descontarse.
    ($this->registrar)($this->obrero)
        ->assertStatus(422);

    expect(PermisoServidor::where('servidor_id', $this->obrero->id)->count())->toBe(0);
});

test('sin saldo suficiente tampoco', function () {
    ($this->periodo)($this->obrero, 15, 14.75); // quedan 0,25 días

    // 4 horas son 0,5 días y solo le quedan 0,25.
    $respuesta = ($this->registrar)($this->obrero)->assertStatus(422);

    expect($respuesta->json('mensaje'))->toContain('Saldo de vacaciones insuficiente');
});

test('el tope de 4 horas al día también le aplica', function () {
    ($this->periodo)($this->obrero, 15, 0);

    ($this->registrar)($this->obrero, '13:00')   // 5 horas
        ->assertStatus(422);
});

test('servicios profesionales sigue fuera del módulo', function () {
    $civil = ($this->crearServidor)('0800009002', RegimenLaboral::SERVICIOS_PROFESIONALES);

    $respuesta = ($this->registrar)($civil)->assertStatus(422);

    expect($respuesta->json('mensaje'))->toContain('no tiene acceso al módulo de permisos');
});

test('el consolidado cuenta el permiso del obrero', function () {
    ($this->periodo)($this->obrero, 15, 0);

    $permiso = ($this->registrar)($this->obrero)->assertCreated()->json('datos');

    $this->actingAs($this->recepcion, 'sanctum')
        ->postJson("/api/v1/asistencia/permisos/confirmar/{$permiso['folio']}")
        ->assertOk();

    // El obrero necesita marcación habilitada para salir en el informe.
    $this->obrero->update(['puede_marcar' => true]);

    $params = http_build_query([
        'fecha_inicio' => Carbon::today()->subDays(5)->toDateString(),
        'fecha_fin'    => Carbon::today()->addDays(5)->toDateString(),
        'tipo'         => 'personal',
    ]);

    $filas = $this->actingAs($this->uath, 'sanctum')
        ->getJson("/api/v1/asistencia/consolidado-permisos?{$params}")
        ->assertOk()
        ->json('datos.consolidado');

    expect(collect($filas)->pluck('cedula')->all())->toContain('0800009001');
});
