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
 * La hoja de vida de «Mi perfil». Los datos personales se leen del expediente,
 * que ya deja ver el propio (`ServidorPolicy::ver`), y la formación, de
 * `MiFormacionController`.
 */
final class MiPerfilController extends Controller
{
    public function __construct(private readonly HojaDeVidaService $hojaDeVida) {}

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
