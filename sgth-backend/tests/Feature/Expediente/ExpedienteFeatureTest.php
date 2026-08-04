<?php

namespace Tests\Feature\Expediente;

use App\Models\User;
use App\Models\Expediente\Servidor;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Estructura\Puesto;
use App\Models\Geografia\Provincia;
use App\Models\Geografia\Canton;
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
    $this->canton = Canton::create(['nombre' => 'Quito', 'provincia_id' => $this->provincia->id, 'codigo' => '1701']);
    
    $this->adminUath = User::factory()->create();
    $this->adminUath->assignRole('admin-uath');

    $this->usuarioNormal = User::factory()->create();
    $this->usuarioNormal->assignRole('servidor');

    // servidores.user_id no existe (la FK se invirtió en
    // 2026_05_27_161227_reestructurar_relacion_users_servidores.php):
    // el vínculo se hace desde users.servidor_id.
    $this->servidorTitular = Servidor::create([
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
        'canton_nacimiento_id' => $this->canton->id,
        'tiene_discapacidad' => false,
        'tiene_enfermedad_catastrofica' => false,
        'tipo_nombramiento' => 'nombramiento_permanente',
        'fecha_ingreso_institucion' => '2020-01-01',
    ]);

    $this->usuarioNormal->update(['servidor_id' => $this->servidorTitular->id]);
});

test('campos condicionales de extranjería validan correctamente', function () {
    // POST /expediente/servidores (StoreServidorRequest) se retiró — sin
    // consumidor real, contradecía la materialización tardía vía
    // creaVinculo(). La creación real de servidores pasa por 'basico'
    // (StoreServidorBasicoRequest), que tiene la misma validación
    // condicional de extranjería.
    $datos = [
        'cedula'  => '1234567890',
        'nombre'  => 'Test',
        'apellido'=> 'Test',
        'fecha_nacimiento' => '1990-01-01',
        'genero' => 'masculino',
        'estado_civil' => 'soltero',
        'es_extranjero' => true,
        // Al ser extranjero faltan 'nacionalidad' y 'pais_origen'
        'tiene_discapacidad' => false,
        'tiene_enfermedad_catastrofica' => false,
    ];

    $response = $this->actingAs($this->adminUath, 'sanctum')
                     ->postJson('/api/v1/expediente/servidores/basico', $datos);

    $response->assertStatus(422)
             ->assertJsonStructure(['errores' => ['nacionalidad', 'pais_origen']]);

    // Corrigiendo:
    $datos['nacionalidad'] = 'Colombiana';
    $datos['pais_origen'] = 'Colombia';

    $response2 = $this->actingAs($this->adminUath, 'sanctum')
                     ->postJson('/api/v1/expediente/servidores/basico', $datos);

    // Debe pasar la validación (tal vez falten campos requeridos como direccion etc., así que probamos que el error de extranjería se fue)
    $response2->assertJsonMissing(['errores' => ['nacionalidad']]);
});



test('el servidor solo puede ver su propio expediente y UATH puede ver todos', function () {
    // Un servidor intenta ver el expediente de otro
    $otroServidor = Servidor::create([
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
        'canton_nacimiento_id' => $this->canton->id,
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
