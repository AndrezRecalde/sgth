<?php

namespace App\Http\Controllers\Autoservicio;

use App\Contracts\InventarioTi\CustodiaServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Resources\InventarioTi\AsignacionBienResource;
use App\Http\Responses\ApiResponse;
use App\Models\InventarioTi\AsignacionBien;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * «Mis equipos» del Portal (fase 1.3): los equipos que el servidor tiene a su
 * cargo o usa, y el acta de los que tiene a su cargo. Siempre los propios.
 */
final class MisEquiposController extends Controller
{
    public function __construct(private readonly CustodiaServiceInterface $custodia) {}

    public function index(Request $request): JsonResponse
    {
        $servidorId = $request->user()->servidor_id;

        if (! $servidorId) {
            return ApiResponse::ok([], 'Su usuario no está vinculado a un expediente.');
        }

        return ApiResponse::ok(
            AsignacionBienResource::collection($this->custodia->deServidor($servidorId)),
            'Sus equipos.'
        );
    }

    /** El acta es del custodio: quien solo usa el equipo no la firmó. */
    public function acta(Request $request, AsignacionBien $asignacion): Response|JsonResponse
    {
        if (! $request->user()->servidor_id || (int) $asignacion->servidor_id !== (int) $request->user()->servidor_id) {
            return ApiResponse::noEncontrado('Esa custodia no es suya.');
        }

        $acta = $this->custodia->generarActa($asignacion->id);

        return response($acta['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $acta['filename'] . '"',
        ]);
    }
}
