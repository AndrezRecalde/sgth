<?php

use App\Enums\RegimenLaboral;
use App\Mail\Helpdesk\TicketAvisoMail;
use App\Models\Expediente\Servidor;
use App\Models\Helpdesk\CategoriaTicket;
use App\Models\Helpdesk\TecnicoDtic;
use App\Models\Helpdesk\Ticket;
use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

/*
| La configuración de la mesa de ayuda y sus correos (docs/diseno-subsistema-
| tic.md, fase 3.5): las categorías, los técnicos por subunidad de TIC (D6) y
| los avisos al técnico y al solicitante.
*/

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    Servidor::unguard();
    $this->seed(RolPermisoSeeder::class);

    $tic = unidadDePrueba(['codigo' => config('helpdesk.unidad_tic_codigo'), 'nombre' => 'Gestión de TIC']);
    $this->soporte = unidadDePrueba(['nombre' => 'Soporte Técnico', 'unidad_padre_id' => $tic->id, 'nivel' => 2]);
    $this->redes = unidadDePrueba(['nombre' => 'Infraestructura y Redes', 'unidad_padre_id' => $tic->id, 'nivel' => 2]);
    $this->ajena = unidadDePrueba(['nombre' => 'Dirección de Obras']);

    $this->paola = Servidor::create(['cedula' => '0805050501', 'nombre' => 'Paola', 'apellido' => 'Benavides', 'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true]);
    $this->usuarioPaola = User::factory()->create(['primer_login' => false, 'activo' => true, 'servidor_id' => $this->paola->id, 'email' => 'paola@gadpe.gob.ec']);
    $this->usuarioPaola->assignRole('servidor');
    $this->tecnico = User::factory()->create(['primer_login' => false, 'activo' => true, 'email' => 'tecnico@gadpe.gob.ec']);
    $this->tecnico->assignRole('tecnico-dtic');
    $this->jefe = User::factory()->create(['primer_login' => false, 'activo' => true, 'email' => 'jefe@gadpe.gob.ec']);
    $this->jefe->assignRole('jefe-tic');
});

test('las categorías se crean, se editan y se desactivan; no se borran', function () {
    $this->actingAs($this->jefe, 'sanctum');

    $id = $this->postJson('/api/v1/helpdesk/categorias', [
        'nombre' => 'Telefonía IP', 'descripcion' => 'Teléfonos de escritorio y extensiones', 'unidad_tic_id' => $this->redes->id,
    ])->assertCreated()->json('datos.id');

    $this->postJson('/api/v1/helpdesk/categorias', ['nombre' => 'TELEFONÍA ip'])->assertStatus(422);
    $this->postJson('/api/v1/helpdesk/categorias', ['nombre' => 'Obras', 'unidad_tic_id' => $this->ajena->id])
        ->assertStatus(422)->assertJsonStructure(['errores' => ['unidad_tic_id']]);

    $this->putJson("/api/v1/helpdesk/categorias/{$id}", ['activa' => false])->assertOk();
    $categorias = collect($this->getJson('/api/v1/helpdesk/categorias')->assertOk()->json('datos'))->keyBy('nombre');
    expect($categorias['Telefonía IP']['activa'])->toBeFalse()
        ->and($categorias['Telefonía IP']['unidad_tic'])->toBe('Infraestructura y Redes');

    // Desactivada, ya no se ofrece ni se puede elegir al abrir un ticket.
    $ofrecidas = collect($this->actingAs($this->usuarioPaola, 'sanctum')->getJson('/api/v1/helpdesk/tickets/catalogos')->json('datos.categorias'))->pluck('id');
    expect($ofrecidas)->not->toContain($id);

    $this->actingAs($this->tecnico, 'sanctum')->postJson('/api/v1/helpdesk/categorias', ['nombre' => 'Otra'])->assertForbidden();
});

test('la tabla de SLA trae también los plazos por defecto de cada prioridad', function () {
    $this->actingAs($this->jefe, 'sanctum')->getJson('/api/v1/helpdesk/slas')->assertOk()
        ->assertJsonPath('datos.reglas', [])
        ->assertJsonPath('datos.por_defecto.urgente', ['respuesta' => 1, 'resolucion' => 4]);
});

test('los técnicos van por subunidad de TIC y tienen que atender tickets', function () {
    $this->actingAs($this->jefe, 'sanctum');

    $this->postJson('/api/v1/helpdesk/tecnicos', ['user_id' => $this->usuarioPaola->id, 'unidad_tic_id' => $this->soporte->id, 'nivel' => 1])
        ->assertStatus(422)->assertJsonStructure(['errores' => ['user_id']]);
    $this->postJson('/api/v1/helpdesk/tecnicos', ['user_id' => $this->tecnico->id, 'unidad_tic_id' => $this->ajena->id, 'nivel' => 1])
        ->assertStatus(422)->assertJsonStructure(['errores' => ['unidad_tic_id']]);

    $id = $this->postJson('/api/v1/helpdesk/tecnicos', ['user_id' => $this->tecnico->id, 'unidad_tic_id' => $this->soporte->id, 'nivel' => 1])
        ->assertCreated()->json('datos.id');
    $this->putJson("/api/v1/helpdesk/tecnicos/{$id}", ['nivel' => 2])->assertOk()->assertJsonPath('datos.nivel', 2);

    $tecnicos = collect($this->getJson('/api/v1/helpdesk/tickets/catalogos')->json('datos.tecnicos'))->keyBy('id');
    expect($tecnicos[$this->tecnico->id]['unidad_tic_id'])->toBe($this->soporte->id)
        ->and($tecnicos[$this->tecnico->id]['nivel'])->toBe(2)
        ->and(TecnicoDtic::sole()->unidadTic->nombre)->toBe('Soporte Técnico');

    // Pausado (vacaciones, licencia), no se le propone al asignar.
    $this->putJson("/api/v1/helpdesk/tecnicos/{$id}", ['estado' => false])->assertOk();
    expect(collect($this->getJson('/api/v1/helpdesk/tickets/catalogos')->json('datos.tecnicos'))->pluck('id'))->not->toContain($this->tecnico->id);
});

test('los correos del trámite: al técnico al asignar y reabrir, al solicitante al preguntar y resolver', function () {
    $categoria = CategoriaTicket::where('nombre', 'Hardware')->sole();
    $id = $this->actingAs($this->usuarioPaola, 'sanctum')->postJson('/api/v1/helpdesk/tickets', [
        'categoria_id' => $categoria->id, 'asunto' => 'La impresora no imprime', 'descripcion' => 'Sale la hoja en blanco desde ayer.',
    ])->assertCreated()->json('datos.id');
    $paso = fn (User $u, string $p, array $d = []) => $this->actingAs($u, 'sanctum')->postJson("/api/v1/helpdesk/tickets/{$id}/{$p}", $d)->assertOk();

    $paso($this->jefe, 'asignar', ['tecnico_id' => $this->tecnico->id]);
    $paso($this->tecnico, 'tomar');
    $paso($this->tecnico, 'pedir-informacion', ['pregunta' => '¿Qué modelo de impresora es?']);
    $this->actingAs($this->usuarioPaola, 'sanctum')->postJson("/api/v1/helpdesk/tickets/{$id}/comentarios", ['comentario' => 'Una HP LaserJet.'])->assertCreated();
    $paso($this->tecnico, 'resolver', ['solucion' => 'Se cambió el tóner y se limpió el rodillo.']);
    $paso($this->usuarioPaola, 'reabrir', ['motivo' => 'Volvió a salir en blanco.']);

    $avisos = fn (string $correo) => Mail::sent(TicketAvisoMail::class)->filter(fn ($m) => $m->hasTo($correo))->map(fn ($m) => $m->aviso)->values()->all();
    expect($avisos('tecnico@gadpe.gob.ec'))->toBe(['asignado', 'reabierto'])
        ->and($avisos('paola@gadpe.gob.ec'))->toBe(['pendiente', 'resuelto']);

    $correo = new TicketAvisoMail(Ticket::with('solicitante', 'tecnico')->find($id), 'pendiente');
    $correo->assertSeeInHtml('¿Qué modelo de impresora es?')->assertSeeInHtml("/portal/mis-tickets/{$id}");
});
