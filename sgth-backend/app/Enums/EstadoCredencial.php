<?php

namespace App\Enums;

/**
 * El estado de una credencial (docs/diseno-comunicacion-social.md, 6.3).
 *
 * Vigente y vencida son la misma credencial viva: vencida es solo que pasó la
 * fecha de fin del contrato, y vuelve a vigente con el mismo código si el
 * vínculo se prorroga o se renueva sin interrupción. Revocada y anulada son
 * finales: su QR ya no valida nunca más.
 */
enum EstadoCredencial: string
{
    case VIGENTE  = 'vigente';
    case VENCIDA  = 'vencida';
    case REVOCADA = 'revocada';
    case ANULADA  = 'anulada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::VIGENTE  => 'Vigente',
            self::VENCIDA  => 'Vencida',
            self::REVOCADA => 'Revocada',
            self::ANULADA  => 'Anulada',
        };
    }

    /** La que el servidor lleva hoy: una sola por servidor. */
    public function esViva(): bool
    {
        return in_array($this, [self::VIGENTE, self::VENCIDA], true);
    }

    /** @return list<string> */
    public static function vivas(): array
    {
        return [self::VIGENTE->value, self::VENCIDA->value];
    }
}
