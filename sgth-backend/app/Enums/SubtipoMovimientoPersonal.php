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
    // Las sanciones de LOSEP Art. 43 que llevan acción (diseño, 4.2; fase 2.5;
    // TH N13). La amonestación verbal no la lleva, y la destitución es una
    // cesación.
    case AMONESTACION_ESCRITA        = 'amonestacion_escrita';
    case SANCION_PECUNIARIA          = 'sancion_pecuniaria';
    case SUSPENSION_SIN_REMUNERACION = 'suspension_sin_remuneracion';
    // La de antes de la fase 2.5, que no decía cuál: queda para el histórico.
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
    // Las de la tabla 4.3 del diseño de Acciones de Personal (fase 2.1), cada
    // una con su base legal: LOSEP 47 y Reglamento 105 y 146.
    case REMOCION                   = 'remocion';
    case PERIODO_PRUEBA_NO_SUPERADO = 'periodo_prueba_no_superado';
    case FIN_DEL_PLAZO              = 'fin_del_plazo';
    case TERMINACION_UNILATERAL     = 'terminacion_unilateral';
    case MUTUO_ACUERDO              = 'mutuo_acuerdo';
    case EVALUACION_INSUFICIENTE    = 'evaluacion_insuficiente';
    case RETIRO_VOLUNTARIO          = 'retiro_voluntario';
    case PERDIDA_DERECHOS           = 'perdida_derechos_ciudadania';
    case FALLECIMIENTO              = 'fallecimiento';
    // Las de las autoridades electas (diseño, 9.14; fase 4.1). El fin del
    // período lo registra el sistema en su fecha [TH 1].
    case FIN_DEL_PERIODO            = 'fin_del_periodo';
    case REVOCATORIA_DEL_MANDATO    = 'revocatoria_del_mandato';
    case REMOCION_POR_EL_CONSEJO    = 'remocion_por_el_consejo';
    // Las del contrato civil de Servicios Profesionales (diseño, 9.10; fase
    // 4.2), con base en el contrato y el Código Civil, no en la LOSEP. El
    // vencimiento del plazo es «Contrato finalizado».
    case TERMINACION_MUTUO_ACUERDO  = 'terminacion_mutuo_acuerdo';
    case TERMINACION_ANTICIPADA     = 'terminacion_anticipada';

    // ── Licencia sin remuneración ────────────────────────────
    // Las causales de LOSEP Art. 28 (diseño, 4.4; fase 2.2), con su tope. Solo
    // para permanentes [TH N9].
    case ASUNTOS_PARTICULARES = 'asuntos_particulares';
    case ESTUDIOS_POSGRADO    = 'estudios_posgrado';
    case SERVICIO_MILITAR     = 'servicio_militar';
    case REEMPLAZO_DIGNATARIO = 'reemplazo_dignatario';
    case CANDIDATURA          = 'candidatura';
    case CUIDADO_HIJOS        = 'cuidado_hijos';
    // Transitoria: los obreros tienen su licencia en su propio régimen (Código
    // del Trabajo), y su bloque todavía no existe. Hasta que llegue conservan
    // la que tenían, sin los topes de la LOSEP (decisión del 2026-10-10). Las
    // autoridades electas la tuvieron hasta la fase 4.1b: ahora tienen la
    // licencia del Consejo, y las suyas de antes quedan en el histórico.
    case SEGUN_SU_REGIMEN     = 'segun_su_regimen';

    public function etiqueta(): string
    {
        return match ($this) {
            self::TRASLADO_ADMINISTRATIVO   => 'Traslado Administrativo',
            self::TRASPASO                  => 'Traspaso',
            self::COMISION_CON_REMUNERACION => 'Comisión de Servicios con Remuneración',
            self::COMISION_SIN_REMUNERACION => 'Comisión de Servicios sin Remuneración',
            self::AMONESTACION_ESCRITA        => 'Amonestación escrita',
            self::SANCION_PECUNIARIA          => 'Sanción pecuniaria administrativa',
            self::SUSPENSION_SIN_REMUNERACION => 'Suspensión temporal sin goce de remuneración',
            self::SANCION_DISCIPLINARIA     => 'Sanción Disciplinaria',
            self::RENUNCIA                  => 'Renuncia',
            self::DESTITUCION               => 'Destitución',
            self::JUBILACION                => 'Jubilación',
            self::INCAPACIDAD               => 'Incapacidad absoluta y permanente',
            self::CONTRATO_FINALIZADO       => 'Contrato Finalizado',
            self::VISTO_BUENO               => 'Visto Bueno',
            self::REMOCION                   => 'Remoción',
            self::PERIODO_PRUEBA_NO_SUPERADO => 'No superar el período de prueba',
            self::FIN_DEL_PLAZO              => 'Terminación por cumplimiento del plazo',
            self::TERMINACION_UNILATERAL     => 'Terminación unilateral',
            self::MUTUO_ACUERDO              => 'Mutuo acuerdo',
            self::EVALUACION_INSUFICIENTE    => 'Evaluación regular o insuficiente',
            self::RETIRO_VOLUNTARIO          => 'Retiro voluntario o compra de renuncia',
            self::PERDIDA_DERECHOS           => 'Pérdida de los derechos de ciudadanía',
            self::FALLECIMIENTO              => 'Fallecimiento',
            self::FIN_DEL_PERIODO            => 'Fin del período',
            self::REVOCATORIA_DEL_MANDATO    => 'Revocatoria del mandato',
            self::REMOCION_POR_EL_CONSEJO    => 'Remoción por el Consejo Provincial',
            self::TERMINACION_MUTUO_ACUERDO  => 'Terminación por mutuo acuerdo',
            self::TERMINACION_ANTICIPADA     => 'Terminación anticipada según el contrato',
            self::ASUNTOS_PARTICULARES       => 'Asuntos particulares',
            self::ESTUDIOS_POSGRADO          => 'Estudios de posgrado',
            self::SERVICIO_MILITAR           => 'Servicio militar',
            self::REEMPLAZO_DIGNATARIO       => 'Reemplazo de un dignatario electo',
            self::CANDIDATURA                => 'Candidatura a elección popular',
            self::CUIDADO_HIJOS              => 'Cuidado de hijos',
            self::SEGUN_SU_REGIMEN           => 'Según su régimen (Código del Trabajo)',
        };
    }

    /**
     * Base legal de la causal, para el documento y la ayuda del formulario
     * (diseño, 4.3). Null en las que no son de cesación.
     */
    public function baseLegal(): ?string
    {
        return match ($this) {
            self::RENUNCIA                   => 'LOSEP Art. 47 a; Reglamento Art. 102',
            self::REMOCION                   => 'LOSEP Art. 47 e; Reglamento Art. 105',
            self::PERIODO_PRUEBA_NO_SUPERADO => 'LOSEP Art. 17 b.5',
            self::FIN_DEL_PLAZO              => 'Reglamento a la LOSEP Art. 146 a',
            self::TERMINACION_UNILATERAL     => 'Reglamento a la LOSEP Art. 146 f',
            self::MUTUO_ACUERDO              => 'Reglamento a la LOSEP Art. 146 b',
            self::EVALUACION_INSUFICIENTE    => 'Reglamento a la LOSEP Art. 146 g',
            self::DESTITUCION                => 'LOSEP Arts. 47 f y 48',
            self::JUBILACION                 => 'LOSEP Art. 47 j',
            self::RETIRO_VOLUNTARIO          => 'LOSEP Art. 47 i y k; Mandato Constituyente 2',
            self::INCAPACIDAD                => 'LOSEP Art. 47 b',
            self::PERDIDA_DERECHOS           => 'LOSEP Art. 47 d',
            self::FALLECIMIENTO              => 'LOSEP Art. 47 l',
            self::VISTO_BUENO                => 'Código del Trabajo Art. 172',
            self::REVOCATORIA_DEL_MANDATO    => 'Constitución Art. 105',
            self::REMOCION_POR_EL_CONSEJO    => 'COOTAD Arts. 333–336',
            // El contrato es ley para las partes, y solo lo invalida su
            // consentimiento mutuo o una causa legal (Código Civil Art. 1561).
            self::CONTRATO_FINALIZADO        => 'El plazo del contrato',
            self::TERMINACION_MUTUO_ACUERDO  => 'Código Civil Art. 1561',
            self::TERMINACION_ANTICIPADA     => 'La cláusula de terminación del contrato',
            self::ASUNTOS_PARTICULARES       => 'LOSEP Art. 28 a',
            self::ESTUDIOS_POSGRADO          => 'LOSEP Art. 28 b',
            self::SERVICIO_MILITAR           => 'LOSEP Art. 28 c',
            self::REEMPLAZO_DIGNATARIO       => 'LOSEP Art. 28 d',
            self::CANDIDATURA                => 'LOSEP Art. 28 e',
            self::CUIDADO_HIJOS              => 'LOSEP Art. 28 f',
            self::AMONESTACION_ESCRITA        => 'LOSEP Art. 43 b',
            self::SANCION_PECUNIARIA          => 'LOSEP Art. 43 c',
            self::SUSPENSION_SIN_REMUNERACION => 'LOSEP Art. 43 d',
            default                          => null,
        };
    }

    /**
     * ¿Se registra desde el formulario de «Nueva acción de personal»? La
     * destitución y el visto bueno no: tienen un procedimiento previo y solo
     * los crea Disciplinario (diseño, 4.3). Hasta la fase 2.1 se podían
     * registrar a mano, sin sumario ni resolución del Inspector detrás.
     */
    public function seRegistraDesdeElFormulario(): bool
    {
        // Tampoco las sanciones (fase 2.5): las impone el sumario. Ni el fin
        // del período de una autoridad, que lo registra el sistema (fase 4.1).
        return ! in_array($this, [
            self::FIN_DEL_PERIODO,
            self::DESTITUCION,
            self::VISTO_BUENO,
            self::AMONESTACION_ESCRITA,
            self::SANCION_PECUNIARIA,
            self::SUSPENSION_SIN_REMUNERACION,
            self::SANCION_DISCIPLINARIA,
        ], true);
    }

    /**
     * Las causales en que una ocasional embarazada o en lactancia está
     * protegida: la Corte Constitucional (309-16-SEP-CC) condiciona el
     * Art. 146 del Reglamento. La terminación unilateral se bloquea; el fin
     * del plazo se avisa (TH 11).
     */
    public function protegeEmbarazoYLactancia(): bool
    {
        return in_array($this, [self::TERMINACION_UNILATERAL, self::FIN_DEL_PLAZO], true);
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
     *   el vencimiento del contrato civil de un año calendario. Con él, desde
     *   la fase 4.2, sus otras dos terminaciones y el fallecimiento.
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

            // Fase 2.5. Al obrero solo le llega la multa: su amonestación va
            // sin acción hasta su bloque (diseño, 9.15), y no se le suspende.
            self::SANCION_PECUNIARIA => [...$conSancionYCesacionLosep, TipoNombramiento::CODIGO_TRABAJO],
            self::AMONESTACION_ESCRITA,
            self::SUSPENSION_SIN_REMUNERACION => $conSancionYCesacionLosep,

            self::DESTITUCION,
            self::JUBILACION,
            self::INCAPACIDAD,
            self::PERDIDA_DERECHOS => $conSancionYCesacionLosep,

            // La renuncia y el fallecimiento también cierran el período de una
            // autoridad electa (diseño, 9.14; fase 4.1). Y el fallecimiento, el
            // contrato civil (fase 4.2).
            self::RENUNCIA => [...$conSancionYCesacionLosep, TipoNombramiento::ELECCION_POPULAR],
            self::FALLECIMIENTO => [
                ...$conSancionYCesacionLosep, TipoNombramiento::ELECCION_POPULAR, TipoNombramiento::SERVICIOS_PROFESIONALES,
            ],

            // Las propias de la autoridad electa.
            self::FIN_DEL_PERIODO,
            self::REVOCATORIA_DEL_MANDATO,
            self::REMOCION_POR_EL_CONSEJO => [TipoNombramiento::ELECCION_POPULAR],

            // La remoción es del provisional cuyo supuesto terminó y del de
            // libre nombramiento (LOSEP 47 e; Reg. 105; TH 8).
            self::REMOCION => [TipoNombramiento::PROVISIONAL, TipoNombramiento::LIBRE_NOMBRAMIENTO],

            // Del provisional de prueba (b.5). Mientras el vínculo no diga el
            // supuesto del provisional (fase 3.2), vale para todo provisional.
            self::PERIODO_PRUEBA_NO_SUPERADO => [TipoNombramiento::PROVISIONAL],

            // Las del Reglamento Art. 146: solo del contrato ocasional.
            self::FIN_DEL_PLAZO,
            self::TERMINACION_UNILATERAL,
            self::MUTUO_ACUERDO,
            self::EVALUACION_INSUFICIENTE => [TipoNombramiento::SERVICIOS_OCASIONALES],

            self::RETIRO_VOLUNTARIO => [TipoNombramiento::PERMANENTE],

            self::ASUNTOS_PARTICULARES,
            self::ESTUDIOS_POSGRADO,
            self::SERVICIO_MILITAR,
            self::REEMPLAZO_DIGNATARIO,
            self::CANDIDATURA,
            self::CUIDADO_HIJOS => [TipoNombramiento::PERMANENTE],

            self::SEGUN_SU_REGIMEN => [TipoNombramiento::CODIGO_TRABAJO],

            self::CONTRATO_FINALIZADO,
            self::TERMINACION_MUTUO_ACUERDO,
            self::TERMINACION_ANTICIPADA => [TipoNombramiento::SERVICIOS_PROFESIONALES],

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
        return $this->esComisionDeServicios() || $this->esLicenciaSinRemuneracion();
    }

    /** Las causales de la licencia sin remuneración (fase 2.2). */
    public function esLicenciaSinRemuneracion(): bool
    {
        return in_array($this, TipoMovimientoPersonal::LICENCIA_SIN_REMUNERACION->subtiposPermitidos(), true);
    }

    /**
     * Subtipos que cierran el vínculo laboral vigente del servidor. Todos los
     * de Cesación de Funciones lo hacen; ninguno de los otros grupos.
     */
    public function cierraVinculo(): bool
    {
        return in_array($this, TipoMovimientoPersonal::CESACION_FUNCIONES->subtiposPermitidos(), true);
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
