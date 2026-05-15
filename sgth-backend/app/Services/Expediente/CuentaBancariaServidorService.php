<?php

namespace App\Services\Expediente;

use App\Models\Expediente\CuentaBancariaServidor;
use Illuminate\Support\Facades\DB;

class CuentaBancariaServidorService
{
    /**
     * Crea o actualiza una cuenta bancaria y gestiona la regla de unicidad para cuentas principales.
     */
    public function guardarCuenta(int $servidorId, array $datos, ?int $cuentaId = null): CuentaBancariaServidor
    {
        return DB::transaction(function () use ($servidorId, $datos, $cuentaId) {
            
            $esPrincipalSueldo = $datos['es_principal_sueldo'] ?? false;
            $esPrincipalViatico = $datos['es_principal_viatico'] ?? false;

            if ($esPrincipalSueldo) {
                CuentaBancariaServidor::where('servidor_id', $servidorId)
                    ->when($cuentaId, fn($q) => $q->where('id', '!=', $cuentaId))
                    ->update(['es_principal_sueldo' => false]);
            }

            if ($esPrincipalViatico) {
                CuentaBancariaServidor::where('servidor_id', $servidorId)
                    ->when($cuentaId, fn($q) => $q->where('id', '!=', $cuentaId))
                    ->update(['es_principal_viatico' => false]);
            }

            $datosCuenta = array_merge($datos, [
                'servidor_id' => $servidorId,
            ]);

            if ($cuentaId) {
                $cuenta = CuentaBancariaServidor::findOrFail($cuentaId);
                $cuenta->update($datosCuenta);
                return $cuenta;
            }

            return CuentaBancariaServidor::create($datosCuenta);
        });
    }
}
