<?php

/*
| El rol `medico-ocupacional` (decisión del 2026-10-29).
|
| La FEMO, las solicitudes y la cobertura dejan de ser de todo médico: son del
| que hace las evaluaciones ocupacionales. El rol es complementario —se da
| junto con `medico`, que sigue dando la cola, la especialidad y «Mi
| jornada»— y no se puede asignar solo.
*/

use App\Enums\Permiso;
use App\Models\Dispensario\FichaSaludOcupacional;
use App\Models\Dispensario\SolicitudCertificacionMedica;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class, RefreshDatabase::class);

function usuarioConRolesOcupacional(array $roles): User
{
    $usuario = User::factory()->create();
    $usuario->assignRole($roles);

    return $usuario;
}

beforeEach(function () {
    $this->seed(RolPermisoSeeder::class);

    $this->general = usuarioConRolesOcupacional(['medico']);
    $this->ocupacional = usuarioConRolesOcupacional(['medico', 'medico-ocupacional']);
});

test('el médico general ya no entra a la salud ocupacional; el ocupacional, sí', function (string $metodo, string $ruta) {
    $this->actingAs($this->general, 'sanctum')->json($metodo, "/api/v1/dispensario/{$ruta}")->assertForbidden();

    expect($this->actingAs($this->ocupacional, 'sanctum')->json($metodo, "/api/v1/dispensario/{$ruta}")->status())
        ->not->toBe(403);
})->with([
    'fichas FEMO'             => ['GET', 'fichas-sso'],
    'llenar una ficha'        => ['POST', 'fichas-sso'],
    'solicitudes'             => ['GET', 'solicitudes-certificacion'],
    'iniciar la evaluación'   => ['PATCH', 'solicitudes-certificacion/999999/iniciar'],
    'cobertura'               => ['GET', 'certificaciones/cobertura'],
    'tablero SSO'             => ['GET', 'salud-ocupacional/tablero'],
    'exámenes por riesgo'     => ['GET', 'fichas-sso/examenes-por-riesgo'],
]);

test('los dos siguen siendo médicos: cola, «Mi jornada» y reportes', function (string $ruta) {
    foreach ([$this->general, $this->ocupacional] as $medico) {
        $this->actingAs($medico, 'sanctum')->getJson("/api/v1/dispensario/{$ruta}")->assertOk();
    }
})->with([
    'mi-jornada',
    'agenda/turnos-del-dia?tipo_atencion=medicina_general',
    'reportes',
]);

test('«Mi jornada» le cuenta las evaluaciones listas solo al médico ocupacional', function () {
    $solicitud = SolicitudCertificacionMedica::create([
        'tipo_evento' => 'periodica', 'origen' => 'expediente',
        'cedula_paciente' => '0800000001', 'nombres_paciente' => 'Ana Lista',
        'solicitado_por' => $this->general->id, 'estado' => 'pendiente',
    ]);
    DB::table('solicitud_constantes_vitales')->insert([
        'solicitud_id' => $solicitud->id, 'enfermera_id' => usuarioConRolesOcupacional(['enfermera'])->id,
        'registrado_en' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    $pendientes = fn (User $medico) => $this->actingAs($medico, 'sanctum')
        ->getJson('/api/v1/dispensario/mi-jornada?perfil=medico')->assertOk()->json('datos.pendientes');

    expect($pendientes($this->ocupacional)['evaluaciones_listas'])->toBe(1)
        ->and($pendientes($this->general)['evaluaciones_listas'])->toBe(0)
        ->and($pendientes($this->general)['fichas_femo'])->toBe(0);
});

test('medico-ocupacional no se asigna sin medico', function () {
    $gestor = User::factory()->create();
    $gestor->givePermissionTo(Permission::firstOrCreate(['name' => Permiso::GESTIONAR_USUARIOS->value, 'guard_name' => 'sanctum']));
    $otro = usuarioConRolesOcupacional(['servidor']);
    $mensaje = 'El rol Médico ocupacional se asigna junto con Médico.';

    $this->actingAs($gestor, 'sanctum')
        ->putJson("/api/v1/admin/usuarios/{$otro->id}", ['roles' => ['medico-ocupacional']])
        ->assertUnprocessable()
        ->assertJsonPath('errores.roles.0', $mensaje);

    $this->actingAs($gestor, 'sanctum')
        ->postJson('/api/v1/admin/usuarios', [
            'email' => 'ocupacional@example.com', 'usuario_ti' => 'ocupacional', 'roles' => ['medico-ocupacional'],
        ])
        ->assertUnprocessable()
        ->assertJsonPath('errores.roles.0', $mensaje);

    $this->actingAs($gestor, 'sanctum')
        ->putJson("/api/v1/admin/usuarios/{$otro->id}", ['roles' => ['medico', 'medico-ocupacional']])
        ->assertOk();

    expect($otro->fresh()->getRoleNames()->all())->toEqualCanonicalizing(['medico', 'medico-ocupacional']);
});

test('la migración pasa la salud ocupacional al rol nuevo y se lo da a quien ya evaluó una FEMO', function () {
    $migracion = require database_path('migrations/2026_12_11_000000_crear_rol_medico_ocupacional.php');
    $titulares = fn () => collect(RolPermisoSeeder::PERMISOS_MEDICO_OCUPACIONAL)->mapWithKeys(fn (Permiso $p) => [
        $p->value => Role::where('guard_name', 'sanctum')
            ->whereHas('permissions', fn ($q) => $q->where('name', $p->value))
            ->orderBy('name')->pluck('name')->all(),
    ])->all();
    $delSeeder = $titulares();

    // Como estaba antes: todo médico con la salud ocupacional y sin el rol.
    $migracion->down();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    expect(Role::where('name', 'medico-ocupacional')->exists())->toBeFalse()
        ->and(User::find($this->general->id)->can('gestionar-fichas-sso-med'))->toBeTrue();

    // Un médico que ya evaluó una FEMO, otro que nunca lo hizo y una
    // enfermera que figura como evaluadora (no recibe el rol: no es médico).
    Servidor::unguard();
    $evaluado = Servidor::create(['cedula' => '0800000002', 'nombre' => 'Luis', 'apellido' => 'Evaluado']);
    $evaluador = usuarioConRolesOcupacional(['medico']);
    $enfermera = usuarioConRolesOcupacional(['enfermera']);
    foreach ([$evaluador, $enfermera] as $quien) {
        FichaSaludOcupacional::create([
            'servidor_id' => $evaluado->id, 'evaluador_id' => $quien->id,
            'fecha_evaluacion' => '2026-10-01', 'tipo_ficha' => 'periodica', 'estado' => true,
        ]);
    }

    $migracion->up();
    $migracion->up(); // idempotente
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($titulares())->toBe($delSeeder)
        ->and(User::find($evaluador->id)->getRoleNames()->all())->toEqualCanonicalizing(['medico', 'medico-ocupacional'])
        ->and(User::find($this->general->id)->hasRole('medico-ocupacional'))->toBeFalse()
        ->and(User::find($this->general->id)->can('gestionar-fichas-sso-med'))->toBeFalse()
        ->and(User::find($enfermera->id)->hasRole('medico-ocupacional'))->toBeFalse();
});
