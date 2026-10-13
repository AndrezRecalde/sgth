<?php

use App\Enums\RegimenLaboral;
use App\Models\Expediente\Servidor;
use App\Models\Helpdesk\CategoriaTicket;
use App\Models\Helpdesk\ComentarioTicket;
use App\Models\Helpdesk\EncuestaSatisfaccion;
use App\Models\Helpdesk\Ticket;
use App\Models\InventarioTi\AsignacionBien;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\MantenimientoBien;
use App\Models\InventarioTi\Marca;
use App\Models\InventarioTi\TipoBien;
use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
| Los tickets de la mesa de ayuda, rehechos (docs/diseno-subsistema-tic.md,
| 4.3; fase 3.1): abrirlos, el folio, el trámite con su historial, la
| conversación con archivos y el mantenimiento al resolver uno de hardware.
| Crear un ticket daba 500 y `cambiarEstado` aceptaba cualquier texto.
*/

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Servidor::unguard();
    BienInformatico::unguard();
    $this->seed(RolPermisoSeeder::class);
    $this->travelTo(now()->setDate(2026, 10, 14)->setTime(9, 0));

    $servidor = fn (string $cedula, string $apellido) => Servidor::create([
        'cedula' => $cedula, 'nombre' => 'Ana', 'apellido' => $apellido, 'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true,
    ]);
    $usuario = function (?Servidor $s, string $rol) {
        $u = User::factory()->create(['primer_login' => false, 'activo' => true, 'servidor_id' => $s?->id]);
        $u->assignRole($rol);

        return $u;
    };

    $this->paola = $servidor('0807070701', 'Benavides');
    $this->diego = $servidor('0807070702', 'Luzuriaga');
    $this->usuarioPaola = $usuario($this->paola, 'servidor');
    $this->usuarioDiego = $usuario($this->diego, 'servidor');
    $this->tecnico = $usuario(null, 'tecnico-dtic');
    $this->jefe = $usuario(null, 'jefe-tic');

    // Las siete categorías las siembra la migración (D7).
    $this->hardware = CategoriaTicket::where('nombre', 'Hardware')->sole();
    $this->red = CategoriaTicket::where('nombre', 'Red e internet')->sole();
});

function abrirTicket(User $quien, array $datos = []): \Illuminate\Testing\TestResponse
{
    return test()->actingAs($quien, 'sanctum')->post('/api/v1/helpdesk/tickets', array_merge([
        'categoria_id' => test()->red->id,
        'asunto'       => 'No tengo internet',
        'descripcion'  => 'Desde la mañana la computadora no navega.',
    ], $datos), ['Accept' => 'application/json']);
}

function pasoTicket(User $quien, int $id, string $paso, array $datos = []): \Illuminate\Testing\TestResponse
{
    return test()->actingAs($quien, 'sanctum')->postJson("/api/v1/helpdesk/tickets/{$id}/{$paso}", $datos);
}

test('el servidor abre su ticket desde el Portal con folio, sin elegir la prioridad', function () {
    $datos = abrirTicket($this->usuarioPaola, [
        'prioridad' => 'urgente',
        'archivo'   => UploadedFile::fake()->image('captura.png'),
    ])->assertCreated()->json('datos');

    expect($datos['folio'])->toBe('TIC-2026-00001')
        ->and($datos['estado'])->toBe('abierto')
        ->and($datos['canal'])->toBe('portal')
        ->and($datos['prioridad'])->toBe('media')
        ->and($datos['solicitante']['nombre'])->toBe('Benavides Ana')
        ->and($datos['historial'][0]['estado_nuevo'])->toBe('abierto')
        ->and($datos['comentarios'][0]['tiene_archivo'])->toBeTrue()
        ->and($datos['comentarios'][0]['archivo_nombre'])->toBe('captura.png');

    $comentario = $datos['comentarios'][0]['id'];
    $this->get("/api/v1/helpdesk/tickets/{$datos['id']}/comentarios/{$comentario}/archivo")->assertOk();
    $this->actingAs($this->usuarioDiego, 'sanctum')
        ->getJson("/api/v1/helpdesk/tickets/{$datos['id']}/comentarios/{$comentario}/archivo")->assertForbidden();

    expect(abrirTicket($this->usuarioDiego)->json('datos.folio'))->toBe('TIC-2026-00002');
});

test('el ticket se valida y el equipo tiene que ser suyo', function () {
    abrirTicket($this->usuarioPaola, ['asunto' => 'x', 'descripcion' => ''])->assertStatus(422)
        ->assertJsonStructure(['errores' => ['asunto', 'descripcion']]);

    $equipo = BienInformatico::create([
        'codigo_qr' => '1774-QR', 'codigo_institucional' => '001774',
        'tipo_bien_id' => TipoBien::create(['nombre' => 'Computador'])->id, 'marca_id' => Marca::create(['nombre' => 'DELL'])->id,
        'estado_operativo' => 'en_uso', 'condicion_fisica' => 'bueno',
    ]);
    abrirTicket($this->usuarioPaola, ['categoria_id' => $this->hardware->id, 'bien_informatico_id' => $equipo->id])
        ->assertStatus(422)->assertJsonStructure(['errores' => ['bien_informatico_id']]);

    AsignacionBien::create(['bien_informatico_id' => $equipo->id, 'servidor_id' => $this->paola->id, 'fecha_asignacion' => '2026-01-10', 'estado' => 'activa']);
    abrirTicket($this->usuarioPaola, ['categoria_id' => $this->hardware->id, 'bien_informatico_id' => $equipo->id])
        ->assertCreated()->assertJsonPath('datos.bien.codigo_institucional', '001774');
});

test('TIC registra un ticket a nombre de quien llamó, con su prioridad', function () {
    $datos = abrirTicket($this->tecnico, ['servidor_id' => $this->diego->id, 'prioridad' => 'alta'])->assertCreated()->json('datos');

    expect($datos['canal'])->toBe('tic')
        ->and($datos['prioridad'])->toBe('alta')
        ->and($datos['solicitante_id'])->toBe($this->diego->id);

    // Un servidor no abre tickets a nombre de otro.
    abrirTicket($this->usuarioPaola, ['servidor_id' => $this->diego->id])->assertForbidden();
});

test('el trámite completo deja su historial y la calificación', function () {
    $id = abrirTicket($this->usuarioPaola)->json('datos.id');

    pasoTicket($this->jefe, $id, 'asignar', ['tecnico_id' => $this->tecnico->id, 'prioridad' => 'alta'])
        ->assertOk()->assertJsonPath('datos.estado', 'asignado')->assertJsonPath('datos.prioridad', 'alta');
    pasoTicket($this->tecnico, $id, 'tomar')->assertOk()->assertJsonPath('datos.estado', 'en_proceso');
    pasoTicket($this->tecnico, $id, 'pedir-informacion', ['pregunta' => '¿Le sale algún mensaje de error?'])
        ->assertOk()->assertJsonPath('datos.estado', 'pendiente_usuario');

    // La respuesta del solicitante devuelve el ticket al técnico.
    $this->actingAs($this->usuarioPaola, 'sanctum')->postJson("/api/v1/helpdesk/tickets/{$id}/comentarios", ['comentario' => 'Dice «sin conexión».'])
        ->assertCreated();
    expect(Ticket::find($id)->estado->value)->toBe('en_proceso');

    pasoTicket($this->tecnico, $id, 'resolver', ['solucion' => 'Se reconfiguró la tarjeta de red y el DNS.'])
        ->assertOk()->assertJsonPath('datos.estado', 'resuelto');
    pasoTicket($this->usuarioPaola, $id, 'confirmar', ['calificacion' => 5, 'comentario' => 'Rápido.'])
        ->assertOk()->assertJsonPath('datos.estado', 'cerrado');

    expect(EncuestaSatisfaccion::sole()->calificacion)->toBe(5);
    $historial = $this->actingAs($this->usuarioPaola, 'sanctum')->getJson("/api/v1/helpdesk/tickets/{$id}")->json('datos.historial');
    expect(collect($historial)->pluck('estado_nuevo')->all())
        ->toBe(['abierto', 'asignado', 'en_proceso', 'pendiente_usuario', 'en_proceso', 'resuelto', 'cerrado']);

    // Cerrado, ya no recibe mensajes.
    $this->postJson("/api/v1/helpdesk/tickets/{$id}/comentarios", ['comentario' => 'Otra cosa'])->assertStatus(422);
});

test('las transiciones que no existen se rechazan', function () {
    $id = abrirTicket($this->usuarioPaola)->json('datos.id');

    pasoTicket($this->tecnico, $id, 'resolver', ['solucion' => 'Sin haberlo tomado nunca.'])->assertStatus(422);
    pasoTicket($this->usuarioPaola, $id, 'confirmar')->assertStatus(422);
    pasoTicket($this->usuarioDiego, $id, 'confirmar')->assertForbidden();
    // Asignar a quien no atiende tickets.
    pasoTicket($this->jefe, $id, 'asignar', ['tecnico_id' => $this->usuarioDiego->id])->assertStatus(422)
        ->assertJsonStructure(['errores' => ['tecnico_id']]);
});

test('reabrir lo devuelve al técnico; escalar sube el nivel', function () {
    $id = abrirTicket($this->usuarioPaola)->json('datos.id');
    pasoTicket($this->tecnico, $id, 'tomar')->assertOk();
    pasoTicket($this->tecnico, $id, 'resolver', ['solucion' => 'Se reinició el punto de acceso.'])->assertOk();

    $datos = pasoTicket($this->usuarioPaola, $id, 'reabrir', ['motivo' => 'Volvió a fallar a la tarde.'])->assertOk()->json('datos');
    expect($datos['estado'])->toBe('asignado')->and($datos['reabierto_veces'])->toBe(1)->and($datos['tecnico']['id'])->toBe($this->tecnico->id);

    pasoTicket($this->tecnico, $id, 'tomar')->assertOk();
    pasoTicket($this->tecnico, $id, 'escalar', ['nivel' => 1, 'motivo' => 'No sé qué más hacer.'])->assertStatus(422);
    $datos = pasoTicket($this->tecnico, $id, 'escalar', ['nivel' => 2, 'motivo' => 'Puede ser el switch del piso.'])->assertOk()->json('datos');
    expect($datos['estado'])->toBe('abierto')->and($datos['nivel'])->toBe(2)->and($datos['tecnico'])->toBeNull();
});

test('el solicitante anula el suyo mientras nadie lo atiende; el jefe, en cualquier momento', function () {
    $propio = abrirTicket($this->usuarioPaola)->json('datos.id');
    pasoTicket($this->usuarioPaola, $propio, 'anular', ['motivo' => 'Ya se arregló solo.'])->assertOk()->assertJsonPath('datos.estado', 'anulado');

    $otro = abrirTicket($this->usuarioPaola, ['asunto' => 'Impresora atascada'])->json('datos.id');
    pasoTicket($this->tecnico, $otro, 'tomar')->assertOk();
    pasoTicket($this->usuarioPaola, $otro, 'anular', ['motivo' => 'Ya no lo necesito.'])->assertStatus(422);
    pasoTicket($this->jefe, $otro, 'anular', ['motivo' => 'Duplicado del TIC-2026-00001.'])->assertOk();
});

test('al resolver uno de hardware con equipo queda el mantenimiento en la ficha', function () {
    $equipo = BienInformatico::create([
        'codigo_qr' => '1774-QR', 'codigo_institucional' => '001774',
        'tipo_bien_id' => TipoBien::create(['nombre' => 'Computador'])->id, 'marca_id' => Marca::create(['nombre' => 'DELL'])->id,
        'estado_operativo' => 'en_uso', 'condicion_fisica' => 'bueno',
    ]);
    $id = abrirTicket($this->tecnico, ['servidor_id' => $this->paola->id, 'categoria_id' => $this->hardware->id, 'bien_informatico_id' => $equipo->id])->json('datos.id');
    pasoTicket($this->tecnico, $id, 'tomar')->assertOk();

    pasoTicket($this->tecnico, $id, 'resolver', ['solucion' => 'Se cambió la fuente de poder.', 'estado_equipo' => 'en_garantia'])->assertOk();

    $mantenimiento = MantenimientoBien::sole();
    expect($mantenimiento->ticket_id)->toBe($id)
        ->and($mantenimiento->tipo_mantenimiento->value)->toBe('correctivo')
        ->and($mantenimiento->descripcion)->toContain('TIC-2026-00001')
        ->and($equipo->fresh()->estado_operativo->value)->toBe('en_garantia');
});

test('las vistas de la bandeja y «Mis tickets»', function () {
    $sinAsignar = abrirTicket($this->usuarioPaola)->json('datos.id');
    $mio = abrirTicket($this->usuarioDiego)->json('datos.id');
    pasoTicket($this->tecnico, $mio, 'tomar')->assertOk();

    $ids = fn (User $u, string $q) => collect($this->actingAs($u, 'sanctum')->getJson("/api/v1/helpdesk/tickets?{$q}")->assertOk()->json('datos'))->pluck('id')->all();

    expect($ids($this->tecnico, 'vista=sin_asignar'))->toBe([$sinAsignar])
        ->and($ids($this->tecnico, 'vista=mios'))->toBe([$mio])
        ->and($ids($this->usuarioPaola, 'propios=1'))->toBe([$sinAsignar])
        ->and($ids($this->jefe, 'search=2026-00002'))->toBe([$mio]);
});

test('la nota interna y su archivo no los ve el solicitante', function () {
    $id = abrirTicket($this->usuarioPaola)->json('datos.id');
    $nota = $this->actingAs($this->tecnico, 'sanctum')->post("/api/v1/helpdesk/tickets/{$id}/comentarios", [
        'comentario' => 'Revisar el log del switch.', 'es_interno' => '1', 'archivo' => UploadedFile::fake()->create('switch.log', 4, 'text/plain'),
    ], ['Accept' => 'application/json'])->assertCreated()->json('datos');

    expect($nota['es_interno'])->toBeTrue();
    $this->actingAs($this->usuarioPaola, 'sanctum')
        ->getJson("/api/v1/helpdesk/tickets/{$id}/comentarios/{$nota['id']}/archivo")->assertNotFound();
    expect(ComentarioTicket::where('ticket_id', $id)->count())->toBe(1);
});

test('los catálogos traen las categorías sembradas, las prioridades y los técnicos', function () {
    $datos = $this->actingAs($this->usuarioPaola, 'sanctum')->getJson('/api/v1/helpdesk/tickets/catalogos')->assertOk()->json('datos');

    expect(collect($datos['categorias'])->pluck('nombre'))->toContain('Hardware', 'Sistemas institucionales')
        ->and(collect($datos['prioridades'])->pluck('valor')->all())->toBe(['baja', 'media', 'alta', 'urgente'])
        ->and(collect($datos['tecnicos'])->pluck('id'))->toContain($this->tecnico->id, $this->jefe->id);
});
