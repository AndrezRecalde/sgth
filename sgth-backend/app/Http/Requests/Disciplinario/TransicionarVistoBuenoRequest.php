<?php

namespace App\Http\Requests\Disciplinario;

use App\Enums\EstadoVistoBueno;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class TransicionarVistoBuenoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'estado'             => ['required', new Enum(EstadoVistoBueno::class)],
            'fecha_notificacion' => ['nullable', 'date'],
            'fecha_resolucion'   => ['nullable', 'date'],
            // Obligatorio al resolver; lo exige VistoBuenoService para dar un
            // mensaje de negocio en vez de un error de validación genérico.
            'resolucion_detalle' => ['nullable', 'string', 'max:5000'],
            'numero_tramite_mdt' => ['nullable', 'string', 'max:50'],
            'inspectoria'        => ['nullable', 'string', 'max:150'],
            'inspector_nombre'   => ['nullable', 'string', 'max:150'],
            'documento_respaldo' => ['nullable', 'string', 'max:255'],
        ];
    }
}
