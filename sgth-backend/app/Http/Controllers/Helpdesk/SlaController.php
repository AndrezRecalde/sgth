<?php

namespace App\Http\Controllers\Helpdesk;

use App\Enums\PrioridadTicket;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Helpdesk\Sla;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * La tabla de SLA (fase 3.2): horas hábiles de respuesta y de resolución por
 * prioridad, en general o para una categoría (D7). Cambiarla rige para los
 * tickets nuevos y los que cambian de prioridad: los demás conservan el plazo
 * con que se abrieron.
 */
final class SlaController extends Controller
{
    public function index(): JsonResponse
    {
        $slas = Sla::with('categoria:id,nombre')->orderByRaw('categoria_id IS NOT NULL')->orderBy('categoria_id')->orderBy('prioridad')->get();

        // Lo que rige para una prioridad sin fila general (config/helpdesk.php).
        return ApiResponse::ok(['reglas' => $slas, 'por_defecto' => config('helpdesk.sla')], 'Tabla de SLA.');
    }

    public function store(Request $request): JsonResponse
    {
        return ApiResponse::created(Sla::create($this->validar($request)), 'SLA registrado.');
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::ok(Sla::with('categoria:id,nombre')->findOrFail($id), 'SLA.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $sla = Sla::findOrFail($id);
        $sla->update($this->validar($request, $sla));

        return ApiResponse::ok($sla, 'SLA actualizado.');
    }

    public function destroy(int $id): JsonResponse
    {
        Sla::findOrFail($id)->delete();

        return ApiResponse::ok(null, 'SLA eliminado: rige el general de esa prioridad.');
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?Sla $sla = null): array
    {
        $datos = $request->validate([
            'prioridad'               => [$sla ? 'sometimes' : 'required', Rule::enum(PrioridadTicket::class), $this->unicaPorCategoria($request, $sla)],
            'categoria_id'            => ['nullable', 'integer', Rule::exists('categorias_ticket', 'id')->whereNull('deleted_at')],
            'tiempo_resolucion_horas' => [$sla ? 'sometimes' : 'required', 'numeric', 'min:0.5', 'max:2000'],
            'tiempo_respuesta_horas'  => [$sla ? 'sometimes' : 'required', 'numeric', 'min:0.1', 'max:2000'],
        ], [], [
            'prioridad' => 'prioridad', 'categoria_id' => 'categoría',
            'tiempo_resolucion_horas' => 'horas de resolución', 'tiempo_respuesta_horas' => 'horas de respuesta',
        ]);

        $resolucion = $datos['tiempo_resolucion_horas'] ?? $sla?->tiempo_resolucion_horas;
        $respuesta = $datos['tiempo_respuesta_horas'] ?? $sla?->tiempo_respuesta_horas;
        if ($resolucion !== null && $respuesta !== null && $respuesta > $resolucion) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'tiempo_respuesta_horas' => 'La primera respuesta no puede tardar más que la resolución.',
            ]);
        }

        return $datos;
    }

    /** Una sola fila por prioridad en general, y una por categoría y prioridad. */
    private function unicaPorCategoria(Request $request, ?Sla $sla): Closure
    {
        return function (string $atributo, mixed $valor, Closure $fallar) use ($request, $sla) {
            $categoria = $request->has('categoria_id') ? $request->input('categoria_id') : $sla?->categoria_id;
            $repetida = Sla::where('prioridad', $valor)
                ->when($categoria, fn ($q) => $q->where('categoria_id', $categoria), fn ($q) => $q->whereNull('categoria_id'))
                ->when($sla, fn ($q) => $q->whereKeyNot($sla->id))
                ->exists();

            if ($repetida) {
                $fallar($categoria ? 'Esa categoría ya tiene un SLA para esa prioridad.' : 'Esa prioridad ya tiene su SLA general.');
            }
        };
    }
}
