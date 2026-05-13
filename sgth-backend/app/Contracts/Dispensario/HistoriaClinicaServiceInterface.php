<?php
namespace App\Contracts\Dispensario;

use App\Models\Dispensario\HistoriaClinica;
use App\Models\Dispensario\ConsultaMedica;

interface HistoriaClinicaServiceInterface
{
    public function crearHistoria(array $datos): HistoriaClinica;
    public function registrarConsulta(array $datos): ConsultaMedica;
}
