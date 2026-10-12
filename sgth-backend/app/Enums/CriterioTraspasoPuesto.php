<?php

namespace App\Enums;

/**
 * Por qué se traspasa un puesto a otra unidad de la misma institución: los
 * criterios del Reglamento a la LOSEP, Art. 69, numeral 1 (fase 7.1a). El
 * informe técnico de la UATH dice cuál aplica.
 */
enum CriterioTraspasoPuesto: string
{
    case REORGANIZACION_INTERNA   = 'reorganizacion_interna';
    case NUEVAS_ATRIBUCIONES      = 'nuevas_atribuciones';
    case NUEVAS_ESTRUCTURAS       = 'nuevas_estructuras';
    case REFORMA_DE_ESTRUCTURA    = 'reforma_de_estructura';
    case DESCONCENTRACION         = 'desconcentracion';
    case SIMPLIFICACION_DE_TRAMITES = 'simplificacion_de_tramites';
    case EVITAR_DUPLICACION       = 'evitar_duplicacion';
    case RACIONALIZACION          = 'racionalizacion';
    case REGLAMENTO_INTERNO       = 'reglamento_interno';

    public function etiqueta(): string
    {
        return match ($this) {
            self::REORGANIZACION_INTERNA     => 'Reorganización interna (Art. 69, 1.a)',
            self::NUEVAS_ATRIBUCIONES        => 'Nuevas atribuciones, responsabilidades y competencias (1.b)',
            self::NUEVAS_ESTRUCTURAS         => 'Nuevas estructuras o más productos institucionales (1.c)',
            self::REFORMA_DE_ESTRUCTURA      => 'Reforma total o parcial de la estructura (1.d)',
            self::DESCONCENTRACION           => 'Desconcentración de funciones y delegación de competencias (1.e)',
            self::SIMPLIFICACION_DE_TRAMITES => 'Simplificación de trámites y procedimientos (1.f)',
            self::EVITAR_DUPLICACION         => 'Evitar la duplicación de funciones (1.g)',
            self::RACIONALIZACION            => 'Racionalización del talento humano por auditoría de la UATH (1.h)',
            self::REGLAMENTO_INTERNO         => 'Otro criterio del reglamento interno de talento humano (1.i)',
        };
    }

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
