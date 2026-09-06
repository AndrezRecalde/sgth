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

    // El módulo pasó a resolver el acceso con la matriz de permisos del
    // seeder en vez de con dos roles escritos a mano en el controlador, así
    // que los usuarios de prueba ya no pueden nacer sin rol: sin `ver-permisos`
    // el listado responde 403, que es justamente lo que se quería.
    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $this->userJefe = User::create([
        'email' => 'jefe@example.com',
        'usuario_ti' => 'jefe_u',
        'password' => bcrypt('123456'),
        'primer_login' => false,
    ]);

    $this->userSubordinado = User::create([
        'email' => 'sub@example.com',
        'usuario_ti' => 'sub_u',
        'password' => bcrypt('123456'),
        'primer_login' => false,
    ]);

    $this->unidad = unidadDePrueba(['nombre' => 'Dirección de TI']);

    $this->puestoJefe        = puestoJefeDePrueba($this->unidad);
    $this->puestoSubordinado = puestoDePrueba($this->unidad);

    $this->servidorJefe = Servidor::create([
        'cedula' => '0801234561',
        'nombre' => 'Juan',
        'apellido' => 'Jefe',
        'puesto_id' => $this->puestoJefe->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'estado' => true,
    ]);

    $this->servidorSubordinado = Servidor::create([
        'cedula' => '0801234562',
        'nombre' => 'Pedro',
        'apellido' => 'Subordinado',
        'puesto_id' => $this->puestoSubordinado->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'estado' => true,
    ]);

    // La FK va de users a servidores, no al revés: servidores.user_id se
    // eliminó al invertir la relación. Puesto en el Servidor era ignorado en
    // silencio, así que el usuario y su servidor nunca quedaban enlazados.
    $this->userJefe->update(['servidor_id' => $this->servidorJefe->id]);
    $this->userSubordinado->update(['servidor_id' => $this->servidorSubordinado->id]);

    $this->userJefe->assignRole('jefe-unidad');
    $this->userSubordinado->assignRole('servidor');
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
    // Confirmar un permiso personal descuenta del saldo vacacional, y ahora
    // exige que haya de dónde descontar: antes, sin período abierto, el
    // descuento se saltaba en silencio y las horas se concedían gratis.
    \App\Models\Asistencia\PeriodoVacacion::create([
        'servidor_id'          => $this->servidorSubordinado->id,
        'anio'                 => (int) now()->format('Y'),
        'fecha_inicio_periodo' => now()->startOfYear()->toDateString(),
        'fecha_fin_periodo'    => now()->endOfYear()->toDateString(),
        'regimen'              => 'losep',
        'anios_antiguedad'     => 2,
        'dias_generados'       => 15,
        'dias_utilizados'      => 0,
        'dias_saldo'           => 15,
        'saldo_acumulado'      => 15,
        'estado'               => 'abierto',
    ]);

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

    $userRecepcion = User::create([
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
