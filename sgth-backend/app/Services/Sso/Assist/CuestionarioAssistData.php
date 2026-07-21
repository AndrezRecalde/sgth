<?php

namespace App\Services\Sso\Assist;

use App\Enums\SustanciaAssist;

/**
 * Cuestionario ASSIST v3.1 (Alcohol, Smoking and Substance Involvement Screening Test),
 * OMS/OPS — "La prueba de detección de consumo de alcohol, tabaco y sustancias (ASSIST):
 * Manual para uso en la atención primaria" (2011). 8 preguntas, 10 categorías de sustancias
 * (Apéndice A). Las puntuaciones y puntos de corte son verbatim del manual (Cap. 12-14).
 */
final class CuestionarioAssistData
{
    /** @return array<int, array{codigo: string, texto: string, tipo: string, aplicaTabaco: bool}> */
    public static function preguntas(): array
    {
        return [
            1 => [
                'codigo' => 'p1',
                'texto' => 'A lo largo de la vida, ¿cuál de las siguientes sustancias ha consumido alguna vez? (solo las que consumió sin receta médica)',
                'tipo' => 'si_no',
                'aplicaTabaco' => true,
            ],
            2 => [
                'codigo' => 'p2',
                'texto' => 'En los últimos tres meses, ¿con qué frecuencia ha consumido las sustancias que mencionó?',
                'tipo' => 'frecuencia_3m',
                'aplicaTabaco' => true,
            ],
            3 => [
                'codigo' => 'p3',
                'texto' => 'En los últimos tres meses, ¿con qué frecuencia ha sentido un fuerte deseo o ansias de consumir?',
                'tipo' => 'frecuencia_3m',
                'aplicaTabaco' => true,
            ],
            4 => [
                'codigo' => 'p4',
                'texto' => 'En los últimos tres meses, ¿con qué frecuencia el consumo le ha causado problemas de salud, sociales, legales o económicos?',
                'tipo' => 'frecuencia_3m',
                'aplicaTabaco' => true,
            ],
            5 => [
                'codigo' => 'p5',
                'texto' => 'En los últimos tres meses, ¿con qué frecuencia dejó de hacer lo que habitualmente se esperaba de usted por el consumo?',
                'tipo' => 'frecuencia_3m',
                'aplicaTabaco' => false, // el manual excluye tabaco de esta pregunta (p.28)
            ],
            6 => [
                'codigo' => 'p6',
                'texto' => '¿Un amigo, un familiar o alguien más ha mostrado alguna vez preocupación por sus hábitos de consumo?',
                'tipo' => 'frecuencia_vida',
                'aplicaTabaco' => true,
            ],
            7 => [
                'codigo' => 'p7',
                'texto' => '¿Ha intentado alguna vez reducir o eliminar el consumo y no lo ha logrado?',
                'tipo' => 'frecuencia_vida',
                'aplicaTabaco' => true,
            ],
        ];
    }

    /** Pregunta 8: global, se pregunta una sola vez (no por sustancia) — manual Apéndice A. */
    public static function preguntaInyectable(): array
    {
        return [
            'codigo' => 'p8',
            'texto' => '¿Alguna vez ha consumido alguna droga por vía inyectada? (solo las que consumió sin receta médica)',
        ];
    }

    /** @return array<string, array{codigo: string, etiqueta: string, ejemplos: string}> */
    public static function sustancias(): array
    {
        $codigos = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j'];
        $resultado = [];
        foreach (SustanciaAssist::cases() as $i => $sustancia) {
            $resultado[$sustancia->value] = [
                'codigo' => $codigos[$i],
                'etiqueta' => $sustancia->etiqueta(),
                'ejemplos' => $sustancia->ejemplos(),
                'incluye_pregunta_5' => $sustancia->incluyePregunta5(),
            ];
        }
        return $resultado;
    }

    /** Opciones de respuesta para P2-P5 (últimos tres meses) — Recuadro 4. */
    public static function opcionesFrecuencia3m(): array
    {
        return [
            'nunca' => 'Nunca',
            'una_o_dos_veces' => 'Una o dos veces',
            'mensualmente' => 'Mensualmente',
            'semanalmente' => 'Semanalmente',
            'diariamente' => 'Diariamente o casi diariamente',
        ];
    }

    /** Opciones de respuesta para P6-P8 (a lo largo de la vida) — Recuadro 4. */
    public static function opcionesFrecuenciaVida(): array
    {
        return [
            'no_nunca' => 'No, nunca',
            'si_no_ultimos_3m' => 'Sí, pero no en los últimos tres meses',
            'si_ultimos_3m' => 'Sí, en los últimos tres meses',
        ];
    }

    /**
     * Puntuación numérica por pregunta y respuesta (Apéndice A del manual).
     * P2/P3/P4/P5 usan escalas distintas; P6/P7 comparten la misma escala.
     *
     * @return array<string, array<string, int>>
     */
    public static function puntuaciones(): array
    {
        return [
            'p2' => ['nunca' => 0, 'una_o_dos_veces' => 2, 'mensualmente' => 3, 'semanalmente' => 4, 'diariamente' => 6],
            'p3' => ['nunca' => 0, 'una_o_dos_veces' => 3, 'mensualmente' => 4, 'semanalmente' => 5, 'diariamente' => 6],
            'p4' => ['nunca' => 0, 'una_o_dos_veces' => 4, 'mensualmente' => 5, 'semanalmente' => 6, 'diariamente' => 7],
            'p5' => ['nunca' => 0, 'una_o_dos_veces' => 5, 'mensualmente' => 6, 'semanalmente' => 7, 'diariamente' => 8],
            'p6' => ['no_nunca' => 0, 'si_no_ultimos_3m' => 3, 'si_ultimos_3m' => 6],
            'p7' => ['no_nunca' => 0, 'si_no_ultimos_3m' => 3, 'si_ultimos_3m' => 6],
        ];
    }

    /**
     * Puntos de corte de riesgo por sustancia (Cap. 14, Recuadro 6): el alcohol tiene una
     * escala distinta al resto de sustancias.
     *
     * @return array{bajo: array{int,int}, moderado: array{int,int}, alto: array{int,int}}
     */
    public static function puntosCorte(SustanciaAssist $sustancia): array
    {
        if ($sustancia === SustanciaAssist::ALCOHOL) {
            return ['bajo' => [0, 10], 'moderado' => [11, 26], 'alto' => [27, PHP_INT_MAX]];
        }

        return ['bajo' => [0, 3], 'moderado' => [4, 26], 'alto' => [27, PHP_INT_MAX]];
    }
}
