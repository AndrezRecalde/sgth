<?php

namespace App\Http\Requests\Autoservicio;

use App\Enums\TipoSolicitudAccion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Una solicitud desde el portal (fase 6.3b): qué pide, desde cuándo y su
 * respaldo en PDF. Quién puede pedir qué —y los 15 días de la renuncia— lo
 * decide `SolicitudAccionPersonalService`, que conoce su vínculo.
 */
class PresentarSolicitudAccionRequest extends FormRequest
{
    public const MAX_KB = 10240;

    public function authorize(): bool
    {
        return true; // Siempre sobre el servidor de la cuenta.
    }

    public function attributes(): array
    {
        return [
            'tipo'        => 'tipo de solicitud',
            'causal'      => 'causal de la licencia',
            'fecha_desde' => 'fecha de inicio',
            'fecha_hasta' => 'fecha de fin',
            'motivo'      => 'motivo',
            'archivo'     => 'respaldo',
        ];
    }

    public function rules(): array
    {
        return [
            'tipo'        => ['required', new Enum(TipoSolicitudAccion::class)],
            'causal'      => ['nullable', 'required_if:tipo,licencia_sin_remuneracion', 'string', 'max:40'],
            'fecha_desde' => ['required', 'date'],
            'fecha_hasta' => ['nullable', 'required_if:tipo,licencia_sin_remuneracion', 'date', 'after_or_equal:fecha_desde'],
            'motivo'      => ['nullable', 'string', 'max:1000'],
            'archivo'     => ['required', 'file', 'mimes:pdf', 'max:'.self::MAX_KB],
        ];
    }

    public function messages(): array
    {
        return [
            'archivo.required' => 'Adjunte el respaldo en PDF: la carta firmada, el documento de la causal o el certificado del IESS.',
            'archivo.mimes'    => 'El respaldo tiene que ser un PDF.',
            'archivo.max'      => 'El PDF no puede pasar de 10 MB.',
        ];
    }
}
