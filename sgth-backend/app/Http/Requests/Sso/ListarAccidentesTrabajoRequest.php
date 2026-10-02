<?php

namespace App\Http\Requests\Sso;

use App\Models\Sso\AccidenteTrabajo;

class ListarAccidentesTrabajoRequest extends ListadoSsoRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', AccidenteTrabajo::class);
    }

    protected function reglasDeFiltros(): array
    {
        return [
            'servidor_id' => ['nullable', 'integer', 'exists:servidores,id'],
            'estado'      => ['nullable', 'boolean'],
        ];
    }

    protected function filtrosDeclarados(): array
    {
        return [
            'servidor_id' => $this->entero('servidor_id'),
            'estado'      => $this->booleano('estado'),
        ];
    }
}
