<?php

namespace App\Enums;

enum CondicionPiezaDental: string
{
    case SANO = 'sano';
    case CARIADO = 'cariado';
    case OBTURADO = 'obturado';
    case AUSENTE = 'ausente';
    case CORONA = 'corona';
    case PROTESIS = 'protesis';
    case SELLANTE = 'sellante';
    case FRACTURADA = 'fracturada';
    case EN_TRATAMIENTO = 'en_tratamiento';
    case A_EXTRAER = 'a_extraer';
    case ENDODONCIA = 'endodoncia';

    public function etiqueta(): string
    {
        return match ($this) {
            self::SANO => 'Sano',
            self::CARIADO => 'Cariado',
            self::OBTURADO => 'Obturado',
            self::AUSENTE => 'Ausente',
            self::CORONA => 'Corona',
            self::PROTESIS => 'Prótesis',
            self::SELLANTE => 'Sellante',
            self::FRACTURADA => 'Fracturada',
            self::EN_TRATAMIENTO => 'En tratamiento',
            self::A_EXTRAER => 'A extraer',
            self::ENDODONCIA => 'Endodoncia',
        };
    }
}
