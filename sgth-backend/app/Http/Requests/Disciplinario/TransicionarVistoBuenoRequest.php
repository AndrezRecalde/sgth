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
            // Al impugnar: el juicio o la causa y su fecha (2026-10-04). Los
            // exige VistoBuenoService, que es quien sabe a qué estado se va.
            // La resolución del Inspector ya no viaja aquí como texto: se
            // sube como PDF por POST vistos-buenos/{id}/documento.
            'impugnacion_referencia' => ['nullable', 'string', 'max:200'],
            'fecha_impugnacion'      => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha_impugnacion.before_or_equal' => 'La impugnación no puede tener fecha futura.',
        ];
    }
}
