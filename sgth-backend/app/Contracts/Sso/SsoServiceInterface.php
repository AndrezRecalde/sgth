<?php

namespace App\Contracts\Sso;

use App\Models\Sso\AccidenteTrabajo;
use App\Models\Sso\CapacitacionSso;
use App\Models\Sso\EquipoProteccion;
use App\Models\Sso\InspeccionSso;
use App\Models\Sso\RiesgoLaboral;

interface SsoServiceInterface
{
    public function registrarRiesgoLaboral(array $datos): RiesgoLaboral;

    public function actualizarRiesgoLaboral(int $id, array $datos): RiesgoLaboral;

    public function registrarAccidente(array $datos): AccidenteTrabajo;

    public function actualizarAccidente(int $id, array $datos): AccidenteTrabajo;

    public function cerrarInvestigacionAccidente(int $id, array $datos): AccidenteTrabajo;

    public function registrarEquipoProteccion(array $datos): EquipoProteccion;

    public function registrarInspeccion(array $datos): InspeccionSso;

    public function registrarCapacitacion(array $datos): CapacitacionSso;

    public function calcularIndicadoresMrl(string $periodo): array;
}
