<?php

use App\Enums\Permiso;
use App\Enums\RegimenLaboral;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/**
 * Crea un rol con los permisos indicados, sin pasar por admin-ti: ese rol tiene
 * un Gate::before que devuelve true para todo y haría que estos tests pasaran
 * aunque las policies estuvieran mal.
 */
function rolConPermisos(string $nombre, array $permisos): Role
{
    $rol = Role::firstOrCreate(['name' => $nombre, 'guard_name' => 'sanctum']);

    foreach ($permisos as $permiso) {
        $rol->givePermissionTo(
            Permission::firstOrCreate([
                'name'       => $permiso->value,
                'guard_name' => 'sanctum',
            ])
        );
    }

    return $rol;
}

function servidorDePrueba(string $cedula, string $apellido): Servidor
{
    return Servidor::create([
        'cedula'                   => $cedula,
        'nombre'                   => 'Ana',
        'apellido'                 => $apellido,
        'puesto_id'                => test()->puesto->id,
        'unidad_administrativa_id' => test()->unidad->id,
        'regimen_laboral'          => RegimenLaboral::LOSEP,
        'estado'                   => true,
    ]);
}

beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();

    $this->unidad = unidadDePrueba(['nombre' => 'Dirección de TI']);
    $this->puesto = puestoDePrueba($this->unidad);

    // Administrador del módulo: todos los permisos del área, ninguno heredado
    // del atajo de admin-ti.
    rolConPermisos('gestor-usuarios', [
        Permiso::GESTIONAR_USUARIOS,
        Permiso::ACTIVAR_USUARIO,
        Permiso::RESTABLECER_CONTRASENA,
        Permiso::GESTIONAR_ROLES,
    ]);
    Role::firstOrCreate(['name' => 'servidor', 'guard_name' => 'sanctum']);

    // Permiso de alto privilegio usado como cebo en los tests de escalación.
    Permission::firstOrCreate([
        'name'       => Permiso::CONFIGURAR_SISTEMA->value,
        'guard_name' => 'sanctum',
    ]);

    $this->admin = User::create([
        'email'        => 'admin@example.com',
        'usuario_ti'   => 'admin',
        'password'     => bcrypt('123456'),
        'primer_login' => false,
        'activo'       => true,
    ]);
    $this->admin->assignRole('gestor-usuarios');
});

// ── Listado y filtros ────────────────────────────────────────────────

test('el listado exige el permiso gestionar-usuarios', function () {
    $intruso = User::create([
        'email'        => 'intruso@example.com',
        'usuario_ti'   => 'intruso',
        'password'     => bcrypt('123456'),
        'primer_login' => false,
    ]);
    $intruso->assignRole('servidor');

    $this->actingAs($intruso, 'sanctum')
        ->getJson('/api/v1/admin/usuarios')
        ->assertStatus(403);
});

test('el listado filtra por estado activo', function () {
    User::create([
        'email' => 'inactivo@example.com', 'usuario_ti' => 'inactivo',
        'password' => bcrypt('x'), 'primer_login' => false, 'activo' => false,
    ]);

    $activos = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/admin/usuarios?activo=true')
        ->assertOk()
        ->json('datos');

    expect(collect($activos)->pluck('usuario_ti'))
        ->toContain('admin')
        ->not->toContain('inactivo');
});

test('el listado busca por cédula del servidor vinculado', function () {
    $servidor = servidorDePrueba('0801111111', 'Buscada');
    User::create([
        'email' => 'vinculado@example.com', 'usuario_ti' => 'vinculado',
        'password' => bcrypt('x'), 'primer_login' => false,
        'servidor_id' => $servidor->id,
    ]);

    $datos = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/admin/usuarios?search=0801111111')
        ->assertOk()
        ->json('datos');

    expect($datos)->toHaveCount(1)
        ->and($datos[0]['usuario_ti'])->toBe('vinculado');
});

// ── Alta ─────────────────────────────────────────────────────────────

test('crear un usuario deja la cédula del servidor como contraseña inicial', function () {
    $servidor = servidorDePrueba('0802222222', 'Nueva');

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/admin/usuarios', [
            'email'       => 'nueva@example.com',
            'usuario_ti'  => 'anueva',
            'roles'       => ['servidor'],
            'servidor_id' => $servidor->id,
        ])
        ->assertCreated();

    $creado = User::where('usuario_ti', 'anueva')->firstOrFail();

    expect(Hash::check('0802222222', $creado->password))->toBeTrue()
        ->and($creado->primer_login)->toBeTrue()
        ->and($creado->activo)->toBeTrue()
        ->and($creado->servidor_id)->toBe($servidor->id);
});

test('no se puede crear un segundo usuario para el mismo servidor', function () {
    $servidor = servidorDePrueba('0803333333', 'Ocupada');

    User::create([
        'email' => 'primero@example.com', 'usuario_ti' => 'primero',
        'password' => bcrypt('x'), 'primer_login' => false,
        'servidor_id' => $servidor->id,
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/admin/usuarios', [
            'email'       => 'segundo@example.com',
            'usuario_ti'  => 'segundo',
            'roles'       => ['servidor'],
            'servidor_id' => $servidor->id,
        ])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'Este servidor ya tiene un usuario asignado.');
});

// ── Guardas de auto-modificación ─────────────────────────────────────

test('un administrador no puede desactivarse a sí mismo', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson("/api/v1/admin/usuarios/{$this->admin->id}/toggle-activo")
        ->assertStatus(422);

    expect($this->admin->fresh()->activo)->toBeTrue();
});

test('un administrador no puede cambiar sus propios roles', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/v1/admin/usuarios/{$this->admin->id}", [
            'roles' => ['servidor'],
        ])
        ->assertStatus(422);

    expect($this->admin->fresh()->hasRole('gestor-usuarios'))->toBeTrue();
});

test('un administrador no puede otorgarse permisos directos', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson("/api/v1/admin/usuarios/{$this->admin->id}/permisos", [
            'permisos' => [Permiso::CONFIGURAR_SISTEMA->value],
        ])
        ->assertStatus(422);

    expect($this->admin->fresh()->hasDirectPermission(
        Permiso::CONFIGURAR_SISTEMA->value
    ))->toBeFalse();
});

test('sincronizar permisos directos exige gestionar-roles, no solo entrar a admin', function () {
    // Perfil de admin-uath: gestiona usuarios pero no roles.
    rolConPermisos('gestor-sin-roles', [Permiso::GESTIONAR_USUARIOS]);

    $gestor = User::create([
        'email' => 'gestor@example.com', 'usuario_ti' => 'gestor',
        'password' => bcrypt('x'), 'primer_login' => false,
    ]);
    $gestor->assignRole('gestor-sin-roles');

    $victima = User::create([
        'email' => 'victima@example.com', 'usuario_ti' => 'victima',
        'password' => bcrypt('x'), 'primer_login' => false,
    ]);

    $this->actingAs($gestor, 'sanctum')
        ->postJson("/api/v1/admin/usuarios/{$victima->id}/permisos", [
            'permisos' => [Permiso::CONFIGURAR_SISTEMA->value],
        ])
        ->assertStatus(403);
});

// ── Estado y sesiones ────────────────────────────────────────────────

test('desactivar a un usuario revoca sus tokens vigentes', function () {
    $objetivo = User::create([
        'email' => 'objetivo@example.com', 'usuario_ti' => 'objetivo',
        'password' => bcrypt('x'), 'primer_login' => false,
    ]);
    $objetivo->createToken('auth_token');

    expect($objetivo->tokens()->count())->toBe(1);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson("/api/v1/admin/usuarios/{$objetivo->id}/toggle-activo")
        ->assertOk()
        ->assertJsonPath('datos.activo', false);

    expect($objetivo->fresh()->tokens()->count())->toBe(0);
});

test('restablecer la contraseña la deja en la cédula y cierra las sesiones', function () {
    $servidor = servidorDePrueba('0804444444', 'Reset');
    $objetivo = User::create([
        'email' => 'reset@example.com', 'usuario_ti' => 'reset',
        'password' => bcrypt('otra-cosa'), 'primer_login' => false,
        'servidor_id' => $servidor->id,
    ]);
    $objetivo->createToken('auth_token');

    $this->actingAs($this->admin, 'sanctum')
        ->postJson("/api/v1/admin/usuarios/{$objetivo->id}/restablecer-contrasena")
        ->assertOk();

    $objetivo->refresh();

    expect(Hash::check('0804444444', $objetivo->password))->toBeTrue()
        ->and($objetivo->primer_login)->toBeTrue()
        ->and($objetivo->tokens()->count())->toBe(0);
});

// ── Vinculación de servidor ──────────────────────────────────────────

test('desvincular deja al usuario inactivo y asignar de nuevo lo reactiva', function () {
    $servidor = servidorDePrueba('0805555555', 'Vinculo');
    $objetivo = User::create([
        'email' => 'vinculo@example.com', 'usuario_ti' => 'vinculo',
        'password' => bcrypt('x'), 'primer_login' => false,
        'servidor_id' => $servidor->id,
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson("/api/v1/admin/usuarios/{$objetivo->id}/desvincular-servidor")
        ->assertOk();

    $objetivo->refresh();
    expect($objetivo->servidor_id)->toBeNull()
        ->and($objetivo->activo)->toBeFalse();

    $this->actingAs($this->admin, 'sanctum')
        ->postJson("/api/v1/admin/usuarios/{$objetivo->id}/asignar-servidor", [
            'servidor_id' => $servidor->id,
        ])
        ->assertOk();

    $objetivo->refresh();
    expect($objetivo->servidor_id)->toBe($servidor->id)
        ->and($objetivo->activo)->toBeTrue();
});

test('no se puede asignar un servidor que ya tiene usuario', function () {
    $servidor = servidorDePrueba('0806666666', 'Tomada');
    User::create([
        'email' => 'dueno@example.com', 'usuario_ti' => 'dueno',
        'password' => bcrypt('x'), 'primer_login' => false,
        'servidor_id' => $servidor->id,
    ]);

    $huerfano = User::create([
        'email' => 'huerfano@example.com', 'usuario_ti' => 'huerfano',
        'password' => bcrypt('x'), 'primer_login' => false,
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson("/api/v1/admin/usuarios/{$huerfano->id}/asignar-servidor", [
            'servidor_id' => $servidor->id,
        ])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'Este servidor ya tiene un usuario asignado.');
});

// ── Borrado ──────────────────────────────────────────────────────────

test('un usuario con historial no se borra: responde 422 en vez de reventar', function () {
    $servidor = servidorDePrueba('0807777777', 'Historial');
    $objetivo = User::create([
        'email' => 'historial@example.com', 'usuario_ti' => 'historial',
        'password' => bcrypt('x'), 'primer_login' => false,
    ]);

    // Rastro con FK restrictiva hacia users.
    DB::table('documentos_servidor')->insert([
        'servidor_id'    => $servidor->id,
        'tipo_documento' => 'cedula_identidad',
        'nombre_archivo' => 'cedula.pdf',
        'ruta_archivo'   => 'docs/cedula.pdf',
        'subido_por'     => $objetivo->id,
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/admin/usuarios/{$objetivo->id}")
        ->assertStatus(422);

    expect(User::find($objetivo->id))->not->toBeNull();
});

test('un usuario sin historial sí se borra', function () {
    $objetivo = User::create([
        'email' => 'efimero@example.com', 'usuario_ti' => 'efimero',
        'password' => bcrypt('x'), 'primer_login' => false,
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/admin/usuarios/{$objetivo->id}")
        ->assertOk();

    expect(User::find($objetivo->id))->toBeNull();
});

test('un administrador no puede borrarse a sí mismo', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/admin/usuarios/{$this->admin->id}")
        ->assertStatus(422);

    expect(User::find($this->admin->id))->not->toBeNull();
});

// ── Sugerencia de usuario TI ─────────────────────────────────────────

test('la sugerencia de usuario TI evita colisiones', function () {
    $servidor = servidorDePrueba('0808888888', 'Perez');
    // El servidor se llama Ana Perez → 'aperez'.
    User::create([
        'email' => 'ya@example.com', 'usuario_ti' => 'aperez',
        'password' => bcrypt('x'), 'primer_login' => false,
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->getJson("/api/v1/admin/usuarios/sugerir-usuario-ti?servidor_id={$servidor->id}")
        ->assertOk()
        ->assertJsonPath('datos.usuario_ti_sugerido', 'aperez1');
});

// ── Catálogo de roles ────────────────────────────────────────────────

test('el catálogo de roles devuelve valor y etiqueta legible', function () {
    $roles = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/admin/usuarios-roles')
        ->assertOk()
        ->json('datos');

    $servidor = collect($roles)->firstWhere('valor', 'servidor');

    expect($servidor)->not->toBeNull()
        ->and($servidor['etiqueta'])->toBe('Servidor Público');
});
