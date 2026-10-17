<?php

namespace App\Http\Controllers\Autoservicio;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Expediente\Servidor;
use App\Services\Autoservicio\HojaDeVidaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * «Mi perfil» del Portal: la formación académica y la hoja de vida del propio
 * servidor. Los datos personales se leen del expediente, que ya deja ver el
 * propio (`ServidorPolicy::ver`).
 *
 * El historial académico del expediente está cerrado a Talento Humano: hasta
 * aquí el servidor no tenía cómo ver su propia formación.
 */
final class MiPerfilController extends Controller
{
    public function __construct(private readonly HojaDeVidaService $hojaDeVida) {}

    public function formacion(Request $request): JsonResponse
    {
        $servidor = $this->servidor($request);

        return ApiResponse::ok(
            $servidor ? $this->hojaDeVida->formacion($servidor) : [],
            'Su formación académica.',
        );
    }

    public function hojaDeVida(Request $request): Response|JsonResponse
    {
        $servidor = $this->servidor($request);

        if (! $servidor) {
            return ApiResponse::error('Su usuario no está vinculado a un expediente.', null, 422);
        }

        return response($this->hojaDeVida->pdf($servidor), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->hojaDeVida->nombreArchivo($servidor).'"',
        ]);
    }

    private function servidor(Request $request): ?Servidor
    {
        $servidorId = $request->user()->servidor_id;

        return $servidorId ? Servidor::find($servidorId) : null;
    }
}
