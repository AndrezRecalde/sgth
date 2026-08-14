<?php

namespace App\Services\Viatico;

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
                ->orderBy('codigo_viatico', 'desc')
                ->lockForUpdate()
                ->first();

            $ultimoCodigo = $ultimo ? $ultimo->codigo_viatico : null;

            $secuencial = $ultimoCodigo
                ? (int) substr($ultimoCodigo, strrpos($ultimoCodigo, '-') + 1) + 1
                : 1;

            return sprintf('%s-%d-%05d', $codigoUnidad, $anio, $secuencial);
        });
    }

    /**
     * Determina la unidad base para el prefijo.
     *
     * Antes miraba primero la Comisión que agrupaba al viático. Esa entidad se
     * disolvió dentro del propio viático el 05/06/2026, así que la unidad sale
     * de donde siempre debió salir: el puesto del servidor que viaja.
     */
    private function obtenerCodigoUnidad(Viatico $viatico): string
    {
        if ($viatico->servidor && $viatico->servidor->puesto && $viatico->servidor->puesto->unidadAdministrativa) {
            return $viatico->servidor->puesto->unidadAdministrativa->codigo ?? 'UNID';
        }

        return 'UNID';
    }
}
