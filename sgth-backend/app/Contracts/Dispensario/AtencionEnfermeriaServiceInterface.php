<?php

namespace App\Contracts\Dispensario;

use App\Models\Dispensario\AtencionEnfermeria;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AtencionEnfermeriaServiceInterface
{
    public function listar(array $filtros): LengthAwarePaginator;

    public function registrar(array $datos, int $enfermeraId): AtencionEnfermeria;
}
