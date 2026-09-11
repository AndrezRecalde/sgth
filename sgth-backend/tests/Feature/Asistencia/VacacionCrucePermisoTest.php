<?php

/*
| Vacaciones sobre días que ya tienen un permiso.
|
| El permiso ya no se registra sobre unas vacaciones, pero al revés nadie
| miraba: un permiso personal confirmado descuenta sus horas del saldo, y unas
| vacaciones aprobadas encima volvían a descontar ese mismo día. Bloquean los
| permisos vivos de cualquier tipo, al pedir y otra vez al aprobar.
*/

use App\Enums\EstadoPermiso;
use App\Enums\RegimenLaboral;
use App\Enums\TipoPermiso;
use App\Models\Asistencia\PermisoServidor;
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
    PermisoServidor::unguard();

    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $unidad = unidadDePrueba();

    $this->servidor = Servidor::create([
        'cedula'                   => '0800002001',
        'nombre'                   => 'Pía',
        'apellido'                 => 'Permiso',
        'puesto_id'                => puestoDePrueba($unidad)->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral'          => RegimenLaboral::LOSEP,
        'estado'                   => true,
    ]);

    $uath = User::create([
        'email'        => 'uath-cruce@example.com',
        'usuario_ti'   => 'uathcruce',
        'password'     => bcrypt('123456'),
        'primer_login' => false,
    ]);
    $uath->assignRole('admin-uath');
    $this->actingAs($uath, 'sanctum');

    // «Matrimonio» no descuenta: el cruce vale para cualquier motivo, y así
    // estas pruebas no necesitan períodos.
    $this->lunes = now()->addWeeks(3)->next(Carbon::MONDAY);

    $this->pedir = fn () => $this->postJson('/api/v1/asistencia/vacaciones', [
        'servidor_id'      => $this->servidor->id,
        'motivo'           => 'matrimonio',
        'fecha_inicio'     => $this->lunes->toDateString(),
        'fecha_fin'        => $this->lunes->copy()->addDays(2)->toDateString(),
        'dias_solicitados' => 3,
        'tipo_dias'        => 'habiles',
    ]);

    $numero = 0;
    $this->permiso = function (
        Carbon $fecha,
        TipoPermiso $tipo = TipoPermiso::PERSONAL,
        EstadoPermiso $estado = EstadoPermiso::PENDIENTE,
    ) use (&$numero) {
        return PermisoServidor::create([
            'servidor_id'              => $this->servidor->id,
            'unidad_administrativa_id' => $this->servidor->unidad_administrativa_id,
            'tipo'                     => $tipo->value,
            'fecha'                    => $fecha->toDateString(),
            'hora_inicio'              => '08:00',
            'hora_fin'                 => '12:00',
            'estado'                   => $estado->value,
            'vence_en'                 => $fecha->copy()->addDays(3),
            'folio'                    => sprintf('PER-2026-%05d', 900 + ++$numero),
        ]);
    };
});

test('un permiso vivo dentro de las fechas impide pedir las vacaciones', function (EstadoPermiso $estado) {
    ($this->permiso)($this->lunes->copy()->addDay(), TipoPermiso::PERSONAL, $estado);

    $respuesta = ($this->pedir)();

    $respuesta->assertStatus(422);
    expect($respuesta->json('mensaje'))
        ->toContain('PER-2026-00901')
        ->toContain($estado->value)
        ->toContain($this->lunes->copy()->addDay()->format('d/m/Y'))
        ->and(Vacacion::count())->toBe(0);
})->with([
    'pendiente'               => EstadoPermiso::PENDIENTE,
    'activo'                  => EstadoPermiso::ACTIVO,
    'validado trabajo social' => EstadoPermiso::VALIDADO_TRABAJO_SOCIAL,
]);

test('bloquea cualquier tipo de permiso, no solo el personal', function (TipoPermiso $tipo) {
    ($this->permiso)($this->lunes, $tipo);

    $respuesta = ($this->pedir)();

    $respuesta->assertStatus(422);
    expect($respuesta->json('mensaje'))->toContain($tipo->value);
})->with([
    'personal'   => TipoPermiso::PERSONAL,
    'oficial'    => TipoPermiso::OFICIAL,
    'enfermedad' => TipoPermiso::ENFERMEDAD,
    'calamidad'  => TipoPermiso::CALAMIDAD,
]);

test('los permisos anulados, rechazados o convertidos en falta no ocupan el día', function (EstadoPermiso $estado) {
    ($this->permiso)($this->lunes->copy()->addDay(), TipoPermiso::PERSONAL, $estado);

    ($this->pedir)()->assertCreated();
})->with([
    'anulado'             => EstadoPermiso::ANULADO,
    'rechazado'           => EstadoPermiso::RECHAZADO,
    'falta injustificada' => EstadoPermiso::FALTA_INJUSTIFICADA,
]);

test('un permiso justo antes o justo después de las fechas no se cruza', function () {
    // El viernes anterior y el jueves siguiente: las vacaciones van de lunes a miércoles.
    ($this->permiso)($this->lunes->copy()->subDays(3));
    ($this->permiso)($this->lunes->copy()->addDays(3));

    ($this->pedir)()->assertCreated();
});

test('al aprobar se vuelve a mirar: un permiso registrado después impide aprobar', function () {
    $id = ($this->pedir)()->assertCreated()->json('datos.id');

    // Entre la solicitud y la aprobación alguien registra un permiso esos días.
    ($this->permiso)($this->lunes->copy()->addDays(2), TipoPermiso::ENFERMEDAD);

    $respuesta = $this->putJson("/api/v1/asistencia/vacaciones/{$id}", ['estado' => 'aprobada']);

    $respuesta->assertStatus(422);
    expect($respuesta->json('mensaje'))->toContain('PER-2026-00901')
        ->and(Vacacion::find($id)->getRawOriginal('estado'))->toBe('pendiente');
});

test('rechazar la solicitud sí se puede aunque haya un permiso esos días', function () {
    $id = ($this->pedir)()->assertCreated()->json('datos.id');

    ($this->permiso)($this->lunes->copy()->addDays(2));

    $this->putJson("/api/v1/asistencia/vacaciones/{$id}", ['estado' => 'rechazada'])->assertOk();

    expect(Vacacion::find($id)->getRawOriginal('estado'))->toBe('rechazada');
});
