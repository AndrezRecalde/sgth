<?php

/*
| La fórmula del viático, confirmada por Gestión Financiera el 2026-09-15.
|
| Se pagan las noches de pernocte; el servidor justifica con comprobantes el
| 70 % y el 30 % se le reconoce sin factura. Lo que presente de más corre por su
| cuenta, y el saldo es uno solo: lo reconocido menos el anticipo.
|
| Antes cada pantalla hacía su propia cuenta: el día de regreso se cobraba,
| media docena de cargos cobraba la tarifa de autoridad, la movilización no
| justificaba nada y el 30 % no aparecía por ningún lado.
*/

use App\Enums\EstadoViatico;
use App\Enums\RegimenLaboral;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Models\Viatico\CategoriaFactura;
use App\Models\Viatico\FacturaViatico;
use App\Models\Viatico\LiquidacionViatico;
use App\Models\Viatico\Viatico;
use App\Services\Viatico\CalculoViaticoService;
use App\Services\Viatico\ViaticoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\TarifaViaticoSeeder::class);

    $this->unidad = unidadDePrueba(['codigo' => 'GCAL']);

    $cedula = 800009000;
    $this->servidorEn = function (\App\Models\Estructura\Puesto $puesto) use (&$cedula) {
        return Servidor::create([
            'cedula'                   => '0'.(++$cedula),
            'nombre'                   => 'Carmen',
            'apellido'                 => 'Cálculo',
            'puesto_id'                => $puesto->id,
            'unidad_administrativa_id' => $puesto->unidad_administrativa_id,
            'regimen_laboral'          => RegimenLaboral::LOSEP,
            'estado'                   => true,
        ]);
    };

    $this->servidor = ($this->servidorEn)(puestoDePrueba($this->unidad));
    $this->calculo  = app(CalculoViaticoService::class);
});

/** Un viático listo para liquidar, con su liquidación abierta. */
function viaticoPorLiquidar(Servidor $servidor, float $derecho, float $anticipo): LiquidacionViatico
{
    $viatico = Viatico::create([
        'servidor_id'        => $servidor->id,
        'zona'               => 'dentro_provincia',
        'datetime_salida'    => '2026-10-05 08:00:00',
        'datetime_llegada'   => '2026-10-08 18:00:00',
        'noches'             => 3,
        'justificacion'      => 'Supervisión de obras viales',
        'estado'             => EstadoViatico::PENDIENTE_LIQUIDACION,
        'monto_calculado'    => $derecho,
        'monto_anticipo'     => $anticipo,
        'modalidad_anticipo' => $anticipo > 0 ? 'total' : 'sin_anticipo',
    ]);

    return LiquidacionViatico::create([
        'viatico_id'        => $viatico->id,
        'total_facturas'    => 0,
        'fecha_liquidacion' => now()->toDateString(),
    ]);
}

function comprobantePor(LiquidacionViatico $liquidacion, float $monto, string $grupo = 'viatico'): FacturaViatico
{
    $categoria = CategoriaFactura::firstOrCreate(
        ['codigo' => $grupo === 'viatico' ? 'HOSP' : 'PEAJ'],
        ['nombre' => $grupo === 'viatico' ? 'Hospedaje' : 'Peaje', 'grupo' => $grupo, 'activo' => true],
    );

    return FacturaViatico::create([
        'liquidacion_viatico_id' => $liquidacion->id,
        'categoria_factura_id'   => $categoria->id,
        'tipo_comprobante'       => 'factura',
        'numero_factura'         => '001-001-'.str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT),
        'ruc_proveedor'          => '1790016919001',
        'nombre_proveedor'       => 'Proveedor de prueba',
        'monto'                  => $monto,
    ]);
}

// ── Las noches ───────────────────────────────────────────────────────────

it('cuenta las noches de pernocte y no el día de regreso', function () {
    $viatico = app(ViaticoService::class)->solicitar($this->servidor->id, [
        'zona'             => 'dentro_provincia',
        'datetime_salida'  => '2026-10-05 08:00:00',  // lunes
        'datetime_llegada' => '2026-10-07 18:00:00',  // miércoles
        'justificacion'    => 'Supervisión de obras viales',
    ], User::factory()->create(['servidor_id' => $this->servidor->id])->id);

    // Dos noches a 80, no tres días.
    expect($viatico->noches)->toBe(2)
        ->and((float) $viatico->monto_calculado)->toBe(160.00);
});

it('rechaza una comisión que empieza y termina el mismo día', function () {
    expect(fn () => $this->calculo->asegurarPernocte(
        \Carbon\Carbon::parse('2026-10-05 08:00:00'),
        \Carbon\Carbon::parse('2026-10-05 20:00:00'),
    ))->toThrow(ReglaNegocioException::class, 'al menos una noche fuera');
});

// ── El nivel ─────────────────────────────────────────────────────────────

it('solo el prefecto cobra la tarifa de autoridad', function () {
    $prefectura = unidadDePrueba(['codigo' => 'GPRE', 'nombre' => 'Prefectura Provincial']);
    $prefectura->update(['es_maxima_autoridad' => true]);

    $prefecto = ($this->servidorEn)(puestoJefeDePrueba($prefectura, 'Prefecto/a Provincial'));
    $director = ($this->servidorEn)(puestoJefeDePrueba($this->unidad, 'Director de Obras Públicas'));

    expect($this->calculo->nivel($prefecto))->toBe('autoridad')
        ->and($this->calculo->nivel($director))->toBe('servidor')
        ->and($this->calculo->derecho($prefecto, 'dentro_provincia', 2))->toBe(260.00)
        // Antes «director» en el nombre del cargo bastaba para cobrar 130.
        ->and($this->calculo->derecho($director, 'dentro_provincia', 2))->toBe(160.00);
});

it('la tarifa del exterior sale del catálogo y se multiplica por el coeficiente', function () {
    expect($this->calculo->derecho($this->servidor, 'exterior', 3, 1.2))->toBe(666.00)
        // Sin coeficiente el monto espera a que Financiero apruebe.
        ->and($this->calculo->derecho($this->servidor, 'exterior', 3))->toBe(0.00);
});

it('ya no hay tarifas de subsistencia en el catálogo', function () {
    expect(DB::table('tarifas_viatico')->where('tipo_tarifa', 'subsistencia')->count())->toBe(0);
});

// ── La liquidación ───────────────────────────────────────────────────────

it('reconoce lo justificado hasta el 70 % más el 30 % sin comprobante', function () {
    // El ejemplo de Financiero: 3 noches a 80 son 240, con 168 de anticipo.
    $liquidacion = viaticoPorLiquidar($this->servidor, 240.00, 168.00);
    comprobantePor($liquidacion, 100.00);

    $resumen = $this->calculo->guardarEn($liquidacion->fresh());

    expect($resumen['tope_justificable'])->toBe(168.00)
        ->and($resumen['justificado'])->toBe(100.00)
        ->and($resumen['reconocido_sin_comprobante'])->toBe(72.00)
        ->and($resumen['reconocido'])->toBe(172.00)
        // Se le reconocen 172 de los 168 que recibió: la institución le paga 4.
        ->and($resumen['saldo'])->toBe(4.00);
});

it('la movilización también justifica el 70 %', function () {
    $liquidacion = viaticoPorLiquidar($this->servidor, 240.00, 168.00);
    comprobantePor($liquidacion, 60.00);
    comprobantePor($liquidacion, 40.00, 'movilizacion');

    expect($this->calculo->guardarEn($liquidacion->fresh())['justificado'])->toBe(100.00);
});

it('lo presentado por encima del 70 % corre por cuenta del servidor', function () {
    $liquidacion = viaticoPorLiquidar($this->servidor, 240.00, 168.00);
    comprobantePor($liquidacion, 200.00);

    $resumen = $this->calculo->guardarEn($liquidacion->fresh());

    expect($resumen['justificado'])->toBe(168.00)
        ->and($resumen['excedente'])->toBe(32.00)
        ->and($resumen['reconocido'])->toBe(240.00)
        ->and($resumen['saldo'])->toBe(72.00);
});

it('sin comprobantes se devuelve el anticipo menos el 30 %', function () {
    $liquidacion = viaticoPorLiquidar($this->servidor, 240.00, 168.00);

    $resumen = $this->calculo->guardarEn($liquidacion->fresh());

    expect($resumen['reconocido'])->toBe(72.00)
        ->and($resumen['saldo'])->toBe(-96.00);
});

it('sin anticipo se paga al liquidar lo justificado más el 30 %', function () {
    $liquidacion = viaticoPorLiquidar($this->servidor, 240.00, 0.00);
    comprobantePor($liquidacion, 150.00);

    $resumen = $this->calculo->guardarEn($liquidacion->fresh());

    expect($resumen['reconocido'])->toBe(222.00)
        ->and($resumen['saldo'])->toBe(222.00);
});

it('guarda el resultado en la liquidación', function () {
    $liquidacion = viaticoPorLiquidar($this->servidor, 240.00, 168.00);
    comprobantePor($liquidacion, 100.00);

    $this->calculo->guardarEn($liquidacion->fresh());

    $this->assertDatabaseHas('liquidaciones_viatico', [
        'id'                => $liquidacion->id,
        'total_facturas'    => 100.00,
        'total_justificado' => 100.00,
        'monto_reconocido'  => 172.00,
        'saldo'             => 4.00,
    ]);
});
