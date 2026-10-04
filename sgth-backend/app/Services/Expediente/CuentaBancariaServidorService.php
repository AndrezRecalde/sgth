<?php

namespace App\Services\Expediente;

use App\Exceptions\ReglaNegocioException;
use App\Models\Expediente\CuentaBancariaServidor;
use Illuminate\Support\Facades\DB;

/**
 * Las cuentas bancarias de un servidor y cuál es la principal de cada cosa:
 * la de nómina y la de viáticos.
 *
 * Hasta el 2026-10-03 podía no haber ninguna: la primera cuenta no se marcaba
 * sola, y borrar la principal o desmarcarla dejaba el hueco sin aviso. El
 * informe de viáticos busca `es_principal_viatico` e imprimía «—». Ahora,
 * tras cada cambio, `asegurarPrincipales()` deja exactamente una por
 * propósito mientras haya alguna cuenta activa que pague eso.
 */
class CuentaBancariaServidorService
{
    /** Qué propósitos de cuenta pueden ser la principal de cada campo. */
    private const COMPATIBLES = [
        'es_principal_sueldo'  => ['sueldo', 'ambos'],
        'es_principal_viatico' => ['viaticos', 'ambos'],
    ];

    /**
     * Crea o actualiza una cuenta bancaria y gestiona la regla de unicidad para cuentas principales.
     */
    public function guardarCuenta(int $servidorId, array $datos, ?int $cuentaId = null): CuentaBancariaServidor
    {
        $this->validarCompatibilidad($datos['proposito'] ?? null, $datos);

        return DB::transaction(function () use ($servidorId, $datos, $cuentaId) {
            foreach (array_keys(self::COMPATIBLES) as $campo) {
                if ($datos[$campo] ?? false) {
                    CuentaBancariaServidor::where('servidor_id', $servidorId)
                        ->when($cuentaId, fn($q) => $q->where('id', '!=', $cuentaId))
                        ->update([$campo => false]);
                }
            }

            $datosCuenta = array_merge($datos, [
                'servidor_id' => $servidorId,
            ]);

            if ($cuentaId) {
                $cuenta = CuentaBancariaServidor::findOrFail($cuentaId);
                $cuenta->update($datosCuenta);
            } else {
                $cuenta = CuentaBancariaServidor::create($datosCuenta);
            }

            $this->asegurarPrincipales($servidorId);

            return $cuenta->fresh();
        });
    }

    public function actualizarCuenta(CuentaBancariaServidor $cuenta, array $datos): CuentaBancariaServidor
    {
        return $this->guardarCuenta($cuenta->servidor_id, $datos, $cuenta->id);
    }

    /** Borra una cuenta; si era la principal de algo, pasa a serlo otra. */
    public function eliminarCuenta(CuentaBancariaServidor $cuenta): void
    {
        DB::transaction(function () use ($cuenta) {
            $cuenta->delete();
            $this->asegurarPrincipales($cuenta->servidor_id);
        });
    }

    public function marcarComoPrincipal(CuentaBancariaServidor $cuenta, string $proposito): void
    {
        $campo = $proposito === 'sueldo' ? 'es_principal_sueldo' : 'es_principal_viatico';
        $this->validarCompatibilidad($cuenta->proposito, [$campo => true]);

        DB::transaction(function () use ($cuenta, $campo) {
            CuentaBancariaServidor::where('servidor_id', $cuenta->servidor_id)
                ->where('id', '!=', $cuenta->id)
                ->update([$campo => false]);

            $cuenta->update([$campo => true]);
        });
    }

    /**
     * Una cuenta solo es la principal de lo que paga. La regla vivía solo en
     * el formulario: por API, una cuenta «solo viáticos» quedaba como la
     * principal de nómina.
     */
    private function validarCompatibilidad(?string $proposito, array $datos): void
    {
        foreach (self::COMPATIBLES as $campo => $admitidos) {
            if (($datos[$campo] ?? false) && ! in_array($proposito, $admitidos, true)) {
                throw new ReglaNegocioException($campo === 'es_principal_sueldo'
                    ? 'Una cuenta solo de viáticos no puede ser la principal de nómina.'
                    : 'Una cuenta solo de nómina no puede ser la principal de viáticos.');
            }
        }
    }

    /**
     * Exactamente una principal por propósito, si hay alguna cuenta activa que
     * lo pague: se quita la marca a la que ya no lo paga y, si no queda
     * ninguna, la toma la más antigua de las compatibles.
     */
    private function asegurarPrincipales(int $servidorId): void
    {
        foreach (self::COMPATIBLES as $campo => $admitidos) {
            CuentaBancariaServidor::where('servidor_id', $servidorId)
                ->where($campo, true)
                ->where(fn ($q) => $q->whereNotIn('proposito', $admitidos)->orWhere('estado', false))
                ->update([$campo => false]);

            $compatibles = CuentaBancariaServidor::where('servidor_id', $servidorId)
                ->whereIn('proposito', $admitidos)
                ->where('estado', true);

            if (! (clone $compatibles)->where($campo, true)->exists()) {
                $compatibles->orderBy('id')->first()?->update([$campo => true]);
            }
        }
    }
}
