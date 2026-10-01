<?php

namespace App\Enums;

/**
 * Estados del trámite de visto bueno. A diferencia del sumario administrativo,
 * quien resuelve es una autoridad externa (el Inspector del Trabajo), así que
 * el sistema no "decide" nada: registra lo que el Ministerio del Trabajo
 * resolvió, y esa resolución es la que dispara —o no— la cesación.
 */
enum EstadoVistoBueno: string
{
    case SOLICITADO       = 'solicitado';
    case NOTIFICADO       = 'notificado';
    case EN_INVESTIGACION = 'en_investigacion';
    case CONCEDIDO        = 'concedido';
    case NEGADO           = 'negado';
    case DESISTIDO        = 'desistido';
    case IMPUGNADO        = 'impugnado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::SOLICITADO       => 'Solicitado',
            self::NOTIFICADO       => 'Notificado al trabajador',
            self::EN_INVESTIGACION => 'En investigación',
            self::CONCEDIDO        => 'Concedido',
            self::NEGADO           => 'Negado',
            self::DESISTIDO        => 'Desistido',
            self::IMPUGNADO        => 'Impugnado',
        };
    }
}
