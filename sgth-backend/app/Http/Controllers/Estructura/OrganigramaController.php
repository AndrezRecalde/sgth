<?php

namespace App\Http\Controllers\Estructura;

use App\Contracts\Estructura\EstructuraServiceInterface;
use App\Enums\Permiso;
use App\Http\Controllers\Controller;
use App\Http\Resources\Estructura\UnidadAdministrativaResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OrganigramaController extends Controller
{
    public function __construct(
        private readonly EstructuraServiceInterface $estructuraService
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        Gate::authorize(Permiso::VER_ESTRUCTURA->value);
        
        $organigrama = $this->estructuraService->obtenerOrganigrama();
        
        return ApiResponse::ok(
            UnidadAdministrativaResource::collection($organigrama),
            'Organigrama institucional obtenido exitosamente'
        );
    }
}
