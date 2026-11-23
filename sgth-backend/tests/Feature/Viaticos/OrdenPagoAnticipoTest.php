<?php

/*
| La orden de pago del anticipo, el papel con el que Gestión Financiera pide el
| egreso (2026-11-23, S3 y S4).
|
| Se emite desde que el viático está aprobado, antes de que el dinero salga, y
| por eso lleva el tipo PREVIA: el comprobante del viático completo es el
| DEFINITIVA. Dos firmas, no las tres del resto del trámite —el Director
| Financiero, que ordena, y quien prepara el pago—, y la segunda no sale del
| organigrama sino de quien genera la orden, así que se sella la primera vez.
*/

use App\Enums\EstadoViatico;
use App\Enums\RegimenLaboral;
use App\Exceptions\ReglaNegocioException;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Models\Viatico\Viatico;
use App\Models\Viatico\ViaticoFirmante;
use App\Services\Viatico\PdfInformeViaticoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $unidad = unidadDePrueba(['codigo' => 'GORD']);
    $cedula = 800009500;
    $crear = function (string $nombre, string $apellido) use ($unidad, &$cedula) {
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

    $this->viajero  = $crear('Olga', 'Orden');
    $this->prepara  = $crear('Fausto', 'Finanzas');
    $this->otra     = $crear('Petra', 'Pagos');

    $this->financiero = User::factory()->create(['servidor_id' => $this->prepara->id]);
    $this->financiero->assignRole('financiero');
    $this->titular = User::factory()->create(['servidor_id' => $this->viajero->id]);
    $this->titular->assignRole('servidor');

    $this->viatico = function (array $extra = []) {
        return Viatico::create(array_merge([
            'servidor_id'        => $this->viajero->id,
            'zona'               => 'fuera_provincia',
            'fecha_solicitud'    => '2026-11-23',
            'datetime_salida'    => '2026-12-01 08:00:00',
            'datetime_llegada'   => '2026-12-03 18:00:00',
            'noches'             => 2,
            'justificacion'      => 'Capacitación en contratación pública',
            'estado'             => EstadoViatico::APROBADO,
            'monto_calculado'    => 160,
            'monto_anticipo'     => 0,
            'modalidad_anticipo' => 'total',
        ], $extra));
    };

    $this->orden = fn (Viatico $viatico, ?Servidor $elabora = null) => app(PdfInformeViaticoService::class)
        ->generarOrdenAnticipo($viatico->id, $elabora);

    /** El HTML de la orden, con los datos que le pasa el servicio. */
    $this->html = function (Viatico $viatico, ?Servidor $elabora = null): string {
        $datos = null;
        View::composer('pdf.viaticos.orden-pago-anticipo', function ($v) use (&$datos) {
            $datos = $v->getData();
        });

        ($this->orden)($viatico, $elabora);

        return view('pdf.viaticos.orden-pago-anticipo', $datos)->render();
    };
});

it('es previa, anuncia el 70 % y la firman dos, sin la maxima autoridad', function () {
    $html = ($this->html)(($this->viatico)(), $this->prepara);

    expect($html)->toContain('Orden de Pago de Anticipo de Viático')
        ->toContain('PREVIA')
        ->not->toContain('DEFINITIVA')
        // El 70 % de $160, que es lo que se ordena pagar.
        ->toContain('CIENTO DOCE DÓLARES')
        ->toContain('Elaborado por')
        ->toContain('Ordenado por')
        ->toContain('Finanzas Fausto')
        // Las tres firmas son de los otros documentos: aquí no va el prefecto.
        ->not->toContain('Máxima Autoridad');
});

it('un viatico sin anticipo no tiene orden que emitir', function () {
    $sinAnticipo = ($this->viatico)(['modalidad_anticipo' => 'sin_anticipo']);

    expect(fn () => ($this->orden)($sinAnticipo, $this->prepara))
        ->toThrow(ReglaNegocioException::class, 'no hay pago que ordenar');
});

it('antes de aprobar el viatico no hay nada que ordenar', function () {
    $solicitado = ($this->viatico)(['estado' => EstadoViatico::SOLICITADO]);

    expect(fn () => ($this->orden)($solicitado, $this->prepara))
        ->toThrow(ReglaNegocioException::class, 'desde que el viático está aprobado');

    // Cancelado y rechazado tampoco: ese dinero ya no sale.
    foreach ([EstadoViatico::CANCELADO, EstadoViatico::RECHAZADO] as $estado) {
        expect(fn () => ($this->orden)(($this->viatico)(['estado' => $estado]), $this->prepara))
            ->toThrow(ReglaNegocioException::class);
    }
});

it('se sella con quien la genera, y reimprimirla no le cambia el nombre', function () {
    $viatico = ($this->viatico)();

    ($this->orden)($viatico, $this->prepara);

    $firmas = ViaticoFirmante::where('viatico_id', $viatico->id)
        ->where('documento', 'orden_anticipo')
        ->pluck('nombre', 'rol');

    expect($firmas)->toHaveCount(2)
        ->and($firmas['elaborado'])->toBe('Finanzas Fausto');

    // La reimprime otra persona: el papel ya está firmado.
    ($this->orden)($viatico, $this->otra);

    expect(ViaticoFirmante::where('viatico_id', $viatico->id)
        ->where('documento', 'orden_anticipo')
        ->where('rol', 'elaborado')
        ->value('nombre'))->toBe('Finanzas Fausto');
});

/*
| Quien viaja no la emite: el documento se sella con el nombre de quien lo
| genera, así que abrirla lo dejaría firmado como el funcionario que preparó su
| propio pago. Los otros tres PDF sí los abre cualquiera que vea el viático.
*/
it('solo la emite quien opera los viaticos', function () {
    $viatico = ($this->viatico)();
    $url = "/api/v1/viaticos/{$viatico->id}/orden-anticipo/generar";

    $this->actingAs($this->titular, 'sanctum')->getJson($url)->assertForbidden();

    expect(ViaticoFirmante::where('viatico_id', $viatico->id)->count())->toBe(0);

    $this->actingAs($this->financiero, 'sanctum')->get($url)->assertOk();
});
