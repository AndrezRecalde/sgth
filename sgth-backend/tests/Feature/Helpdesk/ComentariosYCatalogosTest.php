<?php

use App\Enums\EstadoTicket;
use App\Enums\RegimenLaboral;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\Helpdesk\AreaDtic;
use App\Models\Helpdesk\ComentarioTicket;
use App\Models\Helpdesk\Sla;
use App\Models\Helpdesk\TecnicoDtic;
use App\Models\Helpdesk\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();
    Sla::unguard();
    Ticket::unguard();
    AreaDtic::unguard();
    TecnicoDtic::unguard();

    $unidad = unidadDePrueba(['nombre' => 'Direccion Helpdesk']);
    $puesto = puestoDePrueba($unidad);

    $this->servidor = Servidor::create([
        'cedula' => '0801234571', 'nombre' => 'Pedro', 'apellido' => 'Gomez',
        'puesto_id' => $puesto->id, 'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(2), 'estado' => true,
    ]);

    // Quien abrió el ticket.
    $this->solicitante = User::create([
        'email' => 'pedro@example.com', 'usuario_ti' => 'pedro',
        'password' => bcrypt('123456'), 'primer_login' => false,
        'servidor_id' => $this->servidor->id,
    ]);

    $this->tecnico = User::create([
        'email' => 'tecnico@example.com', 'usuario_ti' => 'tecnico',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $this->tecnico->assignRole(Role::firstOrCreate(
        ['name' => 'tecnico-dtic', 'guard_name' => 'sanctum']
    ));

    $this->admin = User::create([
        'email' => 'adminti@example.com', 'usuario_ti' => 'adminti',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $this->admin->assignRole(Role::firstOrCreate(
        ['name' => 'admin-ti', 'guard_name' => 'sanctum']
    ));

    $this->sla = Sla::create([
        'prioridad' => 'media',
        'tiempo_resolucion_horas' => 24, 'tiempo_respuesta_horas' => 2,
    ]);

    $this->ticket = Ticket::create([
        'codigo_ticket'  => 'TIC-90001',
        'sla_id'         => $this->sla->id,
        'solicitante_id' => $this->servidor->id,
        'tipo_ticket'    => 'incidente',
        'estado'         => EstadoTicket::ABIERTO->value,
        'asunto'         => 'No hay internet',
        'descripcion'    => 'Desconectado desde la mañana',
    ]);
});

// ── Comentarios ────────────────────────────────────────────────────────────

test('un_comentario_se_guarda_de_verdad', function () {
    // El controlador era un muñón: respondía «Comentario agregado» y no
    // guardaba nada, ni siquiera recibía el ticket.
    $this->actingAs($this->solicitante, 'sanctum')
        ->postJson("/api/v1/helpdesk/tickets/{$this->ticket->id}/comentarios", [
            'comentario' => 'Sigue sin funcionar después de reiniciar.',
        ])
        ->assertCreated();

    $comentario = ComentarioTicket::sole();

    expect($comentario->ticket_id)->toBe($this->ticket->id);
    expect($comentario->user_id)->toBe($this->solicitante->id);
    expect($comentario->comentario)
        ->toBe('Sigue sin funcionar después de reiniciar.');
    expect($comentario->es_interno)->toBeFalse();
});

test('el_comentario_necesita_texto_y_un_ticket_que_exista', function () {
    $this->actingAs($this->solicitante, 'sanctum')
        ->postJson("/api/v1/helpdesk/tickets/{$this->ticket->id}/comentarios", [])
        ->assertStatus(422);

    $this->actingAs($this->solicitante, 'sanctum')
        ->postJson('/api/v1/helpdesk/tickets/999999/comentarios', [
            'comentario' => 'Sobre un ticket que no existe.',
        ])
        ->assertNotFound();

    expect(ComentarioTicket::count())->toBe(0);
});

test('la_nota_interna_no_la_ve_quien_abrio_el_ticket', function () {
    // `es_interno` existe para que los técnicos hablen entre ellos sobre el
    // ticket. Un listado que se la entregue al solicitante la desactiva.
    ComentarioTicket::create([
        'ticket_id' => $this->ticket->id, 'user_id' => $this->tecnico->id,
        'comentario' => 'El equipo ya venía con el disco fallando.',
        'es_interno' => true,
    ]);
    ComentarioTicket::create([
        'ticket_id' => $this->ticket->id, 'user_id' => $this->tecnico->id,
        'comentario' => 'Pasamos a revisarlo esta tarde.',
        'es_interno' => false,
    ]);

    $delSolicitante = $this->actingAs($this->solicitante, 'sanctum')
        ->getJson("/api/v1/helpdesk/tickets/{$this->ticket->id}/comentarios")
        ->assertOk();

    expect($delSolicitante->json('datos'))->toHaveCount(1);
    $delSolicitante->assertDontSee('disco fallando');

    $delTecnico = $this->actingAs($this->tecnico, 'sanctum')
        ->getJson("/api/v1/helpdesk/tickets/{$this->ticket->id}/comentarios")
        ->assertOk();

    expect($delTecnico->json('datos'))->toHaveCount(2);
});

test('solo_quien_atiende_puede_marcar_un_comentario_como_interno', function () {
    // Si el solicitante pudiera marcarlo, escondería su propio mensaje del
    // técnico que tiene que leerlo.
    $this->actingAs($this->solicitante, 'sanctum')
        ->postJson("/api/v1/helpdesk/tickets/{$this->ticket->id}/comentarios", [
            'comentario' => 'Intento de nota interna.',
            'es_interno' => true,
        ])
        ->assertCreated();

    expect(ComentarioTicket::sole()->es_interno)->toBeFalse();

    $this->actingAs($this->tecnico, 'sanctum')
        ->postJson("/api/v1/helpdesk/tickets/{$this->ticket->id}/comentarios", [
            'comentario' => 'Nota para el equipo.',
            'es_interno' => true,
        ])
        ->assertCreated();

    expect(ComentarioTicket::where('es_interno', true)->count())->toBe(1);
});

test('el_detalle_del_ticket_carga_sus_comentarios', function () {
    // `show` pedía las relaciones `bien_informatico` y `comentarios`, y ninguna
    // de las dos existía en el modelo: el detalle reventaba en cuanto había un
    // ticket que mostrar. Sobre base vacía no se veía, porque Eloquent solo
    // resuelve la relación si la consulta devuelve algo.
    ComentarioTicket::create([
        'ticket_id' => $this->ticket->id, 'user_id' => $this->tecnico->id,
        'comentario' => 'Asignado al área de redes.', 'es_interno' => false,
    ]);

    $respuesta = $this->actingAs($this->tecnico, 'sanctum')
        ->getJson("/api/v1/helpdesk/tickets/{$this->ticket->id}")
        ->assertOk();

    expect($respuesta->json('datos.comentarios'))->toHaveCount(1);
});

// ── Catálogos: el `show` que el apiResource prometía ───────────────────────

test('los_catalogos_del_helpdesk_devuelven_un_registro', function () {
    $area = AreaDtic::create([
        'nombre' => 'Redes', 'descripcion' => 'Infraestructura de red',
    ]);
    $sla = $this->sla;
    $tecnicoDtic = TecnicoDtic::create([
        'user_id' => $this->tecnico->id, 'area_dtic_id' => $area->id,
        'nivel' => 1,
    ]);

    // Las tres rutas las declaraba el `apiResource` y el método no existía en
    // el controlador: devolvían un 500 desde siempre.
    foreach ([
        "/api/v1/helpdesk/areas/{$area->id}"        => $area->id,
        "/api/v1/helpdesk/slas/{$sla->id}"          => $sla->id,
        "/api/v1/helpdesk/tecnicos/{$tecnicoDtic->id}" => $tecnicoDtic->id,
    ] as $uri => $id) {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson($uri)
            ->assertOk()
            ->assertJsonPath('datos.id', $id);
    }
});

test('un_catalogo_que_no_existe_da_404', function () {
    foreach (['areas', 'slas', 'tecnicos'] as $recurso) {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/helpdesk/{$recurso}/999999")
            ->assertNotFound();
    }
});

test('los_resultados_de_la_encuesta_no_los_traga_el_comodin', function () {
    $ruta = Route::getRoutes()->match(
        Illuminate\Http\Request::create('/api/v1/helpdesk/encuestas-satisfaccion/resultados', 'GET')
    );

    expect($ruta->getActionMethod())->toBe('resultados');
});

// ── Técnicos y SLA: código escrito contra un esquema que no existe ─────────

test('dar_de_alta_un_tecnico_ya_es_posible', function () {
    $area = AreaDtic::create(['nombre' => 'Redes', 'descripcion' => null]);

    // El alta validaba `servidor_id`, columna que `tecnicos_dtic` no tiene: la
    // propia regla `unique` consultaba por ella y reventaba, así que registrar
    // un técnico era imposible.
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/helpdesk/tecnicos', [
            'user_id'      => $this->tecnico->id,
            'area_dtic_id' => $area->id,
            'nivel'        => 2,
        ])
        ->assertCreated();

    expect(TecnicoDtic::sole()->user_id)->toBe($this->tecnico->id);

    // Y el mismo usuario no se registra dos veces.
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/helpdesk/tecnicos', [
            'user_id'      => $this->tecnico->id,
            'area_dtic_id' => $area->id,
            'nivel'        => 2,
        ])
        ->assertStatus(422);
});

test('el_listado_de_tecnicos_no_revienta_cuando_hay_alguno', function () {
    $area = AreaDtic::create(['nombre' => 'Soporte', 'descripcion' => null]);
    TecnicoDtic::create([
        'user_id' => $this->tecnico->id, 'area_dtic_id' => $area->id,
        'nivel' => 1,
    ]);

    // Cargaba la relación `servidor`, que no existe en este modelo.
    $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/helpdesk/tecnicos')
        ->assertOk()
        ->assertJsonCount(1, 'datos');
});

test('crear_un_sla_ya_no_exige_una_categoria_que_no_existe', function () {
    // `categoria_id` se exigía y no está ni en la tabla ni en el `fillable`:
    // había que inventar una categoría que Eloquent luego descartaba.
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/helpdesk/slas', [
            'prioridad'               => 'critica',
            'tiempo_resolucion_horas' => 4,
            'tiempo_respuesta_horas'  => 1,
        ])
        ->assertCreated();

    expect(Sla::where('prioridad', 'critica')->exists())->toBeTrue();
});

test('no_se_repite_la_prioridad_de_un_sla', function () {
    // La prioridad es única en la tabla, pero el alta no lo validaba: repetirla
    // daba un 500 de la base de datos en vez de un error de formulario.
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/helpdesk/slas', [
            'prioridad'               => 'media',
            'tiempo_resolucion_horas' => 24,
            'tiempo_respuesta_horas'  => 2,
        ])
        ->assertStatus(422);
});
