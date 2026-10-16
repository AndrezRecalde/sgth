<?php

use App\Enums\RegimenLaboral;
use App\Models\Expediente\Servidor;
use App\Models\InventarioTi\AsignacionBien;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\Marca;
use App\Models\InventarioTi\MovimientoConsumible;
use App\Models\InventarioTi\TipoBien;
use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
| Los reportes de consumibles y el bloque del tablero (docs/diseno-subsistema-tic.md,
| fase 5.4d): el consumo por impresora, área o consumible; el kárdex en Excel;
| el saldo a una fecha, y lo que bajó del mínimo, que se avisa solo en el
| tablero [P6].
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

    $legal = unidadDePrueba(['nombre' => 'Asesoría Legal']);
    $fomento = unidadDePrueba(['nombre' => 'Fomento Productivo']);
    $servidor = fn (string $cedula, string $apellido, $unidad) => Servidor::create([
        'cedula' => $cedula, 'nombre' => 'Servidor', 'apellido' => $apellido,
        'unidad_administrativa_id' => $unidad->id, 'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true,
    ]);
    $this->deLegal = $servidor('0807711111', 'Legal', $legal);
    $this->deFomento = $servidor('0807722222', 'Fomento', $fomento);

    $hp = Marca::create(['nombre' => 'HP']);
    $impresora = TipoBien::create(['nombre' => 'Impresora']);
    $equipo = function (string $codigo, Servidor $custodio) use ($hp, $impresora) {
        $b = BienInformatico::create([
            'codigo_qr' => "{$codigo}-QR", 'codigo_institucional' => $codigo, 'tipo_bien_id' => $impresora->id,
            'marca_id' => $hp->id, 'modelo' => 'LaserJet M551', 'estado_operativo' => 'en_uso', 'condicion_fisica' => 'bueno',
        ]);
        AsignacionBien::create(['bien_informatico_id' => $b->id, 'servidor_id' => $custodio->id, 'fecha_asignacion' => '2026-01-05', 'estado' => 'activa', 'motivo' => 'entrega']);

        return $b;
    };
    $this->impresoraLegal = $equipo('003001', $this->deLegal);
    $this->impresoraFomento = $equipo('003002', $this->deFomento);

    $this->actingAs($this->tecnico, 'sanctum');
    $nuevo = fn (array $datos) => $this->postJson('/api/v1/inventario/consumibles', array_merge([
        'tipo' => 'toner', 'presentacion' => 'unidad', 'guarda_tic' => true, 'stock_minimo' => 2,
        'modelos' => [['marca_id' => $hp->id, 'modelo' => 'LaserJet M551']],
    ], $datos))->assertCreated()->json('datos.id');
    $this->negro = $nuevo(['descripcion' => 'Tóner HP CE390A negro', 'codigo_bodega' => '0008436']);
    $this->cian = $nuevo(['descripcion' => 'Tóner HP CE391A cian', 'stock_minimo' => 0]);
    $this->deBodega = $nuevo(['descripcion' => 'Unidad de imagen HP', 'tipo' => 'unidad_imagen', 'guarda_tic' => false]);

    $this->postJson("/api/v1/inventario/consumibles/{$this->negro}/apertura", ['fecha' => '2026-09-01', 'cantidad' => 10])->assertCreated();
    $this->postJson("/api/v1/inventario/consumibles/{$this->cian}/apertura", ['fecha' => '2026-09-01', 'cantidad' => 4])->assertCreated();
});

function entregaParaReporte(int $consumible, BienInformatico $bien, int $cantidad, string $fecha): int
{
    return test()->postJson('/api/v1/inventario/consumibles/entregas', [
        'fecha' => $fecha, 'bien_informatico_id' => $bien->id, 'items' => [['consumible_id' => $consumible, 'cantidad' => $cantidad]],
    ])->assertCreated()->json('datos.0.id');
}

test('el consumo del período se agrupa por consumible, por área o por impresora', function () {
    entregaParaReporte($this->negro, $this->impresoraLegal, 2, '2026-10-02');
    entregaParaReporte($this->negro, $this->impresoraFomento, 3, '2026-10-05');
    entregaParaReporte($this->cian, $this->impresoraLegal, 1, '2026-10-06');
    entregaParaReporte($this->deBodega, $this->impresoraLegal, 1, '2026-10-07');
    entregaParaReporte($this->negro, $this->impresoraLegal, 4, '2026-09-20'); // fuera del período
    $anulada = entregaParaReporte($this->cian, $this->impresoraFomento, 1, '2026-10-08');
    $this->postJson("/api/v1/inventario/consumibles/movimientos/{$anulada}/anular", ['motivo' => 'Se registró dos veces'])->assertOk();

    $consumos = fn (string $agrupar) => $this->getJson("/api/v1/tic/reportes/consumos?desde=2026-10-01&hasta=2026-10-31&agrupar={$agrupar}")->assertOk()->json('datos');

    $porConsumible = $consumos('consumible');
    expect($porConsumible)->toMatchArray(['cantidad' => 7, 'entregas' => 4, 'agrupado_por' => 'consumible'])
        ->and(collect($porConsumible['grupos'])->pluck('cantidad', 'nombre')->all())
        ->toBe(['Tóner HP CE390A negro' => 5, 'Tóner HP CE391A cian' => 1, 'Unidad de imagen HP' => 1])
        ->and(collect($porConsumible['grupos'][0]['desglose'])->pluck('cantidad', 'nombre')->all())
        ->toBe(['Fomento Productivo' => 3, 'Asesoría Legal' => 2]);

    expect(collect($consumos('area')['grupos'])->pluck('cantidad', 'nombre')->all())->toBe(['Asesoría Legal' => 4, 'Fomento Productivo' => 3])
        ->and(collect($consumos('impresora')['grupos'])->pluck('cantidad', 'nombre')->all())
        ->toBe(['003001 HP LaserJet M551' => 4, '003002 HP LaserJet M551' => 3]);
});

test('el consumo sale en Excel y en PDF, y lo consulta el auditor', function () {
    entregaParaReporte($this->negro, $this->impresoraLegal, 2, '2026-10-02');
    $url = '/api/v1/tic/reportes/consumos?desde=2026-10-01&hasta=2026-10-31&agrupar=area';

    $excel = $this->get("{$url}&formato=excel")->assertOk();
    expect($excel->headers->get('Content-Disposition'))->toContain('consumo-por-area-2026-10-01-a-2026-10-31.xlsx');
    $pdf = $this->get("{$url}&formato=pdf")->assertOk();
    expect($pdf->headers->get('Content-Type'))->toBe('application/pdf');

    $this->actingAs($this->auditor, 'sanctum')->getJson($url)->assertOk();
    $servidor = User::factory()->create(['primer_login' => false]);
    $servidor->assignRole('servidor');
    $this->actingAs($servidor, 'sanctum')->getJson($url)->assertForbidden();
});

test('el saldo al cierre de una fecha cuadra con el kárdex de ese día', function () {
    entregaParaReporte($this->negro, $this->impresoraLegal, 2, '2026-10-02');
    entregaParaReporte($this->negro, $this->impresoraLegal, 3, '2026-10-10');

    $alDos = $this->getJson('/api/v1/tic/reportes/saldo-consumibles?fecha=2026-10-02')->assertOk()->json('datos');
    $negro = collect($alDos['filas'])->firstWhere('id', $this->negro);
    expect($negro)->toMatchArray(['saldo' => 8, 'stock_minimo' => 2, 'bajo_minimo' => false, 'ultimo_movimiento' => '2026-10-02'])
        ->and($alDos['unidades'])->toBe(12)
        // Lo que despacha Bodega no lleva saldo: no sale.
        ->and(collect($alDos['filas'])->pluck('id'))->not->toContain($this->deBodega);

    expect(collect($this->getJson('/api/v1/tic/reportes/saldo-consumibles?fecha=2026-08-31')->json('datos.filas'))->firstWhere('id', $this->negro)['saldo'])->toBe(0);
    $this->get('/api/v1/tic/reportes/saldo-consumibles?fecha=2026-10-16&formato=excel')->assertOk();
    $this->get('/api/v1/tic/reportes/saldo-consumibles?fecha=2026-10-16&formato=pdf')->assertOk();
    $this->getJson('/api/v1/tic/reportes/saldo-consumibles?fecha=2026-10-17')->assertStatus(422);
});

test('el kárdex de un consumible sale en Excel entre dos fechas', function () {
    entregaParaReporte($this->negro, $this->impresoraLegal, 2, '2026-10-02');

    $excel = $this->get("/api/v1/inventario/consumibles/{$this->negro}/kardex/excel?desde=2026-10-01&hasta=2026-10-31")->assertOk();
    expect($excel->headers->get('Content-Disposition'))->toContain('kardex-toner-hp-ce390a-negro-2026-10-01-a-2026-10-31.xlsx');
    $this->actingAs($this->auditor, 'sanctum')->get("/api/v1/inventario/consumibles/{$this->negro}/kardex/excel")->assertOk();
});

test('el tablero avisa lo que bajó del mínimo, lo que más falta primero', function () {
    entregaParaReporte($this->negro, $this->impresoraLegal, 9, '2026-10-02');

    $bloque = $this->getJson('/api/v1/tic/tablero')->assertOk()->json('datos.consumibles');
    expect($bloque['bajo_minimo'])->toBe(1)
        ->and($bloque['lista'][0])->toMatchArray(['id' => $this->negro, 'saldo' => 1, 'stock_minimo' => 2])
        ->and($bloque['entregas_mes'])->toBe(9);

    // Sin mínimo (el cian) o de Bodega no se avisa; anular la entrega lo saca del aviso.
    $entrega = MovimientoConsumible::where('consumible_id', $this->negro)->where('tipo', 'entrega')->sole();
    $this->postJson("/api/v1/inventario/consumibles/movimientos/{$entrega->id}/anular", ['motivo' => 'Era de otra impresora'])->assertOk();
    expect($this->getJson('/api/v1/tic/tablero')->json('datos.consumibles.bajo_minimo'))->toBe(0);
});
