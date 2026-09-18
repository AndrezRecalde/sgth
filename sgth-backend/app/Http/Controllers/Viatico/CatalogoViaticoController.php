<?php
namespace App\Http\Controllers\Viatico;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Viatico\CatalogoTransporte;
use App\Models\Viatico\CategoriaFactura;
use App\Models\Viatico\EmpresaTransporte;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogoViaticoController extends Controller
{
    public function tiposTransporte(): JsonResponse
    {
        // `con_empresas`: el formulario de tramo pide la empresa solo si las
        // hay. Un vehículo institucional o un taxi no tienen.
        $tipos = CatalogoTransporte::where('activo', true)
            ->withExists(['empresas as con_empresas' => fn ($q) => $q->where('activo', true)])
            ->orderBy('orden')
            ->get();
        return ApiResponse::ok($tipos, 'Tipos de transporte.');
    }

    public function empresasPorTipo(int $tipoId): JsonResponse
    {
        $empresas = EmpresaTransporte::where(
                'catalogo_transporte_id', $tipoId
            )->where('activo', true)
             ->orderBy('orden')
             ->get();
        return ApiResponse::ok($empresas, 'Empresas de transporte.');
    }

    public function categoriasFactura(): JsonResponse
    {
        $categorias = CategoriaFactura::where('activo', true)
            ->orderBy('orden')
            ->get();
        return ApiResponse::ok($categorias, 'Categorías de factura.');
    }
}
