<?php

namespace App\Http\Requests\Seleccion;

use Illuminate\Foundation\Http\FormRequest;

class DeclararGanadorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Controlado por middleware de rol en la ruta (ej: admin-uath)
    }

    /**
     * Acepta uno o varios ganadores. 'postulante_ganador_id' se conserva como
     * atajo de un solo elemento para no romper a quien ya llamaba así.
     */
    public function rules(): array
    {
        return [
            'postulante_ganador_ids'   => ['required_without:postulante_ganador_id', 'array', 'min:1'],
            'postulante_ganador_ids.*' => ['integer', 'exists:postulantes,id'],
            'postulante_ganador_id'    => ['required_without:postulante_ganador_ids', 'integer', 'exists:postulantes,id'],
        ];
    }

    /** @return list<int> */
    public function ganadores(): array
    {
        $ids = $this->validated('postulante_ganador_ids')
            ?? [$this->validated('postulante_ganador_id')];

        return array_values(array_unique(array_map('intval', $ids)));
    }

    public function messages(): array
    {
        return [
            'postulante_ganador_ids.required_without' => 'Debe especificar al menos un postulante ganador.',
            'postulante_ganador_id.required_without'  => 'Debe especificar al menos un postulante ganador.',
            'postulante_ganador_ids.*.exists'         => 'Alguno de los postulantes especificados no existe.',
            'postulante_ganador_id.exists'            => 'El postulante especificado no existe.',
        ];
    }
}
