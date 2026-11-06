<?php

use App\Enums\RegimenLaboral;
use App\Models\Expediente\Servidor;
use App\Models\Helpdesk\CategoriaTicket;
use App\Models\Helpdesk\HistorialEstadoTicket;
use App\Models\Helpdesk\ProblemaFrecuente;
use App\Models\Helpdesk\Ticket;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\MantenimientoBien;
use App\Models\InventarioTi\Marca;
use App\Models\InventarioTi\TipoBien;
use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

/*
| Corregir y cerrar bien un ticket (2026-11-05, PR 3 de los problemas
| frecuentes): quien lo atiende corrige la categoría con un motivo, y uno de
| hardware no se resuelve sin decir qué equipo se atendió o por qué no es uno
| del inventario. Vale la categoría con que se cierra, no la de apertura.
*/

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    Servidor::unguard();
    BienInformatico::unguard();
    $this->seed(RolPermisoSeeder::class);
    $this->travelTo(now()->setDate(2026, 11, 5)->setTime(9, 0));

    $tic = unidadDePrueba(['codigo' => config('helpdesk.unidad_tic_codigo'), 'nombre' => 'Gestión de TIC']);
    $this->soporte = unidadDePrueba(['nombre' => 'Soporte Técnico', 'unidad_padre_id' => $tic->id, 'nivel' => 2]);
    $this->desarrollo = unidadDePrueba(['nombre' => 'Desarrollo de Software', 'unidad_padre_id' => $tic->id, 'nivel' => 2]);
    $this->hardware = CategoriaTicket::where('nombre', 'Hardware')->sole();
    $this->software = CategoriaTicket::where('nombre', 'Software')->sole();
    $this->sistemas = CategoriaTicket::where('nombre', 'Sistemas institucionales')->sole();
    $this->hardware->update(['unidad_tic_id' => $this->soporte->id]);
    $this->sistemas->update(['unidad_tic_id' => $this->desarrollo->id]);

    $this->paola = Servidor::create(['cedula' => '0809090901', 'nombre' => 'Paola', 'apellido' => 'Benavides', 'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true]);
    $this->usuarioPaola = User::factory()->create(['primer_login' => false, 'activo' => true, 'servidor_id' => $this->paola->id]);
    $this->usuarioPaola->assignRole('servidor');
    $this->tecnico = User::factory()->create(['primer_login' => false, 'activo' => true]);
    $this->tecnico->assignRole('tecnico-dtic');
    $this->otroTecnico = User::factory()->create(['primer_login' => false, 'activo' => true]);
    $this->otroTecnico->assignRole('tecnico-dtic');

    $this->equipo = BienInformatico::create([
        'codigo_qr' => '2001-QR', 'codigo_institucional' => '002001',
        'tipo_bien_id' => TipoBien::create(['nombre' => 'Computador'])->id, 'marca_id' => Marca::create(['nombre' => 'HP'])->id,
        'estado_operativo' => 'en_uso', 'condicion_fisica' => 'bueno',
    ]);
});

/** Un ticket del Portal con un problema frecuente, ya tomado por el técnico. */
function ticketTomado(string $problema): int
{
    $id = test()->actingAs(test()->usuarioPaola, 'sanctum')->postJson('/api/v1/helpdesk/tickets', [
        'problema_frecuente_id' => ProblemaFrecuente::where('nombre', $problema)->value('id'),
    ])->assertCreated()->json('datos.id');
    asignarTicketA($id, test()->tecnico);
    test()->actingAs(test()->tecnico, 'sanctum')->postJson("/api/v1/helpdesk/tickets/{$id}/tomar")->assertOk();

    return $id;
}

function resolverTicket(int $id, array $datos = []): Illuminate\Testing\TestResponse
{
    return test()->actingAs(test()->tecnico, 'sanctum')->postJson("/api/v1/helpdesk/tickets/{$id}/resolver", [
        'solucion' => 'Se revisó y quedó funcionando.', ...$datos,
    ]);
}

test('uno de hardware sin equipo no se resuelve sin el equipo atendido o el motivo', function () {
    $id = ticketTomado('El equipo no enciende o está muy lento');

    resolverTicket($id)->assertStatus(422)
        ->assertJsonPath('errores.bien_informatico_id.0', 'Indique el equipo atendido, o marque que no es un equipo del inventario y diga por qué.');
    resolverTicket($id, ['motivo_sin_equipo' => 'no'])->assertStatus(422)->assertJsonStructure(['errores' => ['motivo_sin_equipo']]);
    resolverTicket($id, ['bien_informatico_id' => 999_999])->assertStatus(422)->assertJsonStructure(['errores' => ['bien_informatico_id']]);

    expect(Ticket::find($id)->estado->value)->toBe('en_proceso');
});

test('con el equipo atendido, queda vinculado y con el correctivo en su ficha', function () {
    $id = ticketTomado('El equipo no enciende o está muy lento');

    resolverTicket($id, ['bien_informatico_id' => $this->equipo->id])->assertOk()->assertJsonPath('datos.estado', 'resuelto');

    expect(Ticket::find($id)->bien_informatico_id)->toBe($this->equipo->id)
        ->and(MantenimientoBien::sole()->bien_informatico_id)->toBe($this->equipo->id);
});

test('«No es un equipo del inventario»: se resuelve sin mantenimiento y el motivo queda en la línea de tiempo', function () {
    $id = ticketTomado('La impresora no imprime');

    resolverTicket($id, ['motivo_sin_equipo' => 'Impresora personal del servidor'])->assertOk()
        ->assertJsonPath('datos.motivo_sin_equipo', 'Impresora personal del servidor');

    expect(MantenimientoBien::count())->toBe(0)
        ->and(HistorialEstadoTicket::where('ticket_id', $id)->latest('id')->value('detalle'))
        ->toBe('Resuelto sin equipo del inventario: Impresora personal del servidor');
});

test('si ya tiene equipo, no se pide nada más; y los que no son de hardware, tampoco', function () {
    $conEquipo = test()->actingAs($this->usuarioPaola, 'sanctum')->postJson('/api/v1/helpdesk/tickets', [
        'categoria_id' => $this->hardware->id, 'asunto' => 'El monitor parpadea', 'descripcion' => 'Desde ayer el monitor se apaga solo.',
    ])->assertCreated()->json('datos.id');
    Ticket::whereKey($conEquipo)->update(['bien_informatico_id' => $this->equipo->id]);
    asignarTicketA($conEquipo, $this->tecnico);
    $this->actingAs($this->tecnico, 'sanctum')->postJson("/api/v1/helpdesk/tickets/{$conEquipo}/tomar")->assertOk();
    resolverTicket($conEquipo)->assertOk();

    resolverTicket(ticketTomado('No tengo internet'))->assertOk();
    resolverTicket(ticketTomado('Ayuda con FirmaEC'))->assertOk();
});

test('vale la categoría con que se cierra: el «equipo lento» que era un virus ya no pide equipo', function () {
    $id = ticketTomado('El equipo no enciende o está muy lento');
    $this->travel(2)->hours();

    $this->actingAs($this->tecnico, 'sanctum')->postJson("/api/v1/helpdesk/tickets/{$id}/cambiar-categoria", [
        'categoria_id' => $this->software->id, 'motivo' => 'Era un virus, no el equipo.',
    ])->assertOk()->assertJsonPath('datos.categoria.nombre', 'Software');

    resolverTicket($id)->assertOk();

    $ticket = Ticket::find($id);
    expect($ticket->categoria_id)->toBe($this->software->id)
        // El problema con que se abrió no cambia: el reporte muestra lo que eligió el servidor.
        ->and($ticket->problemaFrecuente->nombre)->toBe('El equipo no enciende o está muy lento');
});

test('cambiar la categoría lleva la subunidad y el plazo de la nueva, desde el alta, y deja el motivo', function () {
    $id = ticketTomado('Ayuda con FirmaEC');
    $antes = Ticket::find($id);
    $this->travel(3)->hours();

    $this->actingAs($this->tecnico, 'sanctum')->postJson("/api/v1/helpdesk/tickets/{$id}/cambiar-categoria", [
        'categoria_id' => $this->sistemas->id, 'motivo' => 'Es un error del sistema de compras.',
    ])->assertOk();

    $despues = Ticket::find($id);
    expect($despues->unidad_tic_id)->toBe($this->desarrollo->id)
        ->and($despues->estado->value)->toBe('en_proceso')
        // Se recalcula desde que se abrió, no desde el cambio.
        ->and($despues->fecha_vencimiento_sla->equalTo($antes->fecha_vencimiento_sla))->toBeTrue()
        ->and(HistorialEstadoTicket::where('ticket_id', $id)->latest('id')->value('detalle'))
        ->toBe('Categoría: Software → Sistemas institucionales. Es un error del sistema de compras.');
});

test('cambiar la categoría: activa, distinta, con motivo, en curso y por quien lo atiende', function () {
    $id = ticketTomado('Ayuda con FirmaEC');
    $cambiar = fn (User $u, array $d) => $this->actingAs($u, 'sanctum')->postJson("/api/v1/helpdesk/tickets/{$id}/cambiar-categoria", $d);

    $cambiar($this->tecnico, ['categoria_id' => $this->software->id, 'motivo' => 'Sin cambio real.'])
        ->assertStatus(422)->assertJsonPath('errores.categoria_id.0', 'El ticket ya tiene esa categoría.');
    $cambiar($this->tecnico, ['categoria_id' => $this->hardware->id, 'motivo' => 'no'])->assertStatus(422);
    $this->sistemas->update(['activa' => false]);
    $cambiar($this->tecnico, ['categoria_id' => $this->sistemas->id, 'motivo' => 'Es del sistema.'])
        ->assertStatus(422)->assertJsonStructure(['errores' => ['categoria_id']]);

    // Ni el solicitante ni un técnico que no lo tiene en su cola.
    $cambiar($this->usuarioPaola, ['categoria_id' => $this->hardware->id, 'motivo' => 'Es del equipo.'])->assertForbidden();
    $cambiar($this->otroTecnico, ['categoria_id' => $this->hardware->id, 'motivo' => 'Es del equipo.'])->assertForbidden();

    resolverTicket($id)->assertOk();
    $cambiar($this->tecnico, ['categoria_id' => $this->hardware->id, 'motivo' => 'Era del equipo.'])
        ->assertStatus(422)->assertJsonPath('mensaje', 'El ticket TIC-2026-00001 está resuelto: ya no se cambia su categoría.');
});
