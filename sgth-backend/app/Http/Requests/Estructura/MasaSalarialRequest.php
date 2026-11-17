<?php

namespace App\Http\Requests\Estructura;

use App\Enums\RegimenLaboral;
use App\Enums\TipoNombramiento;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** La fecha de corte y los filtros de la masa salarial. */
class MasaSalarialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El acceso lo resuelve el permiso de la ruta.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Hasta hoy: un corte futuro sería una proyección, y la masa anual
            // es la mensual por doce (D8).
            'corte'             => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'regimen'           => ['nullable', Rule::enum(RegimenLaboral::class)],
            'tipo_nombramiento' => ['nullable', Rule::enum(TipoNombramiento::class)],
            // Con todo lo que cuelga de ella.
            'unidad_id'         => ['nullable', 'integer', 'exists:unidades_administrativas,id'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'corte'             => 'fecha de corte',
            'regimen'           => 'régimen',
            'tipo_nombramiento' => 'tipo de nombramiento',
            'unidad_id'         => 'unidad administrativa',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'corte.before_or_equal' => 'La fecha de corte no puede ser posterior a hoy.',
        ];
    }

    public function corte(): CarbonImmutable
    {
        return $this->filled('corte') ? CarbonImmutable::parse($this->string('corte')) : CarbonImmutable::today();
    }

    /** @return array{regimen: ?RegimenLaboral, tipo_nombramiento: ?TipoNombramiento, unidad_id: ?int} */
    public function filtros(): array
    {
        return [
            'regimen'           => $this->enum('regimen', RegimenLaboral::class),
            'tipo_nombramiento' => $this->enum('tipo_nombramiento', TipoNombramiento::class),
            'unidad_id'         => $this->filled('unidad_id') ? $this->integer('unidad_id') : null,
        ];
    }
}
