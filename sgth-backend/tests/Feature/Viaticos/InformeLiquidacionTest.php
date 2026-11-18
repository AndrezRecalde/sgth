<?php

/*
| El informe de licencia con remuneración, el papel que el servidor firma al
| volver del viaje.
|
| Lo que decía mal (2026-11-18):
| - el `@if` de los comprobantes envolvía también el Resumen Financiero, así
|   que el informe de quien no justificó nada —justo el que termina en una
|   devolución— salía sin una sola cifra;
| - «Fecha del Informe» era `date('d/m/Y')`, la de impresión: reimprimir un
|   papel ya firmado le cambiaba la fecha;
| - la tabla de gastos no traía la fecha del comprobante, que es lo que hay que
|   verificar —solo valen los fechados dentro del viaje—, ni un total;
| - las horas de las actividades salían como las guarda Postgres: «08:00:00».
*/

use App\Enums\EstadoViatico;
use App\Enums\RegimenLaboral;
use App\Models\Expediente\Servidor;
use App\Models\Viatico\ActividadLiquidacion;
use App\Models\Viatico\CategoriaFactura;
use App\Models\Viatico\FacturaViatico;
use App\Models\Viatico\LiquidacionViatico;
use App\Models\Viatico\Viatico;
use App\Services\Viatico\PdfInformeViaticoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $unidad = unidadDePrueba(['codigo' => 'GINF']);
    $servidor = Servidor::create([
        'cedula'                   => '0800009300',
        'nombre'                   => 'Ingrid',
        'apellido'                 => 'Informe',
        'puesto_id'                => puestoDePrueba($unidad)->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral'          => RegimenLaboral::LOSEP,
        'estado'                   => true,
    ]);

    $this->hospedaje = CategoriaFactura::create([
        'nombre' => 'Hospedaje', 'codigo' => 'hospedaje',
        'grupo' => 'viatico', 'activo' => true, 'orden' => 1,
    ]);

    $this->viatico = Viatico::create([
        'servidor_id'        => $servidor->id,
        'zona'               => 'fuera_provincia',
        'fecha_solicitud'    => '2026-11-18',
        'datetime_salida'    => '2026-12-01 08:00:00',
        'datetime_llegada'   => '2026-12-03 18:00:00',
        'noches'             => 2,
        'justificacion'      => 'Capacitación en contratación pública',
        'estado'             => EstadoViatico::LIQUIDADO,
        'monto_calculado'    => 160,
        'monto_anticipo'     => 112,
        'modalidad_anticipo' => 'total',
    ]);

    // Una fecha de liquidación que no es hoy: es lo que distingue la fecha del
    // documento de la del día en que se imprime.
    $this->liquidacion = LiquidacionViatico::create([
        'viatico_id'        => $this->viatico->id,
        'total_facturas'    => 0,
        'fecha_liquidacion' => '2026-12-04',
    ]);

    ActividadLiquidacion::create([
        'liquidacion_viatico_id' => $this->liquidacion->id,
        'fecha'                  => '2026-12-02',
        'hora_inicio'            => '08:00:00',
        'hora_fin'               => '17:00:00',
        'lugar'                  => 'SERCOP, Quito',
        'descripcion'            => 'Módulos de ínfima cuantía y subasta inversa',
        'orden'                  => 1,
    ]);

    $this->comprobar = fn (float $monto, string $fecha) => FacturaViatico::create([
        'liquidacion_viatico_id' => $this->liquidacion->id,
        'categoria_factura_id'   => $this->hospedaje->id,
        'tipo_comprobante'       => 'factura',
        'numero_factura'         => '001-001-00000'.random_int(1000, 9999),
        'ruc_proveedor'          => '1790016919001',
        'nombre_proveedor'       => 'Hotel de prueba',
        'fecha_factura'          => $fecha,
        'monto'                  => $monto,
    ]);

    /** El HTML del informe, con los datos que le pasa el servicio. */
    $this->html = function (): string {
        $datos = null;
        View::composer('pdf.viaticos.informe-comision', function ($v) use (&$datos) {
            $datos = $v->getData();
        });

        app(PdfInformeViaticoService::class)->generarInformeContent($this->viatico->id);

        return view('pdf.viaticos.informe-comision', $datos)->render();
    };
});

it('el resumen financiero sale aunque no se haya presentado ningun comprobante', function () {
    $html = ($this->html)();

    expect($html)->toContain('Resumen Financiero')
        ->toContain('Sin comprobantes registrados')
        // Sin comprobantes el servidor devuelve: la cifra que no se imprimía.
        ->toContain('A devolver a la institución')
        ->toContain('Total reconocido al servidor');
});

it('la tabla de gastos trae la fecha de cada comprobante y su total', function () {
    ($this->comprobar)(80, '2026-12-02');
    ($this->comprobar)(32, '2026-12-03');

    $html = ($this->html)();

    expect($html)->toContain('02/12/2026')
        ->toContain('03/12/2026')
        ->toContain('Total de comprobantes')
        ->toContain('112.00')
        ->not->toContain('Sin comprobantes registrados');
});

it('la fecha del informe es la de la liquidacion, no la del dia en que se imprime', function () {
    $html = ($this->html)();

    expect($html)->toContain('04/12/2026');

    // Reimprimirlo mañana no puede cambiarle la fecha al papel firmado.
    $this->travel(40)->days();

    expect(($this->html)())->toContain('04/12/2026')
        ->not->toContain('<div class="code-date">'.now()->format('d/m/Y'));
});

it('las horas de las actividades van sin segundos', function () {
    $html = ($this->html)();

    expect($html)->toContain('>08:00<')
        ->toContain('>17:00<')
        ->not->toContain('08:00:00');
});
