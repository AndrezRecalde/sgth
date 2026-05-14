<?php

namespace App\Enums;

enum ConceptoFactura: string
{
    case ALIMENTACION         = 'alimentacion';
    case HOSPEDAJE            = 'hospedaje';
    case TRANSPORTE_TERRESTRE = 'transporte_terrestre';
    case PASAJE_AEREO         = 'pasaje_aereo';
    case COMBUSTIBLE          = 'combustible';
    case PEAJE                = 'peaje';
    case MATERIALES           = 'materiales';
    case OTRO                 = 'otro';

    public function etiqueta(): string
    {
        return match($this) {
            self::ALIMENTACION         => 'Alimentación',
            self::HOSPEDAJE            => 'Hospedaje',
            self::TRANSPORTE_TERRESTRE => 'Transporte Terrestre',
            self::PASAJE_AEREO         => 'Pasaje Aéreo',
            self::COMBUSTIBLE          => 'Combustible',
            self::PEAJE                => 'Peaje',
            self::MATERIALES           => 'Materiales de Trabajo',
            self::OTRO                 => 'Otro',
        };
    }
}
