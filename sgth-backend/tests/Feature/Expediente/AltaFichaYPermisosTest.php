<?php

namespace Tests\Feature\Expediente;

use App\Enums\Permiso;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\User;
use App\Services\Expediente\ExpedienteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    foreach (['admin-uath', 'admin-ti'] as $rol) {
        Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum']);
    }

    $this->provincia = DB::table('provincias')->insertGetId(
        ['nombre' => 'Esmeraldas', 'codigo' => '08', 'created_at' => now(), 'updated_at' => now()]
    );
    $this->canton = DB::table('cantones')->insertGetId(
        ['nombre' => 'Esmeraldas', 'provincia_id' => $this->provincia, 'created_at' => now(), 'updated_at' => now()]
    );

    $this->ficha = fn (string $cedula): array => [
        'cedula'   => $cedula,
        'nombre'   => 'Ficha',
        'apellido' => 'Nueva',
        'fecha_nacimiento' => '1990-05-05',
        'genero'           => 'femenino',
        'estado_civil'     => 'soltero',
        'es_extranjero'    => false,
        'provincia_nacimiento_id' => $this->provincia,
        'canton_nacimiento_id'    => $this->canton,
        'tiene_discapacidad'            => false,
        'tiene_enfermedad_catastrofica' => false,
    ];
});

// ── Dar de alta una ficha no es una acción de personal ───────────

/**
 * crearServidorBasico() fabricaba un MovimientoPersonal de tipo 'ingreso'
 * fechado el día del registro, sin puesto ni nombramiento. Nacía en estado
 * 'registrada', así que salía en la bandeja y permitía descargar el PDF de un
 * acto que nunca ocurrió.
 */
test('registrar la ficha de un servidor no genera ninguna acción de personal', function () {
    $servidor = app(ExpedienteService::class)->crearServidorBasico(($this->ficha)('0801111111'));

    expect(MovimientoPersonal::where('servidor_id', $servidor->id)->count())->toBe(0);
});

test('la ficha se crea igual, con sus datos', function () {
    $servidor = app(ExpedienteService::class)->crearServidorBasico(($this->ficha)('0802222222'));

    expect($servidor->exists)->toBeTrue()
        ->and($servidor->cedula)->toBe('0802222222')
        ->and($servidor->contratoVigente)->toBeNull();
});

// ── El bypass de permisos ya no alcanza a Talento Humano ─────────

/**
 * El seeder le da a admin-uath 'ver-historia-clinica-propia' y le niega
 * 'ver-historia-clinica'. Un Gate::before que devolvía true para ese rol
 * anulaba esa decisión sin que nadie se enterara.
 */
test('talento humano no obtiene permisos que el seeder no le dio', function () {
    Permission::firstOrCreate(['name' => 'ver-historia-clinica', 'guard_name' => 'sanctum']);

    $th = User::factory()->create();
    $th->assignRole('admin-uath');

    expect($th->can('ver-historia-clinica'))->toBeFalse();
});

test('talento humano conserva los permisos que sí le corresponden', function () {
    $permiso = Permission::firstOrCreate([
        'name' => Permiso::GESTIONAR_EXPEDIENTE->value, 'guard_name' => 'sanctum',
    ]);
    Role::findByName('admin-uath', 'sanctum')->givePermissionTo($permiso);

    $th = User::factory()->create();
    $th->assignRole('admin-uath');

    expect($th->can(Permiso::GESTIONAR_EXPEDIENTE->value))->toBeTrue();
});

test('el superusuario técnico conserva el acceso sin restricciones', function () {
    Permission::firstOrCreate(['name' => 'ver-historia-clinica', 'guard_name' => 'sanctum']);

    $ti = User::factory()->create();
    $ti->assignRole('admin-ti');

    expect($ti->can('ver-historia-clinica'))->toBeTrue();
});

/**
 * Las policies de Expediente comprueban el rol directamente, así que el
 * trabajo diario de Talento Humano no dependía del atajo.
 */
test('talento humano sigue pudiendo ver y gestionar expedientes', function () {
    $th = User::factory()->create();
    $th->assignRole('admin-uath');

    expect($th->can('verAny', \App\Models\Expediente\Servidor::class))->toBeTrue()
        ->and($th->can('crear', \App\Models\Expediente\Servidor::class))->toBeTrue();
});
