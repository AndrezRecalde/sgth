<?php

namespace App\Enums;

/**
 * Las 10 categorías de sustancias del cuestionario ASSIST v3.1 (OMS/OPS), letras a-j
 * del formulario oficial (Apéndice A / Recuadro 3 del manual).
 */
enum SustanciaAssist: string
{
    case TABACO = 'tabaco';
    case ALCOHOL = 'alcohol';
    case CANNABIS = 'cannabis';
    case COCAINA = 'cocaina';
    case ESTIMULANTES_ANFETAMINA = 'estimulantes_anfetamina';
    case INHALANTES = 'inhalantes';
    case SEDANTES = 'sedantes';
    case ALUCINOGENOS = 'alucinogenos';
    case OPIACEOS = 'opiaceos';
    case OTRAS = 'otras';

    public function etiqueta(): string
    {
        return match ($this) {
            self::TABACO => 'Tabaco',
            self::ALCOHOL => 'Bebidas alcohólicas',
            self::CANNABIS => 'Cannabis',
            self::COCAINA => 'Cocaína',
            self::ESTIMULANTES_ANFETAMINA => 'Estimulantes de tipo anfetamina',
            self::INHALANTES => 'Inhalantes',
            self::SEDANTES => 'Sedantes o pastillas para dormir',
            self::ALUCINOGENOS => 'Alucinógenos',
            self::OPIACEOS => 'Opiáceos',
            self::OTRAS => 'Otras',
        };
    }

    public function ejemplos(): string
    {
        return match ($this) {
            self::TABACO => 'cigarrillos, tabaco de mascar, puros, etc.',
            self::ALCOHOL => 'cerveza, vinos, licores, etc.',
            self::CANNABIS => 'marihuana, mota, hierba, hachís, etc.',
            self::COCAINA => 'coca, crack, etc.',
            self::ESTIMULANTES_ANFETAMINA => 'speed, anfetaminas, éxtasis, etc.',
            self::INHALANTES => 'óxido nitroso, pegamento, gasolina, solvente para pintura, etc.',
            self::SEDANTES => 'diazepam, alprazolam, flunitrazepam, midazolam, etc.',
            self::ALUCINOGENOS => 'LSD, ácidos, hongos, ketamina, etc.',
            self::OPIACEOS => 'heroína, morfina, metadona, buprenorfina, codeína, etc.',
            self::OTRAS => 'especifique',
        };
    }

    /** La pregunta 5 (incumplimiento de obligaciones) no se evalúa para tabaco (manual ASSIST, p.28). */
    public function incluyePregunta5(): bool
    {
        return $this !== self::TABACO;
    }
}
