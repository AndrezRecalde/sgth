<?php

namespace App\Enums\Actividades;

/** De qué tipo es una actividad laboral: sirve para filtrarlas. */
enum CategoriaActividadEnum: string
{
    case REUNION                = 'reunion';
    case VISITA_CAMPO           = 'visita_campo';
    case ELABORACION_DOCUMENTOS = 'elaboracion_documentos';
    case COORDINACION           = 'coordinacion';
    case CAPACITACION           = 'capacitacion';
    case ATENCION_CIUDADANA     = 'atencion_ciudadana';
    case OTRO                   = 'otro';

    public function etiqueta(): string
    {
        return match ($this) {
            self::REUNION                => 'Reunión',
            self::VISITA_CAMPO           => 'Visita de campo',
            self::ELABORACION_DOCUMENTOS => 'Elaboración de documentos',
            self::COORDINACION           => 'Coordinación',
            self::CAPACITACION           => 'Capacitación',
            self::ATENCION_CIUDADANA     => 'Atención ciudadana',
            self::OTRO                   => 'Otra',
        };
    }
}
