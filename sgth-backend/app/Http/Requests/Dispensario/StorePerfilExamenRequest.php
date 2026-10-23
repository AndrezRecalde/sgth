<?php

namespace App\Http\Requests\Dispensario;

use App\Enums\EspecialidadAtencion;
use App\Models\Dispensario\CatalogoExamen;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Un perfil de exámenes nuevo. */
class StorePerfilExamenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'especialidad' => ['required', 'string', Rule::in(EspecialidadAtencion::valores())],
            'nombre'       => ['required', 'string', 'max:100',
                Rule::unique('perfiles_examen', 'nombre')
                    ->where('especialidad', $this->input('especialidad')),
            ],
            'descripcion'  => ['nullable', 'string', 'max:300'],
            'ocupacional'  => ['sometimes', 'boolean'],
            ...self::reglasDeExamenes(required: true),
        ];
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [fn (Validator $v) => self::sinSensiblesSiEsOcupacional(
            $v, $this->boolean('ocupacional'), (array) $this->input('examenes', [])
        )];
    }

    public function messages(): array
    {
        return self::mensajes();
    }

    /** Lo comparten el alta y la edición. */
    public static function reglasDeExamenes(bool $required): array
    {
        return [
            'examenes'   => [$required ? 'required' : 'sometimes', 'array', 'min:1', 'max:100'],
            'examenes.*' => ['integer', 'distinct',
                Rule::exists('catalogo_examenes', 'id')->where('activo', true),
            ],
        ];
    }

    public static function mensajes(): array
    {
        return [
            'nombre.unique'     => 'Ya hay un perfil con ese nombre en la especialidad.',
            'examenes.required' => 'Un perfil lleva al menos un examen.',
            'examenes.min'      => 'Un perfil lleva al menos un examen.',
            'examenes.*.exists' => 'Ese examen no está activo en el catálogo.',
        ];
    }

    /**
     * Un perfil ocupacional no lleva exámenes sensibles (D8): la prueba de VIH
     * no se puede exigir para obtener o conservar un empleo (AM 00398,
     * reformado por el MDT-2024-198), y el Dispensario extiende el mismo
     * cuidado a los demás sensibles.
     */
    public static function sinSensiblesSiEsOcupacional(Validator $v, bool $ocupacional, array $examenes): void
    {
        if (! $ocupacional || $v->errors()->has('examenes*')) {
            return;
        }

        $sensibles = CatalogoExamen::whereIn('id', $examenes)
            ->where('sensible', true)
            ->orderBy('nombre')
            ->pluck('nombre');

        if ($sensibles->isNotEmpty()) {
            $v->errors()->add(
                'examenes',
                'Un perfil ocupacional no puede llevar exámenes sensibles: '
                    . $sensibles->implode(', ') . '.'
            );
        }
    }
}
