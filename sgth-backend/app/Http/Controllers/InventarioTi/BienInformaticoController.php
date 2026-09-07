<?php
namespace App\Http\Controllers\InventarioTi;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Contracts\InventarioTi\InventarioTiServiceInterface;
use App\Http\Requests\InventarioTi\StoreBienInformaticoRequest;
use App\Http\Requests\InventarioTi\UpdateBienInformaticoRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

final class BienInformaticoController extends Controller
{
    public function __construct(
        private readonly InventarioTiServiceInterface $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $bienes = $this->service->listarBienes($request->all());

        return ApiResponse::paginado($bienes, 'Bienes listados');
    }

    public function store(StoreBienInformaticoRequest $request): JsonResponse
    {
        $bien = $this->service->registrarBien($request->validated());

        // Decía «Bien registrado y QR generado». Lo que se genera es el código
        // con el que la auditoría busca el bien al escanear; la etiqueta física
        // no la produce el sistema.
        return ApiResponse::created($bien, 'Bien registrado');
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::ok($this->service->obtenerBien($id), 'Detalle de bien');
    }

    public function update(UpdateBienInformaticoRequest $request, int $id): JsonResponse
    {
        $bien = $this->service->actualizarBien($id, $request->validated());

        return ApiResponse::ok($bien, 'Bien actualizado');
    }

    /**
     * Borra la ficha de un bien registrado por error.
     *
     * Respondía «Bien dado de baja» sin borrar nada, y encima nombraba otra
     * cosa: la baja retira el bien del servicio, exige motivo y va por
     * «bajas»; el bien sigue en el inventario. Esto borra la ficha, y solo
     * mientras no tenga historial.
     */
    public function destroy(int $id): JsonResponse
    {
        $this->service->retirarBien($id);

        return ApiResponse::ok(null, 'Bien retirado del inventario');
    }

    /**
     * La vida del bien: quién lo ha tenido y qué se le ha hecho.
     *
     * La ruta declaraba este método y el controlador no lo tenía, así que
     * `bienes/{id}/historial` devolvía un 500 desde que se escribió.
     */
    public function historial(int $id): JsonResponse
    {
        $ficha = $this->service->obtenerFichaTecnicaCompleta([
            'bien_informatico_id' => $id,
        ]);

        return ApiResponse::ok($ficha, 'Historial del bien informático.');
    }
}
