<?php

use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Dispensario\FemoService;
use App\Services\Dispensario\PdfFemoService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
| El PDF frente al impreso SNS-MSP/HCU-form.123/2025, en lo que se apartaba:
| la X de la sección F al revés, antecedentes y observaciones que se
| capturaban y no se imprimían, la edad a la fecha de impresión, el sexo en
| texto, los literales de la aptitud y el código del formulario.
|
| Se comprueba el HTML que recibe dompdf. La maquetación no se prueba: para
| eso hay que abrir un PDF.
*/

function htmlFemoFidelidad(array $datos, string $genero = 'femenino'): string
{
    Servidor::unguard();
    User::unguard();

    $servidor = Servidor::create([
        'cedula' => '0804258986', 'nombre' => 'Francis', 'apellido' => 'Quinde',
        'genero' => $genero, 'fecha_nacimiento' => '1998-10-21',
    ]);
    $medico = User::factory()->create();

    $ficha = app(FemoService::class)->registrar([
        ...$datos,
        'ficha' => [
            'servidor_id' => $servidor->id,
            'fecha_evaluacion' => '2026-07-03',
            'tipo_ficha' => 'retiro',
            'aptitud' => 'apto_con_restricciones',
            ...($datos['ficha'] ?? []),
        ],
    ], $medico->id);

    return view(
        'pdf.dispensario.femo.formulario-028',
        app(PdfFemoService::class)->datosDeLaVista($ficha->id),
    )->render();
}

test('la X de la sección F marca patología, con numeral y letra del impreso', function () {
    $html = htmlFemoFidelidad([
        'examen_fisico' => [
            ['region' => 'neurologico', 'item' => 'Marcha', 'normal' => false, 'observacion' => 'Claudicación derecha'],
            ['region' => 'piel', 'item' => 'Cicatrices', 'normal' => true],
        ],
    ]);

    expect($html)
        ->toContain('evidencia de patología')
        ->not->toContain('No evaluado')
        // Los 13 numerales y los ítems con letra, aunque no se hayan tocado.
        ->toContain('1. Piel')
        ->toContain('13. Neurológico')
        ->toContain('a. Cicatrices')
        ->toContain('c. Marcha')
        // La observación se describe con su numeral.
        ->toContain('13c. Marcha: Claudicación derecha');
});

test('se imprimen los antecedentes quirúrgicos y los demás tipos', function () {
    $html = htmlFemoFidelidad([
        'antecedentes' => [
            ['tipo' => 'quirurgico', 'descripcion' => 'Apendicectomía'],
            ['tipo' => 'otro', 'descripcion' => 'Fractura de radio en la infancia'],
        ],
    ]);

    expect($html)
        ->toContain('Apendicectomía')
        ->toContain('Otros Antecedentes')
        ->toContain('Fractura de radio en la infancia');
});

test('el bloque gineco-obstétrico imprime los exámenes realizados', function () {
    $html = htmlFemoFidelidad([
        'antecedente_reproductivo' => [
            'gestas' => 2, 'partos' => 1, 'cesareas' => 1,
            'examenes_realizados' => 'Papanicolaou', 'examenes_tiempo_anios' => 1,
        ],
    ]);

    expect($html)->toContain('Exámenes realizados: Papanicolaou (1 años)');
});

test('la sección H imprime las observaciones de cada empleo', function () {
    $html = htmlFemoFidelidad([
        'empleos_anteriores' => [[
            'centro_trabajo' => 'GADPE', 'tipo_evento_laboral' => 'ninguno',
            'observaciones' => 'Sin novedades en 16 años',
        ]],
    ]);

    expect($html)->toContain('Sin novedades en 16 años');
});

test('el «Otros» de la sección G imprime lo que escribió el médico', function () {
    $html = htmlFemoFidelidad([
        'actividades' => [['actividad' => 'Mantenimiento eléctrico']],
        'factores_riesgo' => [[
            'categoria' => 'fisico', 'factor' => 'Otros', 'presente' => true,
            'medida_preventiva' => 'Campos electromagnéticos', 'actividad_index' => 0,
        ]],
    ]);

    expect($html)->toContain('Otros: Campos electromagnéticos');
});

test('la edad es la del día de la atención y el sexo va en casillas', function () {
    // Nació el 1998-10-21 y se atendió el 2026-07-03: 27 años, como el
    // ejemplo del Dispensario, sin importar cuándo se imprima.
    $html = htmlFemoFidelidad([], 'masculino');

    expect($html)
        ->toContain('(27 años)')
        ->toContain('Hombre [X]')
        ->toContain('Mujer [ ]')
        ->toContain('1998/10/21');
});

test('la aptitud lleva los literales del impreso y el pie el código del formulario', function () {
    $html = htmlFemoFidelidad(['ficha' => ['restricciones' => 'No trabajar en altura.']]);

    expect($html)
        ->toContain('APTO EN OBSERVACIÓN')
        ->toContain('APTO CON LIMITACIONES')
        ->toContain('SNS-MSP/HCU-form.123/2025')
        ->toContain('Firma o Huella del Trabajador');
});
