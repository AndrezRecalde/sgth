<?php

namespace App\Contracts\Dispensario;

use App\Models\Dispensario\AgendaMedica;
use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\HistoriaClinica;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface HistoriaClinicaServiceInterface
{
    public function listar(array $filtros): LengthAwarePaginator;

    public function obtener(int $id): HistoriaClinica;

    public function crearHistoria(array $datos): HistoriaClinica;

    /** La historia del paciente de un turno, abriéndola si no la tiene. */
    public function paraPacienteDeTurno(
        AgendaMedica $agenda
    ): HistoriaClinica;

    public function registrarConsulta(array $datos): ConsultaMedica;

    public function actualizarConsulta(
        int $consultaId,
        array $datos
    ): ConsultaMedica;

    public function obtenerContextoConsulta(
        int $historiaClinicaId,
        ?int $agendaMedicaId = null
    ): array;
}
