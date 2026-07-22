<?php

namespace App\Services\Expediente;

use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use Carbon\Carbon;

class MovimientoPersonalService
{
    public function registrar(int $servidorId, array $datos): MovimientoPersonal
    {
        $servidor = Servidor::with('contratoVigente')->findOrFail($servidorId);
        $tipoMovimiento = TipoMovimientoPersonal::from($datos['tipo_movimiento']);

        if ($tipoMovimiento->esAccionDePersonal()) {
            $this->validarElegibilidad($servidor, $tipoMovimiento);
        }

        if ($tipoMovimiento === TipoMovimientoPersonal::COMISION_SIN_REMUNERACION) {
            $this->validarComisionSinRemuneracion($servidor, $datos);
        }

        $datos['servidor_id']     = $servidorId;
        $datos['autorizado_por']  = auth()->id();

        return MovimientoPersonal::create($datos)
            ->load(['unidadOrigen', 'unidadDestino', 'puestoOrigen.cargo', 'puestoDestino.cargo', 'autorizadoPor']);
    }

    private function validarElegibilidad(
        Servidor $servidor,
        TipoMovimientoPersonal $tipoMovimiento
    ): void {
        $tipoNombramiento = $servidor->contratoVigente?->tipo_nombramiento;

        if (!$tipoNombramiento instanceof TipoNombramiento) {
            throw new ReglaNegocioException(
                'El servidor no tiene un contrato vigente con tipo de nombramiento definido.'
            );
        }

        if (!$tipoMovimiento->elegiblePara($tipoNombramiento)) {
            throw new ReglaNegocioException(
                "\"{$tipoMovimiento->etiqueta()}\" no aplica para el tipo de nombramiento vigente del servidor ({$tipoNombramiento->etiqueta()})."
            );
        }
    }

    private function validarComisionSinRemuneracion(Servidor $servidor, array $datos): void
    {
        if (empty($datos['fecha_inicio']) || empty($datos['fecha_fin'])) {
            throw new ReglaNegocioException(
                'La comisión de servicios sin remuneración requiere fecha de inicio y fecha de fin.'
            );
        }

        if (!$servidor->fecha_ingreso_institucion) {
            throw new ReglaNegocioException(
                'El servidor no tiene fecha de ingreso a la institución registrada.'
            );
        }

        $aniosAntiguedad = Carbon::parse($servidor->fecha_ingreso_institucion)
            ->diffInYears(now());

        if ($aniosAntiguedad <= 2) {
            throw new ReglaNegocioException(
                'La comisión de servicios sin remuneración requiere más de 2 años de antigüedad en la institución.'
            );
        }

        $duracionAnios = Carbon::parse($datos['fecha_inicio'])
            ->diffInYears(Carbon::parse($datos['fecha_fin']));

        if ($duracionAnios < 1 || $duracionAnios > 6) {
            throw new ReglaNegocioException(
                'La comisión de servicios sin remuneración debe durar entre 1 y 6 años.'
            );
        }
    }
}
