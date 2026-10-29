<?php

namespace App\Http\Controllers\Dispensario;

use App\Enums\EstadoOrdenExamen;
use App\Enums\EstadoRevisionResultado;
use App\Enums\Permiso;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Dispensario\OrdenExamen;
use App\Models\Dispensario\ResultadoMedico;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * La bandeja de exámenes (sección 5.4 de docs/diseno-examenes-medicos.md): las
 * órdenes que todavía esperan resultados, para el médico que recibe al
 * paciente sin consulta abierta. Enfermería ya no la ve (ver las rutas).
 *
 * Las vencidas —abiertas y con más de 90 días (D5)— van aparte: dejan de
 * contar como pendientes, pero no desaparecen, para que alguien las mire
 * (riesgos del diseño: que no sea otra cola sin dueño).
 *
 * Cada médico y odontólogo ve aquí solo lo suyo: las órdenes que pidió y los
 * resultados de los que responde. La administración las ve todas, para
 * supervisar. Lo de un colega se atiende desde la consulta del paciente, que
 * enseña sus órdenes abiertas y todos sus resultados (decisión del
 * 2026-10-29). Hasta entonces cada uno veía las de su especialidad.
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
            'por_revisar' => $this->sinRevisar($request)->count(),
        ];

        return ApiResponse::ok($ordenes, 'Bandeja de exámenes.', 200, ['resumen' => $resumen]);
    }

    /**
     * Los resultados que nadie ha leído todavía (D3), los más antiguos primero.
     * `solicitante` deja los de un médico: los de sus órdenes y, sin orden,
     * los de sus consultas. Le sirve a la administración, que ve los de todos.
     */
    public function porRevisar(Request $request): JsonResponse
    {
        $request->validate([
            'solicitante' => ['nullable', 'integer'],
            'buscar'      => ['nullable', 'string', 'max:100'],
            'per_page'    => ['sometimes', 'integer', 'min:1'],
        ]);

        $resultados = $this->sinRevisar($request)
            ->when($request->filled('solicitante'), fn (Builder $q) => $q->deResponsable($request->integer('solicitante')))
            ->with([
                ...ResultadoMedicoController::RELACIONES,
                'historiaClinica.servidor:id,nombre,apellido,cedula',
                'historiaClinica.cargaFamiliar:id,nombres,apellidos,cedula',
                'ordenExamen.solicitante:id,usuario_ti,email,servidor_id',
                'ordenExamen.solicitante.servidor:id,nombre,apellido',
                'consultaMedica:id,medico_id,fecha_consulta',
                'consultaMedica.medico:id,usuario_ti,email,servidor_id',
                'consultaMedica.medico.servidor:id,nombre,apellido',
            ])
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(min($request->integer('per_page', 15), self::PER_PAGE_MAX));

        return ApiResponse::ok($resultados, 'Resultados por revisar.');
    }

    /**
     * De quién es la bandeja: el id de quien pregunta, o `null` para la
     * administración, que supervisa y las ve todas.
     */
    private function propiasDe(Request $request): ?int
    {
        $usuario = $request->user();

        return $usuario->can(Permiso::SUPERVISAR_DISPENSARIO->value) ? null : $usuario->id;
    }

    /**
     * Emitidas o parciales, con la búsqueda por folio, cédula o nombre del
     * paciente: las que pidió quien pregunta.
     */
    private function abiertas(Request $request): Builder
    {
        $like = $this->patron($request);
        $solicitante = $this->propiasDe($request);

        return OrdenExamen::query()
            ->when($solicitante !== null, fn (Builder $q) => $q->where('solicitado_por', $solicitante))
            ->whereIn('estado', [EstadoOrdenExamen::EMITIDA, EstadoOrdenExamen::PARCIAL])
            ->when($like !== null, fn (Builder $q) => $q->where(fn (Builder $w) => $this
                ->delPaciente($w->where('folio', 'ilike', $like), $like)));
    }

    /**
     * Por revisar, con la misma búsqueda: el folio de su orden, la cédula o el
     * nombre. Los de quien pregunta, como las órdenes: los de lo que pidió y,
     * sin orden, los de sus consultas.
     */
    private function sinRevisar(Request $request): Builder
    {
        $like = $this->patron($request);
        $responsable = $this->propiasDe($request);

        return ResultadoMedico::query()
            ->when($responsable !== null, fn (Builder $q) => $q->deResponsable($responsable))
            ->where('estado_revision', EstadoRevisionResultado::POR_REVISAR)
            ->when($like !== null, fn (Builder $q) => $q->where(fn (Builder $w) => $this
                ->delPaciente($w->whereHas('ordenExamen', fn (Builder $o) => $o->where('folio', 'ilike', $like)), $like)));
    }

    /** La búsqueda como patrón de `ilike`, con los comodines escritos tomados tal cual. */
    private function patron(Request $request): ?string
    {
        $buscar = trim((string) $request->input('buscar', ''));

        return $buscar === ''
            ? null
            : '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $buscar) . '%';
    }

    /** O la cédula o el nombre del paciente, servidor o carga familiar, en cualquier orden. */
    private function delPaciente(Builder $q, string $like): Builder
    {
        return $q
            ->orWhereHas('historiaClinica.servidor', fn (Builder $s) => $s
                ->where('cedula', 'ilike', $like)
                ->orWhereRaw("concat_ws(' ', nombre, apellido) ilike ?", [$like])
                ->orWhereRaw("concat_ws(' ', apellido, nombre) ilike ?", [$like]))
            ->orWhereHas('historiaClinica.cargaFamiliar', fn (Builder $c) => $c
                ->where('cedula', 'ilike', $like)
                ->orWhereRaw("concat_ws(' ', nombres, apellidos) ilike ?", [$like])
                ->orWhereRaw("concat_ws(' ', apellidos, nombres) ilike ?", [$like]));
    }

    /** Desde aquí hacia atrás, una orden abierta está vencida. */
    private function limiteDeVigencia(): string
    {
        return today()->subDays(OrdenExamen::DIAS_VIGENCIA)->toDateString();
    }
}
