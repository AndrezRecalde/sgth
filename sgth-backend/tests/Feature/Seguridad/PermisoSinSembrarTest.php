<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/**
 * Qué pasa cuando la base no tiene un permiso que el código exige.
 *
 * El middleware `permission:` no es el problema: por dentro usa la variante
 * indulgente de Spatie, que si el permiso no existe responde «no lo tienes» y
 * acaba en un 403 correcto.
 *
 * El 500 aparece cuando el permiso se consulta con `hasPermissionTo()`, la
 * variante estricta, que lanza `PermissionDoesNotExist`. La carga inicial de
 * servidores lo hace a propósito, para saltarse el `Gate::before` que aprueba
 * todo a admin-ti y poder revocar la vía a una persona concreta. El efecto es
 * que justo el rol que pasa el middleware es el que se come el error interno.
 *
 * Apareció al montar el barrido de rutas GET, cuyo usuario tiene todos los
 * roles y por tanto llega hasta ahí.
 */
beforeEach(function () {
    User::unguard();

    $this->adminTi = User::create([
        'email' => 'adminti@example.com', 'usuario_ti' => 'adminti',
        'password' => bcrypt('123456'), 'primer_login' => false,
        'activo' => true,
    ]);

    // Con el rol, pero sin ningún permiso sembrado: es el estado de una base a
    // la que le faltó pasar el seeder.
    $this->adminTi->assignRole(Role::firstOrCreate(
        ['name' => 'admin-ti', 'guard_name' => 'sanctum']
    ));
});

test('un_permiso_sin_sembrar_responde_no_autorizado_y_no_un_error_interno', function () {
    $respuesta = $this->actingAs($this->adminTi, 'sanctum')
        ->getJson('/api/v1/expediente/vinculacion-inicial');

    // Para quien está delante de la pantalla el hecho es el mismo —no puede
    // pasar—, y nadie puede tener un permiso que no existe.
    $respuesta->assertStatus(403);
    expect($respuesta->json('mensaje'))
        ->toBe('No tiene autorización para realizar esta acción.');
});

test('la_falta_del_permiso_queda_registrada_con_su_nombre', function () {
    Log::spy();

    $this->actingAs($this->adminTi, 'sanctum')
        ->getJson('/api/v1/expediente/vinculacion-inicial')
        ->assertStatus(403);

    // Sin esto, un permiso sin sembrar dejaría a todo el mundo fuera de esa
    // pantalla en silencio y con un 403 de aspecto perfectamente normal: la
    // clase de avería que tarda semanas en encontrarse.
    Log::shouldHaveReceived('error')
        ->withArgs(function (string $mensaje, array $contexto) {
            return str_contains($mensaje, 'Falta sembrar')
                && str_contains(
                    $contexto['detalle'] ?? '', 'vincular-servidor-inicial'
                );
        })
        ->once();
});

test('el_middleware_de_permisos_ya_respondia_403_por_su_cuenta', function () {
    // Quien no pasa el middleware nunca llega al `hasPermissionTo()`, así que
    // su 403 no es el de este arreglo: se comprueba para no atribuirle al
    // renderizador un caso que ya funcionaba.
    $otro = User::create([
        'email' => 'uath@example.com', 'usuario_ti' => 'uath',
        'password' => bcrypt('123456'), 'primer_login' => false,
        'activo' => true,
    ]);
    $otro->assignRole(Role::firstOrCreate(
        ['name' => 'admin-uath', 'guard_name' => 'sanctum']
    ));

    Log::spy();

    $this->actingAs($otro, 'sanctum')
        ->getJson('/api/v1/expediente/vinculacion-inicial')
        ->assertStatus(403);

    Log::shouldNotHaveReceived('error');
});

test('con_el_permiso_sembrado_la_ruta_deja_de_dar_403', function () {
    $this->seed(Database\Seeders\RolPermisoSeeder::class);

    $usuario = User::create([
        'email' => 'conpermisos@example.com', 'usuario_ti' => 'conpermisos',
        'password' => bcrypt('123456'), 'primer_login' => false,
        'activo' => true,
    ]);
    $usuario->assignRole('admin-ti');

    // El 403 de antes era por la falta del permiso, no por el rol: sembrado el
    // catálogo, la misma petición pasa el filtro y llega al controlador.
    $this->actingAs($usuario, 'sanctum')
        ->getJson('/api/v1/expediente/vinculacion-inicial')
        ->assertOk();
});
