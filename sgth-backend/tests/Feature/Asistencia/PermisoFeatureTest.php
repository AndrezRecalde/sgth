<?php

use App\Enums\EstadoPermiso;
use App\Enums\TipoPermiso;
use App\Enums\RegimenLaboral;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\Asistencia\PermisoServidor;
use App\Models\User;
use App\Jobs\Asistencia\VencerPermisosJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();
    PermisoServidor::unguard();

    $this->userJefe = User::create([
        'name' => 'Jefe Unidad',
        'email' => 'jefe@example.com',
        'usuario_ti' => 'jefe_u',
        'password' => bcrypt('123456'),
        'primer_login' => false,
    ]);

    $this->userSubordinado = User::create([
        'name' => 'Subordinado',
        'email' => 'sub@example.com',
        'usuario_ti' => 'sub_u',
        'password' => bcrypt('123456'),
        'primer_login' => false,
    ]);

    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'U01_'.uniqid(),
        'nombre' => 'Dirección de TI',
        'estado' => true,
        'nivel' => 1,
    ]);

    $this->puestoJefe = Puesto::create([
        'codigo' => 'P01_'.uniqid(),
        'denominacion' => 'Director',
        'unidad_administrativa_id' => $this->unidad->id,
        'grupo_ocupacional' => 'Directivo',
        'grado_rmu' => 15,
        'rmu' => 2000.00,
        'nivel' => 1,
        'es_jefe' => true,
        'estado' => true,
    ]);

    $this->puestoSubordinado = Puesto::create([
        'codigo' => 'P02_'.uniqid(),
        'denominacion' => 'Analista',
        'unidad_administrativa_id' => $this->unidad->id,
        'grupo_ocupacional' => 'Profesional',
        'grado_rmu' => 10,
        'rmu' => 1000.00,
        'nivel' => 1,
        'es_jefe' => false,
        'estado' => true,
    ]);

    $this->servidorJefe = Servidor::create([
        'cedula' => '0801234561',
        'nombre' => 'Juan',
        'apellido' => 'Jefe',
        'user_id' => $this->userJefe->id,
        'puesto_id' => $this->puestoJefe->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'estado' => true,
    ]);

    $this->servidorSubordinado = Servidor::create([
        'cedula' => '0801234562',
        'nombre' => 'Pedro',
        'apellido' => 'Subordinado',
        'user_id' => $this->userSubordinado->id,
        'puesto_id' => $this->puestoSubordinado->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'estado' => true,
    ]);
});

test('permiso_personal_no_puede_exceder_4_horas', function () {
    $response = $this->actingAs($this->userSubordinado, 'sanctum')->postJson('/api/v1/asistencia/permisos', [
        'tipo' => TipoPermiso::PERSONAL->value,
        'fecha' => now()->addDay()->format('Y-m-d'),
        'hora_inicio' => '08:00',
        'hora_fin' => '13:00', // 5 horas
    ]);

    $response->assertStatus(422);
});

test('permiso_oficial_requiere_observacion', function () {
    $response = $this->actingAs($this->userSubordinado, 'sanctum')->postJson('/api/v1/asistencia/permisos', [
        'tipo' => TipoPermiso::OFICIAL->value,
        'fecha' => now()->addDay()->format('Y-m-d'),
        'hora_inicio' => '08:00',
        'hora_fin' => '10:00',
        'observacion' => '',
    ]);

    $response->assertStatus(422);
});

test('permiso_pasa_a_activo_al_confirmar_recepcion', function () {
    $permiso = PermisoServidor::create([
        'servidor_id' => $this->servidorSubordinado->id,
        'tipo' => TipoPermiso::PERSONAL->value,
        'fecha' => now()->addDay()->format('Y-m-d'),
        'hora_inicio' => '08:00',
        'hora_fin' => '10:00',
        'estado' => EstadoPermiso::PENDIENTE->value,
        'vence_en' => now()->addDays(4),
        'folio' => 'PER-2026-00001',
    ]);

    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $userRecepcion = User::create([
        'name' => 'Recepción',
        'email' => 'rec@example.com',
        'usuario_ti' => 'rec_u',
        'password' => bcrypt('123456'),
        'primer_login' => false,
    ]);
    $userRecepcion->assignRole('recepcion');
    
    $response = $this->actingAs($userRecepcion, 'sanctum')->postJson("/api/v1/asistencia/permisos/confirmar/{$permiso->folio}");

    $response->assertStatus(200);
    
    $permiso->refresh();
    expect($permiso->estado)->toBe(EstadoPermiso::ACTIVO);
});

test('permiso_pasa_a_falta_injustificada_a_las_72h', function () {
    $permiso = PermisoServidor::create([
        'servidor_id' => $this->servidorSubordinado->id,
        'tipo' => TipoPermiso::PERSONAL->value,
        'fecha' => now()->subDays(5)->format('Y-m-d'),
        'hora_inicio' => '08:00',
        'hora_fin' => '10:00',
        'estado' => EstadoPermiso::PENDIENTE->value,
        'vence_en' => now()->subDays(1), // Ya venció
        'folio' => 'PER-2026-00002',
    ]);

    // Ejecutamos el Job
    $job = new VencerPermisosJob();
    $job->handle();

    $permiso->refresh();
    expect($permiso->estado)->toBe(EstadoPermiso::FALTA_INJUSTIFICADA);
});

test('jefe_puede_ver_permisos_de_su_unidad', function () {
    PermisoServidor::create([
        'servidor_id' => $this->servidorSubordinado->id,
        'tipo' => TipoPermiso::PERSONAL->value,
        'fecha' => now()->addDay()->format('Y-m-d'),
        'hora_inicio' => '08:00',
        'hora_fin' => '10:00',
        'estado' => EstadoPermiso::PENDIENTE->value,
        'vence_en' => now()->addDays(4),
        'folio' => 'PER-2026-00003',
    ]);

    $response = $this->actingAs($this->userJefe, 'sanctum')->getJson('/api/v1/asistencia/permisos');

    $response->assertStatus(200);
    $response->assertJsonFragment([
        'folio' => 'PER-2026-00003',
    ]);
});

test('jefe_no_puede_ver_motivo_de_permiso_personal', function () {
    $permiso = PermisoServidor::create([
        'servidor_id' => $this->servidorSubordinado->id,
        'tipo' => TipoPermiso::PERSONAL->value,
        'fecha' => now()->addDay()->format('Y-m-d'),
        'hora_inicio' => '08:00',
        'hora_fin' => '10:00',
        'observacion' => 'Consulta medica privada',
        'estado' => EstadoPermiso::PENDIENTE->value,
        'vence_en' => now()->addDays(4),
        'folio' => 'PER-2026-00004',
    ]);

    $response = $this->actingAs($this->userJefe, 'sanctum')->getJson("/api/v1/asistencia/permisos/{$permiso->id}");

    $response->assertStatus(200);
    
    $datos = $response->json('datos');
    expect($datos['observacion'] ?? null)->toBeNull();
});
