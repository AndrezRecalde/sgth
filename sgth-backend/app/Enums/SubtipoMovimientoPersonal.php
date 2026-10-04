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
     * - Sanción disciplinaria: Permanente, Provisional, Ocasional, Libre
     *   Nombramiento y, desde el 2026-10-04, obreros (solo la multa).
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
        // Quienes pueden ser sancionados y cesados por la vía de la LOSEP.
        //
        // Libre Nombramiento y Remoción entró el 2026-09-29: al preguntarle a
        // TH si era correcto que se quedara sin NINGUNA acción de personal
        // disponible —consecuencia de las reglas del 2026-09-28— respondieron
        // que «es similar a un nombramiento ocasional, es decir tener las
        // acciones que tiene este último». Se le da exactamente eso.
        $conSancionYCesacionLosep = [
            TipoNombramiento::PERMANENTE,
            TipoNombramiento::PROVISIONAL,
            TipoNombramiento::SERVICIOS_OCASIONALES,
            TipoNombramiento::LIBRE_NOMBRAMIENTO,
        ];

        return match ($this) {
            self::TRASLADO_ADMINISTRATIVO,
            self::TRASPASO,
            self::COMISION_CON_REMUNERACION,
            self::COMISION_SIN_REMUNERACION => [TipoNombramiento::PERMANENTE],

            // Los obreros entran el 2026-10-04 (TH): la multa de un obrero
            // llega a Financiero con la misma acción que la de un servidor
            // LOSEP. Solo la multa —sin suspensión—, y eso lo vigila
            // DisciplinarioService, que es quien sabe qué sanción es.
            self::SANCION_DISCIPLINARIA => [...$conSancionYCesacionLosep, TipoNombramiento::CODIGO_TRABAJO],

            self::RENUNCIA,
            self::DESTITUCION,
            self::JUBILACION,
            self::INCAPACIDAD => $conSancionYCesacionLosep,

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
     * Solo el traspaso, que es «ocupar otro puesto que ya existe en otra unidad,
     * con la misma remuneración» (TH, 2026-09-28).
     *
     * Las comisiones de servicios quedan fuera a propósito: son ausencias
     * temporales, el servidor conserva su puesto y vuelve a él al terminar.
     *
     * Y el TRASLADO ADMINISTRATIVO salió de aquí el 2026-09-28. Estaba por un
     * malentendido de la figura: se había implementado como un movimiento
     * interno, igual que el traspaso, hasta el punto de que los dos subtipos
     * hacían exactamente lo mismo y solo cambiaba la palabra impresa. TH aclaró
     * que es lo contrario de lo que parecía — un traslado es el intercambio de
     * personal ENTRE INSTITUCIONES públicas, y no cierra el vínculo al salir ni
     * lo crea al entrar—, así que no tiene puesto de destino dentro del GAD que
     * ocupar: reubicarlo movía dentro de la casa a quien se marcha de ella, y
     * exigía un `puesto_destino_id` interno que para esta figura no existe.
     *
     * Queda como acto documental: se registra, se firma y se imprime, sin tocar
     * el vínculo. La institución de destino va por ahora en la explicación del
     * acto, que es texto libre y también se imprime; TH dice que la figura se
     * usa muy rara vez, así que no se le inventó una columna sin un caso real
     * delante.
     */
    public function modificaPuesto(): bool
    {
        return $this === self::TRASPASO;
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
