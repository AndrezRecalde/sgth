<?php

namespace App\Enums;

/**
 * Con qué documento se formaliza un acto (diseño de Acciones de Personal, 8.1).
 * Todos comparten el registro, el historial y el motor de efectos; cambian la
 * numeración, el documento y quién firma.
 *
 * Llegan de uno en uno, con su bloque. El primero distinto de la acción de
 * personal es el registro del contrato de un ocasional (fase 2.7; TH N7): la
 * ley no le pide acción a ese ingreso (LOSEP 18, último inciso; Reglamento
 * 19). El segundo, el acto de autoridad (fase 4.1; TH 1–7): al Prefecto y al
 * Viceprefecto no se les emite acción de personal, porque ninguno de sus actos
 * nace de una autoridad nominadora; lleva el documento de origen —la credencial
 * del CNE, la resolución del Consejo— y su propia numeración. El tercero, el
 * contrato civil (fase 4.2; TH N6): quien tiene Servicios Profesionales no es
 * servidor ni tiene relación de dependencia (Reglamento Art. 148). Su contrato,
 * sus adendas y su terminación los firman las partes; aquí se registran. El de
 * obreros vendrá con el suyo.
 */
enum InstrumentoActo: string
{
    case ACCION_PERSONAL   = 'accion_personal';
    case REGISTRO_CONTRATO = 'registro_contrato';
    case ACTO_AUTORIDAD    = 'acto_autoridad';
    case CONTRATO_CIVIL    = 'contrato_civil';

    public function etiqueta(): string
    {
        return match ($this) {
            self::ACCION_PERSONAL   => 'Acción de personal',
            self::REGISTRO_CONTRATO => 'Registro del contrato',
            self::ACTO_AUTORIDAD    => 'Acto de autoridad electa',
            self::CONTRATO_CIVIL    => 'Contrato civil',
        };
    }

    /**
     * El instrumento de un acto, por su tipo y el nombramiento: el que propone
     * si crea el vínculo, o el vigente si no. El ingreso de un ocasional se
     * registra con su contrato, sin acción, y todo acto de una autoridad electa
     * es acto de autoridad. Todo el de Servicios Profesionales, del contrato
     * civil (fase 4.2).
     */
    public static function para(TipoMovimientoPersonal $tipo, ?TipoNombramiento $nombramiento): self
    {
        return match (true) {
            $nombramiento === TipoNombramiento::ELECCION_POPULAR => self::ACTO_AUTORIDAD,
            $nombramiento === TipoNombramiento::SERVICIOS_PROFESIONALES => self::CONTRATO_CIVIL,
            $tipo === TipoMovimientoPersonal::INGRESO
                && $nombramiento === TipoNombramiento::SERVICIOS_OCASIONALES => self::REGISTRO_CONTRATO,
            default => self::ACCION_PERSONAL,
        };
    }

    /**
     * Las clases y nombramientos con que una clase se registra con su contrato.
     * Lo lee el formulario para no pedir firmantes donde nadie firma.
     *
     * @return list<TipoNombramiento>
     */
    public static function nombramientosConRegistroDeContrato(ClaseAccionPersonal $clase): array
    {
        return self::nombramientosCon($clase, self::REGISTRO_CONTRATO);
    }

    /**
     * Los nombramientos con que una clase es acto de autoridad (fase 4.1): el
     * formulario no pide firmantes y pide el documento de origen.
     *
     * @return list<TipoNombramiento>
     */
    public static function nombramientosConActoDeAutoridad(ClaseAccionPersonal $clase): array
    {
        return array_values(array_filter(
            self::nombramientosCon($clase, self::ACTO_AUTORIDAD),
            // Solo los que la clase admite: el ingreso no se le registra a una
            // autoridad, que empieza con «Inicio del período».
            fn (TipoNombramiento $n) => $clase === ClaseAccionPersonal::INICIO_PERIODO || $clase->elegiblePara($n),
        ));
    }

    /**
     * Los nombramientos con que una clase es del contrato civil (fase 4.2): el
     * del ingreso, el que propone; el del resto, el vigente, y solo si la clase
     * le aplica. El formulario no pide firmantes.
     *
     * @return list<TipoNombramiento>
     */
    public static function nombramientosConContratoCivil(ClaseAccionPersonal $clase): array
    {
        return array_values(array_filter(
            self::nombramientosCon($clase, self::CONTRATO_CIVIL),
            fn (TipoNombramiento $n) => $clase->pideContratacion() || $clase->elegiblePara($n),
        ));
    }

    /** @return list<TipoNombramiento> */
    private static function nombramientosCon(ClaseAccionPersonal $clase, self $instrumento): array
    {
        [$tipo] = $clase->tipoYSubtipo();

        return array_values(array_filter(
            TipoNombramiento::cases(),
            fn (TipoNombramiento $n) => self::para($tipo, $n) === $instrumento,
        ));
    }

    /**
     * El documento del Ministerio, con su número AP. El contrato se firma
     * aparte, y el contrato civil con sus adendas también. El acto de autoridad
     * tiene su constancia, opcional y con su número AU [TH 6; fase 4.1b].
     */
    public function tieneDocumento(): bool
    {
        return in_array($this, [self::ACCION_PERSONAL, self::ACTO_AUTORIDAD], true);
    }

    /** Quién firma: la autoridad y Talento Humano, solo en la acción de personal. */
    public function sellaFirmantes(): bool
    {
        return $this === self::ACCION_PERSONAL;
    }

    /** Se notifica lo que la persona no firmó; el contrato y la adenda sí. */
    public function seNotifica(): bool
    {
        return $this === self::ACCION_PERSONAL;
    }
}
