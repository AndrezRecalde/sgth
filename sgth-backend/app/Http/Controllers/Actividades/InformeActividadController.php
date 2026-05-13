<?php
namespace App\Http\Controllers\Actividades;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Contracts\Actividades\ActividadesServiceInterface;
use Illuminate\Http\Request;

class InformeActividadController extends Controller
{
    public function __construct(private readonly ActividadesServiceInterface $service)
    {
    }
    public function generar(Request $request)
    {
        return ApiResponse::ok($this->service->generarInformeMensual($request->servidor_id, $request->mes, $request->anio), 'Informe generado correctamente');
    }
}