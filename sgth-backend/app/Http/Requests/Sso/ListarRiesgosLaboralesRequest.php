<?php

namespace App\Http\Requests\Sso;

use App\Models\Sso\RiesgoLaboral;

class ListarRiesgosLaboralesRequest extends ListadoSsoRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', RiesgoLaboral::class);
    }

    protected function reglasDeFiltros(): array
    {
        return [
            'puesto_id' => ['nullable', 'integer', 'exists:puestos,id'],
            'estado'    => ['nullable', 'boolean'],
        ];
    }

    protected function filtrosDeclarados(): array
    {
        return [
            'puesto_id' => $this->entero('puesto_id'),
            'estado'    => $this->booleano('estado'),
        ];
    }
}
