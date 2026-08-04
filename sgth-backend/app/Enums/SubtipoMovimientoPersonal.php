<?php

namespace App\Enums;

/**
 * Subtipos de acción de personal. Talento Humano opera con dos niveles: un
 * tipo "paraguas" (Cambio Administrativo, Régimen Disciplinario, Cesación de
 * Funciones) y un subtipo que es el que realmente determina la elegibilidad
 * por tipo de nombramiento y el texto del documento impreso.
 *
 * Confirmado con TH (2026-07-27). Ver TipoMovimientoPersonal::subtiposPermitidos().
 */
enum SubtipoMovimientoPersonal: string
{
    // ── Cambio Administrativo ────────────────────────────────
    case TRASLADO_ADMINISTRATIVO   = 'traslado_administrativo';
    case TRASPASO                  = 'traspaso';
    case COMISION_CON_REMUNERACION = 'comision_con_remuneracion';
    case COMISION_SIN_REMUNERACION = 'comision_sin_remuneracion';

    // ── Régimen Disciplinario ────────────────────────────────
    case SANCION_DISCIPLINARIA = 'sancion_disciplinaria';

    // ── Cesación de Funciones ────────────────────────────────
    case RENUNCIA            = 'renuncia';
    case DESTITUCION         = 'destitucion';
    case JUBILACION          = 'jubilacion';
    case INCAPACIDAD         = 'incapacidad';
    case CONTRATO_FINALIZADO = 'contrato_finalizado';
    // Terminación con justa causa de un obrero, resuelta por el Inspector del
    // Trabajo (Art. 172 CT). Es el equivalente de la destitución para el
    // régimen de Código del Trabajo — ver VistoBuenoService.
    case VISTO_BUENO         = 'visto_bueno';

    public function etiqueta(): string
    {
        return match ($this) {
            self::TRASLADO_ADMINISTRATIVO   => 'Traslado Administrativo',
            self::TRASPASO                  => 'Traspaso',
            self::COMISION_CON_REMUNERACION => 'Comisión de Servicios con Remuneración',
            self::COMISION_SIN_REMUNERACION => 'Comisión de Servicios sin Remuneración',
            self::SANCION_DISCIPLINARIA     => 'Sanción Disciplinaria',
            self::RENUNCIA                  => 'Renuncia',
            self::DESTITUCION               => 'Destitución',
            self::JUBILACION                => 'Jubilación',
            self::INCAPACIDAD               => 'Incapacidad',
            self::CONTRATO_FINALIZADO       => 'Contrato Finalizado',
            self::VISTO_BUENO               => 'Visto Bueno',
        };
    }

    /**
     * Elegibilidad por tipo de nombramiento vigente del servidor, según las
     * reglas que dictó Talento Humano:
     * - Cambio administrativo (las cuatro variantes): solo Permanente.
     * - Sanción disciplinaria: Permanente, Provisional y Ocasional.
     * - Cesación por renuncia/destitución/jubilación/incapacidad: Permanente,
     *   Provisional y Ocasional.
     * - Contrato finalizado: exclusivo de Servicios Profesionales, porque es
     *   el vencimiento del contrato civil de un año calendario.
     */
    public function elegiblePara(TipoNombramiento $nombramiento): bool
    {
        return in_array($nombramiento, $this->nombramientosElegibles(), true);
    }

    /** @return list<TipoNombramiento> */
    public function nombramientosElegibles(): array
    {
        $carrera = [
            TipoNombramiento::PERMANENTE,
            TipoNombramiento::PROVISIONAL,
            TipoNombramiento::SERVICIOS_OCASIONALES,
        ];

        return match ($this) {
            self::TRASLADO_ADMINISTRATIVO,
            self::TRASPASO,
            self::COMISION_CON_REMUNERACION,
            self::COMISION_SIN_REMUNERACION => [TipoNombramiento::PERMANENTE],

            self::SANCION_DISCIPLINARIA,
            self::RENUNCIA,
            self::DESTITUCION,
            self::JUBILACION,
            self::INCAPACIDAD => $carrera,

            self::CONTRATO_FINALIZADO => [TipoNombramiento::SERVICIOS_PROFESIONALES],

            // Exclusivo de obreros: es el procedimiento del Código del
            // Trabajo, no de la LOSEP.
            self::VISTO_BUENO => [TipoNombramiento::CODIGO_TRABAJO],
        };
    }

    /**
     * Las dos comisiones de servicios comparten las mismas restricciones de
     * antigüedad (≥ 2 años) y duración (1 a 6 años) — confirmado con TH: la
     * variante con remuneración no es más laxa que la de sin remuneración.
     */
    public function esComisionDeServicios(): bool
    {
        return in_array($this, [
            self::COMISION_CON_REMUNERACION,
            self::COMISION_SIN_REMUNERACION,
        ], true);
    }

    /**
     * Subtipos que reubican al servidor de forma permanente: cambian el puesto
     * y la unidad del vínculo vigente, sin crear uno nuevo — no hay contrato
     * ni nombramiento nuevo, así que el número y la resolución originales se
     * conservan.
     *
     * Las comisiones de servicios quedan fuera a propósito: son ausencias
     * temporales, el servidor conserva su puesto y vuelve a él al terminar.
     */
    public function modificaPuesto(): bool
    {
        return in_array($this, [
            self::TRASLADO_ADMINISTRATIVO,
            self::TRASPASO,
        ], true);
    }

    /**
     * Subtipos que apartan temporalmente al servidor de su puesto sin tocar el
     * vínculo: la plaza sigue ocupada por él y regresa al vencer el período.
     * Alimentan el listado de ausencias que usa Talento Humano para cubrir el
     * hueco con personal temporal.
     */
    public function esAusenciaTemporal(): bool
    {
        return $this->esComisionDeServicios();
    }

    /**
     * Subtipos que cierran el vínculo laboral vigente del servidor. Todos los
     * de Cesación de Funciones lo hacen; ninguno de los otros grupos.
     */
    public function cierraVinculo(): bool
    {
        return in_array($this, [
            self::RENUNCIA,
            self::DESTITUCION,
            self::JUBILACION,
            self::INCAPACIDAD,
            self::CONTRATO_FINALIZADO,
            self::VISTO_BUENO,
        ], true);
    }

    /**
     * Valor por defecto de 'requiere_dictamen_medico'. Jubilación e
     * incapacidad lo traen marcado: ambas son determinaciones médicas. El
     * resto nace desmarcado, y en todos los casos Talento Humano puede
     * cambiarlo desde el formulario mientras la acción esté en borrador.
     */
    public function requiereDictamenMedicoPorDefecto(): bool
    {
        return in_array($this, [self::JUBILACION, self::INCAPACIDAD], true);
    }
}
