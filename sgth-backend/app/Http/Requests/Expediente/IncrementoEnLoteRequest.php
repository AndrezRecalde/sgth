<?php

namespace App\Http\Requests\Expediente;

use Illuminate\Foundation\Http\FormRequest;

/**
 * El incremento de los obreros en lote (fase 4.6). Para previsualizarlo basta
 * el modo, el valor y la unidad; para prepararlo, también la fecha, la
 * explicación, el documento de origen y el dictamen presupuestario.
 */
class IncrementoEnLoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function attributes(): array
    {
        return [
            'modo'                        => 'modo del incremento',
            'valor'                       => 'valor del incremento',
            'unidad_administrativa_id'    => 'unidad administrativa',
            'fecha_efectiva'              => 'fecha en que rige',
            'descripcion'                 => 'explicación',
            'documento_origen'            => 'documento de origen',
            'dictamen_presupuestario_ref' => 'dictamen presupuestario',
        ];
    }

    public function rules(): array
    {
        $preparar = ! str_ends_with($this->path(), 'previsualizar');

        return [
            'modo'                        => ['required', 'in:porcentaje,monto'],
            'valor'                       => ['required', 'numeric', 'gt:0', $this->input('modo') === 'porcentaje' ? 'max:100' : 'max:99999'],
            'unidad_administrativa_id'    => ['nullable', 'integer', 'exists:unidades_administrativas,id'],
            'por_contrato_colectivo'      => ['nullable', 'boolean'],
            'fecha_efectiva'              => [$preparar ? 'required' : 'nullable', 'date'],
            'descripcion'                 => [$preparar ? 'required' : 'nullable', 'string', 'max:1000'],
            'documento_origen'            => [$preparar ? 'required' : 'nullable', 'string', 'max:100'],
            'dictamen_presupuestario_ref' => [$preparar ? 'required' : 'nullable', 'string', 'max:255'],
        ];
    }
}
