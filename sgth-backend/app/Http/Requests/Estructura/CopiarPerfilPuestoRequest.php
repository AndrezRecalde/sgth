<?php

namespace App\Http\Requests\Estructura;

use App\Models\Estructura\Puesto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * El puesto del que se copia el perfil (docs/diseno-manual-de-puestos.md, P6,
 * PR 15). Sirve a la vista previa y a la copia.
 */
class CopiarPerfilPuestoRequest extends FormRequest
{
    /**
     * `PuestoPolicy::update` del puesto que recibe la copia, antes de validar:
     * quien no puede editarlo recibe el 403, no un 422 por los datos.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', Puesto::findOrFail((int) $this->route('puestoId')));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'origen_id' => ['required', 'integer', Rule::exists('puestos', 'id')->whereNull('deleted_at')],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['origen_id' => 'puesto'];
    }
}
