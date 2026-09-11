<?php

/*
| Quién anula un permiso pendiente, y con qué motivo.
|
| Antes: la ruta pedía `role:admin-uath|asistente-uath` y la policy
| `anular-permiso`, que solo tiene admin-uath, así que el asistente pasaba la
| ruta y recibía un 403 de la policy (reproducido en la auditoría). El titular
| no podía retirar un permiso suyo, y anular no pedía motivo.
|
| Decidido con el usuario: anulan el propio servidor y Talento Humano
| (admin-uath y asistente-uath, con `anular-permiso-pendiente`). El jefe
| inmediato no. El motivo es obligatorio siempre.
|
| Ningún usuario de aquí es admin-ti: su `Gate::before` se salta la policy.
*/

use App\Enums\EstadoPermiso;
use App\Enums\Permiso;
use App\Enums\RegimenLaboral;
use App\Models\Asistencia\PermisoServidor;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();
    PermisoServidor::unguard();

    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $unidad = unidadDePrueba();

    $cedula = 800007000;
    $servidor = function (bool $jefe = false) use ($unidad, &$cedula) {
        return Servidor::create([
            'cedula'                   => '0'.(++$cedula),
            'nombre'                   => 'Aníbal',
            'apellido'                 => 'Anula',
            'puesto_id'                => ($jefe ? puestoJefeDePrueba($unidad) : puestoDePrueba($unidad))->id,
            'unidad_administrativa_id' => $unidad->id,
            'regimen_laboral'          => RegimenLaboral::LOSEP,
            'estado'                   => true,
        ]);
    };

    $this->usuario = function (string $rol, ?Servidor $s = null) {
        $usuario = User::create([
            'email'        => uniqid('anula').'@example.com',
            'usuario_ti'   => uniqid('anula'),
            'password'     => bcrypt('123456'),
            'primer_login' => false,
            'servidor_id'  => $s?->id,
        ]);
        $usuario->assignRole($rol);

        return $usuario;
    };

    $this->titularServidor = $servidor();
    $this->jefeServidor    = $servidor(jefe: true);

    $this->titular = ($this->usuario)('servidor', $this->titularServidor);
    $this->jefe    = ($this->usuario)('jefe-unidad', $this->jefeServidor);

    $this->permiso = PermisoServidor::create([
        'servidor_id'              => $this->titularServidor->id,
        'unidad_administrativa_id' => $unidad->id,
        'jefe_id'                  => $this->jefeServidor->id,
        'tipo'                     => 'oficial',
        'fecha'                    => Carbon::today()->next(Carbon::MONDAY)->toDateString(),
        'hora_inicio'              => '08:00',
        'hora_fin'                 => '10:00',
        'observacion'              => 'Comisión de servicio',
        'estado'                   => EstadoPermiso::PENDIENTE->value,
        'vence_en'                 => now()->addDays(10),
        'folio'                    => 'PER-2099-00001',
    ]);

    $this->anular = fn (User $quien, array $datos = ['motivo' => 'Ya no lo voy a necesitar']) =>
        $this->actingAs($quien, 'sanctum')
            ->putJson("/api/v1/asistencia/permisos/{$this->permiso->id}/anular", $datos);
});

test('el servidor anula su propio permiso pendiente, y queda el motivo', function () {
    ($this->anular)($this->titular)->assertOk();

    $anulado = $this->permiso->fresh();
    expect($anulado->estado)->toBe(EstadoPermiso::ANULADO)
        ->and($anulado->anulado_por)->toBe($this->titular->id)
        ->and($anulado->motivo_anulacion)->toBe('Ya no lo voy a necesitar');
});

test('sin motivo no se anula', function () {
    $respuesta = ($this->anular)($this->titular, []);

    $respuesta->assertStatus(422);
    expect($this->permiso->fresh()->estado)->toBe(EstadoPermiso::PENDIENTE);
});

test('Talento Humano anula el permiso pendiente de cualquier servidor', function (string $rol) {
    // asistente-uath pasaba la ruta y la policy lo rechazaba: 403.
    ($this->anular)(($this->usuario)($rol))->assertOk();

    expect($this->permiso->fresh()->estado)->toBe(EstadoPermiso::ANULADO);
})->with(['admin-uath', 'asistente-uath']);

test('el jefe inmediato no anula el permiso de su subordinado', function () {
    ($this->anular)($this->jefe)->assertForbidden();

    expect($this->permiso->fresh()->estado)->toBe(EstadoPermiso::PENDIENTE);
});

test('ni otro servidor ni Recepción lo anulan', function (string $rol) {
    ($this->anular)(($this->usuario)($rol, Servidor::create([
        'cedula'                   => '0899999999',
        'nombre'                   => 'Otro',
        'apellido'                 => 'Servidor',
        'puesto_id'                => $this->titularServidor->puesto_id,
        'unidad_administrativa_id' => $this->titularServidor->unidad_administrativa_id,
        'regimen_laboral'          => RegimenLaboral::LOSEP,
        'estado'                   => true,
    ])))->assertForbidden();
})->with(['servidor', 'recepcion']);

test('a quien no puede anular se le dice 403, aunque no mande motivo', function () {
    // La autorización va antes que la validación: sin permiso, lo que importa
    // es que no puede, no que le falte un campo.
    ($this->anular)($this->jefe, [])->assertForbidden();
});

test('un permiso confirmado ya no se anula, ni siquiera el titular', function () {
    $this->permiso->update(['estado' => EstadoPermiso::ACTIVO->value]);

    $respuesta = ($this->anular)($this->titular);

    $respuesta->assertStatus(422);
    expect($this->permiso->fresh()->estado)->toBe(EstadoPermiso::ACTIVO);
});

test('la migración da el permiso a los roles de Talento Humano que ya existen, y a nadie más', function () {
    foreach (['admin-uath', 'asistente-uath'] as $rol) {
        Role::findByName($rol, 'sanctum')->revokePermissionTo(Permiso::ANULAR_PERMISO_PENDIENTE->value);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $migracion = require database_path('migrations/2026_09_11_100100_crear_permiso_anular_permiso_pendiente.php');
    $migracion->up();

    $tiene = fn (string $rol) => Role::findByName($rol, 'sanctum')
        ->hasPermissionTo(Permiso::ANULAR_PERMISO_PENDIENTE->value);

    expect($tiene('admin-uath'))->toBeTrue()
        ->and($tiene('asistente-uath'))->toBeTrue()
        ->and($tiene('jefe-unidad'))->toBeFalse()
        ->and($tiene('recepcion'))->toBeFalse();
});
