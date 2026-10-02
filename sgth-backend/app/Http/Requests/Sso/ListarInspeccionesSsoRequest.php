<?php

namespace App\Http\Requests\Sso;

use App\Models\Sso\InspeccionSso;

class ListarInspeccionesSsoRequest extends ListadoSsoRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', InspeccionSso::class);
    }

    protected function reglasDeFiltros(): array
    {
        return [
            'unidad_administrativa_id' => ['nullable', 'integer', 'exists:unidades_administrativas,id'],
            'estado'                   => ['nullable', 'boolean'],
        ];
    }

    protected function filtrosDeclarados(): array
    {
        return [
            'unidad_administrativa_id' => $this->entero('unidad_administrativa_id'),
            'estado'                   => $this->booleano('estado'),
        ];
    }
}
