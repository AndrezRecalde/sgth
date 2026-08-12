<?php

use App\Enums\Permiso;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Preparar el permiso y el rol necesario
    $permiso = Permission::firstOrCreate(['name' => Permiso::VER_ESTRUCTURA->value, 'guard_name' => 'sanctum']);
    $rol = Role::firstOrCreate(['name' => 'admin-test', 'guard_name' => 'sanctum']);
    $rol->givePermissionTo($permiso);
    
    // Crear el usuario de prueba con el flag de primer_login en false para no ser bloqueado por el middleware
    $this->user = User::factory()->create([
        'primer_login' => false,
        'password'     => Hash::make('password123'),
    ]);
    $this->user->assignRole($rol);
});

test('puede obtener el organigrama jerárquico completo', function () {
    // 1. Crear Estructura Base manualmente
    $raiz = UnidadAdministrativa::create([
        'codigo'          => 'GAD-01',
        'nombre'          => 'GAD Provincial',
        'descripcion'     => 'Raiz',
        'unidad_padre_id' => null,
        'nivel'           => 1,
        'estado'          => true,
    ]);

    $direccion = UnidadAdministrativa::create([
        'codigo'          => 'DIR-01',
        'nombre'          => 'Direccion A',
        'descripcion'     => 'Direccion',
        'unidad_padre_id' => $raiz->id,
        'nivel'           => 2,
        'estado'          => true,
    ]);

    $subproceso = UnidadAdministrativa::create([
        'codigo'          => 'SUB-01',
        'nombre'          => 'Subproceso A',
        'descripcion'     => 'Subproceso',
        'unidad_padre_id' => $direccion->id,
        'nivel'           => 3,
        'estado'          => true,
    ]);

    // 2. Crear Puestos
    Puesto::create([
        'codigo'                   => 'PUE-01',
        'denominacion'             => 'Prefecto',
        'unidad_administrativa_id' => $raiz->id,
        'grupo_ocupacional'        => 'Nivel Jerárquico Superior',
        'grado_rmu'                => 10,
        'rmu'                      => 5000.00,
        'es_jefe'                  => true,
        'nivel'                    => 1,
        'estado'                   => true,
    ]);

    Puesto::create([
        'codigo'                   => 'PUE-02',
        'denominacion'             => 'Analista',
        'unidad_administrativa_id' => $subproceso->id,
        'grupo_ocupacional'        => 'Servidor Público 5',
        'grado_rmu'                => 5,
        'rmu'                      => 1200.00,
        'es_jefe'                  => false,
        'nivel'                    => 3,
        'estado'                   => true,
    ]);

    // 3. Actuar como usuario autorizado y solicitar la ruta
    $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/estructura/organigrama');

    // 4. Validar respuestas y anidamientos
    //
    // El organigrama devuelve el conteo de puestos, no la lista: es un árbol
    // que se pinta completo de una sola vez, y arrastrar cada puesto en cada
    // nivel lo vuelve una respuesta pesada para mostrar un número. Quien
    // necesita los puestos de una unidad los pide por su propio endpoint.
    $response->assertStatus(200)
        ->assertJsonStructure([
            'exito',
            'mensaje',
            'datos' => [
                '*' => [
                    'id',
                    'codigo',
                    'nombre',
                    'puestos_count',
                    'subrogaciones_vigentes',
                    'hijos' => [
                        '*' => [
                            'id',
                            'nombre',
                            'puestos_count',
                            'hijos' => [
                                '*' => [
                                    'id',
                                    'nombre',
                                    'puestos_count',
                                ]
                            ]
                        ]
                    ],
                ]
            ]
        ])
        ->assertJsonCount(1, 'datos') // Solo debe existir una unidad raíz (nivel 1)
        ->assertJsonPath('datos.0.codigo', 'GAD-01')
        ->assertJsonPath('datos.0.hijos.0.codigo', 'DIR-01')
        ->assertJsonPath('datos.0.hijos.0.hijos.0.codigo', 'SUB-01')
        ->assertJsonPath('datos.0.puestos_count', 1)
        ->assertJsonPath('datos.0.hijos.0.hijos.0.puestos_count', 1);
});

test('usuario sin permiso de ver estructura recibe 403', function () {
    $usuarioSinPermisos = User::factory()->create(['primer_login' => false]);
    
    $response = $this->actingAs($usuarioSinPermisos, 'sanctum')->getJson('/api/v1/estructura/organigrama');
    
    $response->assertStatus(403);
});
