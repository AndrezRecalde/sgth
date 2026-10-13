<?php

namespace App\Http\Requests\InventarioTi;

use App\Contracts\InventarioTi\CustodiaServiceInterface;
use App\Enums\EstadoBien;
use App\Enums\TipoMantenimiento;
use App\Models\InventarioTi\MantenimientoBien;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Registrar un mantenimiento (fase 1.5). La fecha es obligatoria: solo los que
 * vinieron del Excel de TIC no la tienen. El número de soporte del sistema
 * antiguo se acepta hasta la fecha de corte (D1).
 */
final class StoreMantenimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El acceso lo resuelve el permiso de la ruta.
    }

    protected function prepareForValidation(): void
    {
        foreach (['descripcion', 'soporte_anterior'] as $campo) {
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
            'bien_informatico_id'  => ['required', 'integer', Rule::exists('bienes_informaticos', 'id')->whereNull('deleted_at')],
            'tipo_mantenimiento'   => ['required', Rule::enum(TipoMantenimiento::class)],
            'realizado_por'        => ['required', Rule::in([MantenimientoBien::POR_TIC, MantenimientoBien::POR_PROVEEDOR])],
            'contrato_vigencia_id' => ['nullable', 'integer', Rule::exists('contratos_vigencia', 'id')->whereNull('deleted_at')],
            'fecha_mantenimiento'  => ['required', 'date', 'before_or_equal:today'],
            'tecnico_id'           => ['nullable', 'integer', 'exists:users,id'],
            'descripcion'          => ['required', 'string', 'min:5', 'max:2000'],
            'costo'                => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'soporte_anterior'     => ['nullable', 'string', 'max:30', $this->soporteAnteriorVigente()],
            // En qué estado queda el equipo. «disponible» o «en_uso» es «vuelve al
            // servicio»: la ficha pone el que toca por la custodia.
            'estado_equipo'        => ['nullable', Rule::enum(EstadoBien::class)->only([
                EstadoBien::DISPONIBLE, EstadoBien::EN_USO, EstadoBien::EN_MANTENIMIENTO, EstadoBien::EN_GARANTIA,
            ])],
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
            'tipo_mantenimiento'   => 'tipo de mantenimiento',
            'realizado_por'        => 'quién lo hizo',
            'contrato_vigencia_id' => 'contrato de vigencia',
            'fecha_mantenimiento'  => 'fecha',
            'tecnico_id'           => 'técnico',
            'descripcion'          => 'qué se hizo',
            'soporte_anterior'     => 'número de soporte anterior',
            'estado_equipo'        => 'estado en que queda el equipo',
        ];
    }
}
