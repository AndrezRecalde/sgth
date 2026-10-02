<?php

namespace App\Http\Requests\Sso;

use App\Models\Sso\EquipoProteccion;

class ListarEquiposProteccionRequest extends ListadoSsoRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', EquipoProteccion::class);
    }

    protected function reglasDeFiltros(): array
    {
        return [
            'tipo'   => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'boolean'],
        ];
    }

    protected function filtrosDeclarados(): array
    {
        return [
            'tipo'   => $this->cadena('tipo'),
            'estado' => $this->booleano('estado'),
        ];
    }
}
