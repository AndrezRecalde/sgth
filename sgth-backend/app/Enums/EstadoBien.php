<?php

namespace App\Enums;

/**
 * Dónde está un equipo informático en su vida útil
 * (docs/diseno-subsistema-tic.md, 4.2).
 *
 * El Excel de TIC mezclaba esto con la condición física («BUENO», «REGULAR»,
 * frases sueltas) y el módulo anterior tenía `activo`, que no decía si el
 * equipo estaba en manos de alguien. Ahora el estado y la condición son dos
 * datos distintos: un equipo en bodega puede estar en buen estado y uno en
 * uso, en malo.
 *
 * `en_uso` y `disponible` no se eligen: salen de la custodia. Un equipo con
 * custodio vigente está en uso; uno sin custodio está disponible en TIC.
 * `dado_de_baja` tampoco: lo pone la baja, con su informe.
 */
enum EstadoBien: string
{
    /** Tiene un custodio vigente. */
    case EN_USO = 'en_uso';

    /** Sin custodio: en la bodega de TIC o en el grupo de préstamo. */
    case DISPONIBLE = 'disponible';

    /** En reparación por TIC. */
    case EN_MANTENIMIENTO = 'en_mantenimiento';

    /** Con el proveedor, por garantía o vigencia tecnológica. */
    case EN_GARANTIA = 'en_garantia';

    /** Salió del servicio con su informe técnico. No vuelve. */
    case DADO_DE_BAJA = 'dado_de_baja';

    /** No se sabe dónde está, o se reportó robado. */
    case EXTRAVIADO = 'extraviado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::EN_USO           => 'En uso',
            self::DISPONIBLE       => 'Disponible',
            self::EN_MANTENIMIENTO => 'En mantenimiento',
            self::EN_GARANTIA      => 'En garantía',
            self::DADO_DE_BAJA     => 'Dado de baja',
            self::EXTRAVIADO       => 'Extraviado',
        };
    }

    /** Los que TIC puede poner a mano al editar el equipo. */
    public static function manuales(): array
    {
        return [self::EN_MANTENIMIENTO, self::EN_GARANTIA, self::EXTRAVIADO];
    }

    /** Los que dependen de la custodia y no se eligen. */
    public function esDeCustodia(): bool
    {
        return $this === self::EN_USO || $this === self::DISPONIBLE;
    }
}
