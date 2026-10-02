<?php

namespace App\Http\Requests\Sso;

use App\Enums\Permiso;
use App\Services\Sso\PeriodoSso;

class ListarHorasTrabajadasRequest extends ListadoSsoRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permiso::VER_REPORTES_SSO->value)
            || $this->user()->can(Permiso::GESTIONAR_SSO->value);
    }

    protected function reglasDeFiltros(): array
    {
        return [
            'periodo'                  => PeriodoSso::reglasOpcionales(),
            'unidad_administrativa_id' => ['nullable', 'integer', 'exists:unidades_administrativas,id'],
        ];
    }

    protected function filtrosDeclarados(): array
    {
        return [
            'periodo'                  => $this->cadena('periodo'),
            'unidad_administrativa_id' => $this->entero('unidad_administrativa_id'),
        ];
    }
}
