<?php

namespace App\Enums;

/**
 * Gravedad de la falta disciplinaria — Art. 42 de la LOSEP, que solo
 * distingue dos: leves y graves. Hasta el 2026-10-04 había también
 * «muy grave», que la ley no reconoce (decisión de Talento Humano).
 */
enum TipoFalta: string
{
    case LEVE  = 'leve';
    case GRAVE = 'grave';

    /**
     * Espejada por `TIPO_FALTA_LABELS` en
     * `features/disciplinario/utils/etiquetas.ts`, y fijada por
     * `EnumsDisciplinarioTest`.
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::LEVE  => 'Leve',
            self::GRAVE => 'Grave',
        };
    }

    /**
     * Las sanciones que el Art. 42 de la LOSEP admite para esta gravedad: las
     * leves, amonestación verbal, amonestación escrita o multa; las graves,
     * suspensión o destitución. Antes no se relacionaban, y una falta leve
     * aceptaba una destitución.
     *
     * Espejada por `SANCIONES_POR_FALTA` en
     * `features/disciplinario/utils/etiquetas.ts`, y fijada por
     * `EnumsDisciplinarioTest`.
     *
     * @return list<TipoSancion>
     */
    public function sancionesAdmitidas(): array
    {
        return match ($this) {
            self::LEVE  => [
                TipoSancion::AMONESTACION_VERBAL,
                TipoSancion::AMONESTACION_ESCRITA,
                TipoSancion::MULTA,
            ],
            self::GRAVE => [
                TipoSancion::SUSPENSION,
                TipoSancion::DESTITUCION,
            ],
        };
    }

    public function admite(TipoSancion $sancion): bool
    {
        return in_array($sancion, $this->sancionesAdmitidas(), true);
    }
}
