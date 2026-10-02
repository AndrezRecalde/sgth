<?php

namespace App\Http\Requests\Sso;

use App\Enums\Permiso;

class ListarEppEntregasRequest extends ListadoSsoRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permiso::VER_REPORTES_SSO->value)
            || $this->user()->can(Permiso::GESTIONAR_SSO->value);
    }

    protected function reglasDeFiltros(): array
    {
        return [
            'servidor_id'          => ['nullable', 'integer', 'exists:servidores,id'],
            'equipo_proteccion_id' => ['nullable', 'integer', 'exists:equipos_proteccion,id'],
            'fecha_inicio'         => ['nullable', 'date'],
            'fecha_fin'            => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
        ];
    }

    protected function filtrosDeclarados(): array
    {
        return [
            'servidor_id'          => $this->entero('servidor_id'),
            'equipo_proteccion_id' => $this->entero('equipo_proteccion_id'),
            'fecha_inicio'         => $this->cadena('fecha_inicio'),
            'fecha_fin'            => $this->cadena('fecha_fin'),
        ];
    }
}
