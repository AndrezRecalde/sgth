<?php

namespace Tests\Feature\Estructura;

use App\Enums\NivelCompetencia;
use App\Enums\NivelInstruccion;
use App\Models\Estructura\Competencia;
use App\Models\Estructura\FichaManualPuestos;
use App\Models\Estructura\Puesto;
use App\Services\Estructura\Manual\EmparejadorDeCompetencias;
use App\Services\Estructura\Manual\ImportacionDelManualService;
use App\Services\Estructura\Manual\LecturaDeRequisitos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| La importación del Manual de Puestos 2018-2019 a la tabla de staging
| (docs/diseno-manual-de-puestos.md, P4; PR 11): cómo se leen la instrucción,
| la experiencia y las competencias de cada ficha, y el comando que carga el
| JSON del extractor.
*/

/** Una ficha como la deja el extractor, con lo justo para cada prueba. */
function fichaDelManualDePrueba(array $cambios = []): array
{
    return array_merge([
        'archivo'           => '3.PROCESOS HABILITANTES DE APOYO/3.GESTION DE TALENTO HUMANO/4.SEGURIDAD/2.ANALISTA SP5 (2).pdf',
        'proceso'           => '3.PROCESOS HABILITANTES DE APOYO',
        'carpeta'           => '3.GESTION DE TALENTO HUMANO/4.SEGURIDAD',
        'plazas'            => 2,
        'codigo'            => '',
        'denominacion'      => 'Analista',
        'nivel'             => 'Profesional',
        'unidad'            => 'Gestión de Talento Humano / Seguridad y Salud Ocupacional',
        'rol'               => 'Ejecución de Procesos',
        'grupo_ocupacional' => 'Servidor Público 5',
        'grado'             => '11',
        'ambito'            => 'Provincial',
        'mision'            => 'Ejecutar las actividades de seguridad y salud ocupacional.',
        'relaciones'        => 'Servidores de la institución.',
        'area_conocimiento' => 'Seguridad Industrial',
        'especificidad'     => 'Prevención de riesgos laborales.',
        'capacitacion'      => 'Normativa de seguridad y salud en el trabajo.',
        'instruccion'       => [
            ['nivel' => 'Tecnólogo Superior', 'experiencia' => '4 años'],
            ['nivel' => 'Tercer Nivel (Título Profesional)', 'experiencia' => '2 años 6 meses'],
        ],
        'actividades' => [
            ['descripcion' => 'Elabora el plan de prevención de riesgos.', 'conocimientos' => 'Normativa SSO'],
            ['descripcion' => 'Inspecciona los puestos de trabajo.', 'conocimientos' => ''],
        ],
        'tecnicas' => [
            ['nombre' => 'Monitoreo y Control', 'nivel' => 'Medio', 'numero' => 99, 'comportamiento' => ''],
        ],
        'conductuales' => [
            ['nombre' => 'Trabjo en Equipo', 'nivel' => 'Alto', 'numero' => 1, 'comportamiento' => ''],
        ],
        'avisos' => [],
    ], $cambios);
}

function competenciaDelDiccionario(string $nombre): Competencia
{
    return Competencia::where('nombre', $nombre)->firstOrFail();
}

/** El JSON de prueba en un archivo temporal, para el comando. */
function jsonDelManualDePrueba(array $fichas): string
{
    $ruta = tempnam(sys_get_temp_dir(), 'manual') . '.json';
    file_put_contents($ruta, json_encode($fichas, JSON_UNESCAPED_UNICODE));

    return $ruta;
}

test('el nivel de instrucción se lee como lo escribe el manual', function (string $texto, NivelInstruccion $nivel, ?string $detalle, bool $conAviso) {
    $leido = LecturaDeRequisitos::nivel($texto);

    expect($leido['nivel'])->toBe($nivel)
        ->and($leido['detalle'])->toBe($detalle)
        ->and($leido['aviso'] !== null)->toBe($conAviso);
})->with([
    ['Tercer Nivel (Título Profesional)', NivelInstruccion::TERCER_NIVEL, null, false],
    // El mínimo de «tercer o cuarto» es el tercero.
    ['Tercer o Cuarto Nivel (Título Profesional)', NivelInstruccion::TERCER_NIVEL, null, false],
    ['Cuarto Nivel', NivelInstruccion::CUARTO_NIVEL, null, false],
    ['Tecnólogo Superior', NivelInstruccion::TECNOLOGICO_SUPERIOR, null, false],
    ['Tecnológico Supèrior', NivelInstruccion::TECNOLOGICO_SUPERIOR, null, false],
    ['Técnico Superior', NivelInstruccion::TECNICO_SUPERIOR, null, false],
    ['Técnico', NivelInstruccion::TECNICO_SUPERIOR, null, true],
    ['Bachiller', NivelInstruccion::BACHILLER, null, false],
    // Estudios en curso: lo aprobado queda en el detalle.
    ['Tercer año o sexto semestre aprobado', NivelInstruccion::ESTUDIOS_SUPERIORES_EN_CURSO, 'Tercer año o sexto semestre aprobado', false],
]);

test('un nivel que no se reconoce queda vacío y con aviso', function () {
    $leido = LecturaDeRequisitos::nivel('Primaria');

    expect($leido['nivel'])->toBeNull()
        ->and($leido['aviso'])->toBe('No se reconoció el nivel de instrucción «Primaria».');
});

test('el tiempo de experiencia se lee en meses', function (?string $texto, ?int $meses, bool $conAviso) {
    $leido = LecturaDeRequisitos::meses($texto);

    expect($leido['meses'])->toBe($meses)
        ->and($leido['aviso'] !== null)->toBe($conAviso);
})->with([
    ['6 años', 72, false],
    ['2 años 6 meses', 30, false],
    ['1 años 6 meses', 18, false],
    ['6 meses', 6, false],
    ['5 AÑOS', 60, false],
    ['Minimo 4años de experiencia enel ejercicio de actividades', 48, false],
    ['Más de 4 años de experiencia', 48, false],
    ['No requerida', 0, false],
    // Un rango se lee por su mínimo, con aviso.
    ['3 - 4 años', 36, true],
    // La fila de tiempos de las fichas de tres alternativas: años.
    ['5', 60, true],
    // «Hasta» no es un mínimo: no se adivina.
    ['Hasta 1 año', null, true],
    [null, null, true],
    ['Amplia', null, true],
]);

test('las competencias se emparejan con el diccionario y su número se recalcula', function () {
    $monitoreo = competenciaDelDiccionario('Monitoreo y Control');
    $equipo    = competenciaDelDiccionario('Trabajo en Equipo');
    $emparejador = new EmparejadorDeCompetencias(Competencia::all());

    $resultado = $emparejador->emparejar(
        [['nombre' => 'MONITOREO Y CONTROL', 'nivel' => 'Medio', 'numero' => 99, 'comportamiento' => '']],
        [['nombre' => 'Trabjo en Equipo', 'nivel' => 'Alto', 'numero' => 1, 'comportamiento' => '']],
    );

    expect($resultado['competencias'])->toBe([
        [
            'competencia_id' => $monitoreo->id, 'tipo' => 'tecnica', 'nombre' => 'Monitoreo y Control', 'nivel' => 'medio',
            'numero' => $monitoreo->numeroDeComportamiento(NivelCompetencia::MEDIO),
            'nombre_en_ficha' => 'MONITOREO Y CONTROL', 'coincidencia' => 'exacta',
        ],
        [
            'competencia_id' => $equipo->id, 'tipo' => 'conductual', 'nombre' => 'Trabajo en Equipo', 'nivel' => 'alto',
            'numero' => $equipo->numeroDeComportamiento(NivelCompetencia::ALTO),
            'nombre_en_ficha' => 'Trabjo en Equipo', 'coincidencia' => 'aproximada',
        ],
    ])
        ->and($resultado['avisos'])->toBe([])
        ->and($resultado['conteo'])->toBe([
            'filas' => 2, 'exactas' => 1, 'aproximadas' => 1, 'repetidas' => 0, 'sin_reconocer' => 0, 'renumeradas' => 1,
        ]);
});

test('lo que la ficha trae mal se corrige o se descarta, con aviso', function () {
    $planificacion = competenciaDelDiccionario('Planificación y Gestión');
    $emparejador   = new EmparejadorDeCompetencias(Competencia::all());

    $resultado = $emparejador->emparejar([
        // Sin nivel: se deduce del número (la 4.ª técnica, 10 = alto).
        ['nombre' => 'Planificación y Gestión', 'nivel' => '', 'numero' => 10, 'comportamiento' => ''],
        // Repetida con otro nivel: se queda la primera.
        ['nombre' => 'Planificación y Gestión', 'nivel' => 'Bajo', 'numero' => 12, 'comportamiento' => ''],
        // Una conductual entre las técnicas.
        ['nombre' => 'Iniciativa', 'nivel' => 'Medio', 'numero' => 20, 'comportamiento' => ''],
        // El comportamiento en la celda del nombre.
        ['nombre' => competenciaDelDiccionario('Monitoreo y Control')->comportamiento_medio, 'nivel' => 'Medio', 'numero' => 17, 'comportamiento' => ''],
        // Basura de la tabla.
        ['nombre' => 'LOSEP, COOTAD.', 'nivel' => '', 'numero' => null, 'comportamiento' => ''],
    ], []);

    expect(collect($resultado['competencias'])->pluck('nombre', 'coincidencia')->all())->toBe([
        'exacta'         => 'Iniciativa',
        'comportamiento' => 'Monitoreo y Control',
    ])
        ->and($resultado['competencias'][0]['nivel'])->toBe('alto')
        ->and($resultado['competencias'][0]['competencia_id'])->toBe($planificacion->id)
        ->and($resultado['avisos'])->toBe([
            '«Planificación y Gestión» no trae nivel: se tomó del número 10 (Alto).',
            '«Planificación y Gestión» está dos veces, en nivel Alto y Bajo: se dejó la primera.',
            '«Iniciativa» está entre las competencias técnicas, pero el diccionario la tiene como conductual.',
            '«' . Str::limit(competenciaDelDiccionario('Monitoreo y Control')->comportamiento_medio, 80, '…')
                . '» no es el nombre de una competencia: por el comportamiento observable es «Monitoreo y Control», nivel medio.',
            'No se reconoció la competencia «LOSEP, COOTAD.»: se descartó.',
        ])
        ->and($resultado['conteo']['repetidas'])->toBe(1)
        ->and($resultado['conteo']['sin_reconocer'])->toBe(1);
});

test('el comando carga la ficha en los términos del perfil, sin tocar ningún puesto', function () {
    $puestos = Puesto::count();
    $ruta    = jsonDelManualDePrueba([fichaDelManualDePrueba()]);

    $this->artisan('sgth:manual:importar', ['archivo' => $ruta])
        ->expectsOutputToContain('Ningún puesto cambió.')
        ->assertSuccessful();

    $ficha = FichaManualPuestos::sole();
    expect($ficha->only(['denominacion', 'unidad', 'grupo_ocupacional', 'grado', 'plazas']))->toBe([
        'denominacion'      => 'Analista',
        'unidad'            => 'Gestión de Talento Humano / Seguridad y Salud Ocupacional',
        'grupo_ocupacional' => 'Servidor Público 5',
        'grado'             => 11,
        'plazas'            => 2,
    ])
        ->and($ficha->ficha['denominacion'])->toBe('Analista')
        ->and($ficha->avisos)->toBe([])
        ->and($ficha->propuesta)->toMatchArray([
            'mision'                    => 'Ejecutar las actividades de seguridad y salud ocupacional.',
            'nivel'                     => 'profesional',
            'especificidad_experiencia' => 'Prevención de riesgos laborales.',
            // La unidad es «Gestión / Subproceso»: el subproceso, al perfil (D2).
            'subproceso' => 'Seguridad y Salud Ocupacional',
            'requisitos' => [
                ['nivel_instruccion' => 'tecnologico_superior', 'detalle' => null, 'meses_experiencia' => 48, 'texto' => 'Tecnólogo Superior', 'experiencia' => '4 años'],
                ['nivel_instruccion' => 'tercer_nivel', 'detalle' => null, 'meses_experiencia' => 30, 'texto' => 'Tercer Nivel (Título Profesional)', 'experiencia' => '2 años 6 meses'],
            ],
            'actividades' => [
                ['descripcion' => 'Elabora el plan de prevención de riesgos.', 'conocimientos' => 'Normativa SSO'],
                ['descripcion' => 'Inspecciona los puestos de trabajo.', 'conocimientos' => null],
            ],
        ])
        ->and(collect($ficha->propuesta['competencias'])->pluck('nombre')->all())->toBe(['Monitoreo y Control', 'Trabajo en Equipo'])
        ->and(Puesto::count())->toBe($puestos);
});

test('volver a importar actualiza la ficha por su archivo', function () {
    $this->artisan('sgth:manual:importar', ['archivo' => jsonDelManualDePrueba([fichaDelManualDePrueba()])])->assertSuccessful();

    $cambiada = fichaDelManualDePrueba(['mision' => 'Otra misión.', 'conductuales' => []]);
    $this->artisan('sgth:manual:importar', ['archivo' => jsonDelManualDePrueba([$cambiada])])->assertSuccessful();

    $ficha = FichaManualPuestos::sole();
    expect($ficha->propuesta['mision'])->toBe('Otra misión.')
        ->and($ficha->avisos)->toBe(['La ficha no trae competencias conductuales.']);
});

test('la simulación no guarda nada, y un archivo que no es una lista se rechaza', function () {
    $this->artisan('sgth:manual:importar', ['archivo' => jsonDelManualDePrueba([fichaDelManualDePrueba()]), '--simular' => true])
        ->expectsOutputToContain('Simulación: no se guardó nada.')
        ->assertSuccessful();
    expect(FichaManualPuestos::count())->toBe(0);

    $this->artisan('sgth:manual:importar', ['archivo' => jsonDelManualDePrueba(['archivo' => 'x'])])
        ->expectsOutputToContain('no es una lista de fichas')
        ->assertFailed();
    $this->artisan('sgth:manual:importar', ['archivo' => jsonDelManualDePrueba([['mision' => 'Sin archivo']])])
        ->expectsOutputToContain('necesita su «archivo»')
        ->assertFailed();
});

test('el manual versionado se carga entero y casi todas sus competencias se emparejan', function () {
    $fichas  = json_decode(file_get_contents(database_path(ImportacionDelManualService::ARCHIVO)), true);
    $resumen = app(ImportacionDelManualService::class)->importar($fichas);

    $emparejadas = $resumen['exactas'] + $resumen['aproximadas'];
    expect($resumen['fichas'])->toBe(179)
        ->and(FichaManualPuestos::count())->toBe(179)
        // Las que no: repetidas en la misma ficha, que se descartan.
        ->and($emparejadas + $resumen['repetidas'] + $resumen['sin_reconocer'])->toBe($resumen['competencias'])
        ->and($emparejadas / $resumen['competencias'])->toBeGreaterThan(0.94)
        ->and($resumen['sin_reconocer'])->toBeLessThanOrEqual(2);
});
