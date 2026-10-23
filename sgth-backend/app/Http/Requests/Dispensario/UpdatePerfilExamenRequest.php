<?php

namespace App\Http\Requests\Dispensario;

use App\Enums\EspecialidadAtencion;
use App\Models\Dispensario\PerfilExamen;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Corrige un perfil, lo retira (`activo: false`) o lo devuelve. Todo es
 * opcional; si vienen los exámenes, reemplazan a los que tenía.
 */
class UpdatePerfilExamenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $perfil = PerfilExamen::find($this->route('perfil'));
        $especialidad = $this->input('especialidad', $perfil?->especialidad?->value);

        return [
            'especialidad' => ['sometimes', 'string', Rule::in(EspecialidadAtencion::valores())],
            'nombre'       => ['sometimes', 'string', 'max:100',
                Rule::unique('perfiles_examen', 'nombre')
                    ->where('especialidad', $especialidad)
                    ->ignore($perfil?->id),
            ],
            'descripcion'  => ['sometimes', 'nullable', 'string', 'max:300'],
            'ocupacional'  => ['sometimes', 'boolean'],
            'activo'       => ['sometimes', 'boolean'],
            ...StorePerfilExamenRequest::reglasDeExamenes(required: false),
        ];
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [function (Validator $v) {
            $perfil = PerfilExamen::find($this->route('perfil'));

            // Lo que quedará guardado: lo que viene, y si no, lo que había.
            $ocupacional = $this->has('ocupacional')
                ? $this->boolean('ocupacional')
                : (bool) $perfil?->ocupacional;
            $examenes = $this->has('examenes')
                ? (array) $this->input('examenes')
                : ($perfil?->examenes()->pluck('catalogo_examenes.id')->all() ?? []);

            StorePerfilExamenRequest::sinSensiblesSiEsOcupacional($v, $ocupacional, $examenes);
        }];
    }

    public function messages(): array
    {
        return StorePerfilExamenRequest::mensajes();
    }
}
