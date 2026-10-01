<?php

namespace App\Enums;

/**
 * Estados del sumario administrativo (LOSEP). A diferencia del visto bueno, lo
 * resuelve la autoridad nominadora de la institución, y cada estado deja la
 * fecha de su hito para que se puedan vigilar los plazos procesales.
 */
enum EstadoSumario: string
{
    case ABIERTO        = 'abierto';
    case EN_INSTRUCCION = 'en_instruccion';
    case EN_PRUEBA      = 'en_prueba';
    case CON_INFORME    = 'con_informe';
    case RESUELTO       = 'resuelto';
    case APELADO        = 'apelado';
    case CERRADO        = 'cerrado';

    /**
     * Las etiquetas las espeja `ESTADO_SUMARIO_LABELS` en
     * `features/disciplinario/utils/etiquetas.ts`, y `EnumsDisciplinarioTest`
     * las fija para que cambiarlas aquí sea un acto deliberado.
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::ABIERTO        => 'Abierto',
            self::EN_INSTRUCCION => 'En instrucción',
            self::EN_PRUEBA      => 'En prueba',
            self::CON_INFORME    => 'Con informe',
            self::RESUELTO       => 'Resuelto',
            self::APELADO        => 'Apelado',
            self::CERRADO        => 'Cerrado',
        };
    }
}
