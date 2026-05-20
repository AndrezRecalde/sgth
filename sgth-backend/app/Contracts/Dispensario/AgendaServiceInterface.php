<?php

namespace App\Contracts\Dispensario;

use App\Models\Dispensario\AgendaMedica;

interface AgendaServiceInterface
{
    public function agendarCita(array $datos): AgendaMedica;
}
