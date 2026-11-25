<?php
namespace App\Http\Controllers\Asistencia;

use App\Contracts\Asistencia\VacacionServiceInterface;
use App\Enums\EstadoVacacion;
use App\Enums\RolFirmaAccionPersonal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Asistencia\AnularVacacionRequest;
use App\Http\Requests\Asistencia\StoreVacacionRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Asistencia\Vacacion;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Asistencia\ConteoDeVacaciones;
use App\Services\Asistencia\PeriodoVacacionService;
use App\Services\Asistencia\VacacionService;
use App\Services\Expediente\FirmanteAccionPersonalService;
use App\Support\NombreDelServidor;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VacacionController extends Controller
{
    public function __construct(
        private VacacionServiceInterface $vacacionService
    ) {}

    public function index(Request $request)
    {
        $this->authorize('verAny', Vacacion::class);

        // El filtro de estado no se validaba: cualquier cadena entraba a la
        // consulta y volvía una lista vacía sin decir por qué.
        $request->validate([
            'equipo' => ['sometimes', 'boolean'],
            'estado' => ['sometimes', Rule::in(EstadoVacacion::valores())],
        ]);

        $query = Vacacion::with(VacacionService::RELACIONES)
            // created_at es timestamp(0): sin desempate por id, dos páginas
            // del mismo resultado pueden solaparse.
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');

        // ── Filtros ──────────────────────────────────────
        if ($request->filled('folio')) {
            $query->where('folio', 'ilike', '%' . $request->folio . '%');
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('motivo')) {
            $query->where('motivo', $request->motivo);
        }

        if ($request->filled('servidor_id')) {
            $query->where('servidor_id', $request->servidor_id);
        }

        if ($request->filled('unidad_administrativa_id')) {
            $query->where(
                'unidad_administrativa_id',
                $request->unidad_administrativa_id
            );
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha_inicio', '>=', $request->fecha_desde);
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha_inicio', '<=', $request->fecha_hasta);
        }

        // «Mi equipo» del Portal pide solo el equipo, aunque quien lo pide vea
        // más (Talento Humano ve toda la institución).
        if ($request->boolean('equipo')) {
            $this->aplicarEquipo($query, $request->user());
        } else {
            $this->aplicarAlcance($query, $request->user());
        }

        $perPage    = $request->integer('per_page', 20);
        $vacaciones = $query->paginate($perPage);

        return ApiResponse::ok($vacaciones, 'Listado de solicitudes de vacaciones');
    }

    /**
     * El saldo de vacaciones de cada servidor de las unidades que dirige, para
     * «Mi equipo» del Portal. Sin jefatura, la lista sale vacía.
     *
     * Solo lo que hace falta para planificar: nombre, unidad y saldo. Es lo
     * mismo que `saldo()` le muestra al jefe servidor por servidor. A quien su
     * régimen no le genera vacaciones —servicios profesionales— el saldo le va
     * en null y no en 0: no es que no le queden días, es que no los tiene.
     */
    public function saldosDelEquipo(Request $request)
    {
        $user     = $request->user();
        $unidades = $user->unidadesQueDirige();

        $equipo = $unidades === [] ? collect() : Servidor::query()
            ->whereIn('unidad_administrativa_id', $unidades)
            ->where('estado', true)
            ->whereKeyNot($user->servidor_id ?? 0)
            ->with('unidadAdministrativa:id,nombre')
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->orderBy('id')
            ->get(['id', 'nombre', 'apellido', 'unidad_administrativa_id', 'regimen_laboral']);

        return ApiResponse::ok($equipo->map(fn (Servidor $servidor) => [
            'servidor_id' => $servidor->id,
            'apellido'    => $servidor->apellido,
            'nombre'      => $servidor->nombre,
            'unidad'      => $servidor->unidadAdministrativa?->nombre,
            'saldo_dias'  => ($servidor->regimen_laboral?->generaVacaciones() ?? true)
                ? $this->vacacionService->calcularSaldoActual($servidor->id)
                : null,
        ])->values(), 'Saldos de vacaciones del equipo.');
    }

    public function saldo(int $servidorId)
    {
        $this->authorize('verSaldo', [Vacacion::class, Servidor::findOrFail($servidorId)]);

        $saldo = $this->vacacionService->calcularSaldoActual($servidorId);
        return ApiResponse::ok(['saldo_dias' => $saldo], 'Saldo de vacaciones calculado.');
    }

    /**
     * Lo que descontarían unas fechas y el día del regreso, para el formulario
     * (2026-10-20): los cuenta el backend porque conoce los feriados. No
     * guarda nada.
     */
    public function conteo(Request $request, ConteoDeVacaciones $conteo)
    {
        $fechas = $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'required|date|after_or_equal:fecha_inicio',
        ], [], ['fecha_inicio' => 'fecha de inicio', 'fecha_fin' => 'fecha de fin']);

        $inicio = Carbon::parse($fechas['fecha_inicio']);
        $fin = Carbon::parse($fechas['fecha_fin']);

        return ApiResponse::ok([
            'dias'           => $conteo->dias($inicio, $fin),
            'fecha_retorno'  => $conteo->retorno($fin)->toDateString(),
            // Si los días pasan del rango, la parte termina antes de un descanso.
            'dias_del_rango' => (int) $inicio->diffInDays($fin) + 1,
        ], 'Días de la solicitud.');
    }

    public function store(StoreVacacionRequest $request)
    {
        $servidorId = $request->input('servidor_id')
            ?? ($request->user()->servidor->id ?? null);

        if (!$servidorId) {
            return ApiResponse::error('No se identificó el servidor.', 422);
        }

        $this->authorize('crear', [Vacacion::class, Servidor::findOrFail($servidorId)]);

        $datos = array_merge($request->validated(), [
            'creado_por'    => $request->user()->id,
            'fecha_emision' => now()->toDateString(),
        ]);

        $vacacion = $this->vacacionService->solicitar($datos, $servidorId);
        return ApiResponse::created($vacacion, 'Solicitud de vacaciones generada.');
    }

    /**
     * Talento Humano registra la solicitud que recibe firmada en ventanilla.
     *
     * Sin cuerpo: ya no se manda un estado a elegir. El único camino hacia
     * adelante es registrarla, y el único hacia atrás, anularla con motivo.
     */
    public function registrar(Request $request, int $id)
    {
        $this->authorize('registrar', Vacacion::findOrFail($id));

        $vacacion = $this->vacacionService->registrar($id, $request->user());

        // El impreso llega con esas dos casillas en blanco: se llenan a mano
        // aquí, en la ventanilla. Viajan en `meta` para que quien atiende no
        // tenga que ir a buscarlas a otra pantalla con el papel en la mano.
        return ApiResponse::ok(
            $vacacion,
            'Solicitud registrada por Talento Humano.',
            200,
            ['anotar' => $this->loQueSeAnotaEnElPapel($vacacion)],
        );
    }

    /**
     * Lo que Talento Humano escribe en el documento después de registrarlo.
     *
     * Son las dos casillas que el papel trae en blanco: de qué períodos
     * salieron los días y con cuánto queda el servidor. Antes esto alimentaba
     * el PDF; se imprimía al sacar la hoja, cuando todavía no había nada que
     * imprimir.
     *
     * @return array{se_descuenta_de: string, saldo_restante: float}
     */
    private function loQueSeAnotaEnElPapel(Vacacion $vacacion): array
    {
        $tramos   = app(PeriodoVacacionService::class)->paraImpresion($vacacion)['tramos'];
        $sinCeros = fn (float $n) => rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');

        return [
            'se_descuenta_de' => $tramos === []
                ? 'No descuenta del saldo de vacaciones'
                : implode('  ·  ', array_map(
                    fn (array $t) => $t['anio'].': '.$sinCeros($t['dias']).' días',
                    $tramos,
                )),
            'saldo_restante' => $this->vacacionService->calcularSaldoActual((int) $vacacion->servidor_id),
        ];
    }

    /**
     * Anula una autorizada, o una registrada que aún no comienza.
     *
     * El mensaje dice cuántos días volvieron al saldo: es lo que quien anula
     * necesita comprobar, y no se ve en la fila de la solicitud.
     */
    public function anular(AnularVacacionRequest $request, int $id)
    {
        $this->authorize('anular', Vacacion::findOrFail($id));

        [
            'vacacion'       => $vacacion,
            'dias_devueltos' => $dias,
        ] = $this->vacacionService->anular($id, $request->validated('motivo'), $request->user());

        $mensaje = $dias > 0
            ? sprintf('Solicitud anulada. Se devolvieron %s días al saldo del servidor.', number_format($dias, 2))
            : 'Solicitud anulada.';

        return ApiResponse::ok($vacacion, $mensaje);
    }

    public function exportar(int $id): mixed
    {
        $vacacion = Vacacion::with([
            'servidor.puesto.cargo',
            'servidor.unidadAdministrativa',
            'jefe.puesto.cargo',
            'personaReemplaza',
            'unidadAdministrativa',
        ])->findOrFail($id);

        $this->authorize('exportar', $vacacion);

        // La vista recibe los datos ya calculados y no consulta nada: antes
        // leía campos que la solicitud no tiene y armaba el QR por su cuenta.
        $pdf = app('dompdf.wrapper')
            // A4, como los otros treinta exportables del sistema. Era el único
            // en carta.
            ->setPaper('a4', 'portrait')
            ->loadView('vacaciones.vacacion-pdf', [
                'vacacion'  => $vacacion,
                'urlQr'     => Vacacion::urlVerificacion($vacacion->folio ?? (string) $vacacion->id),
                ...$this->firmantesDelDocumento(),
                // El logo vigente el día que se emitió la solicitud (D3).
                ...app(\App\Contracts\Comunicacion\IdentidadInstitucionalServiceInterface::class)->paraDocumento($vacacion->fecha_emision),
            ]);

        // Sin esto, DejaVu entera pesa cerca de 1 MB por PDF; con el
        // subconjunto queda en unos 135 KB y se ve igual. `config/dompdf.php`
        // lo tiene apagado para todo el sistema (hallazgo del PR #665).
        $pdf->getDomPDF()->getOptions()->setIsFontSubsettingEnabled(true);

        $folio = $vacacion->folio ?? $vacacion->id;

        return $pdf->download("vacacion_{$folio}.pdf");
    }

    /**
     * Quién firma por Talento Humano y por Prefectura.
     *
     * El jefe vigente de la unidad anclada en el organigrama, o quien lo
     * subrogue hoy: la misma vía de las acciones de personal, para que no haya
     * dos maneras de saber quién dirige. Se resuelve a la fecha de impresión y
     * no a la de la solicitud, porque el papel lo firma quien ocupe el cargo
     * cuando se lo lleven. Con la jefatura vacante sale el cargo sin nombre,
     * que es preferible a imprimir a alguien que no va a firmar.
     *
     * @return array{firmanteTh: string, cargoTh: string, firmanteAutoridad: string, cargoAutoridad: string}
     */
    private function firmantesDelDocumento(): array
    {
        $firmantes = app(FirmanteAccionPersonalService::class);

        $resolver = function (RolFirmaAccionPersonal $rol) use ($firmantes): array {
            $firma    = $firmantes->resolver($rol, now()->toDateString());
            $servidor = $firma['servidor'];

            return [
                $servidor ? NombreDelServidor::de($servidor) : '',
                $firma['cargo'] ?: $rol->cargoPorDefecto(),
            ];
        };

        [$firmanteTh, $cargoTh] = $resolver(RolFirmaAccionPersonal::RESPONSABLE_TALENTO_HUMANO);
        [$firmanteAutoridad, $cargoAutoridad] = $resolver(RolFirmaAccionPersonal::AUTORIDAD_NOMINADORA);

        return compact('firmanteTh', 'cargoTh', 'firmanteAutoridad', 'cargoAutoridad');
    }

    // ── Apoyos ───────────────────────────────────────────────────────

    /**
     * Recorta el listado a lo que el usuario tiene derecho a ver.
     *
     * Quien no tiene alcance institucional ve las propias, las de las unidades
     * que dirige y las que lo nombran jefe inmediato. Es el mismo criterio que
     * `PermisoServidorController::aplicarAlcance()`.
     */
    private function aplicarAlcance(Builder $query, User $user): void
    {
        if ($user->can('verTodas', Vacacion::class)) {
            return;
        }

        $servidorId = $user->servidor_id;
        $unidades   = $user->unidadesQueDirige();

        $query->where(function (Builder $q) use ($servidorId, $unidades) {
            $q->where('servidor_id', $servidorId ?? 0);

            if ($unidades !== []) {
                $q->orWhereIn('unidad_administrativa_id', $unidades)
                    ->orWhere(function (Builder $sinUnidad) use ($unidades) {
                        $sinUnidad->whereNull('unidad_administrativa_id')
                            ->whereHas('servidor', fn ($s) => $s->whereIn(
                                'unidad_administrativa_id', $unidades
                            ));
                    });
            }

            if ($servidorId) {
                $q->orWhere('jefe_id', $servidorId);
            }
        });
    }

    /**
     * El equipo de quien dirige: lo de las unidades que dirige por su puesto y
     * lo que lo nombra jefe inmediato, sin lo propio. Es la regla de
     * `PermisoServidorController::aplicarEquipo()`: un recorte de lo que ya
     * puede ver, sin el rol ni el alcance institucional.
     */
    private function aplicarEquipo(Builder $query, User $user): void
    {
        $servidorId = $user->servidor_id;

        if (! $servidorId) {
            $query->whereRaw('1 = 0');

            return;
        }

        $unidades = $user->unidadesQueDirige();

        $query->where('servidor_id', '!=', $servidorId)
            ->where(function (Builder $q) use ($servidorId, $unidades) {
                $q->where('jefe_id', $servidorId);

                if ($unidades !== []) {
                    $q->orWhereIn('unidad_administrativa_id', $unidades)
                        ->orWhere(function (Builder $sinUnidad) use ($unidades) {
                            $sinUnidad->whereNull('unidad_administrativa_id')
                                ->whereHas('servidor', fn ($s) => $s->whereIn(
                                    'unidad_administrativa_id', $unidades
                                ));
                        });
                }
            });
    }

}
