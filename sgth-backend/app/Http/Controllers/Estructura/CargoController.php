<?php
namespace App\Http\Controllers\Estructura;

use App\Enums\Permiso;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Estructura\Cargo;
use App\Models\Estructura\Puesto;
use App\Services\Estructura\ProteccionDePuestoOcupado;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * El catálogo de cargos institucionales.
 *
 * La lectura queda abierta a cualquier usuario autenticado: alimenta los
 * selectores de Puestos. Crear, cambiar o borrar un cargo pide
 * `gestionar-puestos`, igual que un puesto; antes no pedía nada, y el código
 * CIUO del cargo es el que hereda la ficha médica ocupacional (FEMO).
 */
class CargoController extends Controller
{
    /**
     * Lanza en vez de devolver la respuesta: con un `?JsonResponse` que se
     * retornaba antes de tiempo, Scramble dejaba sin tipo la respuesta de
     * store, update y destroy. El 403 sale igual, por el manejador de
     * `HttpExceptionInterface` de bootstrap/app.php.
     */
    private function exigirGestion(Request $request): void
    {
        abort_unless(
            $request->user()->can(Permiso::GESTIONAR_PUESTOS->value),
            403,
            'No tiene permiso para gestionar cargos.',
        );
    }

    public function index(Request $request): JsonResponse
    {
        $cargos = Cargo::query()
            ->when(
                $request->filled('search'),
                fn($q) => $q->where('nombre', 'ilike', "%{$request->search}%")
            )
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        // Si alguno de sus puestos está ocupado, el renombrado pide el motivo
        // (fase 7.1b): la pantalla lo pregunta antes de que el backend lo
        // rechace.
        $conOcupados = Puesto::query()
            ->whereIn('cargo_id', $cargos->pluck('id'))
            ->conOcupacion()
            ->get()
            ->filter(fn (Puesto $p) => (bool) $p->getAttribute('ocupado'))
            ->pluck('cargo_id')
            ->flip();
        $cargos->each(fn (Cargo $c) => $c->setAttribute('con_puestos_ocupados', $conOcupados->has($c->id)));

        return ApiResponse::ok($cargos, 'Cargos institucionales.');
    }

    public function store(Request $request): JsonResponse
    {
        $this->exigirGestion($request);

        $validated = $request->validate([
            'nombre'                 => ['required', 'string', 'max:200'],
            'denominacion_generica'  => ['nullable', 'string', 'max:100'],
            // Código CIUO-08 (adaptación INEC). Se define aquí, en el cargo, y lo
            // heredan las fichas médicas ocupacionales de todos sus puestos.
            'codigo_ciuo'            => ['nullable', 'string', 'max:10', 'regex:/^[0-9]+$/'],
            'mision'                 => ['nullable', 'string'],
        ]);

        $cargo = Cargo::create($validated);
        return ApiResponse::created($cargo, 'Cargo creado.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->exigirGestion($request);

        $cargo = Cargo::findOrFail($id);
        $validated = $request->validate([
            'nombre'                 => ['sometimes', 'string', 'max:200'],
            'denominacion_generica'  => ['nullable', 'string', 'max:100'],
            // Código CIUO-08 (adaptación INEC). Se define aquí, en el cargo, y lo
            // heredan las fichas médicas ocupacionales de todos sus puestos.
            'codigo_ciuo'            => ['nullable', 'string', 'max:10', 'regex:/^[0-9]+$/'],
            'mision'                 => ['nullable', 'string'],
            'activo' => ['boolean'],
            // Corregir cómo está escrito un cargo con puestos ocupados pide el
            // motivo (fase 7.1b); cambiar la denominación es reclasificar.
            'motivo_correccion'      => ['nullable', 'string', 'min:5', 'max:300'],
        ], [], ['motivo_correccion' => 'motivo de la corrección']);

        $renombra = array_key_exists('nombre', $validated) && trim((string) $validated['nombre']) !== $cargo->nombre;

        if ($renombra && ProteccionDePuestoOcupado::cargoConPuestosOcupados($cargo->id) && blank($validated['motivo_correccion'] ?? null)) {
            throw ValidationException::withMessages([
                'motivo_correccion' => 'El cargo tiene puestos ocupados: diga por qué se corrige su nombre. Si no es una '
                    .'corrección sino otra denominación, es una reclasificación: use «Revisar la clasificación».',
            ]);
        }

        $antes = $cargo->nombre;
        $motivo = $validated['motivo_correccion'] ?? null;
        unset($validated['motivo_correccion']);

        $cargo->update($validated);

        if ($renombra) {
            activity()
                ->performedOn($cargo)
                ->event('updated')
                ->withProperties(['antes' => ['nombre' => $antes], 'despues' => ['nombre' => $cargo->nombre], 'causa' => $motivo ?? 'Edición directa en Estructura'])
                ->log('Nombre del cargo corregido');
        }

        return ApiResponse::ok($cargo, 'Cargo actualizado.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->exigirGestion($request);

        $cargo = Cargo::findOrFail($id);

        if ($cargo->puestos()->exists()) {
            return ApiResponse::error(
                'No se puede eliminar el cargo porque tiene puestos asignados.',
                null, 422
            );
        }

        $cargo->delete();
        return ApiResponse::ok(null, 'Cargo eliminado.');
    }
}
