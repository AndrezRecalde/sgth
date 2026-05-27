<?php

namespace App\Enums;

enum TipoDocumentoServidor: string
{
    case CEDULA              = 'cedula';
    case PAPELETA_VOTACION   = 'papeleta_votacion';
    case PASAPORTE           = 'pasaporte';
    case TITULO_ACADEMICO    = 'titulo_academico';
    case CERTIFICADO         = 'certificado';
    case CONTRATO            = 'contrato';
    case NOMBRAMIENTO        = 'nombramiento';
    case RESOLUCION          = 'resolucion';
    case DECLARACION         = 'declaracion';
    case OTRO                = 'otro';

    public function etiqueta(): string
    {
        return match($this) {
            self::CEDULA            => 'Cédula de identidad',
            self::PAPELETA_VOTACION => 'Papeleta de votación',
            self::PASAPORTE         => 'Pasaporte',
            self::TITULO_ACADEMICO  => 'Título académico',
            self::CERTIFICADO       => 'Certificado',
            self::CONTRATO          => 'Contrato',
            self::NOMBRAMIENTO      => 'Nombramiento',
            self::RESOLUCION        => 'Resolución',
            self::DECLARACION       => 'Declaración juramentada',
            self::OTRO              => 'Otro',
        };
    }

    public function grupo(): string
    {
        return match($this) {
            self::CEDULA,
            self::PAPELETA_VOTACION,
            self::PASAPORTE         => 'Identificación',
            self::TITULO_ACADEMICO,
            self::CERTIFICADO       => 'Académico',
            self::CONTRATO,
            self::NOMBRAMIENTO,
            self::RESOLUCION        => 'Laboral',
            self::DECLARACION       => 'Declaraciones',
            self::OTRO              => 'Otros',
        };
    }
}
