<?php

namespace App\Http\Requests\Asistencia;

use App\Enums\CausaLicencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Registrar una licencia con remuneración (fase 5.1): de quién, por qué, qué
 * días y su respaldo en PDF. El tope y los cruces los mira el servicio.
 */
class StoreLicenciaRequest extends FormRequest
{
    public const MAX_KB = 10240;

    public function authorize(): bool
    {
        return true; // Controlado en la policy
    }

    public function attributes(): array
    {
        return [
            'servidor_id'  => 'servidor',
            'causa'        => 'causa de la licencia',
            'alcance'      => 'caso',
            'fecha_inicio' => 'fecha de inicio',
            'fecha_fin'    => 'fecha de fin',
            'observacion'  => 'observación',
            'archivo'      => 'respaldo',
        ];
    }

    public function rules(): array
    {
        return [
            'servidor_id'  => ['required', 'integer', 'exists:servidores,id'],
            'causa'        => ['required', new Enum(CausaLicencia::class)],
            'alcance'      => ['nullable', 'string', 'max:40'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin'    => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'observacion'  => ['nullable', 'string', 'max:1000'],
            'archivo'      => ['required', 'file', 'mimes:pdf', 'max:'.self::MAX_KB],
        ];
    }

    public function messages(): array
    {
        return [
            'archivo.required' => 'Adjunte el respaldo en PDF: el certificado, la partida o el acta.',
            'archivo.mimes'    => 'El respaldo tiene que ser un PDF.',
            'archivo.max'      => 'El PDF no puede pasar de 10 MB.',
        ];
    }
}
