<?php

namespace App\Enums;

/**
 * Los pasos de la desvinculación (diseño, 9.9; fases 7.4a y 7.4b). Ninguno se
 * marca como hecho a mano: los que otro módulo registra se leen de él, y la
 * liquidación y la indemnización quedan hechas cuando el Director las
 * confirma con su cálculo. Cualquiera puede no aplicar, con su motivo.
 */
enum PasoDesvinculacion: string
{
    case VACACIONES   = 'vacaciones';
    case INDEMNIZACION = 'indemnizacion';
    case BIENES       = 'bienes';
    /** La tarjeta impresa vuelve a Comunicación Social (Comunicación, 6.5; D13). */
    case CREDENCIAL   = 'credencial';
    case USUARIO      = 'usuario';
    case CERTIFICADO  = 'certificado';
    case DECLARACION  = 'declaracion';
    /** Mandato Constituyente 2, art. 8: quien cobró una indemnización (7.4b). */
    case REINGRESO    = 'reingreso';

    public function etiqueta(): string
    {
        return match ($this) {
            self::VACACIONES    => 'Liquidación de vacaciones no gozadas y de haberes',
            self::INDEMNIZACION => 'Indemnización o compensación',
            self::BIENES        => 'Devolución de los bienes',
            self::CREDENCIAL    => 'Devolución de la credencial',
            self::USUARIO       => 'Baja del usuario',
            self::CERTIFICADO   => 'Certificado laboral',
            self::DECLARACION   => 'Declaración patrimonial de fin de gestión',
            self::REINGRESO     => 'Prohibición de reingreso',
        };
    }

    /** Quién lo hace, para la pantalla. */
    public function responsable(): string
    {
        return match ($this) {
            self::VACACIONES, self::INDEMNIZACION => 'Director de Talento Humano y Financiero',
            self::BIENES, self::USUARIO           => 'Tecnologías de la Información',
            self::CREDENCIAL                      => 'Comunicación Social',
            self::CERTIFICADO                     => 'Talento Humano',
            self::DECLARACION                     => 'El servidor, ante la Contraloría; Talento Humano la registra',
            self::REINGRESO                       => 'Talento Humano',
        };
    }

    /**
     * ¿Se marca como hecho a mano? Ninguno desde la fase 7.4b: los que otro
     * módulo registra quedan hechos solos, y la liquidación y la indemnización,
     * cuando el Director las confirma con su cálculo.
     */
    public function seMarca(): bool
    {
        return false;
    }

    /**
     * ¿Lo decide el Director? Los días de vacaciones y la indemnización son lo
     * que se le informa a Financiero, como registrar una acción [TH 22]:
     * también su «no aplica».
     */
    public function loMarcaElDirector(): bool
    {
        return in_array($this, [self::VACACIONES, self::INDEMNIZACION], true);
    }

    /** Lo que no le toca al contrato civil: no es servidor (TH N6). */
    public function noAplicaAServiciosProfesionales(): bool
    {
        return in_array($this, [self::VACACIONES, self::INDEMNIZACION, self::DECLARACION, self::REINGRESO], true);
    }
}
