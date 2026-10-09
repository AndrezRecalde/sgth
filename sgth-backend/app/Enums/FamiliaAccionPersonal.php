<?php

namespace App\Enums;

/**
 * Las familias en que se agrupan las clases de acción de personal en pantalla
 * (diseño de Acciones de Personal, sección 4.1). No deciden ninguna regla: solo
 * ordenan el selector de «Nueva acción de personal».
 *
 * El orden de los casos es el orden en que se muestran.
 */
enum FamiliaAccionPersonal: string
{
    case INGRESO               = 'ingreso';
    // Se mantiene el nombre del grupo que usa Talento Humano [TH N4]; la figura
    // temporal del Art. 38 de la LOSEP, que lleva el mismo nombre, no se usa.
    case CAMBIO_ADMINISTRATIVO = 'cambio_administrativo';
    case LICENCIAS             = 'licencias';
    case REEMPLAZO             = 'reemplazo';
    case PUESTO_REMUNERACION   = 'puesto_remuneracion';
    case REGIMEN_DISCIPLINARIO = 'regimen_disciplinario';
    case CESACION              = 'cesacion';

    public function etiqueta(): string
    {
        return match ($this) {
            self::INGRESO               => 'Ingreso',
            self::CAMBIO_ADMINISTRATIVO => 'Cambio Administrativo',
            self::LICENCIAS             => 'Licencias',
            self::REEMPLAZO             => 'Reemplazo de Autoridades',
            self::PUESTO_REMUNERACION   => 'Puesto y Remuneración',
            self::REGIMEN_DISCIPLINARIO => 'Régimen Disciplinario',
            self::CESACION              => 'Cesación de Funciones',
        };
    }
}
