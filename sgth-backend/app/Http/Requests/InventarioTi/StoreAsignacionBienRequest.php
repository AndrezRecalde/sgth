<?php

namespace App\Http\Requests\InventarioTi;

use App\Contracts\InventarioTi\CustodiaServiceInterface;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Entregar un equipo en custodia (fase 1.3). Hasta la 1.1 llegaba
 * `$request->all()` sin validar.
 *
 * El memorando se hace en Quipux: aquí va su número, y el PDF se adjunta
 * aparte (D4). El número de soporte del sistema antiguo solo se acepta hasta
 * la fecha de corte (D1).
 */
class StoreAsignacionBienRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El acceso lo resuelve el permiso de la ruta.
    }

    protected function prepareForValidation(): void
    {
        foreach (['memorando_numero', 'soporte_anterior', 'observaciones'] as $campo) {
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
            'bien_informatico_id' => ['required', 'integer', 'exists:bienes_informaticos,id'],
            ...$this->reglasComunes(),
        ];
    }

    /** @return array<string, mixed> */
    protected function reglasComunes(): array
    {
        return [
            'servidor_id'         => ['required', 'integer', 'exists:servidores,id'],
            'usuario_servidor_id' => ['nullable', 'integer', 'exists:servidores,id'],
            'fecha_asignacion'    => ['required', 'date', 'before_or_equal:today'],
            'memorando_numero'    => ['nullable', 'string', 'max:100'],
            'soporte_anterior'    => ['nullable', 'string', 'max:30', $this->soporteAnteriorVigente()],
            'observaciones'       => ['nullable', 'string', 'max:2000'],
            // Dónde queda el equipo; si no se manda, sigue donde estaba.
            'ubicacion_id'        => ['sometimes', 'nullable', 'integer', Rule::exists('ubicaciones', 'id')->whereNull('deleted_at')],
        ];
    }

    protected function soporteAnteriorVigente(): Closure
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
            'servidor_id'         => 'custodio',
            'usuario_servidor_id' => 'usuario del equipo',
            'fecha_asignacion'    => 'fecha',
            'memorando_numero'    => 'número de memorando',
            'soporte_anterior'    => 'número de soporte anterior',
            'ubicacion_id'        => 'ubicación',
        ];
    }
}
