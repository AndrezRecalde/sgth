<?php

namespace App\Http\Controllers\Estructura;

use App\Http\Controllers\Controller;
use App\Http\Requests\Estructura\CopiarPerfilPuestoRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Estructura\Puesto;
use App\Services\Estructura\CopiaDelPerfilService;
use Illuminate\Http\JsonResponse;

/**
 * Copiar el perfil de otro puesto (docs/diseno-manual-de-puestos.md, P6,
 * PR 15). Escribe quien edita el puesto (`gestionar-puestos`, en la petición);
 * del otro puesto basta con verlo.
 */
final class CopiaDelPerfilController extends Controller
{
    public function __construct(private readonly CopiaDelPerfilService $copias)
    {
    }

    /** Lo que se copiaría y lo que se reemplaza, sin tocar nada. */
    public function show(CopiarPerfilPuestoRequest $request, int $puestoId): JsonResponse
    {
        [$destino, $origen] = $this->puestos($request, $puestoId);

        return ApiResponse::ok($this->copias->previsualizar($destino, $origen), 'Vista previa de la copia.');
    }

    public function store(CopiarPerfilPuestoRequest $request, int $puestoId): JsonResponse
    {
        [$destino, $origen] = $this->puestos($request, $puestoId);

        $copiado = $this->copias->copiar($destino, $origen, $request->user());

        // Solo lo que trajo: «Copiado de «Analista de Bodega»: la misión y 2 actividades.»
        $partes = array_values(array_filter([
            $copiado['mision'] ? 'la misión' : null,
            $this->cuenta($copiado['requisitos'], 'alternativa de instrucción', 'alternativas de instrucción'),
            $this->cuenta($copiado['actividades'], 'actividad', 'actividades'),
            $this->cuenta($copiado['competencias'], 'competencia', 'competencias'),
        ]));
        $mensaje = sprintf(
            'Copiado de «%s»: %s.',
            $origen->cargo?->nombre ?? "puesto {$origen->id}",
            match (count($partes)) {
                0       => 'los textos del perfil',
                1       => $partes[0],
                default => implode(', ', array_slice($partes, 0, -1)).' y '.end($partes),
            },
        );
        if ($ajuste = $copiado['ajuste_de_nivel']) {
            $mensaje .= sprintf(
                ' %s del nivel %s %s al %s, el del grado de este puesto.',
                $ajuste['competencias'] === 1 ? 'Una competencia' : "{$ajuste['competencias']} competencias",
                $ajuste['de'],
                $ajuste['competencias'] === 1 ? 'pasó' : 'pasaron',
                $ajuste['a'],
            );
        }

        return ApiResponse::ok($copiado, $mensaje);
    }

    private function cuenta(int $n, string $singular, string $plural): ?string
    {
        return $n > 0 ? "{$n} ".($n === 1 ? $singular : $plural) : null;
    }

    /** @return array{0: Puesto, 1: Puesto} */
    private function puestos(CopiarPerfilPuestoRequest $request, int $puestoId): array
    {
        $destino = Puesto::findOrFail($puestoId);
        $origen  = Puesto::findOrFail((int) $request->validated('origen_id'));
        $this->authorize('view', $origen);

        return [$destino, $origen];
    }
}
