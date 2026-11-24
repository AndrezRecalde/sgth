<?php

/*
| Lo que los tres papeles del viático —solicitud, informe y comprobante— dicen
| igual: quién viaja, a dónde fue y cómo viajó.
|
| Cada plantilla lo calculaba por su cuenta y no coincidían (2026-11-18):
| - el comprobante tomaba el último tramo, que es el regreso, y escribía que el
|   servidor «se trasladó a la ciudad de» aquella de la que salió;
| - `strtoupper` no toca los acentos, así que la justificación salía
|   «CONTRATACIóN PúBLICA»;
| - la firma de la solicitud decía «RECALDE CRISTHIAN» mientras la cabecera del
|   mismo papel decía «Recalde Solano Cristhian Andrés».
*/

use App\Enums\EstadoViatico;
use App\Enums\RegimenLaboral;
use App\Models\Expediente\Servidor;
use App\Models\Geografia\Canton;
use App\Models\Geografia\Provincia;
use App\Models\User;
use App\Models\Viatico\CatalogoTransporte;
use App\Models\Viatico\LiquidacionViatico;
use App\Models\Viatico\TramoViatico;
use App\Models\Viatico\Viatico;
use App\Services\Viatico\PdfInformeViaticoService;
use App\Support\DestinoDelViatico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $esmeraldas = Provincia::create(['nombre' => 'Esmeraldas', 'codigo' => '08']);
    $pichincha = Provincia::create(['nombre' => 'Pichincha', 'codigo' => '17']);
    $guayas = Provincia::create(['nombre' => 'Guayas', 'codigo' => '09']);

    $this->cantones = [
        'esmeraldas' => Canton::create(['provincia_id' => $esmeraldas->id, 'nombre' => 'Esmeraldas', 'codigo' => '0801']),
        'quito'      => Canton::create(['provincia_id' => $pichincha->id, 'nombre' => 'Quito', 'codigo' => '1701']),
        'guayaquil'  => Canton::create(['provincia_id' => $guayas->id, 'nombre' => 'Guayaquil', 'codigo' => '0901']),
    ];

    $unidad = unidadDePrueba(['codigo' => 'GDOC']);

    // Dos apellidos y dos nombres: es lo que distingue al papel bien hecho del
    // que imprime medio nombre.
    $this->servidor = Servidor::create([
        'cedula'                   => '0800009100',
        'nombre'                   => 'Cristhian',
        'segundo_nombre'           => 'Andrés',
        'apellido'                 => 'Recalde',
        'segundo_apellido'         => 'Solano',
        'puesto_id'                => puestoDePrueba($unidad)->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral'          => RegimenLaboral::LOSEP,
        'estado'                   => true,
    ]);
    $this->usuario = User::factory()->create(['servidor_id' => $this->servidor->id]);
    $this->usuario->assignRole('servidor');

    $this->bus = CatalogoTransporte::create([
        'nombre' => 'Bus interprovincial', 'codigo' => 'BUS',
        'tipo_vehiculo' => 'terrestre', 'activo' => true,
    ]);
    $this->avion = CatalogoTransporte::create([
        'nombre' => 'Avión nacional', 'codigo' => 'AVN',
        'tipo_vehiculo' => 'aereo', 'activo' => true,
    ]);

    $this->viatico = Viatico::create([
        'servidor_id'        => $this->servidor->id,
        'zona'               => 'fuera_provincia',
        'fecha_solicitud'    => '2026-11-18',
        'datetime_salida'    => '2026-12-01 08:00:00',
        'datetime_llegada'   => '2026-12-03 18:00:00',
        'noches'             => 2,
        'justificacion'      => 'Capacitación en contratación pública dictada por el SERCOP',
        'estado'             => EstadoViatico::CONTABILIZADO,
        'monto_calculado'    => 160,
        'monto_anticipo'     => 112,
        'modalidad_anticipo' => 'total',
    ]);
    LiquidacionViatico::create([
        'viatico_id'        => $this->viatico->id,
        'total_facturas'    => 0,
        'fecha_liquidacion' => '2026-12-04',
    ]);

    $this->tramo = fn (array $datos) => TramoViatico::withoutEvents(
        fn () => TramoViatico::create(array_merge([
            'viatico_id'  => $this->viatico->id,
            'origen_tipo' => 'nacional', 'destino_tipo' => 'nacional',
        ], $datos))
    );

    // Ida y vuelta, sin paradas declaradas: el caso en que el comprobante
    // nombraba Esmeraldas.
    ($this->tramo)([
        'orden' => 1, 'tipo_tramo' => 'ida',
        'origen_provincia_id' => $esmeraldas->id, 'origen_canton_id' => $this->cantones['esmeraldas']->id,
        'origen_ciudad' => 'Esmeraldas',
        'destino_provincia_id' => $pichincha->id, 'destino_canton_id' => $this->cantones['quito']->id,
        'destino_ciudad' => 'Quito',
        'catalogo_transporte_id' => $this->bus->id,
        'datetime_salida' => '2026-12-01 08:00:00', 'datetime_llegada' => '2026-12-01 14:00:00',
    ]);
    ($this->tramo)([
        'orden' => 2, 'tipo_tramo' => 'regreso',
        'origen_provincia_id' => $pichincha->id, 'origen_canton_id' => $this->cantones['quito']->id,
        'origen_ciudad' => 'Quito',
        'destino_provincia_id' => $esmeraldas->id, 'destino_canton_id' => $this->cantones['esmeraldas']->id,
        'destino_ciudad' => 'Esmeraldas',
        'catalogo_transporte_id' => $this->avion->id,
        'datetime_salida' => '2026-12-03 14:00:00', 'datetime_llegada' => '2026-12-03 18:00:00',
    ]);

    /*
    | El HTML de los tres documentos, con los datos que les pasa el servicio.
    |
    | Se capturan con un composer en vez de armarlos a mano: así la prueba falla
    | también si el servicio deja de mandarle una variable a la plantilla, que
    | es la mitad de lo que se arregló aquí.
    */
    $this->documentos = function (): array {
        if (isset($this->html)) {
            return $this->html;
        }

        $vistas = [
            'solicitud'   => 'pdf.viaticos.solicitud-viatico',
            'informe'     => 'pdf.viaticos.informe-comision',
            'comprobante' => 'pdf.viaticos.comprobante-contabilidad',
        ];

        $datos = [];
        foreach ($vistas as $vista) {
            View::composer($vista, function ($v) use (&$datos, $vista) {
                $datos[$vista] = $v->getData();
            });
        }

        $pdf = app(PdfInformeViaticoService::class);
        $pdf->generarSolicitudContent($this->viatico->id);
        $pdf->generarInformeContent($this->viatico->id);
        $pdf->generarComprobanteContabilidad($this->viatico->id);

        $this->html = collect($vistas)
            ->map(fn (string $vista) => view($vista, $datos[$vista])->render())
            ->all();

        return $this->html;
    };
});

// ── El destino ───────────────────────────────────────────────────────

it('el destino es a donde fue el servidor, no el lugar al que volvio', function () {
    expect(DestinoDelViatico::ciudades($this->viatico)->all())->toBe(['Quito'])
        ->and(DestinoDelViatico::texto($this->viatico))->toBe('Pichincha / Quito');
});

it('con varias paradas manda lo declarado como destino, y el regreso nunca cuenta', function () {
    // Una parada declarada convierte al viaje en multidestino: manda `destino`
    // y la ida deja de valer como tal.
    ($this->tramo)([
        'orden' => 2, 'tipo_tramo' => 'destino',
        'origen_provincia_id' => $this->cantones['quito']->provincia_id,
        'origen_canton_id' => $this->cantones['quito']->id, 'origen_ciudad' => 'Quito',
        'destino_provincia_id' => $this->cantones['guayaquil']->provincia_id,
        'destino_canton_id' => $this->cantones['guayaquil']->id, 'destino_ciudad' => 'Guayaquil',
        'catalogo_transporte_id' => $this->bus->id,
        'datetime_salida' => '2026-12-02 08:00:00', 'datetime_llegada' => '2026-12-02 14:00:00',
    ]);

    expect(DestinoDelViatico::texto($this->viatico->fresh()))->toBe('Guayas / Guayaquil');
});

it('sin itinerario todavia, el destino de un viaje al exterior es el pais', function () {
    $this->viatico->tramos()->delete();
    $this->viatico->update(['zona' => 'exterior', 'pais_destino' => 'Colombia']);

    expect(DestinoDelViatico::texto($this->viatico->fresh()))->toBe('Colombia');
});

// ── Cómo se escribe ──────────────────────────────────────────────────

it('el tipo de transporte se lee con tilde', function () {
    $tramos = $this->viatico->tramos()->orderBy('orden')->get();

    expect($tramos[0]->tipo_transporte)->toBe('Terrestre')
        ->and($tramos[1]->tipo_transporte)->toBe('Aéreo');
});

/*
| Las tres plantillas en una sola prueba: cada documento cuesta un PDF, y lo
| que se comprueba es justamente que los tres digan lo mismo.
*/
it('los tres documentos dicen igual quien viaja, a donde fue y como se llama el viatico', function () {
    $documentos = ($this->documentos)();

    expect(array_keys($documentos))->toBe(['solicitud', 'informe', 'comprobante']);

    foreach ($documentos as $html) {
        expect($html)->toContain('Recalde Solano Cristhian Andrés')
            // El código, no la etiqueta: desde el rediseño del 2026-11-24 va
            // en el cintillo, que lo rotula con su propio recuadro.
            ->and($html)->toContain($this->viatico->codigo_viatico);
    }

    // La firma de la solicitud era la que imprimía medio nombre: ahora el
    // nombre completo sale en la cabecera y en la firma.
    expect(substr_count($documentos['solicitud'], 'Recalde Solano Cristhian Andrés'))
        ->toBeGreaterThanOrEqual(2);

    // El comprobante tomaba el último tramo, que vuelve a Esmeraldas.
    expect($documentos['comprobante'])->toContain('se trasladó a la ciudad de')
        ->and($documentos['comprobante'])->toContain('<strong>QUITO</strong>')
        ->and($documentos['comprobante'])->not->toContain('<strong>ESMERALDAS');

    // `strtoupper` dejaba «CONTRATACIóN PúBLICA».
    expect($documentos['comprobante'])->toContain('CAPACITACIÓN EN CONTRATACIÓN PÚBLICA')
        ->and($documentos['comprobante'])->not->toContain('CONTRATACIóN');

    expect($documentos['informe'])->toContain('Aéreo')
        ->and($documentos['informe'])->not->toContain('AEREO');
});
