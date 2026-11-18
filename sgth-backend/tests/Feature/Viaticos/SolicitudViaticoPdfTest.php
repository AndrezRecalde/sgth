<?php

/*
| La solicitud de viático, el papel con el que arranca el trámite.
|
| Lo que decía mal (2026-11-18):
| - la barra del encabezado llevaba cuatro casillas —«viáticos»,
|   «movilizaciones», «subsistencias», «alimentación»— que no miraban el
|   viático: dos estaban marcadas a mano en la plantilla, una nunca se marcaba
|   y la otra dependía de `$viatico->zona === 'exterior'`, que compara un enum
|   con un texto y nunca era cierto;
| - el bloque de transferencia bancaria salía con tres rayas cuando el servidor
|   no tiene cuenta principal, y así se firmaba;
| - el anticipo, que es lo que se va a transferir, no se imprimía.
*/

use App\Enums\EstadoViatico;
use App\Enums\RegimenLaboral;
use App\Models\Catalogo\EntidadFinanciera;
use App\Models\Expediente\CuentaBancariaServidor;
use App\Models\Expediente\Servidor;
use App\Models\Viatico\Viatico;
use App\Services\Viatico\PdfInformeViaticoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $unidad = unidadDePrueba(['codigo' => 'GSOL']);
    $this->servidor = Servidor::create([
        'cedula'                   => '0800009400',
        'nombre'                   => 'Sonia',
        'apellido'                 => 'Solicitud',
        'puesto_id'                => puestoDePrueba($unidad)->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral'          => RegimenLaboral::LOSEP,
        'estado'                   => true,
    ]);

    $this->viatico = function (array $extra = []) {
        return Viatico::create(array_merge([
            'servidor_id'        => $this->servidor->id,
            'zona'               => 'fuera_provincia',
            'fecha_solicitud'    => '2026-11-18',
            'datetime_salida'    => '2026-12-01 08:00:00',
            'datetime_llegada'   => '2026-12-03 18:00:00',
            'noches'             => 2,
            'justificacion'      => 'Capacitación en contratación pública',
            'estado'             => EstadoViatico::SOLICITADO,
            'monto_calculado'    => 160,
            'monto_anticipo'     => 0,
            'modalidad_anticipo' => 'total',
        ], $extra));
    };

    $this->cuenta = function () {
        $banco = EntidadFinanciera::query()->first() ?? EntidadFinanciera::forceCreate([
            'nombre' => 'Banco de Prueba', 'tipo' => 'banco', 'codigo_bce' => '999', 'estado' => true,
        ]);

        return CuentaBancariaServidor::forceCreate([
            'servidor_id'           => $this->servidor->id,
            'entidad_financiera_id' => $banco->id,
            'tipo_cuenta'           => 'ahorros',
            'numero_cuenta'         => '2200334455',
            'proposito'             => 'ambos',
            'es_principal_sueldo'   => true,
            'es_principal_viatico'  => true,
            'estado'                => true,
        ]);
    };

    /** El HTML de la solicitud, con los datos que le pasa el servicio. */
    $this->html = function (Viatico $viatico): string {
        $datos = null;
        View::composer('pdf.viaticos.solicitud-viatico', function ($v) use (&$datos) {
            $datos = $v->getData();
        });

        app(PdfInformeViaticoService::class)->generarSolicitudContent($viatico->id);

        return view('pdf.viaticos.solicitud-viatico', $datos)->render();
    };
});

it('la barra del encabezado lleva el periodo y el anticipo, y ninguna casilla inventada', function () {
    $html = ($this->html)(($this->viatico)());

    expect($html)->toContain('01/12/2026 08:00')
        ->toContain('03/12/2026 18:00')
        ->toContain('2 noche(s)')
        // El 70 % de $160: lo que Financiero va a transferir.
        ->toContain('Anticipo por entregar: $ 112.00');

    // Las casillas afirmaban lo que nadie había pedido.
    expect($html)->not->toContain('A SOLICITAR')
        ->not->toContain('SUBSISTENCIAS')
        ->not->toContain('MOVILIZACIONES');
});

it('un viatico sin anticipo lo dice, en vez de anunciar una transferencia', function () {
    $html = ($this->html)(($this->viatico)(['modalidad_anticipo' => 'sin_anticipo']));

    expect($html)->toContain('Sin anticipo')
        ->not->toContain('Anticipo por entregar');
});

it('sin cuenta bancaria principal lo avisa, en vez de imprimir tres rayas', function () {
    $html = ($this->html)(($this->viatico)());

    expect($html)->toContain('no tiene una cuenta bancaria principal registrada')
        ->not->toContain('Número de Cuenta');
});

it('con cuenta bancaria principal imprime sus datos y no el aviso', function () {
    ($this->cuenta)();

    $html = ($this->html)(($this->viatico)());

    expect($html)->toContain('Número de Cuenta')
        ->toContain('2200334455')
        ->toContain('AHORROS')
        ->not->toContain('no tiene una cuenta bancaria principal registrada');
});
