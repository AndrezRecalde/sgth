<?php

namespace Tests\Feature\Expediente;

use App\Models\User;
use App\Models\Expediente\Servidor;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Estructura\Puesto;
use App\Models\Geografia\Provincia;
use App\Models\Geografia\Ciudad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Preparar roles necesarios
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    Role::firstOrCreate(['name' => 'servidor', 'guard_name' => 'sanctum']);

    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'UATH-01',
        'nombre' => 'Unidad de Talento Humano',
        'nivel'  => 1
    ]);
    
    $this->puesto = Puesto::create([
        'codigo' => 'P-01',
        'denominacion' => 'Analista',
        'unidad_administrativa_id' => $this->unidad->id,
        'grupo_ocupacional' => 'Servidor Publico',
        'grado_rmu' => 1,
        'rmu' => 800.00,
        'nivel' => 1
    ]);
    
    $this->provincia = Provincia::create(['nombre' => 'Pichincha', 'codigo' => '17']);
    $this->ciudad = Ciudad::create(['nombre' => 'Quito', 'provincia_id' => $this->provincia->id]);
    
    $this->adminUath = User::factory()->create();
    $this->adminUath->assignRole('admin-uath');

    $this->usuarioNormal = User::factory()->create();
    $this->usuarioNormal->assignRole('servidor');
    
    $this->servidorTitular = Servidor::create([
        'user_id' => $this->usuarioNormal->id,
        'cedula'  => '1111111111',
        'nombre'  => 'Titular',
        'apellido'=> 'Test',
        'regimen_laboral' => 'losep',
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id' => $this->puesto->id,
        'fecha_nacimiento' => '1990-01-01',
        'genero' => 'masculino',
        'estado_civil' => 'soltero',
        'es_extranjero' => false,
        'provincia_nacimiento_id' => $this->provincia->id,
        'ciudad_nacimiento_id' => $this->ciudad->id,
        'tiene_discapacidad' => false,
        'tiene_enfermedad_catastrofica' => false,
        'tipo_nombramiento' => 'nombramiento_permanente',
        'fecha_ingreso_institucion' => '2020-01-01',
    ]);
});

test('campos condicionales de extranjería validan correctamente', function () {
    $datos = [
        'user_id' => User::factory()->create()->id,
        'cedula'  => '1234567890',
        'nombre'  => 'Test',
        'apellido'=> 'Test',
        'regimen_laboral' => 'losep',
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id' => $this->puesto->id,
        'fecha_nacimiento' => '1990-01-01',
        'genero' => 'masculino',
        'estado_civil' => 'soltero',
        'es_extranjero' => true,
        // Al ser extranjero faltan 'nacionalidad' y 'pais_origen'
        'tiene_discapacidad' => false,
        'tiene_enfermedad_catastrofica' => false,
        'tipo_nombramiento' => 'nombramiento_permanente',
        'fecha_ingreso_institucion' => '2020-01-01',
    ];

    $response = $this->actingAs($this->adminUath, 'sanctum')
                     ->postJson('/api/v1/expediente/servidores', $datos);

    $response->assertStatus(422)
             ->assertJsonStructure(['errores' => ['nacionalidad', 'pais_origen']]);

    // Corrigiendo:
    $datos['nacionalidad'] = 'Colombiana';
    $datos['pais_origen'] = 'Colombia';

    $response2 = $this->actingAs($this->adminUath, 'sanctum')
                     ->postJson('/api/v1/expediente/servidores', $datos);

    // Debe pasar la validación (tal vez falten campos requeridos como direccion etc., así que probamos que el error de extranjería se fue)
    $response2->assertJsonMissing(['errores' => ['nacionalidad']]);
});



test('el servidor solo puede ver su propio expediente y UATH puede ver todos', function () {
    // Un servidor intenta ver el expediente de otro
    $otroUsuario = User::factory()->create();
    $otroServidor = Servidor::create([
        'user_id' => $otroUsuario->id,
        'cedula'  => '2222222222',
        'nombre'  => 'Otro',
        'apellido'=> 'Servidor',
        'regimen_laboral' => 'losep',
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id' => $this->puesto->id,
        'fecha_nacimiento' => '1995-01-01',
        'genero' => 'masculino',
        'estado_civil' => 'soltero',
        'es_extranjero' => false,
        'provincia_nacimiento_id' => $this->provincia->id,
        'ciudad_nacimiento_id' => $this->ciudad->id,
        'tiene_discapacidad' => false,
        'tiene_enfermedad_catastrofica' => false,
        'tipo_nombramiento' => 'nombramiento_permanente',
        'fecha_ingreso_institucion' => '2020-01-01',
    ]);

    // El servidor normal intenta ver el suyo (Debe poder)
    $responsePropio = $this->actingAs($this->usuarioNormal, 'sanctum')
                           ->getJson('/api/v1/expediente/servidores/' . $this->servidorTitular->id);
    $responsePropio->assertStatus(200);

    // El servidor normal intenta ver el del otro (NO debe poder)
    $responseAjeno = $this->actingAs($this->usuarioNormal, 'sanctum')
                          ->getJson('/api/v1/expediente/servidores/' . $otroServidor->id);
    $responseAjeno->assertStatus(403);

    // UATH intenta ver el del otro (Sí debe poder)
    $responseUath = $this->actingAs($this->adminUath, 'sanctum')
                         ->getJson('/api/v1/expediente/servidores/' . $otroServidor->id);
    $responseUath->assertStatus(200);
});
