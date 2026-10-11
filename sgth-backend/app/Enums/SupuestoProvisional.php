<?php

namespace App\Enums;

/**
 * Para qué se da un nombramiento provisional (LOSEP Art. 17 b; diseño de
 * Acciones de Personal, 4.2 y 4.3; fase 3.2). Decide qué causales le valen: la
 * remoción es del provisional cuyo supuesto terminó (b.1, b.3, b.4) y el no
 * superar la prueba, del de prueba (b.5).
 *
 * No se ofrecen el b.2 —licencia sin remuneración— ni la parte del b.3 que
 * cubre una comisión: son reemplazos, y los reemplazos son solo ocasionales o
 * profesionales [TH N10].
 */
enum SupuestoProvisional: string
{
    case SUSPENDIDO_O_DESTITUIDO   = 'suspendido_o_destituido';
    case PUESTO_VACANTE            = 'puesto_vacante';
    case NIVEL_JERARQUICO_SUPERIOR = 'nivel_jerarquico_superior';
    case DE_PRUEBA                 = 'de_prueba';

    /** Los meses de prueba del b.5 (LOSEP 17 b.5). */
    public const MESES_DE_PRUEBA = 3;

    public function etiqueta(): string
    {
        return match ($this) {
            self::SUSPENDIDO_O_DESTITUIDO   => 'Puesto de un servidor suspendido o destituido',
            self::PUESTO_VACANTE            => 'Puesto vacante',
            self::NIVEL_JERARQUICO_SUPERIOR => 'Puesto del nivel jerárquico superior',
            self::DE_PRUEBA                 => 'De prueba',
        };
    }

    public function baseLegal(): string
    {
        return match ($this) {
            self::SUSPENDIDO_O_DESTITUIDO   => 'LOSEP Art. 17 b.1',
            self::PUESTO_VACANTE            => 'LOSEP Art. 17 b.3',
            self::NIVEL_JERARQUICO_SUPERIOR => 'LOSEP Art. 17 b.4',
            self::DE_PRUEBA                 => 'LOSEP Art. 17 b.5',
        };
    }
}
