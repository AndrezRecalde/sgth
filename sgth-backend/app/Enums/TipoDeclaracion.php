<?php

namespace App\Enums;

enum TipoDeclaracion: string
{
    case INICIO_GESTION = 'inicio_gestion';
    case PERIODICA      = 'periodica';
    case FIN_GESTION    = 'fin_gestion';

    public function etiqueta(): string
    {
        return match($this) {
            self::INICIO_GESTION => 'Inicio de Gestión',
            self::PERIODICA      => 'Periódica',
            self::FIN_GESTION    => 'Fin de Gestión',
        };
    }

    public function etiquetaContraloria(): string
    {
        return match($this) {
            self::INICIO_GESTION => 'INICIO DE GESTION',
            self::PERIODICA      => 'PERIODICA',
            self::FIN_GESTION    => 'FIN DE GESTION',
        };
    }
}