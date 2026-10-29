<?php

namespace App\Rules;

use App\Enums\Rol;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Un rol complementario no se asigna sin su rol base (`Rol::rolBase()`).
 *
 * `medico-ocupacional` solo suma la salud ocupacional. Dado solo, la persona
 * llenaba la FEMO sin ser médico para el sistema: sin cola, sin especialidad y
 * sin los permisos base de todo servidor.
 */
final class RolesComplementariosConSuBase implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        foreach ($value as $nombre) {
            $base = is_string($nombre) ? Rol::tryFrom($nombre)?->rolBase() : null;

            if ($base && ! in_array($base->value, $value, true)) {
                $fail('El rol '.Rol::from($nombre)->etiqueta().' se asigna junto con '.$base->etiqueta().'.');

                return;
            }
        }
    }
}
