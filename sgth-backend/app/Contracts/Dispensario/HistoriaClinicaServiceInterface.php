<?php

namespace App\Contracts\Dispensario;

use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\HistoriaClinica;

interface HistoriaClinicaServiceInterface
{
    public function crearHistoria(array $datos): HistoriaClinica;

    public function registrarConsulta(array $datos): ConsultaMedica;
}
