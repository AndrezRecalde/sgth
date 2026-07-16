<?php

namespace App\Enums;

enum TipoAntecedenteFemo: string
{
    case CLINICO = 'clinico';
    case QUIRURGICO = 'quirurgico';
    case FAMILIAR = 'familiar';
    case GINECOLOGICO = 'ginecologico';
    case REPRODUCTIVO_MASCULINO = 'reproductivo_masculino';
    case TRANSFUSION = 'transfusion';
    case TRATAMIENTO_HORMONAL = 'tratamiento_hormonal';
    case OTRO = 'otro';

    public function etiqueta(): string
    {
        return match ($this) {
            self::CLINICO => 'Clínico',
            self::QUIRURGICO => 'Quirúrgico',
            self::FAMILIAR => 'Familiar',
            self::GINECOLOGICO => 'Ginecológico',
            self::REPRODUCTIVO_MASCULINO => 'Reproductivo Masculino',
            self::TRANSFUSION => 'Autorización de Transfusión',
            self::TRATAMIENTO_HORMONAL => 'Tratamiento Hormonal',
            self::OTRO => 'Otro',
        };
    }
}
