<?php

namespace App\Http\Requests\Expediente;

use App\Enums\EstadoContrato;
use App\Enums\TipoNombramiento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreContratoServidorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo_nombramiento'        => ['required', new Enum(TipoNombramiento::class)],
            'numero_contrato'          => 'nullable|string|max:100',
            'unidad_administrativa_id' => 'required|exists:unidades_administrativas,id',
            'puesto_id'                => 'required|exists:puestos,id',
            'fecha_inicio'             => 'required|date',
            'fecha_fin'                => 'nullable|date|after_or_equal:fecha_inicio',
            'resolucion_numero'        => 'nullable|string|max:100',
            'puede_marcar'             => 'nullable|boolean',
            'estado'                   => ['required', new Enum(EstadoContrato::class)],
            'archivo_contrato'         => 'nullable|file|mimes:pdf|max:5120',
            'remuneracion'             => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
        ];
    }
}
