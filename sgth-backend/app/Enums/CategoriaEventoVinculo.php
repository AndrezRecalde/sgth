<?php

namespace App\Enums;

enum CategoriaEventoVinculo: string
{
    case ACCION_DE_PERSONAL       = 'accion_de_personal';
    case ADENDA_CONTRACTUAL       = 'adenda_contractual';
    case MOVIMIENTO_CODIGO_TRABAJO = 'movimiento_codigo_trabajo';

    public function etiqueta(): string
    {
        return match ($this) {
            self::ACCION_DE_PERSONAL        => 'Acción de Personal',
            self::ADENDA_CONTRACTUAL        => 'Adenda Contractual',
            self::MOVIMIENTO_CODIGO_TRABAJO => 'Movimiento Código de Trabajo',
        };
    }

    /**
     * Deriva la categoría correcta a partir del tipo de nombramiento del
     * contrato que origina el evento: los contratos civiles de servicios
     * profesionales nunca generan Acción de Personal en sentido estricto
     * (no hay carrera administrativa), y el Código de Trabajo tiene su
     * propio régimen de movimientos.
     */
    public static function paraTipoNombramiento(TipoNombramiento $tipo): self
    {
        return match ($tipo) {
            TipoNombramiento::SERVICIOS_PROFESIONALES => self::ADENDA_CONTRACTUAL,
            TipoNombramiento::CODIGO_TRABAJO           => self::MOVIMIENTO_CODIGO_TRABAJO,
            default                                     => self::ACCION_DE_PERSONAL,
        };
    }
}
