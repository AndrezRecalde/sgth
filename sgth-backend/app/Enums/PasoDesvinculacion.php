<?php

namespace App\Enums;

/**
 * Los pasos de la desvinculación (diseño, 9.9; fase 7.4a). Los que otro módulo
 * ya registra se leen de él; los demás los decide Talento Humano. La
 * prohibición de reingreso llega con la indemnización (7.4b).
 */
enum PasoDesvinculacion: string
{
    case VACACIONES   = 'vacaciones';
    case INDEMNIZACION = 'indemnizacion';
    case BIENES       = 'bienes';
    case USUARIO      = 'usuario';
    case CERTIFICADO  = 'certificado';
    case DECLARACION  = 'declaracion';

    public function etiqueta(): string
    {
        return match ($this) {
            self::VACACIONES    => 'Liquidación de vacaciones no gozadas y de haberes',
            self::INDEMNIZACION => 'Indemnización o compensación',
            self::BIENES        => 'Devolución de los bienes',
            self::USUARIO       => 'Baja del usuario',
            self::CERTIFICADO   => 'Certificado laboral',
            self::DECLARACION   => 'Declaración patrimonial de fin de gestión',
        };
    }

    /** Quién lo hace, para la pantalla. */
    public function responsable(): string
    {
        return match ($this) {
            self::VACACIONES, self::INDEMNIZACION => 'Director de Talento Humano y Financiero',
            self::BIENES, self::USUARIO           => 'Tecnologías de la Información',
            self::CERTIFICADO                     => 'Talento Humano',
            self::DECLARACION                     => 'El servidor, ante la Contraloría; Talento Humano la registra',
        };
    }

    /**
     * ¿Lo decide Talento Humano? Los demás se leen del dato: cuando el bien se
     * devuelve, el usuario se da de baja, el certificado se emite o la
     * declaración se registra, el paso queda hecho solo.
     */
    public function seMarca(): bool
    {
        return in_array($this, [self::VACACIONES, self::INDEMNIZACION], true);
    }

    /**
     * ¿Lo marca el Director? La liquidación y la indemnización son dinero que
     * va a Financiero, como registrar una acción [TH 22].
     */
    public function loMarcaElDirector(): bool
    {
        return $this->seMarca();
    }

    /** Lo que no le toca al contrato civil: no es servidor (TH N6). */
    public function noAplicaAServiciosProfesionales(): bool
    {
        return in_array($this, [self::VACACIONES, self::INDEMNIZACION, self::DECLARACION], true);
    }
}
