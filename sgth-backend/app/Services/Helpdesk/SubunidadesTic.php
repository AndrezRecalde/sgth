<?php

namespace App\Services\Helpdesk;

use App\Models\Estructura\UnidadAdministrativa;
use Illuminate\Support\Collection;

/**
 * Las áreas que atienden la mesa de ayuda: las subunidades de la unidad de TIC
 * en la estructura orgánica (D6), por su código en `config/helpdesk.php`.
 */
final class SubunidadesTic
{
    /** @return Collection<int, UnidadAdministrativa> */
    public function todas(): Collection
    {
        $tic = UnidadAdministrativa::where('codigo', config('helpdesk.unidad_tic_codigo'))->first();

        return $tic
            ? UnidadAdministrativa::where('unidad_padre_id', $tic->id)->orderBy('nombre')->get(['id', 'nombre'])
            : collect();
    }

    /** @return list<int> */
    public function ids(): array
    {
        return $this->todas()->pluck('id')->all();
    }
}
