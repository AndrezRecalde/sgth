<?php

namespace App\Enums;

/**
 * Las casillas del formulario «Acción de Personal» del Ministerio del Trabajo
 * (formato del 27-05-2014; diseño, 6.5; fase 6.1). El documento las imprime
 * todas, en este orden, y marca la de la acción.
 *
 * [Pendiente: Talento Humano coteja el listado con un formulario impreso.] Se
 * armó del formato de 2014 sin una copia a la vista: si una casilla cambia de
 * nombre o de orden, se corrige aquí y en ningún otro sitio.
 *
 * Varias no las usa todavía ninguna clase: las vacaciones no son acción de
 * personal [TH N11], y la supresión llega con las operaciones sobre puestos
 * (fase 7). El traspaso del puesto llegó en la 7.1a, y la revalorización, la
 * reclasificación y la ubicación, con la revisión de la clasificación (7.1b).
 */
enum CasillaAccionPersonal: string
{
    case INGRESO               = 'ingreso';
    case NOMBRAMIENTO          = 'nombramiento';
    case ASCENSO               = 'ascenso';
    case SUBROGACION           = 'subrogacion';
    case ENCARGO               = 'encargo';
    case VACACIONES            = 'vacaciones';
    case TRASLADO              = 'traslado';
    case TRASPASO              = 'traspaso';
    case CAMBIO_ADMINISTRATIVO = 'cambio_administrativo';
    case INTERCAMBIO           = 'intercambio';
    case COMISION_SERVICIOS    = 'comision_servicios';
    case LICENCIA              = 'licencia';
    case REVALORIZACION        = 'revalorizacion';
    case RECLASIFICACION       = 'reclasificacion';
    case UBICACION             = 'ubicacion';
    case REINTEGRO             = 'reintegro';
    case RESTITUCION           = 'restitucion';
    case RENUNCIA              = 'renuncia';
    case SUPRESION             = 'supresion';
    case DESTITUCION           = 'destitucion';
    case REMOCION              = 'remocion';
    case JUBILACION            = 'jubilacion';
    case OTRO                  = 'otro';

    /** Como la escribe el formulario. */
    public function etiqueta(): string
    {
        return match ($this) {
            self::INGRESO               => 'Ingreso',
            self::NOMBRAMIENTO          => 'Nombramiento',
            self::ASCENSO               => 'Ascenso',
            self::SUBROGACION           => 'Subrogación',
            self::ENCARGO               => 'Encargo',
            self::VACACIONES            => 'Vacaciones',
            self::TRASLADO              => 'Traslado',
            self::TRASPASO              => 'Traspaso',
            self::CAMBIO_ADMINISTRATIVO => 'Cambio administrativo',
            self::INTERCAMBIO           => 'Intercambio',
            self::COMISION_SERVICIOS    => 'Comisión de servicios',
            self::LICENCIA              => 'Licencia',
            self::REVALORIZACION        => 'Revalorización',
            self::RECLASIFICACION       => 'Reclasificación',
            self::UBICACION             => 'Ubicación',
            self::REINTEGRO             => 'Reintegro',
            self::RESTITUCION           => 'Restitución',
            self::RENUNCIA              => 'Renuncia',
            self::SUPRESION             => 'Supresión',
            self::DESTITUCION           => 'Destitución',
            self::REMOCION              => 'Remoción',
            self::JUBILACION            => 'Jubilación',
            self::OTRO                  => 'Otro',
        };
    }

    /**
     * La casilla de una causal de cesación: las que el formulario nombra
     * tienen la suya; las demás van en «Otro», con el nombre de la causal.
     */
    public static function deLaCesacion(?SubtipoMovimientoPersonal $causal): self
    {
        return match ($causal) {
            SubtipoMovimientoPersonal::RENUNCIA    => self::RENUNCIA,
            SubtipoMovimientoPersonal::DESTITUCION => self::DESTITUCION,
            SubtipoMovimientoPersonal::REMOCION    => self::REMOCION,
            SubtipoMovimientoPersonal::JUBILACION  => self::JUBILACION,
            default                                => self::OTRO,
        };
    }

    /** La de la revisión de la clasificación, por su causal (fase 7.1b). */
    public static function deLaRevision(?SubtipoMovimientoPersonal $causal): self
    {
        return match ($causal) {
            SubtipoMovimientoPersonal::REVALORIZACION  => self::REVALORIZACION,
            SubtipoMovimientoPersonal::RECLASIFICACION => self::RECLASIFICACION,
            SubtipoMovimientoPersonal::UBICACION       => self::UBICACION,
            default                                    => self::OTRO,
        };
    }
}
