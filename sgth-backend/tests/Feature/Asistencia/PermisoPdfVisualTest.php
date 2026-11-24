<?php

/*
| El PDF del permiso, tal como lo imprime Talento Humano.
|
| Cada test es un defecto que se veía en el papel: el corte salía como
| «? CORTAR AQUÍ ?», la firma sin jefe llevaba dos líneas, la unidad salía
| vacía y las horas iban con segundos. Después, las fechas en año-mes-día y la
| hoja: partida en dos mitades, con la primera de altura fija. Desde el
| 2026-11-24 en el estilo de los demás documentos institucionales, sin marca de
| agua y sin «Asistencia» en el encabezado.
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
    $permiso->load(['servidor.puesto.cargo', 'servidor.unidadAdministrativa', 'jefe.puesto.cargo', 'unidadAdministrativa']);

    return view('permisos.permiso-pdf', [
        'permiso'            => $permiso,
        'mostrarObservacion' => true,
    ])->render();
}

test('sin unidad propia, la unidad sale de la del servidor', function () {
    expect(htmlDelPdfDePermiso($this->permiso))->toContain('Gestión Administrativa');
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

test('la fecha del permiso, la de creación con su hora y las horas pedidas van cada una en su celda', function () {
    $this->permiso->forceFill(['created_at' => '2026-09-08 13:06:06'])->save();

    expect(htmlDelPdfDePermiso($this->permiso))
        ->toContain('<td class="label">Fecha del permiso</td>')
        ->toContain('11/09/2026')
        ->toContain('<td class="label">Fecha de creación</td>')
        ->toContain('08/09/2026 13:06')
        ->toContain('<td class="label">Hora de inicio</td>')
        ->toContain('<td class="label">Hora de fin</td>')
        ->not->toContain('2026-09-11')
        ->not->toContain('13:06:06');
});

test('las dos copias caben en una sola hoja, aun con la observación más larga que se admite', function () {
    // 1000 caracteres, el máximo de StorePermisoServidorRequest, y la unidad
    // de nombre más largo que hay hoy. La primera copia tiene media hoja fija:
    // si algo crece de más, la segunda se va a una página nueva.
    $larga = mb_substr(str_repeat('Comisión de servicios en el cantón Quinindé para la entrega de documentación. ', 20), 0, 1000);

    $this->permiso->servidor->unidadAdministrativa
        ->update(['nombre' => 'Gestión de Tecnologías de la Información y Comunicación']);
    $this->permiso->update(['observacion' => $larga]);

    $html = htmlDelPdfDePermiso($this->permiso);
    $pdf  = app('dompdf.wrapper')->setOption('enable_font_subsetting', true)
        ->setPaper('a4', 'portrait')->loadHTML($html)->output();

    expect(preg_match_all('#/Type\s*/Page(?![a-zA-Z])#', $pdf))->toBe(1)
        // Entera: se achica la letra, no se recorta el texto.
        ->and($html)->toContain(e($larga));
});

test('cada copia dice en su pie de quién es, sin marca de agua', function () {
    expect(htmlDelPdfDePermiso($this->permiso))
        ->toContain('<td class="copia-de">Copia del servidor</td>')
        ->toContain('<td class="copia-de">Copia de Talento Humano</td>')
        ->not->toContain('marca-agua');
});

test('ningún encabezado dice «Asistencia» y lo recibe Talento Humano', function () {
    $html = htmlDelPdfDePermiso($this->permiso);

    expect($html)->toContain('<h1>Concesión de permiso</h1>')
        ->toContain('<div class="subtipo">Oficial</div>')
        ->toContain('Recibe · Talento Humano')
        ->not->toContain('Asistencia')
        // El rol de Recepción ya no se asigna.
        ->not->toContain('PERSONAL TTHH');
});

test('sin poder leer el motivo, la copia lo reserva', function () {
    $this->permiso->load(['servidor.puesto.cargo', 'servidor.unidadAdministrativa', 'jefe.puesto.cargo', 'unidadAdministrativa']);

    $html = view('permisos.permiso-pdf', ['permiso' => $this->permiso, 'mostrarObservacion' => false])->render();

    expect($html)->toContain('Reservado: es un dato privado del servidor.')
        ->not->toContain('Comisión de servicios');
});
