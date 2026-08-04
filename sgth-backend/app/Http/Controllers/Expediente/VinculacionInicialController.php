<?php

namespace App\Http\Controllers\Expediente;

use App\Enums\Permiso;
use App\Http\Controllers\Controller;
use App\Http\Requests\Expediente\StoreVinculacionInicialRequest;
use App\Http\Resources\Expediente\ServidorResource;
use App\Http\Responses\ApiResponse;
use App\Services\Expediente\VinculacionInicialService;
use Illuminate\Http\JsonResponse;

/**
 * Carga inicial de servidores ya vinculados, para migrar lo que Talento Humano
 * administra hoy fuera del sistema.
 *
 * Es una vía excepcional y temporal: se habilita con el permiso
 * 'vincular-servidor-inicial' y se revoca al terminar la migración. Desde
 * entonces todo ingreso vuelve a pasar por la Acción de Personal.
 */
final class VinculacionInicialController extends Controller
{
    public function __construct(
        private readonly VinculacionInicialService $service
    ) {
    }

    public function store(StoreVinculacionInicialRequest $request): JsonResponse
    {
        $this->exigirPermiso();

        $servidor = $this->service->registrar(
            $request->datosServidor(),
            $request->datosVinculo(),
        );

        return ApiResponse::created(
            new ServidorResource($servidor),
            'Servidor y vínculo vigente registrados. El contrato quedó marcado '
                .'como carga inicial: no genera Acción de Personal.'
        );
    }

    /** Cohorte cargada por migración, para revisarla o auditarla. */
    public function index(): JsonResponse
    {
        $this->exigirPermiso();

        return ApiResponse::ok(
            $this->service->listarCargados(),
            'Vínculos registrados por carga inicial.'
        );
    }

    /**
     * Se comprueba con hasPermissionTo() y no con can(), a propósito.
     *
     * AppServiceProvider registra un Gate::before que devuelve true para
     * 'admin-ti' y 'admin-uath', de modo que can() —y con él el middleware
     * 'permission'— aprueba cualquier cosa para esos roles. Como esta vía se
     * salta la Acción de Personal y debe poder revocarse a una persona
     * concreta al terminar la migración, aquí se consulta el permiso real.
     */
    private function exigirPermiso(): void
    {
        abort_unless(
            auth()->user()?->hasPermissionTo(Permiso::VINCULAR_SERVIDOR_INICIAL->value, 'sanctum'),
            403,
            'No tiene habilitada la carga inicial de servidores.'
        );
    }
}
