<?php

namespace App\Http\Requests\Expediente;

use App\Enums\TipoDeclaracion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreDeclaracionJuramentadaRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            // Una declaración ya presentada: no puede tener fecha futura.
            'fecha_declaracion' => ['required', 'date', 'before_or_equal:today'],
            // El código de barras identifica la declaración en Contraloría:
            // el mismo dos veces en un expediente salía dos veces en el TXT.
            'codigo_barras'     => [
                'required', 'string', 'max:100',
                Rule::unique('declaraciones_juramentadas', 'codigo_barras')
                    ->where('servidor_id', $this->route('servidorId'))
                    ->withoutTrashed()
                    ->ignore($this->route('id')),
            ],
            'tipo_declaracion'  => ['required', new Enum(TipoDeclaracion::class)],
            'documento'         => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha_declaracion.before_or_equal' => 'La fecha de la declaración no puede ser futura.',
            'codigo_barras.unique'              => 'Ese código de barras ya está registrado en este expediente.',
            'documento.mimes' => 'El documento debe ser un archivo PDF.',
            'documento.max'   => 'El documento no debe superar los 10 MB.',
        ];
    }
}
