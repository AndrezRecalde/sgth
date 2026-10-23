<?php

namespace App\Http\Controllers\Dispensario;

use App\Enums\EstadoOrdenExamen;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Dispensario\OrdenExamen;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * La bandeja de exámenes (sección 5.4 de docs/diseno-examenes-medicos.md): las
 * órdenes que todavía esperan resultados, para el médico que recibe al
 * paciente sin consulta abierta y para Enfermería, que hace el seguimiento.
 *
 * Las vencidas —abiertas y con más de 90 días (D5)— van aparte: dejan de
 * contar como pendientes, pero no desaparecen, para que alguien las mire
 * (riesgos del diseño: que no sea otra cola sin dueño).
 */
final class BandejaExamenesController extends Controller
{
    private const PER_PAGE_MAX = 100;

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'vista'    => ['sometimes', 'string', Rule::in(['pendientes', 'vencidas'])],
            'buscar'   => ['nullable', 'string', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $vencidas = $request->input('vista') === 'vencidas';

        $ordenes = $this->abiertas($request)
            ->where('fecha_solicitud', $vencidas ? '<' : '>=', $this->limiteDeVigencia())
            ->with([
                'items:id,orden_examen_id,catalogo_examen_id,nota,estado,motivo_no_realizado',
                'items.examen:id,nombre,area',
                'historiaClinica:id,servidor_id,carga_familiar_id',
                'historiaClinica.servidor:id,nombre,apellido,cedula',
                'historiaClinica.cargaFamiliar:id,nombres,apellidos,cedula',
                'solicitante:id,usuario_ti,email,servidor_id',
                'solicitante.servidor:id,nombre,apellido',
            ])
            // Las más antiguas primero: son las que más esperan. Y por id para
            // que la página no cambie entre dos lecturas del mismo día.
            ->orderBy('fecha_solicitud')
            ->orderBy('id')
            ->paginate(min($request->integer('per_page', 15), self::PER_PAGE_MAX));

        // Cuántas hay en cada vista con la misma búsqueda, para las pestañas.
        $limite = $this->limiteDeVigencia();
        $resumen = [
            'pendientes' => $this->abiertas($request)->where('fecha_solicitud', '>=', $limite)->count(),
            'vencidas'   => $this->abiertas($request)->where('fecha_solicitud', '<', $limite)->count(),
        ];

        return ApiResponse::ok($ordenes, 'Bandeja de exámenes.', 200, ['resumen' => $resumen]);
    }

    /** Emitidas o parciales, con la búsqueda por folio, cédula o nombre del paciente. */
    private function abiertas(Request $request): Builder
    {
        $buscar = trim((string) $request->input('buscar', ''));

        return OrdenExamen::query()
            ->whereIn('estado', [EstadoOrdenExamen::EMITIDA, EstadoOrdenExamen::PARCIAL])
            ->when($buscar !== '', function (Builder $q) use ($buscar) {
                $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $buscar) . '%';

                $q->where(fn (Builder $w) => $w
                    ->where('folio', 'ilike', $like)
                    ->orWhereHas('historiaClinica.servidor', fn (Builder $s) => $s
                        ->where('cedula', 'ilike', $like)
                        ->orWhereRaw("concat_ws(' ', nombre, apellido) ilike ?", [$like])
                        ->orWhereRaw("concat_ws(' ', apellido, nombre) ilike ?", [$like]))
                    ->orWhereHas('historiaClinica.cargaFamiliar', fn (Builder $c) => $c
                        ->where('cedula', 'ilike', $like)
                        ->orWhereRaw("concat_ws(' ', nombres, apellidos) ilike ?", [$like])
                        ->orWhereRaw("concat_ws(' ', apellidos, nombres) ilike ?", [$like])));
            });
    }

    /** Desde aquí hacia atrás, una orden abierta está vencida. */
    private function limiteDeVigencia(): string
    {
        return today()->subDays(OrdenExamen::DIAS_VIGENCIA)->toDateString();
    }
}
