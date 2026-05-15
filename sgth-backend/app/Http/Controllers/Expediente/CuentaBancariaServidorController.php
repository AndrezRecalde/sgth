<?php

namespace App\Http\Controllers\Expediente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expediente\StoreCuentaBancariaServidorRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Expediente\CuentaBancariaServidor;
use App\Services\Expediente\CuentaBancariaServidorService;
use Illuminate\Http\Request;

class CuentaBancariaServidorController extends Controller
{
    public function index(int $servidorId)
    {
        $cuentas = CuentaBancariaServidor::with('entidadFinanciera')
            ->where('servidor_id', $servidorId)
            ->get();
        return ApiResponse::ok($cuentas, 'Cuentas bancarias listadas exitosamente.');
    }

    public function store(StoreCuentaBancariaServidorRequest $request, int $servidorId, CuentaBancariaServidorService $service)
    {
        $cuenta = $service->guardarCuenta($servidorId, $request->validated());
        return ApiResponse::created($cuenta, 'Cuenta bancaria agregada exitosamente.');
    }

    public function update(StoreCuentaBancariaServidorRequest $request, int $servidorId, int $cuentaId, CuentaBancariaServidorService $service)
    {
        $cuenta = CuentaBancariaServidor::where('servidor_id', $servidorId)->findOrFail($cuentaId);
        $cuentaActualizada = $service->actualizarCuenta($cuenta, $request->validated());
        return ApiResponse::ok($cuentaActualizada, 'Cuenta bancaria actualizada exitosamente.');
    }

    public function destroy(int $servidorId, int $cuentaId)
    {
        $cuenta = CuentaBancariaServidor::where('servidor_id', $servidorId)->findOrFail($cuentaId);
        $cuenta->delete();
        return ApiResponse::ok(null, 'Cuenta bancaria eliminada exitosamente.');
    }

    public function setPrincipal(Request $request, int $servidorId, int $cuentaId, CuentaBancariaServidorService $service)
    {
        $request->validate([
            'proposito' => 'required|in:sueldo,viatico'
        ]);

        $cuenta = CuentaBancariaServidor::where('servidor_id', $servidorId)->findOrFail($cuentaId);
        $service->marcarComoPrincipal($cuenta, $request->proposito);

        return ApiResponse::ok(null, 'Cuenta marcada como principal exitosamente.');
    }
}
