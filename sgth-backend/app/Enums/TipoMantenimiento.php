<?php

namespace App\Enums;

/**
 * Qué se le hizo a un equipo (docs/diseno-subsistema-tic.md, fase 1.5).
 *
 * La repotenciación —el cambio a SSD del Excel de TIC, más memoria— mejora
 * el equipo en vez de repararlo, y conviene contarla aparte de la reparación.
 */
enum TipoMantenimiento: string
{
    case PREVENTIVO = 'preventivo';
    case CORRECTIVO = 'correctivo';
    case REPOTENCIACION = 'repotenciacion';

    public function etiqueta(): string
    {
        return match ($this) {
            self::PREVENTIVO     => 'Preventivo',
            self::CORRECTIVO     => 'Correctivo',
            self::REPOTENCIACION => 'Repotenciación',
        };
    }
}
