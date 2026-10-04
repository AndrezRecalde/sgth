<?php

namespace App\Http\Requests\Expediente;

use App\Enums\NacionalidadEstudio;
use App\Enums\NivelEstudio;
use App\Enums\TipoEstudio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreHistorialAcademicoRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'tipo_estudio'         => ['required', new Enum(TipoEstudio::class)],
            'nivel_estudio'        => [
                'nullable',
                new Enum(NivelEstudio::class),
                'required_if:tipo_estudio,estudio',
            ],
            'nacionalidad_estudio' => ['required', new Enum(NacionalidadEstudio::class)],
            'institucion'          => ['required', 'string', 'max:200'],
            'fecha_inicio'         => ['required', 'date'],
            // `after` impedía registrar un curso de un solo día.
            'fecha_fin'            => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'titulo_capacitacion'  => ['required', 'string', 'max:300'],
            'codigo_senescyt'      => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'nivel_estudio.required_if' =>
                'El nivel de estudio es obligatorio para estudios formales.',
            'fecha_fin.after_or_equal' =>
                'La fecha de finalización no puede ser anterior a la de inicio.',
        ];
    }
}