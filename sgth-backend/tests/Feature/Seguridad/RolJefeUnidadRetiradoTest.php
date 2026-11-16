<?php

/*
| Se retira `jefe-unidad` (decisión del 2026-11-13): el jefe ve a su equipo por
| su puesto de jefatura, en «Mi equipo» del Portal (#644 y #645).
|
| La migración no le quita el rol en silencio a quien no dirige ninguna unidad
| por su puesto: se detiene y dice quién. Y a quien era su único rol le da
| `servidor`, porque el rol traía los permisos base.
*/

use App\Enums\Rol;
use App\Enums\RegimenLaboral;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolPermisoSeeder::class);

    // Una base de antes del retiro: el rol existe, con lo que tenía.
    Role::firstOrCreate(['name' => 'jefe-unidad', 'guard_name' => 'sanctum'])
        ->givePermissionTo(['acceso-autoservicio', 'ver-asistencia-unidad', 'ver-vacaciones-unidad']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->unidad = unidadDePrueba(['nombre' => 'Dirección de Prueba']);
    $cedula = 812000000;

    // Cada jefe en su unidad: solo hay un puesto de jefatura por unidad.
    $this->servidor = function (bool $jefe) use (&$cedula): Servidor {
        $unidad = $jefe ? unidadDePrueba(['nombre' => 'Dirección del jefe']) : $this->unidad;
        $puesto = $jefe ? puestoJefeDePrueba($unidad) : puestoDePrueba($unidad);
        $servidor = Servidor::create([
            'cedula' => '0' . (++$cedula), 'nombre' => 'Servidor', 'apellido' => 'Prueba',
            'puesto_id' => $puesto->id, 'unidad_administrativa_id' => $unidad->id,
            'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true,
        ]);
        ocupaPuestoDePrueba($servidor, $puesto);

        return $servidor;
    };

    $this->conRoles = function (string $usuarioTi, Servidor $servidor, array $roles): User {
        $usuario = User::factory()->create(['usuario_ti' => $usuarioTi, 'servidor_id' => $servidor->id]);
        $usuario->assignRole($roles);

        return $usuario;
    };

    $this->migracion = fn () => require database_path('migrations/2026_12_28_000000_retirar_rol_jefe_unidad.php');
});

it('el seeder y el enum ya no lo tienen', function () {
    $valores = array_map(fn (Rol $r) => $r->value, Rol::cases());

    expect($valores)->not->toContain('jefe-unidad');
});

it('la migración retira el rol, y a quien era el único le da servidor', function () {
    $servidorDelJefe = ($this->servidor)(jefe: true);
    $soloJefe = ($this->conRoles)('solo.jefe', $servidorDelJefe, ['jefe-unidad']);
    $conOtro = ($this->conRoles)('jefe.uath', ($this->servidor)(jefe: true), ['jefe-unidad', 'admin-uath']);

    ($this->migracion)()->up();

    expect(Role::where('name', 'jefe-unidad')->exists())->toBeFalse()
        ->and($soloJefe->fresh()->getRoleNames()->all())->toBe(['servidor'])
        ->and($conOtro->fresh()->getRoleNames()->all())->toBe(['admin-uath'])
        // Sigue en el Portal, y a su equipo lo ve por el puesto.
        ->and($soloJefe->fresh()->can('acceso-autoservicio'))->toBeTrue()
        ->and($soloJefe->fresh()->unidadesQueDirige())->toBe([$servidorDelJefe->unidad_administrativa_id]);
});

it('la migración se detiene y dice quién tiene el rol sin dirigir ninguna unidad', function () {
    ($this->conRoles)('jefe.sin.puesto', ($this->servidor)(jefe: false), ['jefe-unidad']);
    ($this->conRoles)('jefe.bien.puesto', ($this->servidor)(jefe: true), ['jefe-unidad']);

    expect(fn () => ($this->migracion)()->up())
        ->toThrow(RuntimeException::class, 'jefe.sin.puesto');

    expect(Role::where('name', 'jefe-unidad')->exists())->toBeTrue();

    try {
        ($this->migracion)()->up();
    } catch (RuntimeException $e) {
        expect($e->getMessage())->not->toContain('jefe.bien.puesto');
    }
});

it('la migración no hace nada si el rol ya no existe', function () {
    Role::where('name', 'jefe-unidad')->delete();

    ($this->migracion)()->up();

    expect(Role::where('name', 'jefe-unidad')->exists())->toBeFalse()
        ->and(Role::where('name', 'servidor')->exists())->toBeTrue();
});

it('volver atrás recrea el rol con sus permisos', function () {
    Role::where('name', 'jefe-unidad')->delete();

    ($this->migracion)()->down();

    $rol = Role::findByName('jefe-unidad', 'sanctum');

    expect($rol->hasPermissionTo('ver-asistencia-unidad'))->toBeTrue()
        ->and($rol->hasPermissionTo('ver-estructura'))->toBeTrue()
        ->and($rol->hasPermissionTo('crear-permiso'))->toBeTrue();
});
