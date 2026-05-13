<?php

namespace App\Services\Sso;

use App\Contracts\Sso\SsoServiceInterface;
use App\Models\Sso\AccidenteTrabajo;
use App\Models\Sso\RiesgoLaboral;
use App\Models\Sso\EquipoProteccion;
use App\Models\Sso\InspeccionSso;
use App\Models\Sso\CapacitacionSso;

final class SsoService implements SsoServiceInterface
{
    public function registrarRiesgoLaboral(array $datos): RiesgoLaboral
    {
        return RiesgoLaboral::create($datos);
    }

    public function actualizarRiesgoLaboral(int $id, array $datos): RiesgoLaboral
    {
        $riesgo = RiesgoLaboral::findOrFail($id);
        $riesgo->update($datos);
        return $riesgo->fresh();
    }

    public function registrarAccidente(array $datos): AccidenteTrabajo
    {
        $accidente = AccidenteTrabajo::create($datos);
        
        // La ficha de salud ocupacional tipo accidente se generará automáticamente 
        // interconectando con el módulo del dispensario
        
        return $accidente;
    }

    public function actualizarAccidente(int $id, array $datos): AccidenteTrabajo
    {
        $accidente = AccidenteTrabajo::findOrFail($id);
        $accidente->update($datos);
        return $accidente->fresh();
    }

    public function cerrarInvestigacionAccidente(int $id, array $datos): AccidenteTrabajo
    {
        $accidente = AccidenteTrabajo::findOrFail($id);
        $datos['estado'] = false; // cerrado
        $accidente->update($datos);
        return $accidente->fresh();
    }

    public function registrarEquipoProteccion(array $datos): EquipoProteccion
    {
        return EquipoProteccion::create($datos);
    }

    public function registrarInspeccion(array $datos): InspeccionSso
    {
        return InspeccionSso::create($datos);
    }

    public function registrarCapacitacion(array $datos): CapacitacionSso
    {
        return CapacitacionSso::create($datos);
    }

    public function calcularIndicadoresMrl(string $periodo): array
    {
        // Lógica para indicadores de SSO: accidentabilidad, gravedad, frecuencia
        return [
            'tasa_accidentabilidad' => 0.0,
            'indice_gravedad' => 0.0,
            'indice_frecuencia' => 0.0,
        ];
    }
}
