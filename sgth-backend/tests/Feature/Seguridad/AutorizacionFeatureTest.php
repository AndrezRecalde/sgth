<?php

use App\Enums\TipoPermiso;
use App\Models\Asistencia\PermisoServidor;
use App\Models\Nomina\Nomina;
use App\Models\Nomina\RolPago;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();
    PermisoServidor::unguard();
    Nomina::unguard();
    RolPago::unguard();

    Role::firstOrCreate(['name' => 'servidor', 'guard_name' => 'sanctum']);
    Role::firstOrCreate(['name' => 'recepcion', 'guard_name' => 'sanctum']);
    Role::firstOrCreate(['name' => 'trabajo-social', 'guard_name' => 'sanctum']);

    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'U01',
        'nombre' => 'Direccion Test',
        'estado' => true,
        'nivel' => 1,
    ]);

    $this->puesto = Puesto::create([
        'codigo' => 'P01',
        'denominacion' => 'Analista',
        'unidad_administrativa_id' => $this->unidad->id,
        'grupo_ocupacional' => 'Profesional',
        'grado_rmu' => 10,
        'rmu' => 1000.00,
        'nivel' => 1,
        'es_jefe' => false,
        'estado' => true,
    ]);

    // Crear Servidor A
    $this->userA = User::create([
        'name' => 'Servidor A',
        'email' => 'a@example.com',
        'password' => bcrypt('123456'),
        'primer_login' => false,
    ]);
    $this->userA->assignRole('servidor');
    
    $this->servidorA = Servidor::create([
        'cedula' => '0800000001',
        'nombre' => 'Servidor',
        'apellido' => 'A',
        'user_id' => $this->userA->id,
        'puesto_id' => $this->puesto->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'regimen_laboral' => \App\Enums\RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(2),
        'estado' => true,
    ]);

    // Crear Servidor B
    $this->userB = User::create([
        'name' => 'Servidor B',
        'email' => 'b@example.com',
        'password' => bcrypt('123456'),
        'primer_login' => false,
    ]);
    $this->userB->assignRole('servidor');
    
    $this->servidorB = Servidor::create([
        'cedula' => '0800000002',
        'nombre' => 'Servidor',
        'apellido' => 'B',
        'user_id' => $this->userB->id,
        'puesto_id' => $this->puesto->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'regimen_laboral' => \App\Enums\RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(2),
        'estado' => true,
    ]);
});

test('servidor_no_accede_a_nomina_de_otro', function () {
    $nomina = Nomina::create([
        'periodo' => '2026-05',
        'fecha_inicio' => '2026-05-01',
        'fecha_fin' => '2026-05-31',
        'estado' => 'cerrada',
        'total_ingresos' => 1000,
        'total_descuentos' => 100,
        'total_neto' => 900,
    ]);

    RolPago::create([
        'nomina_id' => $nomina->id,
        'servidor_id' => $this->servidorB->id,
        'total_ingresos' => 1000,
        'total_descuentos' => 100,
        'total_neto' => 900,
    ]);

    // A intenta ver el rol de pago de B
    $response = $this->actingAs($this->userA, 'sanctum')
        ->getJson("/api/v1/nomina/{$nomina->id}/rol-pago/{$this->servidorB->id}");

    // Podría devolver 403 Forbidden o 404 (si el scope lo oculta completamente)
    // Generalmente para control de acceso directo es 403. Laravel a veces lanza 404 si es Route Model Binding con Policy.
    expect(in_array($response->status(), [403, 404]))->toBeTrue();
});

test('servidor_no_accede_a_expediente_de_otro', function () {
    $response = $this->actingAs($this->userA, 'sanctum')
        ->getJson("/api/v1/expediente/servidores/{$this->servidorB->id}");

    expect(in_array($response->status(), [403, 404]))->toBeTrue();
});

test('rol_recepcion_solo_puede_confirmar_permisos', function () {
    $userRecepcion = User::create([
        'name' => 'Recepcion',
        'email' => 'rec@example.com',
        'password' => bcrypt('123456'),
        'primer_login' => false,
    ]);
    $userRecepcion->assignRole('recepcion');

    $permiso = PermisoServidor::create([
        'servidor_id' => $this->servidorB->id,
        'folio' => 'PER-001',
        'tipo' => TipoPermiso::PERSONAL->value,
        'fecha' => now()->format('Y-m-d'),
        'hora_inicio' => '08:00:00',
        'hora_fin' => '10:00:00',
        'estado' => \App\Enums\EstadoPermiso::PENDIENTE->value,
        'vence_en' => now()->addDays(2)->format('Y-m-d H:i:s'),
    ]);

    // Aserción 1: usuario recepcion puede confirmar un permiso -> 200
    $response1 = $this->actingAs($userRecepcion, 'sanctum')
        ->postJson("/api/v1/asistencia/permisos/confirmar/{$permiso->folio}");
    
    $response1->assertStatus(200);

    // Aserción 2: usuario recepcion NO puede anular un permiso -> 403
    $response2 = $this->actingAs($userRecepcion, 'sanctum')
        ->putJson("/api/v1/asistencia/permisos/{$permiso->id}/anular");
    
    // Si no tiene permiso de administrador uath/jefe, recibe 403
    if ($response2->status() === 500) {
        dump($response2->json());
    }
    $response2->assertStatus(403);
});

test('rol_trabajo_social_solo_puede_validar_enf_calamidad', function () {
    $this->withExceptionHandling();

    $userTS = User::create([
        'name' => 'Trabajo Social',
        'email' => 'ts@example.com',
        'password' => bcrypt('123456'),
        'primer_login' => false,
    ]);
    $userTS->assignRole('trabajo-social');

    $permisoEnfermedad = PermisoServidor::create([
        'servidor_id' => $this->servidorB->id,
        'folio' => 'PER-002',
        'tipo' => TipoPermiso::ENFERMEDAD->value,
        'fecha' => now()->format('Y-m-d'),
        'hora_inicio' => '08:00:00',
        'hora_fin' => '10:00:00',
        'estado' => \App\Enums\EstadoPermiso::ACTIVO->value,
        'vence_en' => now()->addDays(2)->format('Y-m-d H:i:s'),
    ]);

    $permisoPersonal = PermisoServidor::create([
        'servidor_id' => $this->servidorB->id,
        'folio' => 'PER-003',
        'tipo' => TipoPermiso::PERSONAL->value,
        'fecha' => now()->format('Y-m-d'),
        'hora_inicio' => '08:00:00',
        'hora_fin' => '10:00:00',
        'estado' => \App\Enums\EstadoPermiso::ACTIVO->value,
        'vence_en' => now()->addDays(2)->format('Y-m-d H:i:s'),
    ]);

    // Aserción 1: sí puede con enfermedad
    $response1 = $this->actingAs($userTS, 'sanctum')
        ->postJson("/api/v1/asistencia/permisos/{$permisoEnfermedad->id}/validar-ts", [
            'es_valido' => true,
            'observaciones' => 'OK',
        ]);
    
    $response1->assertStatus(200);

    // Aserción 2: recibe 422 al intentar validar un permiso personal porque la regla de negocio falla
    $response2 = $this->actingAs($userTS, 'sanctum')
        ->postJson("/api/v1/asistencia/permisos/{$permisoPersonal->id}/validar-ts", [
            'es_valido' => true,
            'observaciones' => 'OK',
        ]);
        
    $response2->assertStatus(422)
              ->assertJsonFragment([
                  'mensaje' => 'La validación de Trabajo Social solo aplica para permisos por Enfermedad o Calamidad Doméstica.',
              ]);
});

test('usuario_sin_primer_login_bloqueado_en_todos_endpoints', function () {
    $userNuevo = User::create([
        'name' => 'Nuevo',
        'email' => 'nuevo@example.com',
        'password' => bcrypt('0800000003'),
        'primer_login' => true,
    ]);
    $userNuevo->assignRole('servidor');

    $response = $this->actingAs($userNuevo, 'sanctum')
        ->getJson('/api/v1/autoservicio/mi-expediente');

    $response->assertStatus(403);
    $response->assertJson([
        'exito' => false,
        'mensaje' => 'Por seguridad, debe cambiar su contraseña inicial (número de cédula) antes de continuar.',
    ]);
});

test('token_invalido_devuelve_401_no_500', function () {
    $response = $this->withHeaders([
            'Authorization' => 'Bearer token_basura_12345',
        ])
        ->getJson('/api/v1/autoservicio/mi-expediente');

    $response->assertStatus(401);
    
    // Verificamos que no sea 500 y que esté devolviendo el error genérico de auth
    expect($response->status())->toBe(401);
    $response->assertJsonFragment([
        'mensaje' => 'No autenticado. Por favor inicie sesión.',
    ]);
});
