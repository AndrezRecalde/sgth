<?php

namespace App\Services\Estructura;

use App\Enums\EstadoAccionPersonal;
use App\Enums\EstadoContrato;
use App\Enums\EstadoSubrogacion;
use App\Enums\RegimenLaboral;
use App\Enums\TipoNombramiento;
use App\Enums\TipoSubrogacion;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\Servidor;
use App\Models\Expediente\Subrogacion;
use App\Services\Expediente\OcupantesDelPuesto;
use App\Services\Nomina\AusenciasSinRemuneracion;
use App\Services\Nomina\RemuneracionDelVinculo;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * La masa salarial referencial (docs/diseno-masa-salarial.md): la suma de la
 * remuneración mensual que tiene registrada cada vínculo vigente en una fecha
 * de corte, y esa suma por doce.
 *
 * No es la nómina. El SGTH no la calcula desde el 2026-10-20, y esto no la
 * vuelve a calcular: no hay aportes, décimos, fondos de reserva, horas extra
 * ni descuentos, solo la remuneración que ya dice el contrato o su acción de
 * personal. Talento Humano y Financiero aceptaron mostrarla así, con el aviso
 * fijo de `AVISO`, el 2026-11-17 (D1).
 *
 * La remuneración es la del vínculo y no la del grado del puesto: un
 * ocasional, un obrero o una autoridad electa pueden cobrar distinto del grado,
 * y en desarrollo 7 de 11 vínculos lo hacían. Y la de la fecha de corte:
 * `RemuneracionDelVinculo::al` deshace los cambios que rigen después.
 *
 * Lo que no suma va aparte, para no inflar ni duplicar el total:
 * - quien está sin remuneración ese día —licencia, comisión o suspensión—
 *   (D4). Su reemplazo sí suma: a él se le paga, y la plaza no se cuenta dos
 *   veces;
 * - la diferencia de una subrogación o un encargo (D5);
 * - Servicios Profesionales, que son honorarios de un contrato civil, tiene su
 *   propio bloque, y el total general suma los dos (D6).
 *
 * @phpstan-type Bloque array{personas: int, mensual: float, anual: float, porcentaje: float, promedio: float}
 */
final class MasaSalarialService
{
    public const AVISO = 'Monto referencial: la suma de las remuneraciones mensuales registradas en el SGTH a la fecha de corte. '
        .'No es lo pagado, que está en el sistema de Financiero, y no incluye aportes al IESS, décimos ni fondos de reserva.';

    /** Entra en el total. */
    public const SUMA = 'suma';

    /** Tiene remuneración, pero ese día está con licencia, comisión o suspensión sin ella. */
    public const SIN_REMUNERACION_EN_LA_FECHA = 'sin_remuneracion_en_la_fecha';

    /** El vínculo no tiene remuneración registrada: es un dato por completar. */
    public const SIN_REMUNERACION_REGISTRADA = 'sin_remuneracion_registrada';

    /** Las acciones que surtieron efecto: lo que sigue en trámite todavía no rige. */
    private const ACCIONES_EMITIDAS = [EstadoAccionPersonal::REGISTRADA, EstadoAccionPersonal::NOTIFICADA];

    public function __construct(
        private readonly RemuneracionDelVinculo $remuneracion,
        private readonly AusenciasSinRemuneracion $ausencias,
    ) {
    }

    /**
     * @param  array{regimen?: ?RegimenLaboral, tipo_nombramiento?: ?TipoNombramiento, unidad_id?: ?int}  $filtros
     * @return array{
     *   corte: string,
     *   aviso: string,
     *   filtros: array{regimen: ?string, tipo_nombramiento: ?string, unidad_id: ?int, unidad: ?string},
     *   totales: array{dependencia: Bloque, servicios_profesionales: Bloque, general: Bloque},
     *   vistas: array{
     *     tipo_nombramiento: list<array{clave: string, etiqueta: string, personas: int, mensual: float, anual: float, porcentaje: float, promedio: float}>,
     *     regimen: list<array{clave: string, etiqueta: string, personas: int, mensual: float, anual: float, porcentaje: float, promedio: float}>,
     *     unidad: list<array<string, mixed>>
     *   },
     *   aparte: array{
     *     sin_remuneracion_en_la_fecha: array{personas: int, mensual: float},
     *     subrogaciones: array{casos: int, mensual: float, detalle: list<array{subrogacion_id: int, servidor_id: int, servidor: string, tipo: string, puesto_del_que_asume: float, la_suya: float, diferencia: float}>}
     *   },
     *   por_completar: array{
     *     vinculos_sin_remuneracion: int,
     *     vinculos_sin_unidad: int,
     *     servidores_sin_vinculo: list<array{id: int, servidor: string, cedula: ?string}>|null
     *   },
     *   detalle: list<array{
     *     contrato_id: int, servidor_id: int, servidor: string, cedula: ?string, puesto: ?string, grado: ?string,
     *     tipo_nombramiento: ?string, tipo_nombramiento_etiqueta: ?string, regimen: ?string, regimen_etiqueta: ?string,
     *     unidad_id: ?int, unidad: ?string, remuneracion: ?float, situacion: string
     *   }>
     * }
     */
    public function al(CarbonInterface $corte, array $filtros = []): array
    {
        $corte = CarbonImmutable::parse($corte->toDateString());
        $regimen = $filtros['regimen'] ?? null;
        $tipo = $filtros['tipo_nombramiento'] ?? null;
        $unidadId = $filtros['unidad_id'] ?? null;
        $unidades = $unidadId !== null ? ArbolUnidades::conDescendientes($unidadId) : null;

        // Lo que se leyó en otra llamada pudo cambiar: un acto registrado
        // desde entonces cuenta.
        $this->remuneracion->olvidar();

        $vinculos = $this->vinculos($corte, $regimen, $tipo, $unidades);
        $this->remuneracion->precargar($vinculos);

        $sinRemuneracion = $this->ausencias->entre($corte, $corte)->pluck('servidor_id')->unique()->flip();

        $filas = $vinculos
            ->map(fn (ContratoServidor $vinculo) => $this->fila($vinculo, $corte, $sinRemuneracion))
            ->sortBy([['servidor', 'asc'], ['contrato_id', 'asc']])
            ->values();

        $suman = $filas->where('situacion', self::SUMA)->values();
        $total = (int) $suman->sum('centavos');
        $esServiciosProfesionales = fn (array $f) => $f['regimen'] === RegimenLaboral::SERVICIOS_PROFESIONALES->value;
        $sinLaFecha = $filas->where('situacion', self::SIN_REMUNERACION_EN_LA_FECHA);

        return [
            'corte'   => $corte->toDateString(),
            'aviso'   => self::AVISO,
            'filtros' => [
                'regimen'           => $regimen?->value,
                'tipo_nombramiento' => $tipo?->value,
                'unidad_id'         => $unidadId,
                'unidad'            => $unidadId !== null ? UnidadAdministrativa::withTrashed()->find($unidadId)?->nombre : null,
            ],
            'totales' => [
                'dependencia'             => $this->bloque($suman->reject($esServiciosProfesionales), $total),
                'servicios_profesionales' => $this->bloque($suman->filter($esServiciosProfesionales), $total),
                'general'                 => $this->bloque($suman, $total),
            ],
            'vistas' => [
                /** @var list<array{clave: string, etiqueta: string, personas: int, mensual: float, anual: float, porcentaje: float, promedio: float}> */
                'tipo_nombramiento' => $this->porCaso(TipoNombramiento::cases(), 'tipo_nombramiento', $suman, $total),
                /** @var list<array{clave: string, etiqueta: string, personas: int, mensual: float, anual: float, porcentaje: float, promedio: float}> */
                'regimen'           => $this->porCaso(RegimenLaboral::cases(), 'regimen', $suman, $total),
                /**
                 * El árbol en una lista, en su orden: cada unidad seguida de sus
                 * hijas, con su profundidad. Anidado, el tipo no se podía escribir.
                 *
                 * @var list<array{id: int, nombre: string, nivel: int|null, profundidad: int, padre_id: int|null, tiene_hijas: bool, personas: int, mensual: float, anual: float, porcentaje: float, promedio: float, propias: array{personas: int, mensual: float}}>
                 */
                'unidad'            => $this->arbol($suman, $unidadId, $total),
            ],
            'aparte' => [
                'sin_remuneracion_en_la_fecha' => [
                    'personas' => $sinLaFecha->pluck('servidor_id')->unique()->count(),
                    'mensual'  => $this->dinero((int) $sinLaFecha->sum('centavos')),
                ],
                'subrogaciones' => $this->subrogaciones($corte, $suman->pluck('servidor_id')->unique()->values()),
            ],
            'por_completar' => [
                'vinculos_sin_remuneracion' => $filas->where('situacion', self::SIN_REMUNERACION_REGISTRADA)->count(),
                'vinculos_sin_unidad'       => $suman->whereNull('unidad_id')->count(),
                'servidores_sin_vinculo'    => $this->servidoresSinVinculo($corte, $regimen, $tipo, $unidades),
            ],
            /** @var list<array{contrato_id: int, servidor_id: int, servidor: string, cedula: string|null, puesto: string|null, grado: string|null, tipo_nombramiento: string|null, tipo_nombramiento_etiqueta: string|null, regimen: string|null, regimen_etiqueta: string|null, unidad_id: int|null, unidad: string|null, remuneracion: float|null, situacion: string}> */
            'detalle' => $this->detalle($filas),
        ];
    }

    /**
     * Los vínculos, sin los centavos de trabajo.
     *
     * @param  Collection<int, array<string, mixed>>  $filas
     * @return list<array{
     *   contrato_id: int, servidor_id: int, servidor: string, cedula: ?string, puesto: ?string, grado: ?string,
     *   tipo_nombramiento: ?string, tipo_nombramiento_etiqueta: ?string, regimen: ?string, regimen_etiqueta: ?string,
     *   unidad_id: ?int, unidad: ?string, remuneracion: ?float, situacion: string
     * }>
     */
    private function detalle(Collection $filas): array
    {
        return $filas->map(fn (array $f) => array_diff_key($f, ['centavos' => true]))->values()->all();
    }

    // ── Los vínculos ────────────────────────────────────────────────

    /**
     * Los vigentes en la fecha de corte. El terminado cuenta hasta su fin, y
     * sin fin no se sabe hasta cuándo: no cuenta. El de un servidor borrado
     * tampoco.
     *
     * @param  list<int>|null  $unidades
     * @return Collection<int, ContratoServidor>
     */
    private function vinculos(CarbonImmutable $corte, ?RegimenLaboral $regimen, ?TipoNombramiento $tipo, ?array $unidades): Collection
    {
        return $this->vigentesAl(ContratoServidor::query(), $corte)
            ->whereHas('servidor')
            ->when($tipo, fn (Builder $q) => $q->where('tipo_nombramiento', $tipo->value))
            ->when($regimen, fn (Builder $q) => $q->whereIn('tipo_nombramiento', $this->tiposDe($regimen)))
            ->when($unidades !== null, fn (Builder $q) => $q->whereIn('unidad_administrativa_id', $unidades))
            ->with([
                'servidor:id,nombre,segundo_nombre,apellido,segundo_apellido,cedula',
                'puesto:id,cargo_id,grupo_ocupacional_id',
                'puesto.cargo:id,nombre',
                'puesto.grupoOcupacional:id,grado_codigo',
                'unidadAdministrativa:id,nombre',
            ])
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Builder<ContratoServidor>  $query
     * @return Builder<ContratoServidor>
     */
    private function vigentesAl(Builder $query, CarbonImmutable $corte): Builder
    {
        $fecha = $corte->toDateString();

        return $query
            ->where('estado', '!=', EstadoContrato::CANCELADO->value)
            ->where(fn (Builder $q) => $q->where('estado', EstadoContrato::VIGENTE->value)->orWhereNotNull('fecha_fin'))
            ->whereDate('fecha_inicio', '<=', $fecha)
            ->where(fn (Builder $q) => $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $fecha));
    }

    /** @return list<string> */
    private function tiposDe(RegimenLaboral $regimen): array
    {
        return array_values(array_map(
            fn (TipoNombramiento $t) => $t->value,
            array_filter(TipoNombramiento::cases(), fn (TipoNombramiento $t) => $t->regimen() === $regimen),
        ));
    }

    /**
     * Un renglón por vínculo. Los montos van en centavos mientras se suman,
     * para que el total sea la suma exacta de las filas.
     *
     * @param  Collection<int, int>  $sinRemuneracion  servidor_id => índice
     * @return array<string, mixed>
     */
    private function fila(ContratoServidor $vinculo, CarbonImmutable $corte, Collection $sinRemuneracion): array
    {
        $remuneracion = $this->remuneracion->al($vinculo, $corte);
        $centavos = $remuneracion !== null && $remuneracion > 0 ? (int) round($remuneracion * 100) : null;
        $regimen = $vinculo->tipo_nombramiento?->regimen();

        return [
            'contrato_id'                => $vinculo->id,
            'servidor_id'                => $vinculo->servidor_id,
            'servidor'                   => OcupantesDelPuesto::nombre($vinculo->servidor),
            'cedula'                     => $vinculo->servidor?->cedula,
            'puesto'                     => $vinculo->puesto?->cargo?->nombre,
            'grado'                      => $vinculo->puesto?->grupoOcupacional?->grado_codigo,
            'tipo_nombramiento'          => $vinculo->tipo_nombramiento?->value,
            'tipo_nombramiento_etiqueta' => $vinculo->tipo_nombramiento?->etiqueta(),
            'regimen'                    => $regimen?->value,
            'regimen_etiqueta'           => $regimen?->etiqueta(),
            'unidad_id'                  => $vinculo->unidad_administrativa_id,
            'unidad'                     => $vinculo->unidadAdministrativa?->nombre,
            'remuneracion'               => $centavos !== null ? $this->dinero($centavos) : null,
            'situacion'                  => match (true) {
                $centavos === null                             => self::SIN_REMUNERACION_REGISTRADA,
                $sinRemuneracion->has($vinculo->servidor_id) => self::SIN_REMUNERACION_EN_LA_FECHA,
                default                                        => self::SUMA,
            },
            'centavos'                   => $centavos ?? 0,
        ];
    }

    // ── Las vistas ──────────────────────────────────────────────────

    /**
     * Una fila por cada caso del enum, también los que están en cero: como en
     * la Plantilla, «ningún provisional» es un dato, y una fila que no aparece
     * parece un olvido.
     *
     * @param  list<TipoNombramiento|RegimenLaboral>  $casos
     * @return list<array{clave: string, etiqueta: string, personas: int, mensual: float, anual: float, porcentaje: float, promedio: float}>
     */
    private function porCaso(array $casos, string $columna, Collection $suman, int $total): array
    {
        return array_map(fn (TipoNombramiento|RegimenLaboral $caso) => [
            'clave'    => $caso->value,
            'etiqueta' => $caso->etiqueta(),
            ...$this->bloque($suman->where($columna, $caso->value), $total),
        ], $casos);
    }

    /**
     * Las unidades en árbol: cada una con su subtotal, que incluye todo lo que
     * cuelga de ella, y lo suyo propio aparte. Las que no tienen a nadie no
     * salen. Con un filtro de unidad, el árbol empieza en ella.
     *
     * Va en una lista, en el orden del árbol —cada unidad seguida de sus
     * hijas— y con su profundidad: anidado, el tipo de las hijas no se podía
     * escribir, y el frontend tenía que aplanarlo de todos modos para la tabla.
     * Lo que cuelga de una unidad son las filas que la siguen con más
     * profundidad.
     *
     * @return list<array{id: int, nombre: string, nivel: int|null, profundidad: int, padre_id: int|null, tiene_hijas: bool, personas: int, mensual: float, anual: float, porcentaje: float, promedio: float, propias: array{personas: int, mensual: float}}>
     */
    private function arbol(Collection $suman, ?int $unidadId, int $total): array
    {
        // Con las borradas: un vínculo que sigue en una unidad que ya no está
        // en el organigrama también suma, y tiene que salir en algún sitio.
        $unidades = UnidadAdministrativa::withTrashed()->get(['id', 'nombre', 'nivel', 'unidad_padre_id'])->keyBy('id');
        $hijas = $unidades->groupBy(fn (UnidadAdministrativa $u) => $u->unidad_padre_id !== null && $unidades->has($u->unidad_padre_id)
            ? $u->unidad_padre_id
            : 0);
        $porUnidad = $suman->whereNotNull('unidad_id')->groupBy('unidad_id');
        $vistas = [];

        $nodo = function (int $id) use (&$nodo, &$vistas, $unidades, $hijas, $porUnidad, $total): array {
            // El organigrama es un árbol, pero un ciclo mal cargado no puede
            // colgar la petición.
            if (isset($vistas[$id])) {
                return [null, collect()];
            }
            $vistas[$id] = true;

            $propias = collect($porUnidad->get($id, []))->values();
            $filas = $propias;
            $hijos = [];

            foreach ($hijas->get($id, collect()) as $hija) {
                [$hijo, $filasDeLaHija] = $nodo((int) $hija->id);
                $filas = $filas->merge($filasDeLaHija);

                if ($hijo !== null) {
                    $hijos[] = $hijo;
                }
            }

            if ($filas->isEmpty()) {
                return [null, $filas];
            }

            $unidad = $unidades->get($id);

            return [[
                'id'      => $id,
                'nombre'  => $unidad?->nombre ?? '—',
                'nivel'   => $unidad?->nivel,
                ...$this->bloque($filas, $total),
                'propias' => [
                    'personas' => $propias->pluck('servidor_id')->unique()->count(),
                    'mensual'  => $this->dinero((int) $propias->sum('centavos')),
                ],
                'hijas'   => $this->ordenar($hijos),
            ], $filas];
        };

        $raices = $unidadId !== null
            ? [$unidadId]
            : $hijas->get(0, collect())->pluck('id')->map(fn ($id) => (int) $id)->all();

        return $this->aplanar($this->ordenar(array_values(array_filter(array_map(fn (int $id) => $nodo($id)[0], $raices)))));
    }

    /**
     * @param  list<array<string, mixed>>  $nodos
     * @return list<array<string, mixed>>
     */
    private function aplanar(array $nodos, int $profundidad = 0, ?int $padreId = null): array
    {
        $filas = [];

        foreach ($nodos as $nodo) {
            $hijas = $nodo['hijas'];
            unset($nodo['hijas']);

            $filas[] = [
                ...$nodo,
                'profundidad' => $profundidad,
                'padre_id'    => $padreId,
                'tiene_hijas' => $hijas !== [],
            ];

            array_push($filas, ...$this->aplanar($hijas, $profundidad + 1, $nodo['id']));
        }

        return $filas;
    }

    /**
     * Lo que más pesa primero.
     *
     * @param  list<array<string, mixed>>  $nodos
     * @return list<array<string, mixed>>
     */
    private function ordenar(array $nodos): array
    {
        return collect($nodos)->sortBy([['mensual', 'desc'], ['nombre', 'asc'], ['id', 'asc']])->values()->all();
    }

    /**
     * El tipo va escrito entero y no con el alias `Bloque`: Scramble no lee los
     * alias, y sin él el frontend recibiría un objeto sin campos.
     *
     * @param  Collection<int, array<string, mixed>>  $filas
     * @return array{personas: int, mensual: float, anual: float, porcentaje: float, promedio: float}
     */
    private function bloque(Collection $filas, int $total): array
    {
        $centavos = (int) $filas->sum('centavos');
        $vinculos = $filas->count();

        return [
            'personas'   => $filas->pluck('servidor_id')->unique()->count(),
            'mensual'    => $this->dinero($centavos),
            'anual'      => $this->dinero($centavos * 12),
            'porcentaje' => $total > 0 ? round($centavos * 100 / $total, 1) : 0.0,
            'promedio'   => $vinculos > 0 ? $this->dinero((int) round($centavos / $vinculos)) : 0.0,
        ];
    }

    // ── Lo que va aparte ────────────────────────────────────────────

    /**
     * Las subrogaciones y los encargos que surten efecto en la fecha, con las
     * dos remuneraciones que congeló su acción (LOSEP Arts. 126 y 127). La
     * diferencia no entra en el total (D5). La cancelada cuenta hasta la
     * víspera de cancelarse; la cancelada antes de que quedara dicho cuándo no
     * se sabe cuánto duró, y no va, como en las novedades del mes.
     *
     * @param  Collection<int, int>  $servidores  los que suman
     * @return array{casos: int, mensual: float, detalle: list<array{
     *   subrogacion_id: int, servidor_id: int, servidor: string, tipo: string,
     *   puesto_del_que_asume: float, la_suya: float, diferencia: float
     * }>}
     */
    private function subrogaciones(CarbonImmutable $corte, Collection $servidores): array
    {
        $fecha = $corte->toDateString();

        $detalle = Subrogacion::query()
            ->whereIn('servidor_subrogante_id', $servidores->all())
            ->whereHas('movimientoPersonal', fn (Builder $q) => $q
                ->whereIn('estado', array_map(fn ($e) => $e->value, self::ACCIONES_EMITIDAS)))
            ->whereDate('fecha_inicio', '<=', $fecha)
            ->where(fn (Builder $q) => $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $fecha))
            ->with([
                'subrogante:id,nombre,segundo_nombre,apellido,segundo_apellido',
                'movimientoPersonal:id,remuneracion_propuesta,remuneracion_origen',
            ])
            ->orderBy('id')
            ->get()
            ->filter(fn (Subrogacion $s) => $s->estado !== EstadoSubrogacion::CANCELADA
                || ($s->cancelada_en !== null && $fecha < $s->cancelada_en->toDateString()))
            ->map(function (Subrogacion $s) {
                $acto = $s->movimientoPersonal;

                if ($acto?->remuneracion_propuesta === null || $acto->remuneracion_origen === null) {
                    return null;
                }

                $delPuesto = (int) round((float) $acto->remuneracion_propuesta * 100);
                $suya = (int) round((float) $acto->remuneracion_origen * 100);

                // Si ya percibe lo mismo o más, no hay nada que pagarle.
                if ($delPuesto <= $suya) {
                    return null;
                }

                return [
                    'subrogacion_id'       => $s->id,
                    'servidor_id'          => $s->servidor_subrogante_id,
                    'servidor'             => OcupantesDelPuesto::nombre($s->subrogante),
                    'tipo'                 => $s->tipo === TipoSubrogacion::ENCARGO ? 'encargo' : 'subrogacion',
                    'puesto_del_que_asume' => $this->dinero($delPuesto),
                    'la_suya'              => $this->dinero($suya),
                    'diferencia'           => $this->dinero($delPuesto - $suya),
                    'centavos'             => $delPuesto - $suya,
                ];
            })
            ->filter()
            ->values();

        return [
            'casos'   => $detalle->count(),
            'mensual' => $this->dinero((int) $detalle->sum('centavos')),
            /** @var list<array{subrogacion_id: int, servidor_id: int, servidor: string, tipo: string, puesto_del_que_asume: float, la_suya: float, diferencia: float}> */
            'detalle' => $detalle->map(fn (array $d) => array_diff_key($d, ['centavos' => true]))->all(),
        ];
    }

    /**
     * Los servidores activos sin ningún vínculo vigente en la fecha: no suman
     * nada, y sin esta lista nadie lo notaría. Solo se sabe para hoy —`estado`
     * es el de hoy— y sin filtro de régimen ni de tipo, que salen del vínculo
     * que no tienen.
     *
     * @param  list<int>|null  $unidades
     * @return list<array{id: int, servidor: string, cedula: ?string}>|null
     */
    private function servidoresSinVinculo(CarbonImmutable $corte, ?RegimenLaboral $regimen, ?TipoNombramiento $tipo, ?array $unidades): ?array
    {
        if (! $corte->isSameDay(today()) || $regimen !== null || $tipo !== null) {
            return null;
        }

        return Servidor::query()
            ->where('estado', true)
            ->whereDoesntHave('contratos', fn (Builder $q) => $this->vigentesAl($q, $corte))
            ->when($unidades !== null, fn (Builder $q) => $q->whereIn('unidad_administrativa_id', $unidades))
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->orderBy('id')
            ->get(['id', 'nombre', 'segundo_nombre', 'apellido', 'segundo_apellido', 'cedula'])
            ->map(fn (Servidor $s) => [
                'id'       => $s->id,
                'servidor' => OcupantesDelPuesto::nombre($s),
                'cedula'   => $s->cedula,
            ])
            ->all();
    }

    private function dinero(int $centavos): float
    {
        return round($centavos / 100, 2);
    }
}
