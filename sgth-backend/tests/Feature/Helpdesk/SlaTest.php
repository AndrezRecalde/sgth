<?php

use App\Enums\RegimenLaboral;
use App\Mail\Helpdesk\TicketSlaMail;
use App\Models\Asistencia\FeriadoInstitucional;
use App\Models\Expediente\Servidor;
use App\Models\Helpdesk\CategoriaTicket;
use App\Models\Helpdesk\Sla;
use App\Models\Helpdesk\Ticket;
use App\Models\User;
use App\Services\Helpdesk\Sla\CalendarioHabil;
use Carbon\Carbon;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

/*
| El SLA de la mesa de ayuda (docs/diseno-subsistema-tic.md, 4.3; fase 3.2):
| horas hábiles de 08:00 a 17:00, la pausa mientras espera al usuario, la
| alerta, el escalamiento y el cierre automático. Hoy es el miércoles 14 de
| octubre de 2026 a las 09:00.
*/

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    Servidor::unguard();
    FeriadoInstitucional::unguard();
    $this->seed(RolPermisoSeeder::class);
    $this->travelTo(Carbon::parse('2026-10-14 09:00'));

    $this->paola = Servidor::create(['cedula' => '0806060601', 'nombre' => 'Paola', 'apellido' => 'Benavides', 'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true]);
    $this->usuarioPaola = User::factory()->create(['primer_login' => false, 'activo' => true, 'servidor_id' => $this->paola->id, 'email' => 'paola@gadpe.gob.ec']);
    $this->usuarioPaola->assignRole('servidor');
    $this->tecnico = User::factory()->create(['primer_login' => false, 'activo' => true, 'email' => 'tecnico@gadpe.gob.ec']);
    $this->tecnico->assignRole('tecnico-dtic');
    $this->jefe = User::factory()->create(['primer_login' => false, 'activo' => true, 'email' => 'jefe@gadpe.gob.ec']);
    $this->jefe->assignRole('jefe-tic');

    $this->red = CategoriaTicket::where('nombre', 'Red e internet')->sole();
});

function ticketConSla(array $datos = []): Ticket
{
    $id = test()->actingAs(test()->usuarioPaola, 'sanctum')->postJson('/api/v1/helpdesk/tickets', array_merge([
        'categoria_id' => test()->red->id, 'asunto' => 'No tengo internet', 'descripcion' => 'La computadora no navega desde la mañana.',
    ], $datos))->assertCreated()->json('datos.id');

    return Ticket::find($id);
}

function pasoSla(User $quien, Ticket $t, string $paso, array $datos = []): \Illuminate\Testing\TestResponse
{
    return test()->actingAs($quien, 'sanctum')->postJson("/api/v1/helpdesk/tickets/{$t->id}/{$paso}", $datos);
}

function correosSla(string $correo): array
{
    return Mail::sent(TicketSlaMail::class)->filter(fn ($m) => $m->hasTo($correo))->map(fn ($m) => $m->aviso)->values()->all();
}

test('las horas hábiles saltan la noche, el fin de semana y los feriados', function () {
    $c = app(CalendarioHabil::class);

    expect($c->sumarHoras(Carbon::parse('2026-10-16 16:30'), 2)->format('Y-m-d H:i'))->toBe('2026-10-19 09:30')
        ->and($c->sumarHoras(Carbon::parse('2026-10-17 11:00'), 1)->format('Y-m-d H:i'))->toBe('2026-10-19 09:00')
        ->and($c->horasEntre(Carbon::parse('2026-10-16 16:00'), Carbon::parse('2026-10-19 10:00')))->toBe(3.0);

    // El lunes 19 es feriado: salta al martes.
    FeriadoInstitucional::create(['mes' => 10, 'dia' => 19, 'descripcion' => 'Feriado de prueba', 'es_nacional' => true, 'es_movil' => false]);
    expect(app(CalendarioHabil::class)->sumarHoras(Carbon::parse('2026-10-16 16:30'), 2)->format('Y-m-d H:i'))->toBe('2026-10-20 09:30');
});

test('el plazo sale de la configuración y una fila de la tabla manda sobre ella', function () {
    // Media: 4 h de respuesta y 18 de resolución. Miércoles 09:00 + 18 h hábiles = viernes 09:00.
    $t = ticketConSla();
    expect($t->sla_resolucion_horas)->toBe(18.0)
        ->and($t->fecha_vencimiento_sla->format('Y-m-d H:i'))->toBe('2026-10-16 09:00')
        ->and($t->vence_respuesta_en->format('Y-m-d H:i'))->toBe('2026-10-14 13:00')
        ->and($t->estadoSla())->toBe('en_plazo');

    Sla::create(['prioridad' => 'media', 'tiempo_respuesta_horas' => 2, 'tiempo_resolucion_horas' => 8]);
    expect(ticketConSla()->fecha_vencimiento_sla->format('Y-m-d H:i'))->toBe('2026-10-14 17:00');

    // La de la categoría afina la general.
    Sla::create(['prioridad' => 'media', 'categoria_id' => $this->red->id, 'tiempo_respuesta_horas' => 1, 'tiempo_resolucion_horas' => 3]);
    $t = ticketConSla();
    expect($t->fecha_vencimiento_sla->format('Y-m-d H:i'))->toBe('2026-10-14 12:00')
        ->and($t->sla_id)->not->toBeNull();
});

test('cambiar la prioridad al asignar recalcula el plazo', function () {
    $t = ticketConSla();
    pasoSla($this->jefe, $t, 'asignar', ['tecnico_id' => $this->tecnico->id, 'prioridad' => 'urgente'])->assertOk();

    // Urgente: 4 h de resolución desde el alta.
    expect($t->fresh()->fecha_vencimiento_sla->format('Y-m-d H:i'))->toBe('2026-10-14 13:00');
});

test('esperando al usuario el reloj se detiene y el vencimiento se corre', function () {
    $t = ticketConSla();
    pasoSla($this->tecnico, $t, 'tomar')->assertOk();
    expect($t->fresh()->primera_respuesta_en)->not->toBeNull();

    pasoSla($this->tecnico, $t, 'pedir-informacion', ['pregunta' => '¿Qué luces tiene el router?'])->assertOk()
        ->assertJsonPath('datos.sla.estado', 'pausado');

    // Responde 3 horas hábiles después: el viernes 09:00 pasa a 12:00.
    $this->travelTo(Carbon::parse('2026-10-14 12:00'));
    $this->actingAs($this->usuarioPaola, 'sanctum')->postJson("/api/v1/helpdesk/tickets/{$t->id}/comentarios", ['comentario' => 'Todas en rojo.'])->assertCreated();

    $t->refresh();
    expect($t->estado->value)->toBe('en_proceso')
        ->and($t->pausado_desde)->toBeNull()
        ->and($t->minutos_pausados)->toBe(180)
        ->and($t->fecha_vencimiento_sla->format('Y-m-d H:i'))->toBe('2026-10-16 12:00');
});

test('el seguimiento alerta una vez, escala el vencido y deja tranquilo al que espera al usuario', function () {
    $t = ticketConSla(['asunto' => 'Sin internet en el piso 3']);
    pasoSla($this->jefe, $t, 'asignar', ['tecnico_id' => $this->tecnico->id])->assertOk();
    $enEspera = ticketConSla(['asunto' => 'Correo no sincroniza']);
    pasoSla($this->tecnico, $enEspera, 'tomar')->assertOk();
    pasoSla($this->tecnico, $enEspera, 'pedir-informacion', ['pregunta' => '¿Desde qué equipo?'])->assertOk();

    // Vence el viernes a las 09:00: a las 08:00 le queda 1 h, menos del 25 % de 18.
    $this->travelTo(Carbon::parse('2026-10-16 08:00'));
    $this->artisan('sgth:helpdesk:seguimiento')->expectsOutputToContain('1 alerta(s)')->assertSuccessful();
    $this->artisan('sgth:helpdesk:seguimiento')->assertSuccessful();
    expect(correosSla('tecnico@gadpe.gob.ec'))->toBe(['por_vencer'])
        ->and($t->fresh()->estadoSla())->toBe('por_vencer');

    $this->travelTo(Carbon::parse('2026-10-16 09:30'));
    $this->artisan('sgth:helpdesk:seguimiento')->expectsOutputToContain('1 ticket(s) vencido(s)')->assertSuccessful();
    $this->artisan('sgth:helpdesk:seguimiento')->assertSuccessful();

    $t->refresh();
    expect($t->nivel)->toBe(2)
        ->and($t->estado->value)->toBe('asignado')
        ->and($t->estadoSla())->toBe('vencido')
        ->and($t->historial()->get()->last()->detalle)->toContain('nivel 2')
        ->and(correosSla('tecnico@gadpe.gob.ec'))->toBe(['por_vencer', 'vencido'])
        ->and(correosSla('jefe@gadpe.gob.ec'))->toBe(['vencido'])
        ->and($enEspera->fresh()->nivel)->toBe(1);

    $ids = collect($this->actingAs($this->jefe, 'sanctum')->getJson('/api/v1/helpdesk/tickets?vista=vencidos')->json('datos'))->pluck('id')->all();
    expect($ids)->toBe([$t->id]);
});

test('el resuelto sin respuesta se cierra solo a los 3 días hábiles', function () {
    $t = ticketConSla();
    pasoSla($this->tecnico, $t, 'tomar')->assertOk();
    pasoSla($this->tecnico, $t, 'resolver', ['solucion' => 'Se cambió el cable de red del puesto.'])->assertOk();
    expect($t->fresh()->estadoSla())->toBe('cumplido');

    // Miércoles + 3 días hábiles = lunes 19.
    $this->travelTo(Carbon::parse('2026-10-16 18:00'));
    $this->artisan('sgth:helpdesk:seguimiento')->assertSuccessful();
    expect($t->fresh()->estado->value)->toBe('resuelto');

    $this->travelTo(Carbon::parse('2026-10-19 09:00'));
    $this->artisan('sgth:helpdesk:seguimiento')->expectsOutputToContain('1 cerrado(s)')->assertSuccessful();
    $t->refresh();
    expect($t->estado->value)->toBe('cerrado')
        ->and($t->historial()->get()->last()->detalle)->toContain('3 días hábiles');
});

test('reabrir corre un plazo nuevo desde ese momento', function () {
    $t = ticketConSla();
    pasoSla($this->tecnico, $t, 'tomar')->assertOk();
    pasoSla($this->tecnico, $t, 'resolver', ['solucion' => 'Se reinició el punto de acceso del piso.'])->assertOk();

    $this->travelTo(Carbon::parse('2026-10-15 10:00'));
    pasoSla($this->usuarioPaola, $t, 'reabrir', ['motivo' => 'Volvió a caerse la red.'])->assertOk();

    // Jueves 10:00 + 18 h hábiles = lunes 10:00.
    expect($t->fresh()->fecha_vencimiento_sla->format('Y-m-d H:i'))->toBe('2026-10-19 10:00');
});

test('la tabla de SLA: una fila por prioridad y categoría, y la respuesta no tarda más que la resolución', function () {
    $alta = fn (array $d = []) => $this->actingAs($this->jefe, 'sanctum')->postJson('/api/v1/helpdesk/slas', array_merge([
        'prioridad' => 'alta', 'tiempo_respuesta_horas' => 1, 'tiempo_resolucion_horas' => 6,
    ], $d));

    $alta()->assertCreated();
    $alta()->assertStatus(422)->assertJsonStructure(['errores' => ['prioridad']]);
    $alta(['categoria_id' => $this->red->id])->assertCreated();
    $alta(['categoria_id' => $this->red->id])->assertStatus(422);
    $alta(['prioridad' => 'baja', 'tiempo_respuesta_horas' => 10, 'tiempo_resolucion_horas' => 5])
        ->assertStatus(422)->assertJsonStructure(['errores' => ['tiempo_respuesta_horas']]);
    $alta(['prioridad' => 'critica'])->assertStatus(422);
});
