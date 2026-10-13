<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * Que un nombre de catálogo no exista ya, sin distinguir mayúsculas ni contar
 * las filas borradas.
 *
 * Es la misma regla que los índices únicos de los catálogos del inventario
 * (`lower(nombre) … WHERE deleted_at IS NULL`). `Rule::unique` compara exacto y
 * cuenta las borradas: «Dell» pasaba la validación junto a «DELL» y el índice
 * respondía con un 500, y una marca borrada impedía volver a crearla.
 */
final class UnicoSinMayusculas implements ValidationRule
{
    /**
     * @param  array<string, mixed>  $donde  columnas extra que deben coincidir (el tipo de un subtipo)
     */
    public function __construct(
        private readonly string $tabla,
        private readonly string $columna = 'nombre',
        private readonly ?int $ignorar = null,
        private readonly array $donde = [],
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $existe = DB::table($this->tabla)
            ->whereRaw("lower({$this->columna}) = lower(?)", [trim($value)])
            ->whereNull('deleted_at')
            ->when($this->ignorar, fn ($q) => $q->where('id', '!=', $this->ignorar))
            ->where($this->donde)
            ->exists();

        if ($existe) {
            $fail('Ya existe un registro con ese :attribute.');
        }
    }
}
