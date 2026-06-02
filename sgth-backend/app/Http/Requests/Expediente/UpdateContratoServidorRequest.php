<?php

namespace App\Http\Requests\Expediente;

use App\Enums\EstadoContrato;
use App\Enums\TipoNombramiento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateContratoServidorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo_nombramiento'        => ['sometimes', 'required', new Enum(TipoNombramiento::class)],
            'numero_contrato'          => 'nullable|string|max:100',
            'unidad_administrativa_id' => 'sometimes|required|exists:unidades_administrativas,id',
            'puesto_id'                => 'sometimes|required|exists:puestos,id',
            'fecha_inicio'             => 'sometimes|required|date',
            'fecha_fin'                => 'nullable|date|after_or_equal:fecha_inicio',
            'resolucion_numero'        => 'nullable|string|max:100',
            'puede_marcar'             => 'nullable|boolean',
            'estado'                   => ['sometimes', 'required', new Enum(EstadoContrato::class)],
            'archivo_contrato'         => 'nullable|file|mimes:pdf|max:5120',
            'remuneracion'             => 'nullable|numeric|min:0|max:99999.99',
        ];
    }
}
