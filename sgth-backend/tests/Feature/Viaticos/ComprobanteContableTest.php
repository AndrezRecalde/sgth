<?php

/*
| El comprobante contable que Financiero imprime al contabilizar.
|
| Lo que decía mal (2026-11-18):
| - el bloque de movilización sumaba categorías que no imprimía, porque la
|   lista estaba escrita a mano con ocho ids y la plantilla mostraba seis: una
|   inscripción de cien dólares salía como cuatro ceros y un total de cien;
| - la firma «Elaborado por» pedía `$liquidacion->createdBy->name`, y `users`
|   no tiene columna `name`, así que salía en blanco —y `created_by` es quien
|   creó la liquidación, que es el servidor que viajó—;
| - el detalle decía «del 01 al 3 de diciembre del 2026», y en un viaje que
|   cruza de mes no nombraba el primero.
|
| Lo que respondió Gestión Financiera (2026-11-18, D3): la única cifra en letras
| era la liquidada, de modo que el papel escribía ciento sesenta dólares mientras
| se transferían cuarenta y ocho. Van las dos, y en un documento de pago la que
| prevalece es la que está en letras.
*/

use App\Enums\EstadoViatico;
use App\Enums\RegimenLaboral;
use App\Models\Expediente\Servidor;
use App\Models\User;
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

    $unidad = unidadDePrueba(['codigo' => 'GCOM']);
    $cedula = 800009200;
    $crearServidor = function (string $nombre, string $apellido) use ($unidad, &$cedula) {
        return Servidor::create([
            'cedula'                   => '0'.(++$cedula),
            'nombre'                   => $nombre,
            'apellido'                 => $apellido,
            'puesto_id'                => puestoDePrueba($unidad)->id,
            'unidad_administrativa_id' => $unidad->id,
            'regimen_laboral'          => RegimenLaboral::LOSEP,
            'estado'                   => true,
        ]);
    };

    $this->viajero = $crearServidor('Raúl', 'Recibo');
    $contador = $crearServidor('Fanny', 'Finanzas');
    $this->contable = User::factory()->create(['servidor_id' => $contador->id]);
    $this->contable->assignRole('financiero');

    $this->hospedaje = CategoriaFactura::create([
        'nombre' => 'Hospedaje', 'codigo' => 'hospedaje',
        'grupo' => 'viatico', 'activo' => true, 'orden' => 1,
    ]);
    // Inactiva en el catálogo de hoy, pero las liquidaciones viejas la usan: es
    // justo la que se sumaba sin fila propia.
    $this->inscripcion = CategoriaFactura::create([
        'nombre' => 'Inscripción / Registro', 'codigo' => 'inscripcion',
        'grupo' => 'movilizacion', 'activo' => false, 'orden' => 10,
    ]);

    $this->viatico = function (array $extra = []) {
        $viatico = Viatico::create(array_merge([
            'servidor_id'        => $this->viajero->id,
            'zona'               => 'fuera_provincia',
            'fecha_solicitud'    => '2026-11-18',
            'datetime_salida'    => '2026-12-01 08:00:00',
            'datetime_llegada'   => '2026-12-03 18:00:00',
            'noches'             => 2,
            'justificacion'      => 'Capacitación en contratación pública',
            'estado'             => EstadoViatico::CONTABILIZADO,
            'monto_calculado'    => 160,
            'monto_anticipo'     => 112,
            'modalidad_anticipo' => 'total',
        ], $extra));

        LiquidacionViatico::create([
            'viatico_id'            => $viatico->id,
            'total_facturas'        => 0,
            'fecha_liquidacion'     => '2026-12-04',
            'contabilizado_por'     => $this->contable->id,
            'fecha_contabilizacion' => '2026-12-05',
        ]);

        return $viatico;
    };

    $this->comprobar = function (Viatico $viatico, CategoriaFactura $categoria, float $monto) {
        return FacturaViatico::create([
            'liquidacion_viatico_id' => $viatico->liquidacion()->firstOrFail()->id,
            'categoria_factura_id'   => $categoria->id,
            'tipo_comprobante'       => 'factura',
            'numero_factura'         => '001-001-00000'.random_int(1000, 9999),
            'ruc_proveedor'          => '1790016919001',
            'nombre_proveedor'       => 'Proveedor de prueba',
            'fecha_factura'          => '2026-12-02',
            'monto'                  => $monto,
        ]);
    };

    /** El HTML del comprobante, con los datos que le pasa el servicio. */
    $this->html = function (Viatico $viatico): string {
        $datos = null;
        View::composer('pdf.viaticos.comprobante-contabilidad', function ($v) use (&$datos) {
            $datos = $v->getData();
        });

        app(PdfInformeViaticoService::class)->generarComprobanteContabilidad($viatico->id);

        return view('pdf.viaticos.comprobante-contabilidad', $datos)->render();
    };
});

it('imprime todas las categorias que suman al total, no solo las de una lista fija', function () {
    $viatico = ($this->viatico)();
    ($this->comprobar)($viatico, $this->hospedaje, 80);
    ($this->comprobar)($viatico, $this->inscripcion, 25);

    $html = ($this->html)($viatico->fresh());

    // Antes el total de movilización decía 25.00 y arriba había cuatro ceros.
    expect($html)->toContain('Inscripción / Registro')
        ->and($html)->toContain('Hospedaje');

    // Lo que suma, se ve: cada total es el de las filas impresas.
    expect(montoTrasEtiquetaDelComprobante($html, 'TOTAL H&amp;A:'))->toBe('80.00')
        ->and(montoTrasEtiquetaDelComprobante($html, 'TOTAL MOVILIZACIÓN:'))->toBe('25.00');
});

it('sin comprobantes de un grupo lo dice, en vez de dejar filas en cero', function () {
    $viatico = ($this->viatico)();
    ($this->comprobar)($viatico, $this->hospedaje, 80);

    $html = ($this->html)($viatico->fresh());

    expect(substr_count($html, 'Sin comprobantes en este grupo'))->toBe(1)
        ->and($html)->not->toContain('Pasajes terrestres / fluviales');
});

it('la firma de quien elabora lleva el nombre de quien contabilizo', function () {
    $html = ($this->html)(($this->viatico)());

    expect($html)->toContain('Finanzas Fanny')
        ->and($html)->toContain('Elaborado por');
});

it('el detalle fecha el viaje en español y nombra los dos meses si los cruza', function () {
    $html = ($this->html)(($this->viatico)());

    expect($html)->toContain('del 1 al 3 de diciembre de 2026')
        // «quién» es la de la pregunta; aquí va el relativo, sin tilde.
        ->and($html)->toContain('quien se trasladó')
        ->and($html)->not->toContain('quién se trasladó');

    $cruzado = ($this->viatico)([
        'datetime_salida'  => '2026-11-30 08:00:00',
        'datetime_llegada' => '2026-12-02 18:00:00',
    ]);

    expect(($this->html)($cruzado))
        ->toContain('del 30 de noviembre de 2026 al 2 de diciembre de 2026');
});

it('dice en letras lo liquidado y, debajo, lo que se gira', function () {
    $viatico = ($this->viatico)();
    ($this->comprobar)($viatico, $this->hospedaje, 80);

    $html = ($this->html)($viatico->fresh());

    // Reconocido 128 = 80 justificados + los 48 del 30 % que no se justifica;
    // el anticipo fue de 112, así que quedan 16 por pagarle.
    expect($html)->toContain('Valor liquidado:')
        ->toContain('CIENTO VEINTE Y OCHO DÓLARES')
        ->toContain('A pagar:')
        ->toContain('DIECISÉIS DÓLARES');
});

it('cuando el servidor tiene que devolver, lo dice en letras y con su rotulo', function () {
    // Sin comprobantes: se le reconocen los 48 del 30 % y devuelve el resto.
    $html = ($this->html)(($this->viatico)());

    expect($html)->toContain('A devolver:')
        ->toContain('SESENTA Y CUATRO DÓLARES')
        ->not->toContain('A pagar:');
});

/*
| El «TIPO» del comprobante es la etapa del pago, no la modalidad del anticipo.
| Lo definió Gestión Financiera el 2026-11-23: previa el anticipo, definitiva el
| viático completo. Antes salía de `modalidad_anticipo`, de modo que un viático
| tramitado sin anticipo decía «SIN ANTICIPO» en vez de decir que su pago es el
| definitivo.
*/
it('el tipo es la etapa del pago: el comprobante es siempre el definitivo', function () {
    expect(($this->html)(($this->viatico)()))->toContain('DEFINITIVA');

    $sinAnticipo = ($this->viatico)([
        'modalidad_anticipo' => 'sin_anticipo',
        'monto_anticipo'     => 0,
    ]);

    expect(($this->html)($sinAnticipo))
        ->toContain('DEFINITIVA')
        ->not->toContain('SIN ANTICIPO');
});

it('la linea del anticipo dice cuando salio y con que comprobante', function () {
    $viatico = ($this->viatico)([
        'fecha_anticipo'    => '2026-11-20',
        'numero_resolucion' => 'CP-2026-00987',
    ]);

    expect(($this->html)($viatico))
        ->toContain('Anticipo entregado el 20/11/2026, comprobante N.° CP-2026-00987');
});

it('un viatico sin anticipo no inventa fecha ni comprobante', function () {
    $viatico = ($this->viatico)([
        'modalidad_anticipo' => 'sin_anticipo',
        'monto_anticipo'     => 0,
        'numero_resolucion'  => 'CP-2026-00987',
    ]);

    expect(($this->html)($viatico))
        ->toContain('Sin anticipo')
        ->not->toContain('comprobante N.°');
});

it('la fecha de la ciudad va en el orden del español', function () {
    $html = ($this->html)(($this->viatico)());

    expect($html)->toContain('Esmeraldas,')
        ->and($html)->toContain('5 de diciembre de 2026')
        ->and($html)->not->toContain('diciembre 5, 2026');
});

/** El importe que sigue a una etiqueta de total, para no afirmar sobre el HTML entero. */
function montoTrasEtiquetaDelComprobante(string $html, string $etiqueta): ?string
{
    $desde = strpos($html, $etiqueta);

    if ($desde === false) {
        return null;
    }

    preg_match('/\$ ([\d,]+\.\d{2})/', substr($html, $desde, 400), $coincidencias);

    return $coincidencias[1] ?? null;
}
