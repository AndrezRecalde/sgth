<?php

namespace App\Services\Estructura\Manual;

use App\Enums\NivelInstruccion;
use Illuminate\Support\Str;

/**
 * Lee la instrucción y la experiencia de una ficha del Manual de Puestos tal
 * como están escritas («Tecnólogo Superior», «2 años 6 meses», «3 - 4 años»)
 * y las pasa a los términos del perfil: un `NivelInstruccion` y los meses de
 * experiencia (docs/diseno-manual-de-puestos.md, P4).
 *
 * Lo que se interpreta lleva aviso, para que TH lo mire al confirmar. Lo que
 * no se entiende queda en null, también con aviso: no se adivina.
 */
final class LecturaDeRequisitos
{
    /**
     * El nivel de una alternativa de instrucción.
     *
     * @return array{nivel: ?NivelInstruccion, detalle: ?string, aviso: ?string}
     */
    public static function nivel(string $texto): array
    {
        $texto = trim($texto);
        $k     = self::clave($texto);

        // «Tercer año o sexto semestre aprobado»: estudios en curso, y lo
        // aprobado se dice con el texto. Va antes que «tercer nivel».
        if (preg_match('/\b(ano|semestre)\b/', $k)) {
            return ['nivel' => NivelInstruccion::ESTUDIOS_SUPERIORES_EN_CURSO, 'detalle' => Str::limit($texto, 200, ''), 'aviso' => null];
        }

        // «Tercer o cuarto nivel»: el mínimo es el tercero.
        $nivel = match (true) {
            str_contains($k, 'tercer')    => NivelInstruccion::TERCER_NIVEL,
            str_contains($k, 'cuarto')    => NivelInstruccion::CUARTO_NIVEL,
            str_contains($k, 'tecnolog')  => NivelInstruccion::TECNOLOGICO_SUPERIOR,
            str_contains($k, 'tecnic')    => NivelInstruccion::TECNICO_SUPERIOR,
            str_contains($k, 'bachiller') => NivelInstruccion::BACHILLER,
            default                       => null,
        };

        $aviso = match (true) {
            $nivel === null => "No se reconoció el nivel de instrucción «{$texto}».",
            $nivel === NivelInstruccion::TECNICO_SUPERIOR && ! str_contains($k, 'superior')
                => "«{$texto}» se leyó como técnico superior.",
            default => null,
        };

        return ['nivel' => $nivel, 'detalle' => null, 'aviso' => $aviso];
    }

    /**
     * Los meses de experiencia de una alternativa. Un rango se lee por su
     * mínimo, porque el requisito es un mínimo.
     *
     * @return array{meses: ?int, aviso: ?string}
     */
    public static function meses(?string $texto): array
    {
        $texto = trim((string) $texto);
        $k     = self::clave($texto);

        if ($k === '') {
            return ['meses' => null, 'aviso' => 'Una alternativa no trae tiempo de experiencia.'];
        }
        if (str_contains($k, 'no requerid') || str_contains($k, 'sin experiencia')) {
            return ['meses' => 0, 'aviso' => null];
        }
        if (str_starts_with($k, 'hasta')) {
            return ['meses' => null, 'aviso' => "«{$texto}» no dice un mínimo de experiencia."];
        }
        if (preg_match('/(\d+)\s*(?:-|\sa\s)\s*\d+\s*anos?\b/', $k, $m)) {
            return ['meses' => (int) $m[1] * 12, 'aviso' => "«{$texto}» es un rango: se tomó el mínimo, {$m[1]} años."];
        }
        // En las fichas de tres alternativas, la cabecera dice «Tiempo de
        // experiencia» y debajo solo el número: son años.
        if (preg_match('/^\d+$/', $k)) {
            return ['meses' => (int) $k * 12, 'aviso' => "«{$texto}» no dice la unidad: se leyó como años."];
        }

        $anios = preg_match('/(\d+)\s*anos?\b/', $k, $a) ? (int) $a[1] : null;
        $meses = preg_match('/(\d+)\s*mes(?:es)?\b/', $k, $m) ? (int) $m[1] : null;

        if ($anios === null && $meses === null) {
            return ['meses' => null, 'aviso' => "No se entendió el tiempo de experiencia «{$texto}»."];
        }

        return ['meses' => ($anios ?? 0) * 12 + ($meses ?? 0), 'aviso' => null];
    }

    private static function clave(string $texto): string
    {
        return trim((string) preg_replace('/\s+/', ' ', Str::lower(Str::ascii($texto))));
    }
}
