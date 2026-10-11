<?php

namespace App\Enums;

enum TipoMovimientoPersonal: string
{
    case TRASLADO                  = 'traslado';
    case SUBROGACION               = 'subrogacion';
    case COMISION_SERVICIOS        = 'comision_servicios';
    case INGRESO                   = 'ingreso';
    // 'novedad_contrato', 'cambio_puesto', 'cambio_regimen' y 'egreso' se
    // retiraron en la fase 1.2 del rediseño (2026-10-09): eran bitácora del
    // expediente, no actos, y pasaron a `eventos_vinculo` (TipoEventoVinculo).
    // Acciones de personal formales (Sprint E-04)
    case CAMBIO_DENOMINACION       = 'cambio_denominacion';
    case PRESTACION_SERVICIOS      = 'prestacion_servicios';
    case CAMBIO_ADMINISTRATIVO     = 'cambio_administrativo';
    case COMISION_SIN_REMUNERACION = 'comision_sin_remuneracion';
    case LICENCIA_SIN_REMUNERACION = 'licencia_sin_remuneracion';
    // Sprint E-05 (fase 2 — máquina de estados)
    case INCREMENTO_REMUNERACION   = 'incremento_remuneracion';
    // Sprint E-06 (mecanismo único puesto/unidad — cerrar+crear de vínculo)
    case TRASPASO                  = 'traspaso';
    // Egreso disciplinario formal (Sumario Administrativo — ver DisciplinarioService)
    case DESTITUCION               = 'destitucion';
    // Sprint E-07 (taxonomía de dos niveles — ver SubtipoMovimientoPersonal).
    // Estos dos nacen ya con subtipo obligatorio; los tipos planos anteriores
    // que hoy son subtipos (traslado, traspaso, comision_*, destitucion) se
    // conservan como legado para no reescribir el histórico, y se normalizan
    // vía subtipoEquivalente().
    case CESACION_FUNCIONES        = 'cesacion_funciones';
    case REGIMEN_DISCIPLINARIO     = 'regimen_disciplinario';
    // Cierra una comisión o una licencia cuando el servidor vuelve (LOSEP 32;
    // fase 2.4). Va enlazado a la ausencia que cierra (movimiento_relacionado_id).
    case REINTEGRO                 = 'reintegro';
    // Vuelve al servidor a su puesto por sentencia (LOSEP 46; fase 2.6). Crea
    // el vínculo como el ingreso, enlazado a la cesación que deja sin efecto.
    case RESTITUCION               = 'restitucion';
    // Convierte el provisional de prueba en permanente al concluir los tres
    // meses, sin cerrar el vínculo (LOSEP 17 b.5; fase 3.2).
    case NOMBRAMIENTO_DEFINITIVO   = 'nombramiento_definitivo';
    // El servidor de carrera pasa al puesto que ganó en concurso, con
    // nombramiento provisional de prueba por seis meses y su puesto anterior
    // reservado (LOSEP 17 b.5 y 68; TH N2; fase 3.3). Se había retirado el
    // 2026-07-23 porque TH lo operaba como cesación más ingreso, que le quitaba
    // al servidor el derecho a volver.
    case ASCENSO                   = 'ascenso';
    // El Prefecto y el Viceprefecto empiezan el período para el que fueron
    // elegidos (diseño, 9.14; TH 1–7; fase 4.1). Crea el vínculo como el
    // ingreso, pero no es una acción de personal: se registra con la
    // credencial del CNE y no lo firma nadie.
    case INICIO_PERIODO            = 'inicio_periodo';
    // La licencia que el Consejo Provincial concede a la autoridad electa, con
    // o sin remuneración según su resolución (COOTAD Art. 47 s; fase 4.1b).
    case LICENCIA_CONSEJO          = 'licencia_consejo';
    // El Viceprefecto pasa a Prefecto hasta terminar el período, por la
    // ausencia definitiva del titular (COOTAD Art. 51; fase 4.1b).
    case ASUNCION_DEFINITIVA       = 'asuncion_definitiva';
    // Lo que cambia del contrato civil de Servicios Profesionales —la unidad y
    // el puesto, el plazo, los honorarios— lo cambia una adenda que firman las
    // partes, no una acción de personal (diseño, 9.10; fase 4.2). Reemplaza a
    // su «Prestación de servicios».
    case ADENDA                    = 'adenda';

    public function etiqueta(): string
    {
        return match ($this) {
            self::TRASLADO                  => 'Traslado',
            self::SUBROGACION               => 'Subrogación',
            self::COMISION_SERVICIOS        => 'Comisión de Servicios',
            self::INGRESO                   => 'Ingreso',
            self::CAMBIO_DENOMINACION       => 'Cambio de Denominación',
            self::PRESTACION_SERVICIOS      => 'Prestación de Servicios',
            self::CAMBIO_ADMINISTRATIVO     => 'Cambio Administrativo',
            self::COMISION_SIN_REMUNERACION => 'Comisión de Servicios sin Remuneración',
            self::LICENCIA_SIN_REMUNERACION => 'Licencia sin Remuneración',
            self::INCREMENTO_REMUNERACION   => 'Incremento de Remuneración',
            self::TRASPASO                  => 'Traspaso',
            self::DESTITUCION               => 'Destitución',
            self::CESACION_FUNCIONES        => 'Cesación de Funciones',
            self::REGIMEN_DISCIPLINARIO     => 'Régimen Disciplinario',
            self::REINTEGRO                 => 'Reintegro',
            self::RESTITUCION               => 'Restitución',
            self::NOMBRAMIENTO_DEFINITIVO   => 'Nombramiento Definitivo',
            self::ASCENSO                   => 'Ascenso',
            self::INICIO_PERIODO            => 'Inicio del Período',
            self::LICENCIA_CONSEJO          => 'Licencia del Consejo',
            self::ASUNCION_DEFINITIVA       => 'Asunción Definitiva',
            self::ADENDA                    => 'Adenda',
        };
    }

    /**
     * Subtipos válidos para este tipo. Un array vacío significa que el tipo
     * no admite subtipo (y que enviarlo es un error de validación).
     *
     * @return list<SubtipoMovimientoPersonal>
     */
    public function subtiposPermitidos(): array
    {
        return match ($this) {
            self::CAMBIO_ADMINISTRATIVO => [
                SubtipoMovimientoPersonal::TRASLADO_ADMINISTRATIVO,
                SubtipoMovimientoPersonal::TRASPASO,
                SubtipoMovimientoPersonal::COMISION_CON_REMUNERACION,
                SubtipoMovimientoPersonal::COMISION_SIN_REMUNERACION,
            ],
            self::REGIMEN_DISCIPLINARIO => [
                SubtipoMovimientoPersonal::AMONESTACION_ESCRITA,
                SubtipoMovimientoPersonal::SANCION_PECUNIARIA,
                SubtipoMovimientoPersonal::SUSPENSION_SIN_REMUNERACION,
                // La genérica de antes de la fase 2.5, para el histórico.
                SubtipoMovimientoPersonal::SANCION_DISCIPLINARIA,
            ],
            // El orden es el del formulario: las de todos primero, las de un
            // solo régimen después (diseño, 4.3).
            self::CESACION_FUNCIONES => [
                SubtipoMovimientoPersonal::RENUNCIA,
                SubtipoMovimientoPersonal::REMOCION,
                SubtipoMovimientoPersonal::PERIODO_PRUEBA_NO_SUPERADO,
                SubtipoMovimientoPersonal::FIN_DEL_PLAZO,
                SubtipoMovimientoPersonal::TERMINACION_UNILATERAL,
                SubtipoMovimientoPersonal::MUTUO_ACUERDO,
                SubtipoMovimientoPersonal::EVALUACION_INSUFICIENTE,
                SubtipoMovimientoPersonal::DESTITUCION,
                SubtipoMovimientoPersonal::JUBILACION,
                SubtipoMovimientoPersonal::RETIRO_VOLUNTARIO,
                SubtipoMovimientoPersonal::INCAPACIDAD,
                SubtipoMovimientoPersonal::PERDIDA_DERECHOS,
                SubtipoMovimientoPersonal::FALLECIMIENTO,
                SubtipoMovimientoPersonal::CONTRATO_FINALIZADO,
                // Las del contrato civil (fase 4.2).
                SubtipoMovimientoPersonal::TERMINACION_MUTUO_ACUERDO,
                SubtipoMovimientoPersonal::TERMINACION_ANTICIPADA,
                SubtipoMovimientoPersonal::VISTO_BUENO,
                // Las de la autoridad electa (fase 4.1).
                SubtipoMovimientoPersonal::REVOCATORIA_DEL_MANDATO,
                SubtipoMovimientoPersonal::REMOCION_POR_EL_CONSEJO,
                SubtipoMovimientoPersonal::FIN_DEL_PERIODO,
            ],
            // LOSEP Art. 28, y la transitoria de obreros y autoridades (fase 2.2).
            self::LICENCIA_SIN_REMUNERACION => [
                SubtipoMovimientoPersonal::ASUNTOS_PARTICULARES,
                SubtipoMovimientoPersonal::ESTUDIOS_POSGRADO,
                SubtipoMovimientoPersonal::SERVICIO_MILITAR,
                SubtipoMovimientoPersonal::REEMPLAZO_DIGNATARIO,
                SubtipoMovimientoPersonal::CANDIDATURA,
                SubtipoMovimientoPersonal::CUIDADO_HIJOS,
                SubtipoMovimientoPersonal::SEGUN_SU_REGIMEN,
            ],
            default => [],
        };
    }

    public function requiereSubtipo(): bool
    {
        return $this->subtiposPermitidos() !== [];
    }

    /**
     * Tipos planos anteriores a la taxonomía de dos niveles que hoy son, en
     * realidad, subtipos. Se mantienen aceptados para no reescribir el
     * histórico, pero la elegibilidad se evalúa contra el subtipo equivalente
     * — así 'traslado' y 'traspaso' dejan de saltarse las reglas de Talento
     * Humano, que era el hueco que tenían antes de esta fase.
     */
    public function subtipoEquivalente(): ?SubtipoMovimientoPersonal
    {
        return match ($this) {
            self::TRASLADO                  => SubtipoMovimientoPersonal::TRASLADO_ADMINISTRATIVO,
            self::TRASPASO                  => SubtipoMovimientoPersonal::TRASPASO,
            self::COMISION_SERVICIOS        => SubtipoMovimientoPersonal::COMISION_CON_REMUNERACION,
            self::COMISION_SIN_REMUNERACION => SubtipoMovimientoPersonal::COMISION_SIN_REMUNERACION,
            self::DESTITUCION               => SubtipoMovimientoPersonal::DESTITUCION,
            default                         => null,
        };
    }

    /**
     * Valor por defecto de 'requiere_dictamen_medico' cuando el tipo no tiene
     * subtipo. El ingreso siempre lo exige: nadie se vincula sin ficha de
     * salud ocupacional. Con subtipo manda
     * SubtipoMovimientoPersonal::requiereDictamenMedicoPorDefecto().
     */
    public function requiereDictamenMedicoPorDefecto(): bool
    {
        return $this === self::INGRESO;
    }

    /**
     * Tipos que, al registrarse, reestructuran el ContratoServidor vigente:
     * cambian su puesto y su unidad sin cerrarlo ni crear otro, porque la
     * relación laboral no se interrumpe y no hay instrumento nuevo que firmar
     * (ver ContratoServidorService::reestructurarDesdeMovimiento()).
     *
     * Nunca true junto con creaVinculo() para el mismo tipo — probado en
     * MovimientoPersonalVinculoTest.
     *
     * Es un flag grueso, de tipo: CAMBIO_ADMINISTRATIVO está porque uno de sus
     * subtipos reubica, no porque reubiquen todos —las comisiones no—. Quién
     * reubica de verdad lo decide `MovimientoPersonal::reubicaAlServidor()`,
     * que mira el tipo Y el subtipo.
     */
    public function modificaVinculo(): bool
    {
        return in_array($this, [
            self::TRASLADO,
            self::TRASPASO,
            self::CAMBIO_ADMINISTRATIVO,
            self::PRESTACION_SERVICIOS,
            self::ADENDA,
        ], true);
    }

    /**
     * ¿Este tipo, por sí solo, reubica al servidor?
     *
     * Solo la prestación de servicios, que es el único que lo hace sin pasar
     * por un subtipo: TH la describe como «prácticamente un traspaso, pero para
     * nombramientos Provisionales, Ocasionales y Servicios Profesionales,
     * igualmente con su situación actual y situación propuesta» (2026-09-28).
     *
     * Hasta entonces se registraba sin proponer nada y no movía a nadie: el
     * formulario ni siquiera enseñaba la columna de situación propuesta, porque
     * esa decisión se tomaba solo con el subtipo y este tipo no tiene.
     */
    public function reubicaAlServidor(): bool
    {
        return $this === self::PRESTACION_SERVICIOS;
    }

    /**
     * Tipos que, al registrarse, crean un ContratoServidor nuevo. Lo usual
     * es que no haya vínculo previo que cerrar (alta nueva), pero
     * MovimientoPersonalStateService::aplicarRegistro() sí contempla el
     * caso contrario (candidato interno que gana un concurso a otro
     * puesto, o reingreso de un ex-servidor con vínculo sin cerrar): cierra
     * el vigente antes de crear el nuevo. Solo INGRESO por ahora —
     * REINGRESO/REINTEGRO no existen como tipo_movimiento propio todavía
     * (hallazgo IMPORTANTE #8 de la auditoría original, parcialmente
     * cubierto por lo anterior y por TRASPASO).
     */
    public function creaVinculo(): bool
    {
        return in_array($this, [self::INGRESO, self::RESTITUCION, self::INICIO_PERIODO], true);
    }

    /**
     * Cambia el nombramiento del vínculo vigente sin cerrarlo: el provisional
     * de prueba pasa a permanente (fase 3.2).
     */
    public function convierteNombramiento(): bool
    {
        return $this === self::NOMBRAMIENTO_DEFINITIVO;
    }

    /**
     * Mueve al servidor de carrera al puesto que ganó, en el mismo vínculo:
     * cambia de puesto, de remuneración y de nombramiento —provisional de
     * prueba—, y reserva el que deja (fase 3.3).
     */
    public function asciende(): bool
    {
        return $this === self::ASCENSO;
    }

    /**
     * El Viceprefecto pasa al puesto del Prefecto, en el mismo vínculo, hasta
     * terminar el período (fase 4.1b).
     */
    public function asumeLaPrimeraAutoridad(): bool
    {
        return $this === self::ASUNCION_DEFINITIVA;
    }

    /**
     * Cambia el contrato civil vigente en lo que diga: la unidad y el puesto, el
     * plazo, los honorarios (fase 4.2).
     */
    public function esAdenda(): bool
    {
        return $this === self::ADENDA;
    }

    /**
     * Las "acciones de personal" formales tienen restricción de elegibilidad
     * por tipo de nombramiento y nacen en estado BORRADOR (deben pasar por el
     * flujo guardado de MovimientoPersonalStateService). La bitácora del
     * expediente, que antes compartía este enum, vive desde la fase 1.2 en
     * `eventos_vinculo`.
     *
     * Desde la taxonomía de dos niveles se suman CESACION_FUNCIONES y
     * REGIMEN_DISCIPLINARIO, y también los tipos planos legados que tienen
     * subtipo equivalente — traslado y traspaso quedaban fuera de este flujo
     * y por eso se saltaban las reglas de Talento Humano.
     */
    public function esAccionDePersonal(): bool
    {
        return in_array($this, [
            self::CAMBIO_DENOMINACION,
            self::PRESTACION_SERVICIOS,
            self::CAMBIO_ADMINISTRATIVO,
            self::COMISION_SIN_REMUNERACION,
            self::LICENCIA_SIN_REMUNERACION,
            self::INCREMENTO_REMUNERACION,
            self::CESACION_FUNCIONES,
            self::REGIMEN_DISCIPLINARIO,
            self::REINTEGRO,
            self::NOMBRAMIENTO_DEFINITIVO,
            self::ASCENSO,
            self::LICENCIA_CONSEJO,
            self::ASUNCION_DEFINITIVA,
            self::ADENDA,
        ], true) || $this->subtipoEquivalente() !== null;
    }

    /**
     * Tipos que producen el documento impreso de Acción de Personal.
     *
     * Coincide con esAccionDePersonal() salvo por dos que quedan fuera de
     * aquella lista por un motivo de construcción —no porque no sean actos
     * formales—, y que por lo tanto hay que sumar a mano:
     *
     *  - **Subrogación**: se crea con su propio servicio y no con el genérico,
     *    así que no necesita sus reglas de elegibilidad. Acto formal lo es: el
     *    Art. 21 del Reglamento a la LOSEP la trata como tal, pasa por el flujo
     *    guardado, exige dictamen presupuestario y sella firmantes.
     *  - **Ingreso y Vinculación**: está fuera de esAccionDePersonal() porque
     *    ahí vive la validación de elegibilidad por nombramiento vigente, y
     *    quien ingresa todavía no tiene ninguno —incluirlo hacía fallar todo
     *    ingreso con "el servidor no tiene un contrato vigente". Pero nace en
     *    borrador, se suscribe, sella firmantes y recibe su correlativo
     *    AP-AAAA-NNNN como cualquier otra.
     *
     * Hasta el 2026-09-27 el ingreso no estaba aquí, y era el único acto del
     * módulo sin documento: la pantalla ofrecía el botón «PDF» —su estado y su
     * tipo pasan el filtro de puedeDescargarPdf()— y la descarga respondía 422
     * «"Ingreso" es un registro interno del expediente». La plantilla sí lo
     * contemplaba desde el principio: accion-personal.blade.php imprime «Sin
     * vínculo laboral previo — este es el primer ingreso del servidor».
     *
     * La bitácora del expediente —la novedad de contrato, las constancias de
     * una subrogación terminada antes— nunca tuvo documento, y desde la fase
     * 1.2 ni siquiera está en esta tabla: vive en `eventos_vinculo`.
     */
    public function tieneDocumentoImprimible(): bool
    {
        return $this->esAccionDePersonal() || in_array($this, [
            self::SUBROGACION,
            self::INGRESO,
            // Como el ingreso, queda fuera de esAccionDePersonal() porque quien
            // vuelve no tiene vínculo que validar (fase 2.6).
            self::RESTITUCION,
            // La autoridad electa, igual (fase 4.1). Su instrumento —el acto de
            // autoridad— es el que dice que no tiene documento.
            self::INICIO_PERIODO,
        ], true);
    }

    /**
     * Tipos que comprometen presupuesto (Art. 105 LOSEP): la transición a
     * SUSCRITA exige dictamen_presupuestario_ref y disponibilidad
     * verificada en la partida del puesto involucrado.
     *
     * SUBROGACION nace en BORRADOR vía SubrogacionService::registrar();
     * INCREMENTO_REMUNERACION nace en BORRADOR vía
     * MovimientoPersonalService::registrar() (ver esAccionDePersonal()).
     * Ambos pasan realmente por este guard, no solo en teoría.
     */
    public function tieneEfectoEconomico(): bool
    {
        return in_array($this, [
            self::SUBROGACION,
            self::INCREMENTO_REMUNERACION,
        ], true);
    }

    /**
     * Reglas de elegibilidad de Talento Humano por tipo de nombramiento
     * vigente del servidor (revisadas con TH el 2026-09-28):
     * - Cambio de denominación: solo obreros (Código de Trabajo).
     * - Incremento de remuneración: solo obreros (Código de Trabajo).
     * - Prestación de servicios: Provisional y Servicios Ocasionales. Servicios
     *   Profesionales la tuvo hasta la fase 4.2: su cambio de unidad es la
     *   adenda del contrato.
     * - Cambio administrativo: solo Nombramiento Permanente.
     * - Comisión de servicios sin remuneración: solo Permanente
     *   (+ validación de antigüedad y duración en el servicio).
     * - Licencia sin remuneración: Permanente, Código de Trabajo o
     *   Elección Popular.
     *
     * Y una añadida el 2026-09-29: Libre Nombramiento y Remoción tiene las
     * mismas acciones que Servicios Ocasionales. Con las reglas de arriba se
     * quedaba sin ninguna, y preguntado por eso TH lo equiparó al ocasional.
     *
     * Los tipos con subtipo (cambio administrativo, régimen disciplinario,
     * cesación de funciones) no deciden aquí: delegan en
     * SubtipoMovimientoPersonal::elegiblePara(), porque es el subtipo el que
     * fija la regla. Este método devuelve true para ellos y el llamador
     * (MovimientoPersonalService::validarElegibilidad()) evalúa el subtipo.
     */
    public function elegiblePara(TipoNombramiento $tipo): bool
    {
        if ($subtipo = $this->subtipoEquivalente()) {
            return $subtipo->elegiblePara($tipo);
        }

        return match ($this) {
            self::CAMBIO_DENOMINACION =>
                $tipo === TipoNombramiento::CODIGO_TRABAJO,
            // Las tres que nombró TH (2026-09-28), enumeradas, más Libre
            // Nombramiento y Remoción (2026-09-29). Antes era
            // `esLosep() && !== PERMANENTE`, que daba un conjunto parecido pero
            // no el mismo: dejaba fuera Servicios Profesionales —porque
            // esLosep() es falso para el contrato civil— y colaba Elección
            // Popular, que TH no incluye.
            //
            // Libre Nombramiento no estaba en aquella enumeración, y por eso se
            // quedaba sin ninguna acción disponible. Preguntado, TH dijo que
            // «es similar a un nombramiento ocasional, es decir tener las
            // acciones que tiene este último», y el ocasional la tiene. Es lo
            // único de esta respuesta que choca con la lista del 2026-09-28:
            // conviene que TH lo confirme viéndolo en pantalla.
            //
            // Servicios Profesionales salió en la fase 4.2: su contrato civil
            // se cambia con una adenda.
            self::PRESTACION_SERVICIOS => in_array($tipo, [
                TipoNombramiento::PROVISIONAL,
                TipoNombramiento::SERVICIOS_OCASIONALES,
                TipoNombramiento::LIBRE_NOMBRAMIENTO,
            ], true),
            // La adenda, solo del contrato civil (fase 4.2).
            self::ADENDA =>
                $tipo === TipoNombramiento::SERVICIOS_PROFESIONALES,
            self::CAMBIO_ADMINISTRATIVO =>
                $tipo === TipoNombramiento::PERMANENTE,
            self::LICENCIA_SIN_REMUNERACION => in_array($tipo, [
                TipoNombramiento::PERMANENTE,
                TipoNombramiento::CODIGO_TRABAJO,
                TipoNombramiento::ELECCION_POPULAR,
            ], true),
            // Solo obreros (TH, 2026-09-28). Caía en el `default` de abajo, así
            // que se ofrecía a cualquier nombramiento: un permanente veía
            // «Incremento de Remuneración» entre sus opciones, y el backend lo
            // aceptaba.
            self::INCREMENTO_REMUNERACION =>
                $tipo === TipoNombramiento::CODIGO_TRABAJO,
            // Del provisional de prueba; que lo sea lo comprueba el servicio,
            // que mira el supuesto del vínculo.
            self::NOMBRAMIENTO_DEFINITIVO =>
                $tipo === TipoNombramiento::PROVISIONAL,
            // De quien puede tener la ausencia que cierra: los permanentes, y
            // obreros y dignatarios por su licencia «según su régimen» (fase
            // 2.2). Se deduce de las causales para no quedarse atrás si cambian.
            // Y el ascendido que no superó la prueba, que es provisional (fase
            // 3.3): que lo sea lo comprueba AscensoService. Y la autoridad que
            // vuelve de la licencia del Consejo (fase 4.1b).
            self::REINTEGRO => in_array($tipo, [TipoNombramiento::PROVISIONAL, TipoNombramiento::ELECCION_POPULAR], true) || array_filter(
                SubtipoMovimientoPersonal::cases(),
                fn (SubtipoMovimientoPersonal $s) => $s->esAusenciaTemporal() && $s->elegiblePara($tipo)
            ) !== [],
            // El servidor de carrera (diseño, 5; LOSEP 68).
            self::ASCENSO =>
                $tipo === TipoNombramiento::PERMANENTE,
            // La reelección: la autoridad empieza su período nuevo mientras el
            // anterior termina (fase 4.1). Sin vínculo no se pregunta.
            self::INICIO_PERIODO,
            // Lo del Consejo y la ley, solo de las autoridades electas (4.1b).
            self::LICENCIA_CONSEJO,
            self::ASUNCION_DEFINITIVA =>
                $tipo === TipoNombramiento::ELECCION_POPULAR,
            default => true,
        };
    }
}
