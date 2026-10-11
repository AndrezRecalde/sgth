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
 *
 * El ascendido también es provisional de prueba (b.5, inciso segundo; fase
 * 3.3), pero con reglas propias: seis meses, su puesto anterior reservado y, si
 * no la supera, el reintegro a ese puesto en lugar de la cesación. No se elige
 * en el ingreso: lo pone el ascenso.
 */
enum SupuestoProvisional: string
{
    case SUSPENDIDO_O_DESTITUIDO   = 'suspendido_o_destituido';
    case PUESTO_VACANTE            = 'puesto_vacante';
    case NIVEL_JERARQUICO_SUPERIOR = 'nivel_jerarquico_superior';
    case DE_PRUEBA                 = 'de_prueba';
    case ASCENSO                   = 'ascenso';

    public function etiqueta(): string
    {
        return match ($this) {
            self::SUSPENDIDO_O_DESTITUIDO   => 'Puesto de un servidor suspendido o destituido',
            self::PUESTO_VACANTE            => 'Puesto vacante',
            self::NIVEL_JERARQUICO_SUPERIOR => 'Puesto del nivel jerárquico superior',
            self::DE_PRUEBA                 => 'De prueba',
            self::ASCENSO                   => 'De prueba por ascenso',
        };
    }

    public function baseLegal(): string
    {
        return match ($this) {
            self::SUSPENDIDO_O_DESTITUIDO   => 'LOSEP Art. 17 b.1',
            self::PUESTO_VACANTE            => 'LOSEP Art. 17 b.3',
            self::NIVEL_JERARQUICO_SUPERIOR => 'LOSEP Art. 17 b.4',
            self::DE_PRUEBA                 => 'LOSEP Art. 17 b.5',
            self::ASCENSO                   => 'LOSEP Arts. 17 b.5 y 68',
        };
    }

    /**
     * Cuánto dura la prueba: tres meses quien ingresa y seis quien asciende
     * (LOSEP 17 b.5; TH N2). Null en los demás, que no son de prueba.
     */
    public function mesesDePrueba(): ?int
    {
        return match ($this) {
            self::DE_PRUEBA => 3,
            self::ASCENSO   => 6,
            default         => null,
        };
    }

    public function esDePrueba(): bool
    {
        return $this->mesesDePrueba() !== null;
    }

    /** Los que se eligen en el ingreso: todos menos el del ascenso, que lo pone el ascenso. */
    public function seEligeEnElIngreso(): bool
    {
        return $this !== self::ASCENSO;
    }

    /** @return list<self> */
    public static function delIngreso(): array
    {
        return array_values(array_filter(self::cases(), fn (self $s) => $s->seEligeEnElIngreso()));
    }
}
