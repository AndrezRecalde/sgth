<?php

namespace App\Services\Viatico;

use App\Models\Viatico\Comision;
use App\Models\Viatico\Viatico;
use Illuminate\Support\Facades\DB;

class CodigoViaticoService
{
    /**
     * Genera un código secuencial para el viático (ej: GTIC-2026-0001)
     */
    public function generarCodigoViatico(Viatico $viatico): string
    {
        $codigoUnidad = $this->obtenerCodigoUnidad($viatico);
        $anio = $viatico->created_at ? $viatico->created_at->format('Y') : date('Y');

        return DB::transaction(function () use ($codigoUnidad, $anio) {
            $ultimo = Viatico::where('codigo_viatico', 'like', "{$codigoUnidad}-{$anio}-%")
                ->lockForUpdate()
                ->max('codigo_viatico');

            $secuencial = $ultimo
                ? (int) substr($ultimo, strrpos($ultimo, '-') + 1) + 1
                : 1;

            return sprintf('%s-%d-%04d', $codigoUnidad, $anio, $secuencial);
        });
    }

    /**
     * Genera un código secuencial para la comisión (ej: COM-GTIC-2026-0001)
     */
    public function generarCodigoComision(Comision $comision): string
    {
        $codigoUnidad = $comision->unidadAdministrativa->codigo ?? 'UNID';
        $anio = $comision->created_at ? $comision->created_at->format('Y') : date('Y');

        return DB::transaction(function () use ($codigoUnidad, $anio) {
            $ultimo = Comision::where('codigo_comision', 'like', "COM-{$codigoUnidad}-{$anio}-%")
                ->lockForUpdate()
                ->max('codigo_comision');

            $secuencial = $ultimo
                ? (int) substr($ultimo, strrpos($ultimo, '-') + 1) + 1
                : 1;

            return sprintf('COM-%s-%d-%04d', $codigoUnidad, $anio, $secuencial);
        });
    }

    /**
     * Determina la unidad base para el prefijo
     */
    private function obtenerCodigoUnidad(Viatico $viatico): string
    {
        if ($viatico->comision_id && $viatico->comision) {
            return $viatico->comision->unidadAdministrativa->codigo ?? 'UNID';
        }

        if ($viatico->servidor && $viatico->servidor->puesto && $viatico->servidor->puesto->unidadAdministrativa) {
            return $viatico->servidor->puesto->unidadAdministrativa->codigo ?? 'UNID';
        }

        return 'UNID';
    }
}
