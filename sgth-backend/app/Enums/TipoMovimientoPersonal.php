<?php

namespace App\Enums;

enum TipoMovimientoPersonal: string
{
    case TRASLADO                  = 'traslado';
    case SUBROGACION               = 'subrogacion';
    case COMISION_SERVICIOS        = 'comision_servicios';
    case CAMBIO_REGIMEN            = 'cambio_regimen';
    case CAMBIO_PUESTO             = 'cambio_puesto';
    case INGRESO                   = 'ingreso';
    case EGRESO                    = 'egreso';
    case NOVEDAD_CONTRATO          = 'novedad_contrato';
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
            self::CAMBIO_REGIMEN            => 'Cambio de Régimen',
            self::CAMBIO_PUESTO             => 'Cambio de Puesto',
            self::INGRESO                   => 'Ingreso',
            self::EGRESO                    => 'Egreso',
            self::NOVEDAD_CONTRATO          => 'Novedad de Contrato',
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
            self::CESACION_FUNCIONES => [
                SubtipoMovimientoPersonal::RENUNCIA,
                SubtipoMovimientoPersonal::DESTITUCION,
                SubtipoMovimientoPersonal::JUBILACION,
                SubtipoMovimientoPersonal::INCAPACIDAD,
                SubtipoMovimientoPersonal::CONTRATO_FINALIZADO,
                SubtipoMovimientoPersonal::VISTO_BUENO,
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
     * Tipos que, al registrarse, cierran el ContratoServidor vigente y
     * crean uno nuevo con puesto/unidad propuestos (ver
     * ContratoServidorService::reestructurarDesdeMovimiento()). Nunca
     * true junto con creaVinculo() para el mismo tipo — probado en
     * TipoMovimientoPersonalVinculoTest.
     */
    public function modificaVinculo(): bool
    {
        return in_array($this, [
            self::TRASLADO,
            self::TRASPASO,
            self::CAMBIO_ADMINISTRATIVO,
        ], true);
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
     * flujo guardado de MovimientoPersonalStateService); los movimientos
     * históricos genéricos (cambio_puesto, novedad_contrato, etc.) no.
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
        ], true) || $this->subtipoEquivalente() !== null;
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
     * vigente del servidor:
     * - Cambio de denominación: solo obreros (Código de Trabajo).
     * - Prestación de servicios: solo LOSEP, excepto Permanente.
     * - Cambio administrativo: solo Nombramiento Permanente.
     * - Comisión de servicios sin remuneración: solo Permanente
     *   (+ validación de antigüedad y duración en el servicio).
     * - Licencia sin remuneración: Permanente, Código de Trabajo o
     *   Elección Popular.
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
            self::PRESTACION_SERVICIOS =>
                $tipo->esLosep() && $tipo !== TipoNombramiento::PERMANENTE,
            self::CAMBIO_ADMINISTRATIVO =>
                $tipo === TipoNombramiento::PERMANENTE,
            self::LICENCIA_SIN_REMUNERACION => in_array($tipo, [
                TipoNombramiento::PERMANENTE,
                TipoNombramiento::CODIGO_TRABAJO,
                TipoNombramiento::ELECCION_POPULAR,
            ], true),
            default => true,
        };
    }
}
