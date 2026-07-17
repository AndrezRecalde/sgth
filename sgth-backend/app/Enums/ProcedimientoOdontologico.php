<?php

namespace App\Enums;

enum ProcedimientoOdontologico: string
{
    case EXAMEN_INICIAL = 'examen_inicial';
    case PROFILAXIS = 'profilaxis';
    case APLICACION_FLUOR = 'aplicacion_fluor';
    case SELLANTE = 'sellante';
    case RESINA = 'resina';
    case AMALGAMA = 'amalgama';
    case ENDODONCIA = 'endodoncia';
    case EXTRACCION = 'extraccion';
    case CORONA = 'corona';
    case PROTESIS_PARCIAL = 'protesis_parcial';
    case PROTESIS_TOTAL = 'protesis_total';
    case CURETAJE = 'curetaje';
    case EXODONCIA_QUIRURGICA = 'exodoncia_quirurgica';
    case PULPOTOMIA = 'pulpotomia';
    case RECUBRIMIENTO_PULPAR = 'recubrimiento_pulpar';
    case FERULIZACION = 'ferulizacion';
    case BLANQUEAMIENTO = 'blanqueamiento';
    case CONTROL_ORTODONCIA = 'control_ortodoncia';
    case MUDA_NATURAL = 'muda_natural';
    case OTRO = 'otro';

    public function etiqueta(): string
    {
        return match ($this) {
            self::EXAMEN_INICIAL => 'Examen inicial',
            self::PROFILAXIS => 'Profilaxis',
            self::APLICACION_FLUOR => 'Aplicación de flúor',
            self::SELLANTE => 'Sellante',
            self::RESINA => 'Resina',
            self::AMALGAMA => 'Amalgama',
            self::ENDODONCIA => 'Endodoncia',
            self::EXTRACCION => 'Extracción',
            self::CORONA => 'Corona',
            self::PROTESIS_PARCIAL => 'Prótesis parcial',
            self::PROTESIS_TOTAL => 'Prótesis total',
            self::CURETAJE => 'Curetaje',
            self::EXODONCIA_QUIRURGICA => 'Exodoncia quirúrgica',
            self::PULPOTOMIA => 'Pulpotomía',
            self::RECUBRIMIENTO_PULPAR => 'Recubrimiento pulpar',
            self::FERULIZACION => 'Ferulización',
            self::BLANQUEAMIENTO => 'Blanqueamiento',
            self::CONTROL_ORTODONCIA => 'Control de ortodoncia',
            self::MUDA_NATURAL => 'Muda natural (diente de leche caído)',
            self::OTRO => 'Otro',
        };
    }

    /**
     * Condición que debe quedar en la pieza tras aplicar este procedimiento.
     */
    public function condicionResultante(): CondicionPiezaDental
    {
        return match ($this) {
            self::EXTRACCION, self::EXODONCIA_QUIRURGICA,
            self::MUDA_NATURAL => CondicionPiezaDental::AUSENTE,
            self::RESINA, self::AMALGAMA => CondicionPiezaDental::OBTURADO,
            self::ENDODONCIA, self::PULPOTOMIA, self::RECUBRIMIENTO_PULPAR => CondicionPiezaDental::ENDODONCIA,
            self::CORONA => CondicionPiezaDental::CORONA,
            self::PROTESIS_PARCIAL, self::PROTESIS_TOTAL => CondicionPiezaDental::PROTESIS,
            self::SELLANTE => CondicionPiezaDental::SELLANTE,
            default => CondicionPiezaDental::EN_TRATAMIENTO,
        };
    }
}
