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
    // 'ascenso' se retiró (2026-07-23): confirmado con Talento Humano/UATH
    // que no existe como acción de personal en la operación real del GAD
    // (cero registros, sin mecanismo formal de registro). También era uno
    // de los 4 tipos mapeados como reportable al SIITH — ver
    // ConfiguracionReporteMovimientoSeeder, que quedó con 3 confirmados.

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
                SubtipoMovimientoPersonal::VISTO_BUENO,
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
        return $this === self::INGRESO;
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
     * - Prestación de servicios: Provisional, Servicios Ocasionales y
     *   Servicios Profesionales.
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
            self::PRESTACION_SERVICIOS => in_array($tipo, [
                TipoNombramiento::PROVISIONAL,
                TipoNombramiento::SERVICIOS_OCASIONALES,
                TipoNombramiento::SERVICIOS_PROFESIONALES,
                TipoNombramiento::LIBRE_NOMBRAMIENTO,
            ], true),
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
            // De quien puede tener la ausencia que cierra: los permanentes, y
            // obreros y dignatarios por su licencia «según su régimen» (fase
            // 2.2). Se deduce de las causales para no quedarse atrás si cambian.
            // El del provisional ascendido que vuelve a su puesto llega con el
            // ascenso (fase 3).
            self::REINTEGRO => array_filter(
                SubtipoMovimientoPersonal::cases(),
                fn (SubtipoMovimientoPersonal $s) => $s->esAusenciaTemporal() && $s->elegiblePara($tipo)
            ) !== [],
            default => true,
        };
    }
}
