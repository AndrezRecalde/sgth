<?php

namespace App\Http\Requests\Comunicacion;

use Illuminate\Foundation\Http\FormRequest;

/** «Volver a esta versión»: desde cuándo vuelve a regir (hoy, si no se dice). */
class ReutilizarRecursoIdentidadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El acceso lo resuelve el permiso de la ruta.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['vigente_desde' => ['nullable', 'date']];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['vigente_desde' => 'fecha desde la que rige'];
    }
}
