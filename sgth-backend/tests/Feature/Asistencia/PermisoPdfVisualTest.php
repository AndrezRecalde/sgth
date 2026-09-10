<?php

/*
| El PDF del permiso, tal como lo imprime Recepción.
|
| Cada test es un defecto que se veía en el papel: el corte salía como
| «? CORTAR AQUÍ ?», la marca de agua asomaba a trozos entre las celdas, la
| firma sin jefe llevaba dos líneas, «Departamento» salía vacío y las horas
| iban con segundos.
*/

use App\Enums\RegimenLaboral;
use App\Models\Asistencia\PermisoServidor;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();
    PermisoServidor::unguard();

    $unidad = unidadDePrueba(['nombre' => 'Gestión Administrativa']);

    $servidor = Servidor::create([
        'cedula'                   => '0801000009',
        'nombre'                   => 'Nelson',
        'apellido'                 => 'Arroyo',
        'puesto_id'                => puestoDePrueba($unidad)->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral'          => RegimenLaboral::LOSEP,
        'estado'                   => true,
    ]);

    // Como los que ya existen: sin unidad propia y sin jefe asignado.
    $this->permiso = PermisoServidor::create([
        'servidor_id'              => $servidor->id,
        'unidad_administrativa_id' => null,
        'jefe_id'                  => null,
        'tipo'                     => 'oficial',
        'fecha'                    => '2026-09-11',
        'hora_inicio'              => '08:00',
        'hora_fin'                 => '10:00',
        'observacion'              => 'Comisión de servicios',
        'estado'                   => 'pendiente',
        'vence_en'                 => now()->addDays(3),
        'folio'                    => 'PER-2026-00042',
    ]);
});

/** El HTML de la vista, con lo mismo que carga `exportar()`. */
function htmlDelPdfDePermiso(PermisoServidor $permiso): string
{
    $permiso->load(['servidor.puesto.cargo', 'jefe', 'unidadAdministrativa', 'creadoPor']);

    return view('permisos.permiso-pdf', [
        'permiso'            => $permiso,
        'mostrarObservacion' => true,
    ])->render();
}

test('sin unidad propia, el departamento sale de la unidad del servidor', function () {
    expect(htmlDelPdfDePermiso($this->permiso))->toContain('GESTIÓN ADMINISTRATIVA');
});

test('sin jefe asignado, la firma lleva una sola línea', function () {
    expect(htmlDelPdfDePermiso($this->permiso))->not->toContain('__________________');
});

test('las horas salen sin segundos, como en la pantalla', function () {
    expect(htmlDelPdfDePermiso($this->permiso))
        ->toContain('08:00')
        ->not->toContain('08:00:00')
        ->not->toContain('10:00:00');
});

test('el corte no usa un símbolo que la fuente no tiene', function () {
    expect(htmlDelPdfDePermiso($this->permiso))
        ->toContain('CORTAR AQUÍ')
        ->not->toContain('✂');
});

test('cada copia lleva una marca de agua que coincide con su pie', function () {
    expect(htmlDelPdfDePermiso($this->permiso))
        ->toContain('<div class="marca-agua">SERVIDOR</div>')
        ->toContain('<div class="marca-agua">TALENTO HUMANO</div>')
        ->toContain('COPIA TALENTO HUMANO')
        // La imagen de fondo decía RECEPCIÓN y quedaba tapada por las celdas.
        ->not->toContain('recepcion-bg.png');
});
