<?php

use App\Enums\RegimenLaboral;
use App\Models\Expediente\Servidor;
use App\Models\Helpdesk\CategoriaTicket;
use App\Models\Helpdesk\HistorialEstadoTicket;
use App\Models\Helpdesk\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

/*
| El tiempo hasta la asignación (mesa de ayuda centralizada, respuesta 7 de
| TIC del 2026-11-06): el plazo corre desde que se abre el ticket, así que lo
| que tarda en repartirse lo consume el técnico. `asignado_en` es la primera
| vez que el ticket tuvo dueño, y el reporte la promedia en horas hábiles.
*/

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    Servidor::unguard();
    $this->seed(RolPermisoSeeder::class);
    // Miércoles 14 de octubre de 2026, en horario de oficina.
    $this->travelTo(Carbon::parse('2026-10-14 09:00'));

    $this->paola = Servidor::create(['cedula' => '0811111101', 'nombre' => 'Paola', 'apellido' => 'Cortez', 'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true]);
    $this->usuarioPaola = User::factory()->create(['primer_login' => false, 'activo' => true, 'servidor_id' => $this->paola->id]);
    $this->usuarioPaola->assignRole('servidor');
    $this->coordinador = User::factory()->create(['primer_login' => false, 'activo' => true]);
    $this->coordinador->assignRole('coordinador-mesa-ayuda');
    $this->tecnico = User::factory()->create(['primer_login' => false, 'activo' => true]);
    $this->tecnico->assignRole('tecnico-dtic');
    $this->otroTecnico = User::factory()->create(['primer_login' => false, 'activo' => true]);
    $this->otroTecnico->assignRole('tecnico-dtic');
});

function ticketParaAsignar(): int
{
    return test()->actingAs(test()->usuarioPaola, 'sanctum')->postJson('/api/v1/helpdesk/tickets', [
        'categoria_id' => CategoriaTicket::where('nombre', 'Red e internet')->value('id'),
        'asunto' => 'No tengo internet', 'descripcion' => 'Desde la mañana la computadora no navega.',
    ])->assertCreated()->json('datos.id');
}

function pasoDeAsignacion(User $quien, int $id, string $paso, array $datos = []): void
{
    test()->actingAs($quien, 'sanctum')->postJson("/api/v1/helpdesk/tickets/{$id}/{$paso}", $datos)->assertOk();
}

test('es la primera asignación: reasignar o escalar no la cambia', function () {
    $id = ticketParaAsignar();
    expect(Ticket::find($id)->asignado_en)->toBeNull();

    $this->travelTo(Carbon::parse('2026-10-14 11:00'));
    pasoDeAsignacion($this->coordinador, $id, 'asignar', ['tecnico_id' => $this->tecnico->id]);
    expect(Ticket::find($id)->asignado_en->format('Y-m-d H:i'))->toBe('2026-10-14 11:00');

    $this->travelTo(Carbon::parse('2026-10-14 15:00'));
    pasoDeAsignacion($this->tecnico, $id, 'tomar');
    pasoDeAsignacion($this->tecnico, $id, 'escalar', ['nivel' => 2, 'motivo' => 'Es del switch del piso.']);
    pasoDeAsignacion($this->coordinador, $id, 'asignar', ['tecnico_id' => $this->otroTecnico->id]);

    expect(Ticket::find($id)->asignado_en->format('Y-m-d H:i'))->toBe('2026-10-14 11:00');
});

test('si el coordinador lo toma para resolverlo él, eso es la asignación', function () {
    $id = ticketParaAsignar();
    $this->travelTo(Carbon::parse('2026-10-14 09:30'));

    pasoDeAsignacion($this->coordinador, $id, 'tomar');

    expect(Ticket::find($id)->asignado_en->format('H:i'))->toBe('09:30');
});

test('el reporte promedia las horas hábiles hasta la asignación', function () {
    $rapido = ticketParaAsignar();
    $lento = ticketParaAsignar();
    $sinAsignar = ticketParaAsignar();

    $this->travelTo(Carbon::parse('2026-10-14 10:00'));
    pasoDeAsignacion($this->coordinador, $rapido, 'asignar', ['tecnico_id' => $this->tecnico->id]);
    $this->travelTo(Carbon::parse('2026-10-14 12:00'));
    pasoDeAsignacion($this->coordinador, $lento, 'asignar', ['tecnico_id' => $this->tecnico->id]);

    $datos = $this->actingAs($this->coordinador, 'sanctum')
        ->getJson('/api/v1/tic/reportes/tickets?desde=2026-10-01&hasta=2026-10-31')->assertOk()->json('datos');

    // Una hora y tres horas: dos de promedio. El que sigue sin asignar no cuenta.
    expect($datos['totales']['horas_asignacion'])->toEqual(2)
        ->and(collect($datos['por_tecnico'])->firstWhere('tecnico', $this->tecnico->nombre_completo)['horas_asignacion'])->toEqual(2)
        ->and(Ticket::find($sinAsignar)->asignado_en)->toBeNull();

    $this->get('/api/v1/tic/reportes/tickets?desde=2026-10-01&hasta=2026-10-31&formato=pdf')->assertOk();
});

test('la migración completa los anteriores con su línea de tiempo', function () {
    $id = ticketParaAsignar();
    $this->travelTo(Carbon::parse('2026-10-14 10:30'));
    pasoDeAsignacion($this->coordinador, $id, 'asignar', ['tecnico_id' => $this->tecnico->id]);
    $nunca = ticketParaAsignar();
    Ticket::query()->update(['asignado_en' => null]);

    (require database_path('migrations/2026_12_20_000000_ticket_asignado_en.php'))->up();

    expect(Ticket::find($id)->asignado_en->format('H:i'))->toBe('10:30')
        ->and(Ticket::find($nunca)->asignado_en)->toBeNull()
        ->and(HistorialEstadoTicket::where('ticket_id', $id)->where('estado_nuevo', 'asignado')->count())->toBe(1);
});
