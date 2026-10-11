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
 * 19). Los de obreros, profesionales y autoridades electas vendrán con los
 * suyos.
 */
enum InstrumentoActo: string
{
    case ACCION_PERSONAL   = 'accion_personal';
    case REGISTRO_CONTRATO = 'registro_contrato';

    public function etiqueta(): string
    {
        return match ($this) {
            self::ACCION_PERSONAL   => 'Acción de personal',
            self::REGISTRO_CONTRATO => 'Registro del contrato',
        };
    }

    /**
     * El instrumento de un acto, por su tipo y el nombramiento que propone: el
     * ingreso de un ocasional se registra con su contrato, sin acción. El de
     * Servicios Profesionales sigue como acción hasta su bloque (fase 4.2).
     */
    public static function para(TipoMovimientoPersonal $tipo, ?TipoNombramiento $nombramiento): self
    {
        return $tipo === TipoMovimientoPersonal::INGRESO && $nombramiento === TipoNombramiento::SERVICIOS_OCASIONALES
            ? self::REGISTRO_CONTRATO
            : self::ACCION_PERSONAL;
    }

    /**
     * Las clases y nombramientos con que una clase se registra con su contrato.
     * Lo lee el formulario para no pedir firmantes donde nadie firma.
     *
     * @return list<TipoNombramiento>
     */
    public static function nombramientosConRegistroDeContrato(ClaseAccionPersonal $clase): array
    {
        [$tipo] = $clase->tipoYSubtipo();

        return array_values(array_filter(
            TipoNombramiento::cases(),
            fn (TipoNombramiento $n) => self::para($tipo, $n) === self::REGISTRO_CONTRATO,
        ));
    }

    /** El documento del Ministerio, con su número AP. El contrato se firma aparte. */
    public function tieneDocumento(): bool
    {
        return $this === self::ACCION_PERSONAL;
    }

    /** Quién firma: la autoridad y Talento Humano, solo en la acción de personal. */
    public function sellaFirmantes(): bool
    {
        return $this === self::ACCION_PERSONAL;
    }

    /** Se notifica lo que la persona no firmó; el contrato sí lo firmó. */
    public function seNotifica(): bool
    {
        return $this === self::ACCION_PERSONAL;
    }
}
