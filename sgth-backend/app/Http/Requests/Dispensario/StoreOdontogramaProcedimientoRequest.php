<?php

namespace App\Http\Requests\Dispensario;

use App\Enums\ProcedimientoOdontologico;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOdontogramaProcedimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'odontograma_pieza_id' => ['required', 'integer', 'exists:odontograma_piezas,id'],
            'consulta_medica_id' => ['nullable', 'integer', 'exists:consultas_medicas,id'],
            'procedimiento' => ['required', Rule::enum(ProcedimientoOdontologico::class)],
            'superficie' => ['nullable', 'string', 'max:20'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'fecha' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'exists' => 'El registro seleccionado no existe.',
            'string' => 'El campo :attribute debe ser texto.',
            'date' => 'El campo :attribute debe ser una fecha válida.',
        ];
    }
}
