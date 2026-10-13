<?php

namespace App\Http\Controllers\Helpdesk;

use App\Contracts\Helpdesk\HelpdeskServiceInterface;
use App\Enums\Permiso;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Helpdesk\TecnicoDtic;
use App\Models\User;
use App\Services\Helpdesk\SubunidadesTic;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Los técnicos de la mesa de ayuda por subunidad de TIC y nivel (D6; fase
 * 3.5). Atiende tickets quien tiene `gestionar-tickets`; esto dice dónde y en
 * qué nivel, para repartir.
 */
final class TecnicoDticController extends Controller
{
    public function __construct(
        private readonly HelpdeskServiceInterface $service,
        private readonly SubunidadesTic $subunidades,
    ) {}

    public function index(): JsonResponse
    {
        $tecnicos = TecnicoDtic::with('user:id,usuario_ti,email,servidor_id,activo', 'user.servidor:id,nombre,apellido', 'unidadTic:id,nombre')
            ->orderBy('unidad_tic_id')->orderBy('nivel')->orderBy('id')
            ->get()
            ->map(fn (TecnicoDtic $t) => $this->fila($t));

        return ApiResponse::ok($tecnicos, 'Técnicos de la mesa de ayuda.');
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::ok($this->fila(TecnicoDtic::with('user.servidor', 'unidadTic')->findOrFail($id)), 'Técnico de la mesa de ayuda.');
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $this->validar($request);

        return ApiResponse::created($this->fila(TecnicoDtic::create($datos)->load('user.servidor', 'unidadTic')), 'Técnico registrado.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tecnico = TecnicoDtic::findOrFail($id);
        $tecnico->update($this->validar($request, $tecnico));

        return ApiResponse::ok($this->fila($tecnico->load('user.servidor', 'unidadTic')), 'Técnico actualizado.');
    }

    public function destroy(int $id): JsonResponse
    {
        TecnicoDtic::findOrFail($id)->delete();

        return ApiResponse::ok(null, 'El usuario ya no figura como técnico de la mesa de ayuda.');
    }

    public function cargaTrabajo(int $id): JsonResponse
    {
        return ApiResponse::ok($this->service->obtenerCargaTrabajoYMetricas($id), 'Carga de trabajo del técnico.');
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?TecnicoDtic $tecnico = null): array
    {
        return $request->validate([
            'user_id'       => [$tecnico ? 'prohibited' : 'required', 'integer', Rule::unique('tecnicos_dtic', 'user_id')->whereNull('deleted_at'), $this->atiendeTickets()],
            'unidad_tic_id' => [$tecnico ? 'sometimes' : 'required', 'integer', Rule::in($this->subunidades->ids())],
            'nivel'         => [$tecnico ? 'sometimes' : 'required', 'integer', 'min:1', 'max:3'],
            'estado'        => ['sometimes', 'boolean'],
        ], [
            'unidad_tic_id.in' => 'Elija una de las subunidades de TIC.',
            'user_id.unique'   => 'Ese usuario ya figura como técnico.',
        ], ['user_id' => 'usuario', 'unidad_tic_id' => 'subunidad de TIC']);
    }

    private function atiendeTickets(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $fallar) {
            $usuario = User::find($valor);
            if (! $usuario || ! $usuario->can(Permiso::GESTIONAR_TICKETS->value)) {
                $fallar('Ese usuario no atiende tickets: dele un rol de TIC en Usuarios antes.');
            }
        };
    }

    /** @return array<string, mixed> */
    private function fila(TecnicoDtic $t): array
    {
        return [
            'id'            => $t->id,
            'user_id'       => $t->user_id,
            'nombre'        => $t->user?->nombre_completo,
            'usuario_ti'    => $t->user?->usuario_ti,
            'unidad_tic_id' => $t->unidad_tic_id,
            'unidad_tic'    => $t->unidadTic?->nombre,
            'nivel'         => $t->nivel,
            'estado'        => $t->estado,
        ];
    }
}
