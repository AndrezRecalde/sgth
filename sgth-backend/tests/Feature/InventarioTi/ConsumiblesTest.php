<?php

use App\Enums\RegimenLaboral;
use App\Models\Expediente\Servidor;
use App\Models\InventarioTi\AsignacionBien;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\Consumible;
use App\Models\InventarioTi\Marca;
use App\Models\InventarioTi\MovimientoConsumible;
use App\Models\InventarioTi\TipoBien;
use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/*
| Los consumibles de impresión y los repuestos (docs/diseno-subsistema-tic.md,
| fase 5.4a): el catálogo con la compatibilidad por modelo, la marca «lo guarda
| TIC» y los movimientos. El saldo de lo que guarda TIC es la suma de sus
| movimientos y nunca queda negativo; lo que despacha Bodega solo registra
| entregas, sin saldo. Nada se borra: se anula con su motivo.
*/

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Servidor::unguard();
    BienInformatico::unguard();
    AsignacionBien::unguard();
    $this->seed(RolPermisoSeeder::class);
    $this->travelTo(now()->setDate(2026, 10, 16)->setTime(10, 0));

    $this->tecnico = User::factory()->create(['primer_login' => false]);
    $this->tecnico->assignRole('tecnico-dtic');
    $this->auditor = User::factory()->create(['primer_login' => false]);
    $this->auditor->assignRole('auditor');

    $this->hp = Marca::create(['nombre' => 'HP']);
    $this->lexmark = Marca::create(['nombre' => 'Lexmark']);
    $this->impresora = TipoBien::create(['nombre' => 'Impresora']);

    $this->unidadLegal = unidadDePrueba(['nombre' => 'Asesoría Legal']);
    $this->unidadFomento = unidadDePrueba(['nombre' => 'Fomento Productivo']);
    $this->ana = Servidor::create([
        'cedula' => '0805555551', 'nombre' => 'Ana', 'apellido' => 'Mero',
        'unidad_administrativa_id' => $this->unidadLegal->id, 'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true,
    ]);
    $this->luis = Servidor::create([
        'cedula' => '0805555552', 'nombre' => 'Luis', 'apellido' => 'Cortez',
        'unidad_administrativa_id' => $this->unidadFomento->id, 'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true,
    ]);

    // La impresora de Legal, con Ana de custodia.
    $this->m551 = impresoraDeConsumibles(['modelo' => 'LaserJet M551']);
    AsignacionBien::create([
        'bien_informatico_id' => $this->m551->id, 'servidor_id' => $this->ana->id,
        'fecha_asignacion' => '2026-01-05', 'estado' => 'activa', 'motivo' => 'entrega',
    ]);

    $this->actingAs($this->tecnico, 'sanctum');
});

function impresoraDeConsumibles(array $atributos = []): BienInformatico
{
    static $n = 2030;
    $n++;

    return BienInformatico::create(array_merge([
        'codigo_qr' => "{$n}-QR", 'codigo_institucional' => sprintf('%06d', $n),
        'tipo_bien_id' => test()->impresora->id, 'marca_id' => test()->hp->id, 'modelo' => 'LaserJet M551',
        'estado_operativo' => 'en_uso', 'condicion_fisica' => 'bueno',
    ], $atributos));
}

function nuevoConsumible(array $datos = []): int
{
    return test()->postJson('/api/v1/inventario/consumibles', array_merge([
        'descripcion' => 'Tóner HP CE390A negro', 'tipo' => 'toner', 'color' => 'negro', 'presentacion' => 'unidad',
        'guarda_tic' => true, 'stock_minimo' => 2,
        'modelos' => [['marca_id' => test()->hp->id, 'modelo' => 'LaserJet M551']],
    ], $datos))->assertCreated()->json('datos.id');
}

function abrir(int $consumible, int $cantidad, string $fecha = '2026-10-01'): \Illuminate\Testing\TestResponse
{
    return test()->postJson("/api/v1/inventario/consumibles/{$consumible}/apertura", ['fecha' => $fecha, 'cantidad' => $cantidad]);
}

function ingresar(int $consumible, int $cantidad, string $fecha = '2026-10-05'): \Illuminate\Testing\TestResponse
{
    return test()->postJson('/api/v1/inventario/consumibles/ingresos', [
        'fecha' => $fecha, 'tipo_documento' => 'egreso_bodega', 'numero_documento' => 'EB-2026-0412',
        'fecha_compra' => '2026-09-30', 'items' => [['consumible_id' => $consumible, 'cantidad' => $cantidad]],
    ]);
}

function entregar(int $consumible, int $cantidad, array $datos = []): \Illuminate\Testing\TestResponse
{
    return test()->postJson('/api/v1/inventario/consumibles/entregas', array_merge([
        'fecha' => '2026-10-16', 'bien_informatico_id' => test()->m551->id,
        'items' => [['consumible_id' => $consumible, 'cantidad' => $cantidad]],
    ], $datos));
}

function saldoDe(int $consumible): ?int
{
    return test()->getJson("/api/v1/inventario/consumibles/{$consumible}")->assertOk()->json('datos.saldo');
}

// ── Acceso ────────────────────────────────────────────────────────────────

test('registran los técnicos; el auditor consulta y nadie más entra', function () {
    $id = nuevoConsumible();
    abrir($id, 5)->assertCreated();

    $this->actingAs($this->auditor, 'sanctum');
    $this->getJson('/api/v1/inventario/consumibles')->assertOk()->assertJsonPath('datos.0.id', $id);
    $this->getJson("/api/v1/inventario/consumibles/{$id}")->assertOk();
    $this->getJson("/api/v1/inventario/consumibles/{$id}/kardex")->assertOk();
    $this->getJson("/api/v1/inventario/bienes/{$this->m551->id}/consumibles")->assertOk();
    $this->postJson('/api/v1/inventario/consumibles', [])->assertForbidden();
    entregar($id, 1)->assertForbidden();
    ingresar($id, 1)->assertForbidden();
    $this->postJson("/api/v1/inventario/consumibles/{$id}/ajustes", [])->assertForbidden();
    $movimiento = MovimientoConsumible::sole();
    $this->postJson("/api/v1/inventario/consumibles/movimientos/{$movimiento->id}/anular", ['motivo' => 'Prueba de acceso'])->assertForbidden();

    $servidor = User::factory()->create(['primer_login' => false]);
    $servidor->assignRole('servidor');
    $this->actingAs($servidor, 'sanctum')->getJson('/api/v1/inventario/consumibles')->assertForbidden();
});

// ── Catálogo y compatibilidad ─────────────────────────────────────────────

test('el código de bodega es opcional y no se repite', function () {
    nuevoConsumible(['codigo_bodega' => '0008436']);
    nuevoConsumible(['descripcion' => 'Tinta sin código', 'tipo' => 'tinta', 'codigo_bodega' => null]);
    nuevoConsumible(['descripcion' => 'Otra tinta sin código', 'tipo' => 'tinta', 'codigo_bodega' => '']);

    $this->postJson('/api/v1/inventario/consumibles', [
        'codigo_bodega' => '0008436', 'descripcion' => 'Repetido', 'tipo' => 'toner', 'presentacion' => 'unidad', 'guarda_tic' => true,
    ])->assertStatus(422)->assertJsonValidationErrors('codigo_bodega', 'errores');
});

test('el modelo se compara sin mayúsculas ni espacios y junto a cada uno salen sus equipos', function () {
    $otra = impresoraDeConsumibles(['modelo' => 'LASERJET  M551']);
    impresoraDeConsumibles(['modelo' => 'LaserJet M551', 'estado_operativo' => 'dado_de_baja']);

    $id = nuevoConsumible(['modelos' => [
        ['marca_id' => $this->hp->id, 'modelo' => 'LaserJet M551'],
        ['marca_id' => $this->hp->id, 'modelo' => 'laserjet m551'],
        ['marca_id' => $this->lexmark->id, 'modelo' => 'MX622DHE'],
    ]]);

    $modelos = collect($this->getJson("/api/v1/inventario/consumibles/{$id}")->json('datos.modelos'));
    expect($modelos)->toHaveCount(2);
    $deHp = $modelos->firstWhere('marca_id', $this->hp->id);
    expect(collect($deHp['equipos'])->pluck('id')->sort()->values()->all())->toBe([$this->m551->id, $otra->id])
        ->and(collect($deHp['equipos'])->firstWhere('id', $this->m551->id)['custodio'])->toBe($this->ana->nombre_completo)
        // Un modelo sin equipos: en alguna ficha está escrito distinto.
        ->and($modelos->firstWhere('marca_id', $this->lexmark->id)['equipos'])->toBe([]);

    $this->postJson("/api/v1/inventario/consumibles/{$id}/modelos", ['marca_id' => $this->hp->id, 'modelo' => ' LaserJet  M551'])->assertStatus(422);
    $this->postJson("/api/v1/inventario/consumibles/{$id}/modelos", ['marca_id' => $this->hp->id, 'modelo' => 'LaserJet M402'])->assertCreated()
        ->assertJsonCount(3, 'datos.modelos');
    $lexmark = $modelos->firstWhere('marca_id', $this->lexmark->id)['id'];
    $this->deleteJson("/api/v1/inventario/consumibles/{$id}/modelos/{$lexmark}")->assertOk()->assertJsonCount(2, 'datos.modelos');
});

test('al editar, los modelos que vienen reemplazan a los que tenía', function () {
    $id = nuevoConsumible();
    $this->putJson("/api/v1/inventario/consumibles/{$id}", [
        'descripcion' => 'Tóner HP CE390X negro', 'tipo' => 'toner', 'presentacion' => 'unidad', 'guarda_tic' => true,
        'stock_minimo' => 3, 'modelos' => [['marca_id' => $this->lexmark->id, 'modelo' => 'MX622DHE']],
    ])->assertOk()
        ->assertJsonPath('datos.descripcion', 'Tóner HP CE390X negro')
        ->assertJsonPath('datos.stock_minimo', 3)
        ->assertJsonCount(1, 'datos.modelos')
        ->assertJsonPath('datos.modelos.0.modelo', 'MX622DHE');
});

test('el catálogo filtra por tipo, por lo compatible con un equipo, por lo bajo del mínimo y oculta lo inactivo', function () {
    $toner = nuevoConsumible();
    abrir($toner, 1)->assertCreated(); // mínimo 2: bajo
    $tinta = nuevoConsumible(['descripcion' => 'Tinta Epson 664 cian', 'tipo' => 'tinta', 'color' => 'cian', 'stock_minimo' => 0, 'modelos' => [
        ['marca_id' => $this->lexmark->id, 'modelo' => 'X1'],
    ]]);
    $teclado = nuevoConsumible(['descripcion' => 'Teclado USB', 'tipo' => 'repuesto', 'color' => null, 'modelos' => [], 'guarda_tic' => false]);
    $viejo = nuevoConsumible(['descripcion' => 'Cinta vieja', 'tipo' => 'cinta', 'stock_minimo' => 0]);
    $this->putJson("/api/v1/inventario/consumibles/{$viejo}", [
        'descripcion' => 'Cinta vieja', 'tipo' => 'cinta', 'presentacion' => 'unidad', 'guarda_tic' => true, 'activo' => false,
    ])->assertOk();

    $ids = fn (string $q = '') => array_column($this->getJson("/api/v1/inventario/consumibles{$q}")->json('datos'), 'id');
    expect($ids())->toEqualCanonicalizing([$toner, $tinta, $teclado])
        ->and($ids('?incluir_inactivos=1'))->toContain($viejo)
        ->and($ids('?tipo=tinta'))->toBe([$tinta])
        ->and($ids("?bien_informatico_id={$this->m551->id}"))->toBe([$toner])
        ->and($ids("?marca_id={$this->hp->id}&modelo=LASERJET%20M551"))->toBe([$toner])
        ->and($ids('?bajo_minimo=1'))->toBe([$toner])
        ->and($ids('?guarda_tic=0'))->toBe([$teclado])
        ->and($ids('?search=epson'))->toBe([$tinta]);

    $fila = collect($this->getJson('/api/v1/inventario/consumibles')->json('datos'))->firstWhere('id', $teclado);
    expect($fila)->toMatchArray(['saldo' => null, 'stock_minimo' => null, 'bajo_minimo' => false, 'de_impresion' => false]);
});

// ── Lo que guarda TIC: saldo y kárdex ─────────────────────────────────────

test('apertura, ingreso, entrega y ajuste dejan el saldo, y el kárdex muestra lo que quedó tras cada uno', function () {
    $id = nuevoConsumible();
    abrir($id, 3, '2026-10-01')->assertCreated();
    ingresar($id, 4, '2026-10-05')->assertCreated()->assertJsonPath('datos.0.numero_documento', 'EB-2026-0412');
    entregar($id, 2)->assertCreated();
    $this->postJson("/api/v1/inventario/consumibles/{$id}/ajustes", [
        'fecha' => '2026-10-16', 'cantidad' => -1, 'motivo_ajuste' => 'dano', 'observacion' => 'Llegó con la carcasa rota',
    ])->assertCreated();

    expect(saldoDe($id))->toBe(4);
    $kardex = $this->getJson("/api/v1/inventario/consumibles/{$id}/kardex")->assertOk()->json('datos');
    expect(array_column($kardex['filas'], 'tipo'))->toBe(['apertura', 'ingreso', 'entrega', 'ajuste'])
        ->and(array_column($kardex['filas'], 'cantidad'))->toBe([3, 4, -2, -1])
        ->and(array_column($kardex['filas'], 'saldo'))->toBe([3, 7, 5, 4])
        ->and($kardex['saldo_final'])->toBe(4);

    // Desde una fecha: parte del saldo que había antes.
    $desde = $this->getJson("/api/v1/inventario/consumibles/{$id}/kardex?desde=2026-10-10")->json('datos');
    expect($desde['saldo_inicial'])->toBe(7)
        ->and(array_column($desde['filas'], 'saldo'))->toBe([5, 4]);
});

test('no se entrega más de lo que hay', function () {
    $id = nuevoConsumible();
    abrir($id, 2)->assertCreated();

    entregar($id, 3)->assertStatus(422)->assertJsonPath('mensaje', 'No hay saldo suficiente de «Tóner HP CE390A negro»: quedan 2.');
    $this->postJson("/api/v1/inventario/consumibles/{$id}/ajustes", [
        'fecha' => '2026-10-16', 'cantidad' => -3, 'motivo_ajuste' => 'diferencia_conteo', 'observacion' => 'Conteo de fin de mes',
    ])->assertStatus(422);
    expect(saldoDe($id))->toBe(2);
});

test('el saldo tampoco queda negativo en el pasado: una entrega atrasada antes del ingreso no pasa', function () {
    $id = nuevoConsumible();
    abrir($id, 1, '2026-10-01')->assertCreated();
    ingresar($id, 5, '2026-10-10')->assertCreated();

    // Hoy hay 6, pero el 8 de octubre solo había 1.
    entregar($id, 2, ['fecha' => '2026-10-08'])->assertStatus(422)
        ->assertJsonPath('mensaje', 'Al 08/10/2026 no alcanzaba el saldo de «Tóner HP CE390A negro»: el kárdex quedaría en negativo el 08/10/2026.');
    entregar($id, 2, ['fecha' => '2026-10-10'])->assertCreated();
});

test('en el mismo día lo que entra va antes que lo que sale', function () {
    $id = nuevoConsumible();
    abrir($id, 1, '2026-10-01')->assertCreated();
    entregar($id, 1, ['fecha' => '2026-10-12'])->assertCreated();
    // Un ingreso del mismo día registrado después va antes en el kárdex.
    ingresar($id, 3, '2026-10-12')->assertCreated();

    $kardex = $this->getJson("/api/v1/inventario/consumibles/{$id}/kardex")->json('datos');
    expect(array_column($kardex['filas'], 'tipo'))->toBe(['apertura', 'ingreso', 'entrega'])
        ->and(array_column($kardex['filas'], 'saldo'))->toBe([1, 4, 3]);
});

test('una sola apertura viva, y va antes que todo el kárdex', function () {
    $id = nuevoConsumible();
    ingresar($id, 2, '2026-10-05')->assertCreated();
    abrir($id, 3, '2026-10-06')->assertStatus(422);
    $apertura = abrir($id, 3, '2026-10-01')->assertCreated()->json('datos.id');
    abrir($id, 4, '2026-10-01')->assertStatus(422);

    $this->postJson("/api/v1/inventario/consumibles/movimientos/{$apertura}/anular", ['motivo' => 'Se contó mal la bodega'])->assertOk();
    abrir($id, 4, '2026-10-01')->assertCreated();
    expect(saldoDe($id))->toBe(6);

    // La base también la exige.
    expect(fn () => MovimientoConsumible::create([
        'consumible_id' => $id, 'tipo' => 'apertura', 'cantidad' => 1, 'afecta_saldo' => true,
        'fecha' => '2026-10-01', 'registrado_por' => $this->tecnico->id,
    ]))->toThrow(QueryException::class);
});

test('la base rechaza una cantidad con el signo cambiado', function () {
    $id = nuevoConsumible();

    expect(fn () => DB::table('movimientos_consumible')->insert([
        'consumible_id' => $id, 'tipo' => 'entrega', 'cantidad' => 2, 'afecta_saldo' => true,
        'fecha' => '2026-10-16', 'registrado_por' => $this->tecnico->id, 'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

// ── Lo que despacha Bodega ────────────────────────────────────────────────

test('lo que despacha Bodega no lleva saldo: solo entregas', function () {
    $id = nuevoConsumible(['guarda_tic' => false, 'stock_minimo' => 5]);

    abrir($id, 3)->assertStatus(422)->assertJsonPath('mensaje', '«Tóner HP CE390A negro» lo despacha Bodega: no lleva saldo en TIC, así que no tiene apertura.');
    ingresar($id, 3)->assertStatus(422);
    $this->postJson("/api/v1/inventario/consumibles/{$id}/ajustes", [
        'fecha' => '2026-10-16', 'cantidad' => 1, 'motivo_ajuste' => 'otro', 'observacion' => 'No debería poder',
    ])->assertStatus(422);

    entregar($id, 2)->assertCreated()->assertJsonPath('datos.0.afecta_saldo', false);
    $detalle = $this->getJson("/api/v1/inventario/consumibles/{$id}")->json('datos');
    expect($detalle)->toMatchArray(['saldo' => null, 'stock_minimo' => null, 'bajo_minimo' => false]);
    expect($this->getJson("/api/v1/inventario/consumibles/{$id}/kardex")->json('datos.filas.0.saldo'))->toBeNull();
});

test('pasarlo a Bodega o desactivarlo exige su saldo en cero', function () {
    $id = nuevoConsumible();
    abrir($id, 2)->assertCreated();
    $editar = fn (array $datos) => $this->putJson("/api/v1/inventario/consumibles/{$id}", array_merge([
        'descripcion' => 'Tóner HP CE390A negro', 'tipo' => 'toner', 'presentacion' => 'unidad', 'guarda_tic' => true,
    ], $datos));

    $editar(['guarda_tic' => false])->assertStatus(422);
    $editar(['activo' => false])->assertStatus(422);

    entregar($id, 2)->assertCreated();
    $editar(['activo' => false])->assertOk()->assertJsonPath('datos.activo', false);
    // Desactivado no recibe movimientos.
    ingresar($id, 1)->assertStatus(422);
    $editar(['activo' => true, 'guarda_tic' => false])->assertOk()->assertJsonPath('datos.saldo', null);
    // Y si vuelve a TIC, arranca en cero: las entregas de Bodega no cuentan.
    entregar($id, 1)->assertCreated();
    $editar(['guarda_tic' => true])->assertOk()->assertJsonPath('datos.saldo', 0);
});

// ── La entrega ────────────────────────────────────────────────────────────

test('lo de impresión va a un equipo compatible que no esté de baja', function () {
    $id = nuevoConsumible();
    abrir($id, 5)->assertCreated();

    entregar($id, 1, ['bien_informatico_id' => null, 'servidor_retira_id' => $this->ana->id])->assertStatus(422)
        ->assertJsonPath('mensaje', '«Tóner HP CE390A negro» es de impresión: se entrega a un equipo.');

    $lexmark = impresoraDeConsumibles(['marca_id' => $this->lexmark->id, 'modelo' => 'MX622DHE']);
    entregar($id, 1, ['bien_informatico_id' => $lexmark->id, 'servidor_retira_id' => $this->ana->id])->assertStatus(422)
        ->assertJsonPath('mensaje', "«Tóner HP CE390A negro» no está marcado como compatible con el equipo {$lexmark->codigo_institucional} (Lexmark MX622DHE). Agregue el modelo a sus compatibles.");

    $deBaja = impresoraDeConsumibles(['estado_operativo' => 'dado_de_baja']);
    entregar($id, 1, ['bien_informatico_id' => $deBaja->id, 'servidor_retira_id' => $this->ana->id])->assertStatus(422);
    expect(saldoDe($id))->toBe(5);
});

test('retira por defecto el custodio, y la entrega guarda su unidad de ese momento', function () {
    $id = nuevoConsumible();
    abrir($id, 5)->assertCreated();

    entregar($id, 1)->assertCreated()
        ->assertJsonPath('datos.0.retira', $this->ana->nombre_completo)
        ->assertJsonPath('datos.0.unidad', 'Asesoría Legal')
        ->assertJsonPath('datos.0.equipo.codigo_institucional', $this->m551->codigo_institucional);

    // Si Ana cambia de unidad, lo ya entregado sigue contando para Legal.
    $this->ana->update(['unidad_administrativa_id' => $this->unidadFomento->id]);
    expect(MovimientoConsumible::where('tipo', 'entrega')->sole()->unidad_administrativa_id)->toBe($this->unidadLegal->id);

    // Otro retira, pero la unidad sigue siendo la del custodio del equipo.
    entregar($id, 1, ['servidor_retira_id' => $this->luis->id])->assertCreated()
        ->assertJsonPath('datos.0.retira', $this->luis->nombre_completo)
        ->assertJsonPath('datos.0.unidad', 'Fomento Productivo');
});

test('un equipo sin custodio pide quién retira', function () {
    $libre = impresoraDeConsumibles(['estado_operativo' => 'disponible', 'unidad_administrativa_id' => $this->unidadLegal->id]);
    $id = nuevoConsumible();
    abrir($id, 5)->assertCreated();

    entregar($id, 1, ['bien_informatico_id' => $libre->id])->assertStatus(422)->assertJsonValidationErrors('servidor_retira_id', 'errores');
    // La unidad es la del equipo cuando no tiene custodio.
    entregar($id, 1, ['bien_informatico_id' => $libre->id, 'servidor_retira_id' => $this->luis->id])->assertCreated()
        ->assertJsonPath('datos.0.unidad', 'Asesoría Legal');
});

test('el repuesto puede ir sin equipo, y cuenta para la unidad de quien retira', function () {
    $teclado = nuevoConsumible(['descripcion' => 'Teclado USB negro', 'tipo' => 'repuesto', 'color' => null, 'modelos' => []]);
    abrir($teclado, 3)->assertCreated();

    entregar($teclado, 1, ['bien_informatico_id' => null, 'servidor_retira_id' => $this->luis->id])->assertCreated()
        ->assertJsonPath('datos.0.equipo', null)
        ->assertJsonPath('datos.0.unidad', 'Fomento Productivo');
    // Con equipo, no hace falta que sea compatible.
    entregar($teclado, 1)->assertCreated();
});

test('varios consumibles en una entrega: o pasan todos o ninguno', function () {
    $negro = nuevoConsumible();
    $cian = nuevoConsumible(['descripcion' => 'Tóner HP CE391A cian', 'color' => 'cian']);
    abrir($negro, 3)->assertCreated();
    abrir($cian, 1)->assertCreated();

    $this->postJson('/api/v1/inventario/consumibles/entregas', [
        'fecha' => '2026-10-16', 'bien_informatico_id' => $this->m551->id,
        'items' => [['consumible_id' => $negro, 'cantidad' => 1], ['consumible_id' => $cian, 'cantidad' => 2]],
    ])->assertStatus(422);
    expect(saldoDe($negro))->toBe(3);

    $this->postJson('/api/v1/inventario/consumibles/entregas', [
        'fecha' => '2026-10-16', 'bien_informatico_id' => $this->m551->id,
        'items' => [['consumible_id' => $negro, 'cantidad' => 1], ['consumible_id' => $negro, 'cantidad' => 1]],
    ])->assertStatus(422);

    $this->postJson('/api/v1/inventario/consumibles/entregas', [
        'fecha' => '2026-10-16', 'bien_informatico_id' => $this->m551->id,
        'items' => [['consumible_id' => $negro, 'cantidad' => 1], ['consumible_id' => $cian, 'cantidad' => 1]],
    ])->assertCreated()->assertJsonCount(2, 'datos');
    expect(saldoDe($negro))->toBe(2)->and(saldoDe($cian))->toBe(0);
});

// ── Anulación ─────────────────────────────────────────────────────────────

test('anular con motivo: deja de contar pero sigue en el kárdex, y no si deja el saldo negativo', function () {
    $id = nuevoConsumible();
    abrir($id, 1, '2026-10-01')->assertCreated();
    $ingreso = ingresar($id, 4, '2026-10-05')->assertCreated()->json('datos.0.id');
    $entrega = entregar($id, 3)->assertCreated()->json('datos.0.id');

    $anular = fn (int $m, string $motivo = 'Se registró dos veces') => $this->postJson("/api/v1/inventario/consumibles/movimientos/{$m}/anular", ['motivo' => $motivo]);
    $anular($ingreso)->assertStatus(422)->assertJsonPath('mensaje', 'No se puede anular: el saldo de «Tóner HP CE390A negro» quedaría en -2.');
    $this->postJson("/api/v1/inventario/consumibles/movimientos/{$entrega}/anular", ['motivo' => 'mal'])->assertStatus(422)
        ->assertJsonValidationErrors('motivo', 'errores');

    $anular($entrega)->assertOk()->assertJsonPath('datos.motivo_anulacion', 'Se registró dos veces');
    $anular($entrega)->assertStatus(422);
    $anular($ingreso)->assertOk();
    expect(saldoDe($id))->toBe(1);

    $filas = collect($this->getJson("/api/v1/inventario/consumibles/{$id}/kardex")->json('datos.filas'));
    expect($filas)->toHaveCount(3)
        ->and($filas->firstWhere('id', $entrega))->toMatchArray(['saldo' => null, 'anulado_por' => $this->tecnico->nombre_completo])
        ->and($filas->firstWhere('id', $ingreso)['anulado_en'])->not->toBeNull();
});

test('anular un ingreso que el kárdex necesitó en su día tampoco pasa', function () {
    $id = nuevoConsumible();
    $ingreso = ingresar($id, 2, '2026-10-05')->assertCreated()->json('datos.0.id');
    entregar($id, 2, ['fecha' => '2026-10-06'])->assertCreated();
    ingresar($id, 5, '2026-10-10')->assertCreated();

    // Hoy quedarían 3, pero el 6 de octubre se entregó lo que había entrado el 5.
    $this->postJson("/api/v1/inventario/consumibles/movimientos/{$ingreso}/anular", ['motivo' => 'Documento equivocado'])
        ->assertStatus(422)->assertJsonPath('mensaje', 'No se puede anular: el kárdex de «Tóner HP CE390A negro» quedaría en negativo el 06/10/2026.');
});

// ── La ficha del equipo ───────────────────────────────────────────────────

test('la ficha del equipo muestra sus compatibles con saldo, las últimas entregas y los días desde la última', function () {
    $this->m551->update(['caracteristicas_tecnicas' => ['conexion' => 'red', 'consumible' => 'Tóner HP 90A']]);
    $id = nuevoConsumible();
    nuevoConsumible(['descripcion' => 'Tinta de otra impresora', 'tipo' => 'tinta', 'modelos' => [['marca_id' => $this->lexmark->id, 'modelo' => 'X1']]]);
    abrir($id, 5, '2026-09-01')->assertCreated();
    entregar($id, 1, ['fecha' => '2026-09-10'])->assertCreated();
    entregar($id, 1, ['fecha' => '2026-10-06'])->assertCreated();

    $ficha = $this->getJson("/api/v1/inventario/bienes/{$this->m551->id}/consumibles")->assertOk()->json('datos');
    expect(array_column($ficha['compatibles'], 'id'))->toBe([$id])
        ->and($ficha['compatibles'][0]['saldo'])->toBe(3)
        ->and(array_column($ficha['entregas'], 'fecha'))->toBe(['2026-10-06', '2026-09-10'])
        ->and($ficha['entregas'][0]['cantidad'])->toBe(1)
        ->and($ficha['ultima_entrega'])->toBe('2026-10-06')
        ->and($ficha['dias_desde_ultima'])->toBe(10)
        ->and($ficha['consumible_anterior'])->toBe('Tóner HP 90A');
});
