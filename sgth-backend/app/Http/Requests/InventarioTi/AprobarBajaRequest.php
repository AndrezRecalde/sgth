<?php

namespace App\Http\Requests\InventarioTi;

use App\Models\InventarioTi\BajaBien;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Aprobar una baja (fase 1.6): la fecha, el memorando de Quipux que la
 * respalda (D4) y qué componentes salen con el equipo. Los componentes que no
 * se eligen se desenlazan y siguen en servicio.
 */
final class AprobarBajaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El acceso lo resuelve el permiso de la ruta.
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('memorando_numero'))) {
            $valor = trim($this->input('memorando_numero'));
            $this->merge(['memorando_numero' => $valor === '' ? null : $valor]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'fecha_baja'         => ['required', 'date', 'before_or_equal:today', $this->noAntesDelInforme()],
            'memorando_numero'   => ['required', 'string', 'max:60'],
            'componentes_baja'   => ['nullable', 'array'],
            'componentes_baja.*' => ['integer', 'distinct', $this->componenteDelEquipo()],
        ];
    }

    private function baja(): ?BajaBien
    {
        $baja = $this->route('baja');

        return $baja instanceof BajaBien ? $baja : null;
    }

    private function noAntesDelInforme(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $fallar) {
            $informe = $this->baja()?->fecha_informe;
            if ($informe && strtotime((string) $valor) !== false && date('Y-m-d', strtotime((string) $valor)) < $informe->toDateString()) {
                $fallar('La baja no puede ser anterior al informe técnico del ' . $informe->format('d/m/Y') . '.');
            }
        };
    }

    private function componenteDelEquipo(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $fallar) {
            $bien = $this->baja()?->bien;
            if ($bien && ! $bien->componentes()->whereKey((int) $valor)->exists()) {
                $fallar('Solo pueden salir con el equipo los componentes que tiene enlazados.');
            }
        };
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'fecha_baja'         => 'fecha de la baja',
            'memorando_numero'   => 'número de memorando',
            'componentes_baja'   => 'componentes',
            'componentes_baja.*' => 'componente',
        ];
    }
}
