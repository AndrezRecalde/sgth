<?php

namespace App\Http\Controllers\Estructura;

use App\Http\Controllers\Controller;
use App\Http\Requests\Estructura\EscalaRemuneracionRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Estructura\EscalaRemuneracion;
use App\Services\Estructura\EscalaRemuneracionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * La nueva escala de remuneraciones de la LOSEP (fase 7.3b): la prepara quien
 * prepara acciones, y la registra y la revierte el Director de Talento Humano,
 * como una acción.
 */
class EscalaRemuneracionController extends Controller
{
    public function __construct(
        private EscalaRemuneracionService $escalas,
    ) {
    }

    /** Las escalas registradas, la más reciente primero. */
    public function index(): JsonResponse
    {
        return ApiResponse::ok($this->escalas->listar(), 'Escalas de remuneraciones.');
    }

    /** Los grados con su RMU nueva, los vínculos que suben y lo que lo impide. */
    public function previsualizar(EscalaRemuneracionRequest $request): JsonResponse
    {
        return ApiResponse::ok(
            $this->escalas->previsualizar($request->validated(), $request->user()),
            'Vista previa de la escala.',
        );
    }

    public function store(EscalaRemuneracionRequest $request): JsonResponse
    {
        $escala = $this->escalas->registrar($request->validated(), $request->user());

        return ApiResponse::created(
            ['id' => $escala->id, 'vinculos' => $escala->vinculos->count()],
            "Se registró la escala {$escala->instrumento}: subió la remuneración de {$escala->vinculos->count()} vínculo(s).",
        );
    }

    /** La escala registrada por error, entera: el catálogo y cada vínculo vuelven a lo de antes. */
    public function revertir(Request $request, EscalaRemuneracion $escala): JsonResponse
    {
        // Lo mismo que pide el MotivoModal de la pantalla.
        $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:2000']], [], ['motivo' => 'motivo']);

        $revertida = $this->escalas->revertir($escala, $request->string('motivo')->toString(), $request->user());

        return ApiResponse::ok(['id' => $revertida->id], "Se revirtió la escala {$revertida->instrumento}.");
    }
}
