<?php

namespace App\Http\Requests\Sso;

use App\Models\Sso\CapacitacionSso;

class ListarCapacitacionesSsoRequest extends ListadoSsoRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', CapacitacionSso::class);
    }

    protected function reglasDeFiltros(): array
    {
        return [
            'estado' => ['nullable', 'boolean'],
        ];
    }

    protected function filtrosDeclarados(): array
    {
        return [
            'estado' => $this->booleano('estado'),
        ];
    }
}
