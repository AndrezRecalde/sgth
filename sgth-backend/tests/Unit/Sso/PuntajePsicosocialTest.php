<?php

use App\Enums\NivelRiesgoPsicosocial;
use App\Services\Sso\Psicosocial\CuestionarioPsicosocialData;
use App\Services\Sso\PsicosocialService;

/**
 * La puntuación del cuestionario de riesgo psicosocial del Ministerio del
 * Trabajo (guía de octubre de 2018).
 *
 * Son 58 ítems repartidos en 8 dimensiones y 8 subdimensiones, con tres tablas
 * de rangos copiadas a mano. El instrumento es INVERSO —a menor puntaje, mayor
 * riesgo—, así que una errata no produce un error: produce un «riesgo bajo»
 * donde había uno alto, y eso nadie lo nota leyendo el resultado.
 *
 * Sin base de datos: la puntuación es aritmética sobre las tablas.
 */

/** Todos los ítems con la misma respuesta (1 a 4). */
function respuestasUniformes(int $puntuacion): array
{
    return array_fill_keys(range(1, 58), $puntuacion);
}

function servicioPsicosocial(): PsicosocialService
{
    return new PsicosocialService();
}

// ── Las tablas ────────────────────────────────────────────────────────

test('las 8 dimensiones cubren los 58 ítems, cada uno una vez', function () {
    $items = collect(CuestionarioPsicosocialData::dimensiones())
        ->flatMap(fn(array $dimension) => $dimension['items'])
        ->sort()
        ->values()
        ->all();

    expect($items)->toBe(range(1, 58));
});

test('las subdimensiones desglosan los ítems 35 a 58, sin solaparse', function () {
    // Son un desglose de «otros puntos importantes», no ítems aparte: si se
    // solaparan o faltara uno, el desglose no sumaría lo que la dimensión dice.
    $items = collect(CuestionarioPsicosocialData::subdimensiones())
        ->flatMap(fn(array $sub) => $sub['items'])
        ->sort()
        ->values()
        ->all();

    expect($items)->toBe(range(35, 58));
});

test('cada rango de la Tabla 3 cubre la escala completa de su dimensión', function () {
    $dimensiones = CuestionarioPsicosocialData::dimensiones() + CuestionarioPsicosocialData::subdimensiones();

    foreach ($dimensiones as $clave => $dimension) {
        $items = count($dimension['items']);
        [$altoMin, $altoMax] = $dimension['rangos']['alto'];
        [$medioMin, $medioMax] = $dimension['rangos']['medio'];
        [$bajoMin, $bajoMax] = $dimension['rangos']['bajo'];

        // El mínimo posible es 1 por ítem y el máximo 4: los tres rangos, en
        // orden inverso, tienen que ir de uno a otro sin huecos ni solapes.
        expect($altoMin)->toBe($items, "mínimo del rango alto de {$clave}");
        expect($bajoMax)->toBe($items * 4, "máximo del rango bajo de {$clave}");
        expect($medioMin)->toBe($altoMax + 1, "continuidad alto→medio de {$clave}");
        expect($bajoMin)->toBe($medioMax + 1, "continuidad medio→bajo de {$clave}");
    }
});

test('el rango global de la Tabla 4 va de 58 a 232', function () {
    $global = CuestionarioPsicosocialData::rangoGlobal();

    expect($global['alto'])->toBe([58, 116]);
    expect($global['medio'])->toBe([117, 174]);
    expect($global['bajo'])->toBe([175, 232]);
});

test('las 58 preguntas tienen texto y dimensión', function () {
    $preguntas = CuestionarioPsicosocialData::preguntas();

    expect(array_keys($preguntas))->toBe(range(1, 58));

    foreach ($preguntas as $numero => $pregunta) {
        expect($pregunta['texto'])->not->toBeEmpty("texto del ítem {$numero}");
        expect($pregunta['dimension'])->not->toBeEmpty("dimensión del ítem {$numero}");
    }
});

// ── La puntuación ─────────────────────────────────────────────────────

test('todo el cuestionario en desacuerdo da el riesgo más alto', function () {
    // 58 ítems × 1 = 58, el suelo de la escala: riesgo alto.
    [$dimensiones, $global, $nivelGlobal] = servicioPsicosocial()->calcularPuntajes(respuestasUniformes(1));

    expect($global)->toBe(58);
    expect($nivelGlobal)->toBe(NivelRiesgoPsicosocial::ALTO);

    foreach ($dimensiones as $clave => $dimension) {
        expect($dimension['nivel'])->toBe('alto', "nivel de {$clave}");
    }
});

test('todo el cuestionario de acuerdo da el riesgo más bajo', function () {
    // 58 ítems × 4 = 232, el techo de la escala: riesgo bajo.
    [$dimensiones, $global, $nivelGlobal] = servicioPsicosocial()->calcularPuntajes(respuestasUniformes(4));

    expect($global)->toBe(232);
    expect($nivelGlobal)->toBe(NivelRiesgoPsicosocial::BAJO);

    foreach ($dimensiones as $clave => $dimension) {
        expect($dimension['nivel'])->toBe('bajo', "nivel de {$clave}");
    }
});

test('el puntaje global es la suma de las 8 dimensiones, sin contar dos veces el desglose', function () {
    // Si las subdimensiones se sumaran aparte, el global pasaría de 232.
    [$dimensiones, $global] = servicioPsicosocial()->calcularPuntajes(respuestasUniformes(3));

    $sumaDimensiones = collect($dimensiones)->sum('puntaje');

    expect($global)->toBe(58 * 3);
    expect($sumaDimensiones)->toBe($global);
});

test('cada dimensión suma solo sus ítems', function () {
    // Un 4 en el ítem 1 —«carga y ritmo de trabajo»— y un 1 en el resto.
    $respuestas = respuestasUniformes(1);
    $respuestas[1] = 4;

    [$dimensiones, $global] = servicioPsicosocial()->calcularPuntajes($respuestas);

    expect($dimensiones['carga_ritmo_trabajo']['puntaje'])->toBe(7);   // 4 + 1 + 1 + 1
    expect($dimensiones['desarrollo_competencias']['puntaje'])->toBe(4);
    expect($global)->toBe(61);
});

test('las subdimensiones viajan dentro de otros puntos importantes', function () {
    [$dimensiones] = servicioPsicosocial()->calcularPuntajes(respuestasUniformes(4));

    $subdimensiones = $dimensiones['otros_puntos_importantes']['subdimensiones'];

    expect(array_keys($subdimensiones))
        ->toBe(array_keys(CuestionarioPsicosocialData::subdimensiones()));

    // Acoso sexual son dos ítems (43 y 48): 2 × 4 = 8, el techo de su escala.
    expect($subdimensiones['acoso_sexual']['puntaje'])->toBe(8);
    expect($subdimensiones['acoso_sexual']['nivel'])->toBe('bajo');
});

test('el acoso sexual en su suelo se marca como riesgo alto', function () {
    // El resto de acuerdo, los dos ítems de acoso sexual en desacuerdo.
    $respuestas = respuestasUniformes(4);
    $respuestas[43] = 1;
    $respuestas[48] = 1;

    [$dimensiones, , $nivelGlobal] = servicioPsicosocial()->calcularPuntajes($respuestas);

    $acoso = $dimensiones['otros_puntos_importantes']['subdimensiones']['acoso_sexual'];

    expect($acoso['puntaje'])->toBe(2);
    expect($acoso['nivel'])->toBe('alto');
    // Dos ítems de 58 no mueven el global: por eso el desglose se informa aparte.
    expect($nivelGlobal)->toBe(NivelRiesgoPsicosocial::BAJO);
});

test('las claves del cuestionario se leen como número o como cadena', function () {
    // Las respuestas llegan de un JSON, donde las claves son cadenas.
    $comoCadenas = [];
    foreach (respuestasUniformes(2) as $item => $puntuacion) {
        $comoCadenas[(string) $item] = $puntuacion;
    }

    [, $global] = servicioPsicosocial()->calcularPuntajes($comoCadenas);

    expect($global)->toBe(58 * 2);
});
