<?php

use App\Enums\RegimenLaboral;
use App\Models\Expediente\Servidor;
use App\Models\Helpdesk\CategoriaTicket;
use App\Models\Helpdesk\ProblemaFrecuente;
use App\Models\Helpdesk\Ticket;
use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

/*
| «Pedir ayuda a TIC» con un toque (respuestas de TIC del 2026-11-05): el
| servidor elige un problema frecuente y el ticket sale con la categoría, el
| asunto, la descripción, el tipo y la prioridad que fijó TIC. Puede agregar
| un comentario. Cada ticket guarda con qué problema se abrió, para el reporte
| de uso que TIC revisa cada trimestre.
*/

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    Servidor::unguard();
    $this->seed(RolPermisoSeeder::class);

    $tic = unidadDePrueba(['codigo' => config('helpdesk.unidad_tic_codigo'), 'nombre' => 'Gestión de TIC']);
    $this->redes = unidadDePrueba(['nombre' => 'Infraestructura y Redes', 'unidad_padre_id' => $tic->id, 'nivel' => 2]);
    CategoriaTicket::where('nombre', 'Red e internet')->update(['unidad_tic_id' => $this->redes->id]);

    $this->paola = Servidor::create(['cedula' => '0805050501', 'nombre' => 'Paola', 'apellido' => 'Benavides', 'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true]);
    $this->usuarioPaola = User::factory()->create(['primer_login' => false, 'activo' => true, 'servidor_id' => $this->paola->id]);
    $this->usuarioPaola->assignRole('servidor');
    $this->jefe = User::factory()->create(['primer_login' => false, 'activo' => true]);
    $this->jefe->assignRole('jefe-tic');

    $this->internet = ProblemaFrecuente::where('nombre', 'No tengo internet')->sole();
    $this->firma = ProblemaFrecuente::where('nombre', 'Ayuda con FirmaEC')->sole();
});

function pedirAyuda(User $u, array $datos): Illuminate\Testing\TestResponse
{
    return test()->actingAs($u, 'sanctum')->postJson('/api/v1/helpdesk/tickets', $datos);
}

test('desde el Portal, el problema llena el ticket: asunto, descripción, categoría, tipo y prioridad', function () {
    $id = pedirAyuda($this->usuarioPaola, ['problema_frecuente_id' => $this->internet->id])->assertCreated()->json('datos.id');

    $ticket = Ticket::findOrFail($id);
    expect($ticket->asunto)->toBe('No tengo internet')
        ->and($ticket->descripcion)->toBe('No tengo conexión a internet en mi equipo.')
        ->and($ticket->categoria->nombre)->toBe('Red e internet')
        ->and($ticket->tipo_ticket)->toBe('incidente')
        // La prioridad la fijó TIC en el problema; sin él, el Portal entra en «media».
        ->and($ticket->prioridad->value)->toBe('alta')
        ->and($ticket->problema_frecuente_id)->toBe($this->internet->id)
        // La subunidad sigue saliendo de la categoría.
        ->and($ticket->unidad_tic_id)->toBe($this->redes->id)
        ->and($ticket->sla_resolucion_horas)->not->toBeNull();
});

test('el comentario del servidor va debajo de la descripción de TIC', function () {
    $id = pedirAyuda($this->usuarioPaola, [
        'problema_frecuente_id' => $this->firma->id, 'comentario' => '  Me sale «certificado no válido».  ',
        // Aunque se manden, el asunto y la categoría son los del problema.
        'asunto' => 'Otra cosa', 'categoria_id' => CategoriaTicket::where('nombre', 'Hardware')->value('id'),
    ])->assertCreated()->json('datos.id');

    $ticket = Ticket::findOrFail($id);
    expect($ticket->descripcion)->toBe("Necesito ayuda con FirmaEC: instalarlo, actualizarlo o resolver un error al firmar.\n\nMe sale «certificado no válido».")
        ->and($ticket->asunto)->toBe('Ayuda con FirmaEC')
        ->and($ticket->categoria->nombre)->toBe('Software')
        ->and($ticket->tipo_ticket)->toBe('solicitud')
        ->and($ticket->prioridad->value)->toBe('media');
});

test('un problema inactivo, o con su categoría inactiva, ya no se puede elegir', function () {
    $this->internet->update(['activo' => false]);
    pedirAyuda($this->usuarioPaola, ['problema_frecuente_id' => $this->internet->id])
        ->assertStatus(422)->assertJsonPath('errores.problema_frecuente_id.0', 'Ese problema ya no se ofrece: elija otro o «Otro problema».');

    CategoriaTicket::where('nombre', 'Software')->update(['activa' => false]);
    pedirAyuda($this->usuarioPaola, ['problema_frecuente_id' => $this->firma->id])
        ->assertStatus(422)->assertJsonStructure(['errores' => ['problema_frecuente_id']]);

    expect(Ticket::count())->toBe(0);
});

test('sin problema, «Otro problema» es el formulario libre de siempre', function () {
    $errores = array_keys(pedirAyuda($this->usuarioPaola, [])->assertStatus(422)->json('errores'));
    expect($errores)->toEqualCanonicalizing(['categoria_id', 'asunto', 'descripcion']);

    $id = pedirAyuda($this->usuarioPaola, [
        'categoria_id' => CategoriaTicket::where('nombre', 'Hardware')->value('id'),
        'asunto' => 'El monitor parpadea', 'descripcion' => 'Desde ayer el monitor se apaga solo.',
    ])->assertCreated()->json('datos.id');

    $ticket = Ticket::findOrFail($id);
    expect($ticket->problema_frecuente_id)->toBeNull()
        ->and($ticket->prioridad->value)->toBe('media')
        ->and($ticket->tipo_ticket)->toBe('incidente');
});

test('TIC registra con un problema: su prioridad manda, y si no pone una, la del problema', function () {
    $conPrioridad = pedirAyuda($this->jefe, ['servidor_id' => $this->paola->id, 'problema_frecuente_id' => $this->firma->id, 'prioridad' => 'urgente'])
        ->assertCreated()->json('datos.id');
    $sinPrioridad = pedirAyuda($this->jefe, ['servidor_id' => $this->paola->id, 'problema_frecuente_id' => $this->internet->id])
        ->assertCreated()->json('datos.id');

    expect(Ticket::find($conPrioridad)->prioridad->value)->toBe('urgente')
        ->and(Ticket::find($sinPrioridad)->prioridad->value)->toBe('alta')
        ->and(Ticket::find($sinPrioridad)->solicitante_id)->toBe($this->paola->id)
        ->and(Ticket::find($sinPrioridad)->problema_frecuente_id)->toBe($this->internet->id);
});

test('el catálogo trae los problemas activos con categoría activa, en el orden de TIC', function () {
    $this->firma->update(['activo' => false]);
    CategoriaTicket::where('nombre', 'Correo institucional')->update(['activa' => false]);
    $this->internet->update(['orden' => 0]);

    $problemas = $this->actingAs($this->usuarioPaola, 'sanctum')->getJson('/api/v1/helpdesk/tickets/catalogos')
        ->assertOk()->json('datos.problemas');

    expect(array_column($problemas, 'nombre'))->toBe([
        'No tengo internet',
        'El equipo no enciende o está muy lento',
        'La impresora no imprime',
        'Instalar o conectar una impresora',
        'Desbloquear mi usuario o cambiar mi contraseña',
    ])->and($problemas[0])->toMatchArray([
        'categoria' => 'Red e internet', 'unidad_tic' => 'Infraestructura y Redes', 'prioridad' => 'alta', 'tipo_ticket' => 'incidente',
    ]);
});

test('el reporte muestra el uso de cada problema, los que nadie usó y el formulario libre', function () {
    pedirAyuda($this->usuarioPaola, ['problema_frecuente_id' => $this->internet->id])->assertCreated();
    pedirAyuda($this->usuarioPaola, ['problema_frecuente_id' => $this->internet->id])->assertCreated();
    pedirAyuda($this->usuarioPaola, [
        'categoria_id' => CategoriaTicket::where('nombre', 'Hardware')->value('id'),
        'asunto' => 'El monitor parpadea', 'descripcion' => 'Desde ayer el monitor se apaga solo.',
    ])->assertCreated();
    // Uno desactivado y sin uso ya no aparece; usado en el rango, sí.
    ProblemaFrecuente::where('nombre', 'No puedo entrar a mi correo')->update(['activo' => false]);

    $filas = collect($this->actingAs($this->jefe, 'sanctum')
        ->getJson('/api/v1/tic/reportes/tickets?desde=' . today()->subMonths(3)->toDateString() . '&hasta=' . today()->toDateString())
        ->assertOk()->json('datos.por_problema'))->keyBy('problema');

    expect($filas['No tengo internet']['abiertos'])->toBe(2)
        ->and($filas['Ayuda con FirmaEC']['abiertos'])->toBe(0)
        ->and($filas['Otro problema (formulario libre)']['abiertos'])->toBe(1)
        ->and($filas->has('No puedo entrar a mi correo'))->toBeFalse()
        ->and($filas->keys()->last())->toBe('Otro problema (formulario libre)');

    // Configuración muestra el uso de los últimos tres meses a quien mantiene el catálogo.
    $lista = collect($this->getJson('/api/v1/helpdesk/problemas-frecuentes')->json('datos'))->keyBy('nombre');
    expect($lista['No tengo internet']['tickets_recientes'])->toBe(2)
        ->and($lista['Ayuda con FirmaEC']['tickets_recientes'])->toBe(0);
});

test('el Excel y el PDF del reporte llevan la sección por problema', function () {
    pedirAyuda($this->usuarioPaola, ['problema_frecuente_id' => $this->internet->id])->assertCreated();
    $rango = 'desde=' . today()->toDateString() . '&hasta=' . today()->toDateString();
    $this->actingAs($this->jefe, 'sanctum');

    $this->get("/api/v1/tic/reportes/tickets?{$rango}&formato=excel")->assertOk();
    $this->get("/api/v1/tic/reportes/tickets?{$rango}&formato=pdf")->assertOk();
});
