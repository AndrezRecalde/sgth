<?php

use App\Enums\RegimenLaboral;
use App\Helpers\NumeroALetras;
use App\Models\Estructura\Cargo;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\Servidor;
use App\Models\Helpdesk\CategoriaTicket;
use App\Models\Helpdesk\Ticket;
use App\Models\InventarioTi\AsignacionBien;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\MantenimientoBien;
use App\Models\InventarioTi\Marca;
use App\Models\InventarioTi\MovimientoConsumible;
use App\Models\InventarioTi\TipoBien;
use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
| La «Solicitud de materiales informáticos a bodega» (docs/diseno-subsistema-tic.md,
| fase 5.4c): sale de un ticket «Consumibles» o del módulo, con número por año y
| los jefes del organigrama sellados; lo que despacha Bodega es la entrega y
| cierra el ticket.
*/

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Servidor::unguard();
    BienInformatico::unguard();
    AsignacionBien::unguard();
    $this->seed(RolPermisoSeeder::class);
    $this->travelTo(now()->setDate(2026, 10, 16)->setTime(10, 0));

    $this->tecnico = User::factory()->create(['primer_login' => false, 'activo' => true]);
    $this->tecnico->assignRole('tecnico-dtic');
    $this->auditor = User::factory()->create(['primer_login' => false]);
    $this->auditor->assignRole('auditor');

    // El organigrama: la institución, Gestión Administrativa, TIC y la unidad del servidor dentro de su dirección.
    $raiz = UnidadAdministrativa::create(['codigo' => 'GADPE', 'nombre' => 'GADPE', 'nivel' => 1]);
    $hija = fn (string $codigo, string $nombre, UnidadAdministrativa $padre, int $nivel = 2) => UnidadAdministrativa::create([
        'codigo' => $codigo, 'nombre' => $nombre, 'nivel' => $nivel, 'unidad_padre_id' => $padre->id,
    ]);
    $administrativa = $hija('GADPE-01', 'Gestión Administrativa', $raiz);
    $tic = $hija('GADPE-15', 'Gestión de Tecnologías de la Información y Comunicación', $raiz);
    $fomento = $hija('SB-FOM', 'Fomento y Desarrollo Productivo', $raiz);
    $this->emprendimiento = $hija('SB-EMP', 'Unidad de Emprendimiento', $fomento, 3);

    $n = 0;
    $jefe = function (UnidadAdministrativa $u, string $cargo, string $apellido) use (&$n): Servidor {
        $n++;
        $puesto = Puesto::create([
            'unidad_administrativa_id' => $u->id, 'plazas' => 1, 'activo' => true, 'es_jefe' => true,
            'cargo_id' => Cargo::firstOrCreate(['nombre' => $cargo])->id,
        ]);
        $s = Servidor::create([
            'cedula' => sprintf('08066%05d', $n), 'nombre' => 'Jefe', 'apellido' => $apellido,
            'unidad_administrativa_id' => $u->id, 'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true,
        ]);
        ContratoServidor::create([
            'servidor_id' => $s->id, 'tipo_nombramiento' => 'nombramiento_permanente', 'unidad_administrativa_id' => $u->id,
            'puesto_id' => $puesto->id, 'fecha_inicio' => '2023-05-15', 'estado' => 'vigente',
        ]);

        return $s;
    };
    $jefe($administrativa, 'Director Administrativo', 'Gutiérrez');
    $jefe($tic, 'Directora de TIC', 'Estupiñán');
    $jefe($this->emprendimiento, 'Jefe de Emprendimiento', 'Quiñónez');

    $this->ana = Servidor::create([
        'cedula' => '0806611111', 'nombre' => 'Alisson', 'apellido' => 'Girón',
        'unidad_administrativa_id' => $this->emprendimiento->id, 'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true,
    ]);
    $this->usuarioAna = User::factory()->create(['primer_login' => false, 'servidor_id' => $this->ana->id]);
    $this->usuarioAna->assignRole('servidor');

    $this->hp = Marca::create(['nombre' => 'HP']);
    $this->impresora = BienInformatico::create([
        'codigo_qr' => '1696-QR', 'codigo_institucional' => '001696', 'codigo_anterior' => '0815',
        'tipo_bien_id' => TipoBien::create(['nombre' => 'Impresora'])->id, 'marca_id' => $this->hp->id, 'modelo' => 'LaserJet M551',
        'estado_operativo' => 'en_uso', 'condicion_fisica' => 'bueno',
    ]);
    AsignacionBien::create([
        'bien_informatico_id' => $this->impresora->id, 'servidor_id' => $this->ana->id,
        'fecha_asignacion' => '2026-01-05', 'estado' => 'activa', 'motivo' => 'entrega',
    ]);

    $this->actingAs($this->tecnico, 'sanctum');
    $consumible = fn (array $datos) => $this->postJson('/api/v1/inventario/consumibles', array_merge([
        'tipo' => 'toner', 'presentacion' => 'unidad', 'guarda_tic' => false,
        'modelos' => [['marca_id' => $this->hp->id, 'modelo' => 'LaserJet M551']],
    ], $datos))->assertCreated()->json('datos.id');
    $this->toner = $consumible(['descripcion' => 'Tóner HP CE390A negro', 'codigo_bodega' => '0008436']);
    $this->cable = $consumible(['descripcion' => 'Cable de poder', 'tipo' => 'repuesto', 'modelos' => []]);
    $this->tonerTic = $consumible(['descripcion' => 'Tóner HP CE390X negro', 'guarda_tic' => true]);
});

function solicitarABodega(array $datos = []): \Illuminate\Testing\TestResponse
{
    return test()->postJson('/api/v1/inventario/solicitudes-bodega', array_merge([
        'servidor_id' => test()->ana->id, 'bien_informatico_id' => test()->impresora->id,
        'motivo' => 'La impresora de Emprendimiento se quedó sin tóner',
        'items' => [['consumible_id' => test()->toner, 'cantidad' => 2], ['consumible_id' => test()->cable, 'cantidad' => 1]],
    ], $datos));
}

function despacharSolicitud(int $id, array $cantidades, string $fecha = '2026-10-16'): \Illuminate\Testing\TestResponse
{
    $items = collect(test()->getJson("/api/v1/inventario/solicitudes-bodega/{$id}")->json('datos.items'));

    return test()->postJson("/api/v1/inventario/solicitudes-bodega/{$id}/despachar", [
        'fecha' => $fecha,
        'items' => $items->map(fn (array $i) => ['item_id' => $i['id'], 'cantidad_despachada' => $cantidades[$i['consumible_id']] ?? 0])->all(),
    ]);
}

function ticketDeConsumibles(array $datos = []): int
{
    return test()->post('/api/v1/helpdesk/tickets', array_merge([
        'servidor_id' => test()->ana->id, 'categoria_id' => CategoriaTicket::where('es_consumibles', true)->sole()->id,
        'bien_informatico_id' => test()->impresora->id,
        'asunto' => 'Tóner para la impresora', 'descripcion' => 'Pidió por teléfono el tóner de la impresora de su unidad.',
    ], $datos), ['Accept' => 'application/json'])->assertCreated()->json('datos.id');
}

test('se genera sin ticket con número por año y los jefes del organigrama sellados', function () {
    $s = solicitarABodega()->assertCreated()->json('datos');
    solicitarABodega()->assertCreated()->assertJsonPath('datos.codigo', 'SB-2026-0002');

    expect($s['codigo'])->toBe('SB-2026-0001')
        ->and($s['estado'])->toBe('pendiente')
        ->and($s['unidad'])->toBe('Unidad de Emprendimiento')
        ->and($s['equipo'])->toMatchArray(['codigo_institucional' => '001696', 'codigo_anterior' => '0815'])
        ->and($s['firmantes']['area'])->toBe(['nombre' => 'Jefe Quiñónez', 'cargo' => 'Jefe de Emprendimiento'])
        ->and($s['firmantes']['tic'])->toBe(['nombre' => 'Jefe Estupiñán', 'cargo' => 'Directora de TIC'])
        ->and($s['firmantes']['administrativo'])->toBe(['nombre' => 'Jefe Gutiérrez', 'cargo' => 'Director Administrativo'])
        ->and($s['firmantes']['solicitante']['nombre'])->toBe('Alisson Girón')
        ->and(array_column($s['items'], 'cantidad_solicitada'))->toBe([2, 1]);

    $pdf = $this->get("/api/v1/inventario/solicitudes-bodega/{$s['id']}/pdf")->assertOk();
    expect($pdf->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($pdf->headers->get('Content-Disposition'))->toContain('sb-2026-0001.pdf');
});

test('la consulta el auditor; la generan, despachan y anulan los técnicos', function () {
    $id = solicitarABodega()->assertCreated()->json('datos.id');

    $this->actingAs($this->auditor, 'sanctum');
    $this->getJson('/api/v1/inventario/solicitudes-bodega')->assertOk()->assertJsonPath('datos.0.id', $id)->assertJsonPath('meta.total', 1);
    $this->get("/api/v1/inventario/solicitudes-bodega/{$id}/pdf")->assertOk();
    solicitarABodega()->assertForbidden();
    $this->postJson("/api/v1/inventario/solicitudes-bodega/{$id}/despachar", [])->assertForbidden();
    $this->postJson("/api/v1/inventario/solicitudes-bodega/{$id}/anular", ['motivo' => 'Prueba de acceso'])->assertForbidden();
});

test('lo de impresión va con su equipo compatible, y sin ticket hay que decir de quién es', function () {
    solicitarABodega(['bien_informatico_id' => null])->assertStatus(422)
        ->assertJsonPath('mensaje', '«Tóner HP CE390A negro» es de impresión: la solicitud va con el equipo que lo usa.');
    $otra = BienInformatico::create([
        'codigo_qr' => '2000-QR', 'codigo_institucional' => '002000', 'tipo_bien_id' => $this->impresora->tipo_bien_id,
        'marca_id' => $this->hp->id, 'modelo' => 'OfficeJet 9010', 'estado_operativo' => 'en_uso', 'condicion_fisica' => 'bueno',
    ]);
    solicitarABodega(['bien_informatico_id' => $otra->id])->assertStatus(422);
    solicitarABodega(['servidor_id' => null])->assertStatus(422)->assertJsonValidationErrors('servidor_id', 'errores');
    // Un repuesto solo, sin equipo, sí.
    solicitarABodega(['bien_informatico_id' => null, 'items' => [['consumible_id' => $this->cable, 'cantidad' => 3]]])->assertCreated();
});

test('lo despachado es la entrega: sin saldo lo de Bodega y por la solicitud lo que guarda TIC', function () {
    $id = solicitarABodega(['items' => [
        ['consumible_id' => $this->toner, 'cantidad' => 2], ['consumible_id' => $this->cable, 'cantidad' => 1],
        ['consumible_id' => $this->tonerTic, 'cantidad' => 1],
    ]])->assertCreated()->json('datos.id');

    despacharSolicitud($id, [$this->toner => 3])->assertStatus(422);
    despacharSolicitud($id, [])->assertStatus(422);
    despacharSolicitud($id, [$this->toner => 1], '2026-10-15')->assertStatus(422);
    despacharSolicitud($id, [$this->toner => 1, $this->tonerTic => 1])->assertOk()
        ->assertJsonPath('datos.estado', 'despachada')
        ->assertJsonPath('datos.fecha_despacho', '2026-10-16');

    $items = collect($this->getJson("/api/v1/inventario/solicitudes-bodega/{$id}")->json('datos.items'))->keyBy('consumible_id');
    expect($items[$this->toner]['cantidad_despachada'])->toBe(1)
        ->and($items[$this->cable]['cantidad_despachada'])->toBe(0);

    $movimientos = MovimientoConsumible::where('solicitud_bodega_id', $id)->orderBy('id')->get();
    expect($movimientos->map(fn ($m) => [$m->consumible_id, $m->tipo->value, $m->cantidad, $m->afecta_saldo])->all())->toBe([
        [$this->tonerTic, 'ingreso', 1, true],
        [$this->toner, 'entrega', -1, false],
        [$this->tonerTic, 'entrega', -1, true],
    ])
        ->and($movimientos[0]->tipo_documento->value)->toBe('solicitud_bodega')
        ->and($movimientos[0]->numero_documento)->toBe('SB-2026-0001')
        ->and($movimientos[1]->servidor_retira_id)->toBe($this->ana->id)
        ->and($movimientos[1]->bien_informatico_id)->toBe($this->impresora->id);
    // Lo que guarda TIC entró y salió el mismo día: su saldo no cambia.
    $this->getJson("/api/v1/inventario/consumibles/{$this->tonerTic}")->assertJsonPath('datos.saldo', 0);

    despacharSolicitud($id, [$this->toner => 1])->assertStatus(422);
    $this->postJson("/api/v1/inventario/solicitudes-bodega/{$id}/anular", ['motivo' => 'Ya no la quiere'])->assertStatus(422);
});

test('desde un ticket «Consumibles»: toma su servidor y su equipo, y el despacho lo cierra', function () {
    $ticket = ticketDeConsumibles();

    $s = solicitarABodega(['ticket_id' => $ticket, 'servidor_id' => null, 'bien_informatico_id' => null])->assertCreated()->json('datos');
    expect($s)->toMatchArray(['ticket_id' => $ticket, 'servidor_id' => $this->ana->id])
        ->and($s['equipo']['id'])->toBe($this->impresora->id);
    // Una pendiente por ticket.
    solicitarABodega(['ticket_id' => $ticket])->assertStatus(422);

    despacharSolicitud($s['id'], [$this->toner => 2, $this->cable => 1])->assertOk();
    $t = Ticket::findOrFail($ticket);
    expect($t->estado->value)->toBe('cerrado')
        ->and($t->solucion)->toBe('Bodega despachó la solicitud SB-2026-0001: 2 × Tóner HP CE390A negro; 1 × Cable de poder.')
        ->and($t->tecnico_id)->toBe($this->tecnico->id)
        ->and(MovimientoConsumible::where('ticket_id', $ticket)->count())->toBe(2)
        // Lleva equipo, pero no es un mantenimiento.
        ->and(MantenimientoBien::count())->toBe(0);
    solicitarABodega(['ticket_id' => $ticket])->assertStatus(422);
});

test('el ticket tiene que ser de la categoría «Consumibles»', function () {
    $otra = CategoriaTicket::where('es_consumibles', false)->where('activa', true)->first();
    $ticket = ticketDeConsumibles(['categoria_id' => $otra->id]);

    solicitarABodega(['ticket_id' => $ticket])->assertStatus(422)->assertJsonPath('mensaje', 'El ticket TIC-2026-00001 no es de la categoría «Consumibles».');
});

test('resolver a mano un ticket «Consumibles» no deja un mantenimiento en la ficha', function () {
    $ticket = ticketDeConsumibles();
    $this->postJson("/api/v1/helpdesk/tickets/{$ticket}/tomar")->assertOk();
    $this->postJson("/api/v1/helpdesk/tickets/{$ticket}/resolver", ['solucion' => 'Se entregó de lo que guarda TIC.'])->assertOk();

    expect(MantenimientoBien::count())->toBe(0);
});

test('se anula la pendiente con su motivo', function () {
    $id = solicitarABodega()->assertCreated()->json('datos.id');

    $this->postJson("/api/v1/inventario/solicitudes-bodega/{$id}/anular", ['motivo' => 'Se pidió el tóner equivocado'])->assertOk()
        ->assertJsonPath('datos.estado', 'anulada')
        ->assertJsonPath('datos.motivo_anulacion', 'Se pidió el tóner equivocado');
    despacharSolicitud($id, [$this->toner => 1])->assertStatus(422);
    $this->get("/api/v1/inventario/solicitudes-bodega/{$id}/pdf")->assertOk();
});

test('el documento de la solicitud no se elige al registrar un ingreso a mano', function () {
    $this->postJson('/api/v1/inventario/consumibles/ingresos', [
        'fecha' => '2026-10-16', 'tipo_documento' => 'solicitud_bodega', 'numero_documento' => 'SB-2026-0001',
        'items' => [['consumible_id' => $this->tonerTic, 'cantidad' => 1]],
    ])->assertStatus(422)->assertJsonValidationErrors('tipo_documento', 'errores');
});

test('la cantidad va en letras', function () {
    expect(NumeroALetras::cantidad(1))->toBe('UNO')
        ->and(NumeroALetras::cantidad(21))->toBe('VEINTE Y UNO')
        ->and(NumeroALetras::cantidad(12))->toBe('DOCE')
        ->and(NumeroALetras::cantidad(0))->toBe('CERO');
});
