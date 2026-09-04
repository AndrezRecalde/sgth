<?php

namespace App\Services\Dispensario;

use App\Enums\NivelAlertaTriaje;

/**
 * Compara los signos vitales de un triaje con los rangos esperables y dice
 * cuáles se salen y cuánto.
 *
 * IMPORTANTE — los umbrales de abajo son de uso general en adultos y sirven
 * para llamar la atención, no para diagnosticar. Antes de poner esto en
 * producción conviene que el personal médico del dispensario los revise y los
 * ajuste a lo que use el MSP: son una tabla, cambiarlos es editar una constante.
 *
 * Solo se valoran adultos. En un menor, una frecuencia cardiaca de 120 es
 * normal y con la tabla de adulto saltaría como crítica; el ruido acabaría
 * haciendo que se ignore la alerta, así que se prefiere no emitir juicio y
 * dejar la valoración a criterio de quien atiende.
 */
final class ValoracionSignosVitales
{
    /** Desde esta edad se aplican los rangos de adulto. */
    public const EDAD_ADULTO = 15;

    /**
     * [crítico bajo, atención bajo, atención alto, crítico alto].
     *
     * Un valor por debajo del primero o por encima del último es crítico;
     * entre el primero y el segundo, o entre el tercero y el cuarto, requiere
     * atención; en medio, normal.
     */
    private const RANGOS = [
        'presion_sistolica'       => [90, 100, 139, 180],
        'presion_diastolica'      => [60, 65,   89, 110],
        'frecuencia_cardiaca'     => [50, 60,   99, 120],
        'frecuencia_respiratoria' => [10, 12,   20,  30],
        'temperatura_c'           => [35, 36,  37.4, 39],
        'saturacion_oxigeno'      => [90, 94,  100, 100],
        'glucosa'                 => [54, 70,  180, 300],
    ];

    private const ETIQUETAS = [
        'presion_sistolica'       => 'Presión sistólica',
        'presion_diastolica'      => 'Presión diastólica',
        'frecuencia_cardiaca'     => 'Frecuencia cardiaca',
        'frecuencia_respiratoria' => 'Frecuencia respiratoria',
        'temperatura_c'           => 'Temperatura',
        'saturacion_oxigeno'      => 'Saturación de oxígeno',
        'glucosa'                 => 'Glucosa',
    ];

    /**
     * @param  array<string, mixed>  $constantes
     * @return array{nivel: NivelAlertaTriaje, hallazgos: list<array{constante: string, etiqueta: string, valor: float, nivel: string}>}
     */
    public static function evaluar(array $constantes, ?int $edad): array
    {
        if ($edad !== null && $edad < self::EDAD_ADULTO) {
            return ['nivel' => NivelAlertaTriaje::NO_EVALUADO, 'hallazgos' => []];
        }

        $hallazgos = [];

        foreach (self::RANGOS as $campo => [$criticoBajo, $atencionBajo, $atencionAlto, $criticoAlto]) {
            $valor = $constantes[$campo] ?? null;

            if ($valor === null || $valor === '') {
                continue;
            }

            $valor = (float) $valor;
            $nivel = null;

            if ($valor < $criticoBajo || $valor > $criticoAlto) {
                $nivel = NivelAlertaTriaje::CRITICO;
            } elseif ($valor < $atencionBajo || $valor > $atencionAlto) {
                $nivel = NivelAlertaTriaje::ATENCION;
            }

            if ($nivel !== null) {
                $hallazgos[] = [
                    'constante' => $campo,
                    'etiqueta'  => self::ETIQUETAS[$campo],
                    'valor'     => $valor,
                    'nivel'     => $nivel->value,
                ];
            }
        }

        return [
            'nivel'     => self::peorNivel($hallazgos),
            'hallazgos' => $hallazgos,
        ];
    }

    /**
     * @param  list<array{nivel: string}>  $hallazgos
     */
    private static function peorNivel(array $hallazgos): NivelAlertaTriaje
    {
        foreach ($hallazgos as $hallazgo) {
            if ($hallazgo['nivel'] === NivelAlertaTriaje::CRITICO->value) {
                return NivelAlertaTriaje::CRITICO;
            }
        }

        return $hallazgos === []
            ? NivelAlertaTriaje::NORMAL
            : NivelAlertaTriaje::ATENCION;
    }

    /** Los rangos, para que el frontend avise mientras se escribe. */
    public static function rangos(): array
    {
        return self::RANGOS;
    }
}
