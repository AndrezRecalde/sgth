<?php

namespace App\Http\Requests\InventarioTi;

use App\Contracts\InventarioTi\CustodiaServiceInterface;
use App\Enums\MotivoBaja;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Proponer la baja de un equipo con su informe técnico (fase 1.6). El
 * diagnóstico es lo que sostiene la baja ante Activos Fijos: no basta una
 * palabra.
 */
final class ProponerBajaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El acceso lo resuelve el permiso de la ruta.
    }

    protected function prepareForValidation(): void
    {
        foreach (['diagnostico', 'recomendacion', 'soporte_anterior'] as $campo) {
            if (is_string($this->input($campo))) {
                $valor = trim($this->input($campo));
                $this->merge([$campo => $valor === '' ? null : $valor]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'bien_informatico_id' => ['required', 'integer', Rule::exists('bienes_informaticos', 'id')->whereNull('deleted_at')],
            'motivo'              => ['required', Rule::enum(MotivoBaja::class)],
            'diagnostico'         => ['required', 'string', 'min:20', 'max:3000'],
            'recomendacion'       => ['nullable', 'string', 'max:2000'],
            'fecha_informe'       => ['required', 'date', 'before_or_equal:today'],
            'soporte_anterior'    => ['nullable', 'string', 'max:30', $this->soporteAnteriorVigente()],
        ];
    }

    private function soporteAnteriorVigente(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $fallar) {
            if ($valor !== null && ! app(CustodiaServiceInterface::class)->aceptaSoporteAnterior()) {
                $fallar('Desde el arranque de la mesa de ayuda el soporte se registra como ticket: el número del sistema anterior ya no se acepta.');
            }
        };
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'bien_informatico_id' => 'equipo',
            'motivo'              => 'motivo de la baja',
            'diagnostico'         => 'diagnóstico técnico',
            'recomendacion'       => 'recomendación',
            'fecha_informe'       => 'fecha del informe',
            'soporte_anterior'    => 'número de soporte anterior',
        ];
    }
}
