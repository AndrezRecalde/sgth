<?php

namespace App\Enums;

/**
 * Cómo está un servidor frente a su evaluación médica ocupacional periódica.
 *
 * `SIN_EVALUACION` es la categoría que motivó el tablero: hasta ahora el
 * sistema solo sabía listar solicitudes, así que quien nunca tuvo una era
 * invisible en las tres pantallas del módulo, y es justo quien importa.
 */
enum EstadoCoberturaCertificacion: string
{
    case AL_DIA = 'al_dia';
    case POR_VENCER = 'por_vencer';
    case VENCIDA = 'vencida';
    case SIN_EVALUACION = 'sin_evaluacion';

    public function etiqueta(): string
    {
        return match ($this) {
            self::AL_DIA => 'Al día',
            self::POR_VENCER => 'Por vencer',
            self::VENCIDA => 'Vencida',
            self::SIN_EVALUACION => 'Sin evaluación',
        };
    }

    /** Los que hay que perseguir: alimentan el aviso del tablero. */
    public static function pendientes(): array
    {
        return [self::VENCIDA, self::SIN_EVALUACION];
    }
}
