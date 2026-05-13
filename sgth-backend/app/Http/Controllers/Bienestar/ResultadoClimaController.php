<?php
namespace App\Http\Controllers\Bienestar;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Contracts\Bienestar\BienestarServiceInterface;
use Illuminate\Http\Request;
class ResultadoClimaController extends Controller
{
    public function __construct(private readonly BienestarServiceInterface $service)
    {
    }
    public function storeRespuestaAnonima(Request $request)
    {
        $this->service->registrarRespuestaAnonima($request->all());
        return ApiResponse::created([], 'Gracias por su participación. Su respuesta anónima ha sido registrada.');
    }
    public function reporteUnidad(int $encuestaId, int $unidadId)
    {
        $datos = $this->service->obtenerResultadosAgregadosPorUnidad($encuestaId, $unidadId);
        return ApiResponse::ok($datos, 'Reporte agregado por unidad.');
    }
}