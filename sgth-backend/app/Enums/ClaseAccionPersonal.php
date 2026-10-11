<?php

namespace App\Enums;

/**
 * La clase legal de una acción de personal: el acto que se registra, con el
 * nombre que le da la LOSEP (diseño de Acciones de Personal, sección 4).
 *
 * Es la identidad pública del catálogo. El frontend elige una clase —y, en la
 * cesación, una causal— y el backend la traduce al par tipo/subtipo con el que
 * todavía se guarda y opera cada acción. Esa traducción vive aquí, en
 * `tipoYSubtipo()`, y su inversa en `desde()`: son las dos únicas puertas entre
 * los dos vocabularios.
 *
 * Las reglas no se repiten. La elegibilidad por nombramiento se pregunta a
 * `TipoMovimientoPersonal` y `SubtipoMovimientoPersonal`, que siguen siendo su
 * única fuente: esta clase solo las agrupa con el nombre legal. Lo que es solo
 * de formulario —qué bloques pide, el aviso de la comisión— sí vive aquí, y
 * `CatalogoAccionesPersonalTest` comprueba que coincida con lo que el
 * tipo/subtipo traducido hace de verdad.
 *
 * Nombres que cambian respecto de los que veía Talento Humano, todos aceptados
 * el 2026-10-09 (cuestionario, preguntas N1, 9 y 15):
 *  - el «Traspaso» de los permanentes y la «Prestación de servicios» de los
 *    demás son el mismo acto, el **traslado** del Art. 35;
 *  - el «Traslado administrativo» entre instituciones es el **intercambio
 *    voluntario** del Art. 39;
 *  - el «Cambio de denominación» de los obreros es el **cambio de ocupación**
 *    del Art. 192 del Código del Trabajo.
 */
enum ClaseAccionPersonal: string
{
    case INGRESO                   = 'ingreso';
    case TRASLADO                  = 'traslado';
    case INTERCAMBIO_VOLUNTARIO    = 'intercambio_voluntario';
    case COMISION_CON_REMUNERACION = 'comision_con_remuneracion';
    case COMISION_SIN_REMUNERACION = 'comision_sin_remuneracion';
    case LICENCIA_SIN_REMUNERACION = 'licencia_sin_remuneracion';
    case SUBROGACION               = 'subrogacion';
    case ENCARGO                   = 'encargo';
    case INCREMENTO_REMUNERACION   = 'incremento_remuneracion';
    case CAMBIO_OCUPACION          = 'cambio_ocupacion';
    case SANCION                   = 'sancion';
    case CESACION                  = 'cesacion';
    case REINTEGRO                 = 'reintegro';

    public function etiqueta(): string
    {
        return match ($this) {
            self::INGRESO                   => 'Ingreso y Vinculación',
            self::TRASLADO                  => 'Traslado',
            self::INTERCAMBIO_VOLUNTARIO    => 'Intercambio Voluntario',
            self::COMISION_CON_REMUNERACION => 'Comisión de Servicios con Remuneración',
            self::COMISION_SIN_REMUNERACION => 'Comisión de Servicios sin Remuneración',
            self::LICENCIA_SIN_REMUNERACION => 'Licencia sin Remuneración',
            self::SUBROGACION               => 'Subrogación',
            self::ENCARGO                   => 'Encargo',
            self::INCREMENTO_REMUNERACION   => 'Incremento de Remuneración',
            self::CAMBIO_OCUPACION          => 'Cambio de Ocupación',
            self::SANCION                   => 'Sanción Disciplinaria',
            self::CESACION                  => 'Cesación de Funciones',
            self::REINTEGRO                 => 'Reintegro',
        };
    }

    public function familia(): FamiliaAccionPersonal
    {
        return match ($this) {
            self::INGRESO,
            self::REINTEGRO => FamiliaAccionPersonal::INGRESO,

            self::TRASLADO,
            self::INTERCAMBIO_VOLUNTARIO,
            self::COMISION_CON_REMUNERACION,
            self::COMISION_SIN_REMUNERACION => FamiliaAccionPersonal::CAMBIO_ADMINISTRATIVO,

            self::LICENCIA_SIN_REMUNERACION => FamiliaAccionPersonal::LICENCIAS,

            self::SUBROGACION,
            self::ENCARGO => FamiliaAccionPersonal::REEMPLAZO,

            self::INCREMENTO_REMUNERACION,
            self::CAMBIO_OCUPACION => FamiliaAccionPersonal::PUESTO_REMUNERACION,

            self::SANCION  => FamiliaAccionPersonal::REGIMEN_DISCIPLINARIO,
            self::CESACION => FamiliaAccionPersonal::CESACION,
        };
    }

    /**
     * ¿Se registra desde «Nueva acción de personal»?
     *
     * La subrogación y el encargo no: nacen en su propia pantalla, que crea a la
     * vez la fila de `subrogaciones` de la que depende quién firma. Por la API
     * genérica salía una acción sin esa fila, que al registrarse no activaba
     * nada (hallazgo del 2026-10-09).
     */
    public function seCreaDesdeElFormulario(): bool
    {
        // El reintegro tampoco: nace de la ausencia que cierra —el botón de
        // «Ausencias y reemplazos» o el comando diario—, porque sin ella no
        // sabe qué cerrar (fase 2.4).
        return ! in_array($this, [self::SUBROGACION, self::ENCARGO, self::REINTEGRO], true);
    }

    /**
     * El ingreso es la única clase que se registra a quien todavía no tiene
     * vínculo: es la que lo crea. Todas las demás exigen uno vigente.
     */
    public function requiereVinculo(): bool
    {
        return $this !== self::INGRESO;
    }

    /**
     * Las causales que se eligen en el formulario: los subtipos de la clase con
     * el mismo valor.
     *
     * - Cesación: los de Cesación de Funciones, menos la destitución y el visto
     *   bueno, que nacen en Disciplinario con su procedimiento detrás (diseño,
     *   4.3; fase 2.1).
     * - Licencia sin remuneración: las de LOSEP Art. 28, y la transitoria de
     *   obreros y autoridades (diseño, 4.4; fase 2.2).
     *
     * @return list<SubtipoMovimientoPersonal>
     */
    public function causales(): array
    {
        return match ($this) {
            self::CESACION => array_values(array_filter(
                TipoMovimientoPersonal::CESACION_FUNCIONES->subtiposPermitidos(),
                fn (SubtipoMovimientoPersonal $c) => $c->seRegistraDesdeElFormulario(),
            )),
            self::LICENCIA_SIN_REMUNERACION => TipoMovimientoPersonal::LICENCIA_SIN_REMUNERACION->subtiposPermitidos(),
            default        => [],
        };
    }

    public function requiereCausal(): bool
    {
        return $this->causales() !== [];
    }

    /**
     * ¿Se puede registrar a quien tiene este nombramiento vigente?
     *
     * Con causal, decide la causal. Sin ella, en una clase que la exige, basta
     * con que alguna aplique: es la pregunta que hace el selector antes de que
     * se elija una.
     *
     * Falso en el ingreso, que no depende del nombramiento sino de no tener
     * vínculo, y en las clases que no se crean desde el formulario: quién puede
     * subrogar lo decide su propio módulo.
     */
    public function elegiblePara(
        TipoNombramiento $nombramiento,
        ?SubtipoMovimientoPersonal $causal = null
    ): bool {
        if (! $this->requiereVinculo() || ! $this->seCreaDesdeElFormulario()) {
            return false;
        }

        if ($this->requiereCausal()) {
            $candidatas = $causal ? [$causal] : $this->causales();

            foreach ($candidatas as $candidata) {
                if (in_array($candidata, $this->causales(), true) && $candidata->elegiblePara($nombramiento)) {
                    return true;
                }
            }

            return false;
        }

        [$tipo, $subtipo] = $this->tipoYSubtipo($nombramiento);

        return $subtipo
            ? $subtipo->elegiblePara($nombramiento)
            : $tipo->elegiblePara($nombramiento);
    }

    /**
     * Los nombramientos que pueden recibir esta clase, en el orden del enum.
     * Vacía en el ingreso —que no depende del nombramiento sino de no tener
     * vínculo— y en las que no se crean desde el formulario.
     *
     * @return list<TipoNombramiento>
     */
    public function nombramientosElegibles(): array
    {
        return array_values(array_filter(
            TipoNombramiento::cases(),
            fn (TipoNombramiento $n) => $this->elegiblePara($n)
        ));
    }

    /**
     * Con qué tipo y subtipo se guarda una acción de esta clase.
     *
     * El traslado es el único que depende del nombramiento: Talento Humano lo
     * registraba como «Traspaso» en los permanentes y como «Prestación de
     * servicios» en los demás, y las dos filas siguen operando así —mismo
     * efecto, misma reubicación— hasta que el traslado tenga su propia regla
     * (fase 3 del diseño).
     *
     * @return array{0: TipoMovimientoPersonal, 1: ?SubtipoMovimientoPersonal}
     */
    public function tipoYSubtipo(
        ?TipoNombramiento $nombramiento = null,
        ?SubtipoMovimientoPersonal $causal = null
    ): array {
        return match ($this) {
            self::INGRESO => [TipoMovimientoPersonal::INGRESO, null],

            self::TRASLADO => $nombramiento === TipoNombramiento::PERMANENTE
                ? [TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO, SubtipoMovimientoPersonal::TRASPASO]
                : [TipoMovimientoPersonal::PRESTACION_SERVICIOS, null],

            self::INTERCAMBIO_VOLUNTARIO => [
                TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO,
                SubtipoMovimientoPersonal::TRASLADO_ADMINISTRATIVO,
            ],
            self::COMISION_CON_REMUNERACION => [
                TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO,
                SubtipoMovimientoPersonal::COMISION_CON_REMUNERACION,
            ],
            self::COMISION_SIN_REMUNERACION => [
                TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO,
                SubtipoMovimientoPersonal::COMISION_SIN_REMUNERACION,
            ],

            self::LICENCIA_SIN_REMUNERACION => [TipoMovimientoPersonal::LICENCIA_SIN_REMUNERACION, $causal],

            self::SUBROGACION,
            self::ENCARGO => [TipoMovimientoPersonal::SUBROGACION, null],

            self::INCREMENTO_REMUNERACION => [TipoMovimientoPersonal::INCREMENTO_REMUNERACION, null],
            self::CAMBIO_OCUPACION        => [TipoMovimientoPersonal::CAMBIO_DENOMINACION, null],

            self::SANCION => [
                TipoMovimientoPersonal::REGIMEN_DISCIPLINARIO,
                SubtipoMovimientoPersonal::SANCION_DISCIPLINARIA,
            ],
            self::CESACION => [TipoMovimientoPersonal::CESACION_FUNCIONES, $causal],
            self::REINTEGRO => [TipoMovimientoPersonal::REINTEGRO, null],
        };
    }

    /**
     * La clase de una acción ya guardada, a partir de su tipo y su subtipo
     * efectivo. Null solo si el tipo no tiene clase: hasta la fase 1.2 era la
     * bitácora del expediente, que desde entonces vive en `eventos_vinculo`.
     *
     * La subrogación devuelve SUBROGACION: si es un encargo solo lo sabe la fila
     * de `subrogaciones`, así que `SubrogacionService` fija la clase al crear la
     * acción y la migración la rellenó desde esa fila.
     *
     * Los tipos planos antiguos se resuelven por su subtipo equivalente, igual
     * que el resto del módulo los opera: el `traslado` plano es hoy un traslado
     * administrativo —entre instituciones— y por eso cae en el intercambio.
     */
    public static function desde(
        ?TipoMovimientoPersonal $tipo,
        ?SubtipoMovimientoPersonal $subtipo
    ): ?self {
        // Toda causal de cesación —las de la tabla 4.3 y las que vengan— es una
        // cesación: se pregunta por la regla y no por la lista, que en la fase
        // 2.1 creció de seis a quince.
        if ($subtipo?->cierraVinculo()) {
            return self::CESACION;
        }

        if ($subtipo?->esLicenciaSinRemuneracion()) {
            return self::LICENCIA_SIN_REMUNERACION;
        }

        return match ($subtipo) {
            SubtipoMovimientoPersonal::TRASPASO                  => self::TRASLADO,
            SubtipoMovimientoPersonal::TRASLADO_ADMINISTRATIVO   => self::INTERCAMBIO_VOLUNTARIO,
            SubtipoMovimientoPersonal::COMISION_CON_REMUNERACION => self::COMISION_CON_REMUNERACION,
            SubtipoMovimientoPersonal::COMISION_SIN_REMUNERACION => self::COMISION_SIN_REMUNERACION,
            SubtipoMovimientoPersonal::SANCION_DISCIPLINARIA     => self::SANCION,

            null => match ($tipo) {
                TipoMovimientoPersonal::INGRESO                   => self::INGRESO,
                TipoMovimientoPersonal::PRESTACION_SERVICIOS      => self::TRASLADO,
                TipoMovimientoPersonal::LICENCIA_SIN_REMUNERACION => self::LICENCIA_SIN_REMUNERACION,
                TipoMovimientoPersonal::INCREMENTO_REMUNERACION   => self::INCREMENTO_REMUNERACION,
                TipoMovimientoPersonal::CAMBIO_DENOMINACION       => self::CAMBIO_OCUPACION,
                TipoMovimientoPersonal::SUBROGACION               => self::SUBROGACION,
                // Una cesación o sanción sin subtipo no la crea el sistema desde
                // la taxonomía de dos niveles, pero se reconoce igual.
                TipoMovimientoPersonal::CESACION_FUNCIONES        => self::CESACION,
                TipoMovimientoPersonal::REGIMEN_DISCIPLINARIO     => self::SANCION,
                TipoMovimientoPersonal::REINTEGRO                 => self::REINTEGRO,
                default                                           => null,
            },
        };
    }

    // ── Lo que pide el formulario ───────────────────────────────

    /**
     * Situación actual frente a situación propuesta: puesto, unidad, R.M.U. y
     * partida de destino. La pide el ingreso, donde nace el vínculo, y el
     * traslado, que reubica al servidor dentro de él.
     */
    public function pideSituacionPropuesta(): bool
    {
        return in_array($this, [self::INGRESO, self::TRASLADO], true);
    }

    /**
     * Desde y hasta: las comisiones y, desde la fase 2.2, la licencia sin
     * remuneración, que siempre lleva fechas [TH 16]. Sin ellas la licencia no
     * salía en «Ausencias y reemplazos», que lista por período.
     */
    public function pidePeriodo(): bool
    {
        return $this === self::LICENCIA_SIN_REMUNERACION
            || (bool) $this->tipoYSubtipo()[1]?->esComisionDeServicios();
    }

    /**
     * La entidad del Estado a la que va el servidor: la comisión de servicios
     * es servir en otra (LOSEP 30 y 31), y el intercambio voluntario es entre
     * instituciones (LOSEP 39). Fase 2.3.
     */
    public function pideInstitucionDestino(): bool
    {
        return in_array($this, [
            self::COMISION_CON_REMUNERACION,
            self::COMISION_SIN_REMUNERACION,
            self::INTERCAMBIO_VOLUNTARIO,
        ], true);
    }

    /**
     * Si la comisión con remuneración es para estudios o eventos: al volver,
     * el servidor debe servir un tiempo igual al de la comisión (LOSEP 30).
     */
    public function admiteParaEstudiosOEventos(): bool
    {
        return $this === self::COMISION_CON_REMUNERACION;
    }

    /** Nombramiento, contrato, plazo y marcación: solo existen en el ingreso. */
    public function pideContratacion(): bool
    {
        return $this === self::INGRESO;
    }

    /**
     * Si la casilla «Requiere ficha de salud ocupacional» abre marcada. Es el
     * mismo valor que aplica el servicio cuando la petición no lo trae.
     */
    public function dictamenMedicoPorDefecto(?SubtipoMovimientoPersonal $causal = null): bool
    {
        [$tipo, $subtipo] = $this->tipoYSubtipo(null, $causal);

        return $subtipo
            ? $subtipo->requiereDictamenMedicoPorDefecto()
            : $tipo->requiereDictamenMedicoPorDefecto();
    }

    /**
     * Lo que conviene saber antes de llenar el formulario. La regla que
     * describe la valida `MovimientoPersonalService`; el texto viaja con el
     * catálogo para que la pantalla no tenga que repetirla.
     */
    public function aviso(): ?string
    {
        return match (true) {
            $this === self::LICENCIA_SIN_REMUNERACION =>
                'Siempre con fechas, y con el tope de su causal: asuntos particulares hasta '
                    .'60 días al año; estudios de posgrado con 2 años de servicio; cuidado de '
                    .'hijos hasta 12 meses, dentro de los primeros 15 meses de vida.',
            // La regla legal desde la fase 2.3 [TH N5]. La de 2 años de
            // antigüedad y 1 a 6 de duración para las dos venía de la LOIP,
            // anulada por la Corte Constitucional (52-25-IN/25).
            $this === self::COMISION_CON_REMUNERACION =>
                'Con 1 año de servicio a la fecha de inicio, y hasta 2 años. Si es para estudios '
                    .'o eventos, al volver el servidor debe servir un tiempo igual al de la comisión '
                    .'(LOSEP Art. 30).',
            $this === self::COMISION_SIN_REMUNERACION =>
                'Con 1 año de servicio a la fecha de inicio, y hasta 6 años sumados en toda la '
                    .'carrera. Nunca para un puesto del nivel jerárquico superior (LOSEP Art. 31).',
            default => null,
        };
    }
}
