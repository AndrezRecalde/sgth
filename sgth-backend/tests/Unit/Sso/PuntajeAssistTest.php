<?php

use App\Enums\NivelRiesgoAssist;
use App\Enums\SustanciaAssist;
use App\Services\Sso\Assist\CuestionarioAssistData;
use App\Services\Sso\AssistService;

/**
 * La puntuación del tamizaje ASSIST v3.1 (OMS/OPS), Fase 4 del programa de
 * prevención de drogas (Instructivo MDT-MSP-2019-038).
 *
 * Cada pregunta tiene su propia escala —P2 no puntúa como P3, ni P3 como P4— y
 * el alcohol tiene puntos de corte distintos al resto de sustancias. Copiar una
 * escala en el lugar de otra da un puntaje verosímil que cambia el nivel de
 * riesgo de una persona sin que nada falle.
 *
 * Sin base de datos: la puntuación es aritmética sobre las tablas del manual.
 */

function servicioAssist(): AssistService
{
    return new AssistService();
}

// ── Las tablas del manual ─────────────────────────────────────────────

test('las puntuaciones de P2 a P7 son las del manual', function () {
    $puntuaciones = CuestionarioAssistData::puntuaciones();

    expect($puntuaciones['p2'])->toBe([
        'nunca' => 0, 'una_o_dos_veces' => 2, 'mensualmente' => 3, 'semanalmente' => 4, 'diariamente' => 6,
    ]);
    expect($puntuaciones['p3'])->toBe([
        'nunca' => 0, 'una_o_dos_veces' => 3, 'mensualmente' => 4, 'semanalmente' => 5, 'diariamente' => 6,
    ]);
    expect($puntuaciones['p4'])->toBe([
        'nunca' => 0, 'una_o_dos_veces' => 4, 'mensualmente' => 5, 'semanalmente' => 6, 'diariamente' => 7,
    ]);
    expect($puntuaciones['p5'])->toBe([
        'nunca' => 0, 'una_o_dos_veces' => 5, 'mensualmente' => 6, 'semanalmente' => 7, 'diariamente' => 8,
    ]);
    // P6 y P7 comparten escala: son de «a lo largo de la vida».
    expect($puntuaciones['p6'])->toBe(['no_nunca' => 0, 'si_no_ultimos_3m' => 3, 'si_ultimos_3m' => 6]);
    expect($puntuaciones['p7'])->toBe($puntuaciones['p6']);
});

test('las 10 categorías de sustancias llevan las letras a-j', function () {
    $sustancias = CuestionarioAssistData::sustancias();

    expect($sustancias)->toHaveCount(10);
    expect(array_column($sustancias, 'codigo'))->toBe(['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j']);

    foreach ($sustancias as $clave => $sustancia) {
        expect($sustancia['etiqueta'])->not->toBeEmpty("etiqueta de {$clave}");
    }
});

test('la pregunta 5 no aplica al tabaco y sí al resto', function () {
    // El manual excluye al tabaco del incumplimiento de obligaciones (p. 28).
    expect(SustanciaAssist::TABACO->incluyePregunta5())->toBeFalse();

    foreach (SustanciaAssist::cases() as $sustancia) {
        if ($sustancia !== SustanciaAssist::TABACO) {
            expect($sustancia->incluyePregunta5())->toBeTrue($sustancia->value);
        }
    }
});

test('el alcohol tiene puntos de corte propios', function () {
    $alcohol = CuestionarioAssistData::puntosCorte(SustanciaAssist::ALCOHOL);
    $cannabis = CuestionarioAssistData::puntosCorte(SustanciaAssist::CANNABIS);

    expect($alcohol['bajo'])->toBe([0, 10]);
    expect($alcohol['moderado'][0])->toBe(11);
    expect($cannabis['bajo'])->toBe([0, 3]);
    expect($cannabis['moderado'][0])->toBe(4);

    // El corte de riesgo alto es 27 para todas.
    expect($alcohol['alto'][0])->toBe(27);
    expect($cannabis['alto'][0])->toBe(27);
});

// ── La puntuación de una sustancia ────────────────────────────────────

test('no haber consumido en tres meses ni tener antecedentes puntúa cero', function () {
    $puntaje = servicioAssist()->calcularPuntajeSustancia(SustanciaAssist::CANNABIS, [
        'p2' => 'nunca', 'p6' => 'no_nunca', 'p7' => 'no_nunca',
    ]);

    expect($puntaje)->toBe(0);
    expect(servicioAssist()->determinarNivel(SustanciaAssist::CANNABIS, 0))
        ->toBe(NivelRiesgoAssist::BAJO);
});

test('con P2 en nunca, P3 a P5 no suman aunque vengan contestadas', function () {
    // El manual salta esas preguntas cuando no hubo consumo reciente; si el
    // formulario las mandara igual, no pueden contar.
    $puntaje = servicioAssist()->calcularPuntajeSustancia(SustanciaAssist::COCAINA, [
        'p2' => 'nunca', 'p3' => 'diariamente', 'p4' => 'diariamente', 'p5' => 'diariamente',
        'p6' => 'no_nunca', 'p7' => 'no_nunca',
    ]);

    expect($puntaje)->toBe(0);
});

test('P6 y P7 suman aunque no haya consumo en los últimos tres meses', function () {
    // Son de «a lo largo de la vida»: un antecedente cuenta igual.
    $puntaje = servicioAssist()->calcularPuntajeSustancia(SustanciaAssist::ALCOHOL, [
        'p2' => 'nunca', 'p6' => 'si_no_ultimos_3m', 'p7' => 'si_ultimos_3m',
    ]);

    expect($puntaje)->toBe(9);   // 0 + 3 + 6
});

test('el puntaje de una sustancia suma P2 a P7', function () {
    $puntaje = servicioAssist()->calcularPuntajeSustancia(SustanciaAssist::CANNABIS, [
        'p2' => 'mensualmente',      // 3
        'p3' => 'una_o_dos_veces',   // 3
        'p4' => 'semanalmente',      // 6
        'p5' => 'nunca',             // 0
        'p6' => 'si_ultimos_3m',     // 6
        'p7' => 'no_nunca',          // 0
    ]);

    expect($puntaje)->toBe(18);
});

test('el tabaco no suma P5 ni cuando viene contestada', function () {
    $respuestas = [
        'p2' => 'diariamente',   // 6
        'p3' => 'diariamente',   // 6
        'p4' => 'diariamente',   // 7
        'p5' => 'diariamente',   // 8, que para tabaco no cuenta
        'p6' => 'no_nunca',
        'p7' => 'no_nunca',
    ];

    expect(servicioAssist()->calcularPuntajeSustancia(SustanciaAssist::TABACO, $respuestas))->toBe(19);
    expect(servicioAssist()->calcularPuntajeSustancia(SustanciaAssist::CANNABIS, $respuestas))->toBe(27);
});

test('el máximo de una sustancia la deja en riesgo alto', function () {
    $todoLoPeor = [
        'p2' => 'diariamente', 'p3' => 'diariamente', 'p4' => 'diariamente',
        'p5' => 'diariamente', 'p6' => 'si_ultimos_3m', 'p7' => 'si_ultimos_3m',
    ];

    $puntaje = servicioAssist()->calcularPuntajeSustancia(SustanciaAssist::OPIACEOS, $todoLoPeor);

    expect($puntaje)->toBe(39);   // 6 + 6 + 7 + 8 + 6 + 6
    expect(servicioAssist()->determinarNivel(SustanciaAssist::OPIACEOS, $puntaje))
        ->toBe(NivelRiesgoAssist::ALTO);
});

// ── Los niveles de riesgo ─────────────────────────────────────────────

dataset('niveles_por_puntaje', [
    // El mismo puntaje significa cosas distintas según la sustancia.
    'alcohol, 10 es bajo'      => [SustanciaAssist::ALCOHOL, 10, NivelRiesgoAssist::BAJO],
    'alcohol, 11 es moderado'  => [SustanciaAssist::ALCOHOL, 11, NivelRiesgoAssist::MODERADO],
    'alcohol, 26 es moderado'  => [SustanciaAssist::ALCOHOL, 26, NivelRiesgoAssist::MODERADO],
    'alcohol, 27 es alto'      => [SustanciaAssist::ALCOHOL, 27, NivelRiesgoAssist::ALTO],
    'cannabis, 3 es bajo'      => [SustanciaAssist::CANNABIS, 3, NivelRiesgoAssist::BAJO],
    'cannabis, 4 es moderado'  => [SustanciaAssist::CANNABIS, 4, NivelRiesgoAssist::MODERADO],
    'cannabis, 10 es moderado' => [SustanciaAssist::CANNABIS, 10, NivelRiesgoAssist::MODERADO],
    'tabaco, 4 es moderado'    => [SustanciaAssist::TABACO, 4, NivelRiesgoAssist::MODERADO],
]);

test('el nivel de riesgo depende de la sustancia y del puntaje', function (
    SustanciaAssist $sustancia,
    int $puntaje,
    NivelRiesgoAssist $esperado,
) {
    expect(servicioAssist()->determinarNivel($sustancia, $puntaje))->toBe($esperado);
})->with('niveles_por_puntaje');

test('un puntaje de 4 se lee distinto en alcohol que en cannabis', function () {
    // Es la diferencia que justifica la tabla aparte del alcohol.
    expect(servicioAssist()->determinarNivel(SustanciaAssist::ALCOHOL, 4))
        ->toBe(NivelRiesgoAssist::BAJO);
    expect(servicioAssist()->determinarNivel(SustanciaAssist::CANNABIS, 4))
        ->toBe(NivelRiesgoAssist::MODERADO);
});
