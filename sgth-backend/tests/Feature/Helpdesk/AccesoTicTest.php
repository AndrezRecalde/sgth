<?php

use App\Enums\EstadoTicket;
use App\Enums\RegimenLaboral;
use App\Models\Expediente\Servidor;
use App\Models\Helpdesk\ComentarioTicket;
use App\Models\Helpdesk\Sla;
use App\Models\Helpdesk\Ticket;
use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
| Quién entra a qué en TIC (docs/diseno-subsistema-tic.md, 4.4).
|
| Hasta el PR 0.2 el listado de tickets devolvía los de todos a cualquiera, el
| detalle y los comentarios no miraban de quién era el ticket, y el inventario
| iba por rol. Las pruebas usan los roles reales del seeder y ninguna con
| admin-ti, que se salta la policy por el Gate::before.
*/

uses(Tests\TestCase::class, RefreshDatabase::class);

function usuarioDeAccesoTic(string $rol, ?int $servidorId = null): User
{
    $usuario = User::factory()->create(['servidor_id' => $servidorId, 'primer_login' => false]);
    $usuario->assignRole($rol);

    return $usuario;
}

function servidorDeAccesoTic(string $cedula): Servidor
{
    return Servidor::create([
        'cedula' => $cedula, 'nombre' => 'Ana', 'apellido' => 'Prueba',
        'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true,
    ]);
}

function ticketDeAccesoTic(Servidor $solicitante, ?User $tecnico = null): Ticket
{
    static $n = 0;
    $n++;

    return Ticket::create([
        'codigo_ticket'  => "TIC-ACC-{$n}",
        'sla_id'         => Sla::firstOrCreate(
            ['prioridad' => 'media'],
            ['tiempo_resolucion_horas' => 24, 'tiempo_respuesta_horas' => 2],
        )->id,
        'solicitante_id' => $solicitante->id,
        'tipo_ticket'    => 'incidente',
        'estado'         => EstadoTicket::ABIERTO->value,
        'asunto'         => "Ticket {$n}",
        'descripcion'    => 'Prueba de acceso',
        'tecnico_id'     => $tecnico?->id,
    ]);
}

beforeEach(function () {
    Servidor::unguard();
    Sla::unguard();
    Ticket::unguard();
    ComentarioTicket::unguard();

    $this->seed(RolPermisoSeeder::class);

    $this->ana = servidorDeAccesoTic('0801111111');
    $this->luis = servidorDeAccesoTic('0802222222');

    $this->usuarioAna = usuarioDeAccesoTic('servidor', $this->ana->id);
    $this->tecnico = usuarioDeAccesoTic('tecnico-dtic');
    $this->otroTecnico = usuarioDeAccesoTic('tecnico-dtic');
    $this->jefe = usuarioDeAccesoTic('jefe-tic');

    $this->deAna = ticketDeAccesoTic($this->ana);
    $this->deLuisSinAsignar = ticketDeAccesoTic($this->luis);
    $this->deLuisDelTecnico = ticketDeAccesoTic($this->luis, $this->tecnico);
    $this->deLuisDeOtro = ticketDeAccesoTic($this->luis, $this->otroTecnico);
});

function codigosVisiblesTic($test, User $usuario): array
{
    return collect($test->actingAs($usuario, 'sanctum')
        ->getJson('/api/v1/helpdesk/tickets')
        ->assertOk()
        ->json('datos'))
        ->pluck('id')->sort()->values()->all();
}

// ── Listado ────────────────────────────────────────────────────────────────

test('el servidor solo ve sus tickets', function () {
    expect(codigosVisiblesTic($this, $this->usuarioAna))->toBe([$this->deAna->id]);
});

test('el tecnico ve su cola y los sin asignar, no los de otro tecnico', function () {
    expect(codigosVisiblesTic($this, $this->tecnico))->toBe(collect([
        $this->deAna->id, $this->deLuisSinAsignar->id, $this->deLuisDelTecnico->id,
    ])->sort()->values()->all());
});

test('el jefe de tic ve todos los tickets', function () {
    expect(codigosVisiblesTic($this, $this->jefe))->toHaveCount(4);
});

// ── Detalle y conversación ─────────────────────────────────────────────────

test('el servidor no abre ni lee la conversacion de un ticket ajeno', function () {
    $this->actingAs($this->usuarioAna, 'sanctum');

    $this->getJson("/api/v1/helpdesk/tickets/{$this->deLuisSinAsignar->id}")->assertForbidden();
    $this->getJson("/api/v1/helpdesk/tickets/{$this->deLuisSinAsignar->id}/comentarios")->assertForbidden();
    $this->postJson("/api/v1/helpdesk/tickets/{$this->deLuisSinAsignar->id}/comentarios", [
        'comentario' => 'Me meto en un ticket ajeno',
    ])->assertForbidden();
});

test('el solicitante abre su ticket sin las notas internas', function () {
    ComentarioTicket::create([
        'ticket_id' => $this->deAna->id, 'user_id' => $this->tecnico->id,
        'comentario' => 'Nota entre tecnicos', 'es_interno' => true,
    ]);
    ComentarioTicket::create([
        'ticket_id' => $this->deAna->id, 'user_id' => $this->tecnico->id,
        'comentario' => 'Respuesta para la usuaria', 'es_interno' => false,
    ]);

    $comentarios = $this->actingAs($this->usuarioAna, 'sanctum')
        ->getJson("/api/v1/helpdesk/tickets/{$this->deAna->id}")
        ->assertOk()
        ->json('datos.comentarios');

    expect(collect($comentarios)->pluck('comentario')->all())->toBe(['Respuesta para la usuaria']);

    $deTecnico = $this->actingAs($this->tecnico, 'sanctum')
        ->getJson("/api/v1/helpdesk/tickets/{$this->deAna->id}/comentarios")
        ->assertOk()
        ->json('datos');

    expect($deTecnico)->toHaveCount(2);
});

// ── Atender y asignar ──────────────────────────────────────────────────────

test('el tecnico no mueve el ticket que tiene otro tecnico', function () {
    $this->actingAs($this->tecnico, 'sanctum')
        ->patchJson("/api/v1/helpdesk/tickets/{$this->deLuisDeOtro->id}/estado", ['estado' => 'en_proceso'])
        ->assertForbidden();

    $this->actingAs($this->tecnico, 'sanctum')
        ->patchJson("/api/v1/helpdesk/tickets/{$this->deLuisDelTecnico->id}/estado", ['estado' => 'en_proceso'])
        ->assertOk();

    $this->actingAs($this->jefe, 'sanctum')
        ->patchJson("/api/v1/helpdesk/tickets/{$this->deLuisDeOtro->id}/estado", ['estado' => 'en_proceso'])
        ->assertOk();
});

test('el servidor no cambia el estado ni el tecnico asigna', function () {
    $this->actingAs($this->usuarioAna, 'sanctum')
        ->patchJson("/api/v1/helpdesk/tickets/{$this->deAna->id}/estado", ['estado' => 'cerrado'])
        ->assertForbidden();

    $this->actingAs($this->tecnico, 'sanctum')
        ->postJson("/api/v1/helpdesk/tickets/{$this->deLuisSinAsignar->id}/asignar", ['tecnico_dtic_id' => 1])
        ->assertForbidden();
});

// ── Inventario ─────────────────────────────────────────────────────────────

test('el auditor consulta el inventario pero no lo cambia', function () {
    $auditor = usuarioDeAccesoTic('auditor');
    $this->actingAs($auditor, 'sanctum');

    $this->getJson('/api/v1/inventario/bienes')->assertOk();
    $this->getJson('/api/v1/inventario/bajas')->assertOk();
    $this->postJson('/api/v1/inventario/bienes', [])->assertForbidden();
    $this->postJson('/api/v1/inventario/mantenimientos', [])->assertForbidden();
});

test('un servidor sin permisos de tic no entra al inventario', function () {
    $this->actingAs($this->usuarioAna, 'sanctum')
        ->getJson('/api/v1/inventario/bienes')
        ->assertForbidden();
});

test('dar de baja es del jefe de tic y no del tecnico', function () {
    $this->actingAs($this->tecnico, 'sanctum')
        ->postJson('/api/v1/inventario/bajas', [])
        ->assertForbidden();

    // Pasa la autorización y lo frena la validación.
    $this->actingAs($this->jefe, 'sanctum')
        ->postJson('/api/v1/inventario/bajas', [])
        ->assertStatus(422);
});

test('la base de conocimiento solo publica lectura', function () {
    $this->actingAs($this->jefe, 'sanctum')
        ->postJson('/api/v1/helpdesk/base-conocimiento', ['titulo' => 'x'])
        ->assertStatus(405);
});

// ── Migración ──────────────────────────────────────────────────────────────

test('la migracion crea el jefe de tic y deja los roles como el seeder', function () {
    $permisos = fn (string $rol) => Role::findByName($rol, 'sanctum')
        ->permissions()->pluck('name')->sort()->values()->all();

    $delSeeder = [
        'jefe-tic'     => $permisos('jefe-tic'),
        'tecnico-dtic' => $permisos('tecnico-dtic'),
        'auditor'      => $permisos('auditor'),
    ];

    $migracion = require database_path('migrations/2026_10_17_000000_crear_rol_jefe_tic_y_permisos_tic.php');

    // La base de un despliegue anterior.
    $migracion->down();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(Role::where('name', 'jefe-tic')->exists())->toBeFalse()
        ->and(Role::findByName('tecnico-dtic', 'sanctum')->hasPermissionTo('asignar-ticket'))->toBeTrue()
        ->and(Role::findByName('auditor', 'sanctum')->hasPermissionTo('ver-inventario-ti'))->toBeFalse();

    $migracion->up();
    $migracion->up(); // idempotente
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ($delSeeder as $rol => $esperados) {
        expect($permisos($rol))->toBe($esperados);
    }

    expect(Role::findByName('admin-ti', 'sanctum')->hasAllPermissions(
        array_map(fn ($p) => $p->value, RolPermisoSeeder::PERMISOS_JEFE_TIC)
    ))->toBeTrue();
});
