<?php

namespace App\Services\Estructura\Manual;

use App\Enums\NivelInstruccion;
use App\Models\Estructura\Competencia;
use App\Models\Estructura\EquivalenciaUnidadManual;
use App\Models\Estructura\FichaManualPuestos;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\PuestoPerfil;
use App\Models\User;
use App\Services\Estructura\PerfilDelPuestoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * La revisión de las fichas del Manual de Puestos 2018-2019
 * (docs/diseno-manual-de-puestos.md, P4; PR 12). Talento Humano ve cada
 * ficha con el puesto que se le sugiere, corrige las equivalencias de
 * unidades y carga el perfil en el puesto que confirma.
 *
 * Cargar reemplaza lo que el puesto tenía: la misión, las relaciones, los
 * requisitos, la capacitación, las actividades y las competencias. Sin
 * aprobación (D6), lo cargado queda como el perfil del puesto. El grado y la
 * unidad siguen siendo los del puesto.
 */
class RevisionDelManualService
{
    /** Los estados de la revisión, en el orden en que se muestran. */
    public const ESTADOS = ['lista', 'para_revisar', 'sin_puesto', 'sin_unidad', 'cargada'];

    /** Lo que tiene un puesto sin perfil. */
    private const SIN_CONTENIDO = ['mision' => false, 'requisitos' => 0, 'actividades' => 0, 'competencias' => 0];

    public function __construct(private readonly PerfilDelPuestoService $perfiles)
    {
    }

    /**
     * Todas las fichas, con su estado y el puesto sugerido o cargado.
     *
     * @return array{fichas: list<array<string, mixed>>, resumen: array<string, int>}
     */
    public function fichas(): array
    {
        $fichas   = $this->todas();
        $analisis = SugerenciaDePuestos::desdeLaBase($fichas)->analizar($fichas);
        $puestos  = $this->puestos(collect($analisis)->pluck('sugerido')->filter()->all());

        $filas = $fichas->map(fn (FichaManualPuestos $f) => $this->fila($f, $analisis[$f->id], $puestos))->values()->all();

        $resumen = array_fill_keys(self::ESTADOS, 0);
        foreach ($filas as $fila) {
            $resumen[$fila['estado']]++;
        }

        return ['fichas' => $filas, 'resumen' => $resumen];
    }

    /**
     * Una ficha entera: la propuesta de perfil, los avisos y los puestos de su
     * unidad para elegir, cada uno con lo que ya tiene y se reemplazaría.
     *
     * @return array<string, mixed>
     */
    public function ficha(FichaManualPuestos $ficha): array
    {
        $fichas   = $this->todas();
        $ficha    = $fichas->firstWhere('id', $ficha->id) ?? $ficha;
        $analisis = SugerenciaDePuestos::desdeLaBase($fichas)->analizar($fichas)[$ficha->id];

        $ids        = collect($analisis['candidatos'])->pluck('puesto_id')->push($analisis['sugerido'])->filter()->unique()->all();
        $puestos    = $this->puestos($ids);
        $contenidos = $this->contenidos($ids);

        return [
            ...$this->fila($ficha, $analisis, $puestos),
            'avisos'     => $ficha->avisos ?? [],
            'propuesta'  => $ficha->propuesta,
            'ficha'      => [
                'nivel'             => $ficha->ficha['nivel'] ?? null,
                'rol'               => $ficha->ficha['rol'] ?? null,
                'ambito'            => $ficha->ficha['ambito'] ?? null,
                'instruccion'       => $ficha->ficha['instruccion'] ?? [],
            ],
            'candidatos' => collect($analisis['candidatos'])
                ->map(fn (array $c) => [...$c, 'contenido' => $contenidos[$c['puesto_id']] ?? self::SIN_CONTENIDO])
                ->values()
                ->all(),
            'contenido_cargado' => $ficha->puesto_id ? ($contenidos[$ficha->puesto_id] ?? self::SIN_CONTENIDO) : null,
        ];
    }

    /**
     * Las gestiones que nombra el manual, cada una con la unidad de hoy que
     * eligió TH o la que se sugiere.
     *
     * @return list<array{gestion: string, nombre: string, fichas: int, unidad: ?array{id: int, nombre: string, origen: string}}>
     */
    public function equivalencias(): array
    {
        $fichas     = $this->todas();
        $sugerencia = SugerenciaDePuestos::desdeLaBase($fichas);

        return $fichas
            ->map(fn (FichaManualPuestos $f) => SugerenciaDePuestos::gestionDe($f))
            ->filter(fn (string $g) => SugerenciaDePuestos::clave($g) !== '')
            ->groupBy(fn (string $g) => SugerenciaDePuestos::clave($g))
            ->map(fn (Collection $nombres, string $clave) => [
                'gestion' => $clave,
                // El nombre con que más fichas la escriben; el de la carpeta,
                // en mayúsculas, solo si ninguna ficha la escribe.
                'nombre'  => $nombres->countBy()->sortDesc()->keys()
                    ->sortBy(fn (string $n) => mb_strtoupper($n) === $n ? 1 : 0)->first(),
                'fichas'  => $nombres->count(),
                'unidad'  => $sugerencia->unidadDe($nombres->first()),
            ])
            ->sortBy('nombre', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /** Sin unidad, la gestión vuelve a la que se sugiere por el nombre. */
    public function guardarEquivalencia(string $gestion, ?int $unidadId): void
    {
        $clave = SugerenciaDePuestos::clave($gestion);

        if ($unidadId === null) {
            EquivalenciaUnidadManual::where('gestion', $clave)->delete();

            return;
        }

        EquivalenciaUnidadManual::updateOrCreate(['gestion' => $clave], ['unidad_administrativa_id' => $unidadId]);
    }

    /**
     * Carga la propuesta de la ficha como perfil del puesto.
     *
     * @return array{requisitos: int, actividades: int, competencias: int}
     */
    public function cargar(FichaManualPuestos $ficha, Puesto $puesto, User $usuario): array
    {
        return DB::transaction(function () use ($ficha, $puesto, $usuario) {
            // Bloqueadas, para que dos personas no carguen dos fichas en el
            // mismo puesto a la vez.
            $ficha = FichaManualPuestos::whereKey($ficha->id)->lockForUpdate()->firstOrFail();
            $otra  = FichaManualPuestos::where('puesto_id', $puesto->id)->whereKeyNot($ficha->id)->lockForUpdate()->first();

            if ($ficha->puesto_id !== null && $ficha->puesto_id !== $puesto->id) {
                throw ValidationException::withMessages([
                    'puesto_id' => 'Esta ficha ya está cargada en otro puesto. Quite ese emparejamiento antes de cargarla en este.',
                ]);
            }
            if ($otra !== null) {
                throw ValidationException::withMessages([
                    'puesto_id' => "El puesto ya tiene cargada la ficha «{$otra->archivo}». Un puesto tiene un solo perfil: quite ese emparejamiento primero.",
                ]);
            }

            $propuesta = $ficha->propuesta;
            $antes     = [
                'actividades'  => $puesto->actividades()->count(),
                'competencias' => $puesto->competencias()->count(),
            ];

            // La misión, las relaciones, los requisitos y la capacitación.
            $requisitos = collect($propuesta['requisitos'] ?? [])
                ->filter(fn ($r) => NivelInstruccion::tryFrom((string) ($r['nivel_instruccion'] ?? '')) !== null)
                ->take(5)
                ->map(fn ($r) => [
                    'nivel_instruccion' => $r['nivel_instruccion'],
                    'detalle'           => isset($r['detalle']) ? Str::limit((string) $r['detalle'], 200, '') : null,
                    // «Hasta 1 año» no pide un mínimo: cero.
                    'meses_experiencia' => min(600, max(0, (int) ($r['meses_experiencia'] ?? 0))),
                ])
                ->values()
                ->all();
            $this->perfiles->guardar($puesto, [
                ...array_intersect_key($propuesta, array_flip(PuestoPerfil::CAMPOS)),
                'requisitos' => $requisitos,
            ], 'Manual de Puestos');

            // Las actividades, en el orden de la ficha.
            $puesto->actividades()->delete();
            foreach (array_values($propuesta['actividades'] ?? []) as $i => $actividad) {
                $puesto->actividades()->create([
                    'descripcion'   => (string) $actividad['descripcion'],
                    'conocimientos' => isset($actividad['conocimientos']) ? Str::limit((string) $actividad['conocimientos'], 1000, '') : null,
                    'orden'         => min(255, $i + 1),
                    'activo'        => true,
                ]);
            }

            // Las competencias, con las que sigan en el diccionario.
            $vigentes = Competencia::whereIn('id', collect($propuesta['competencias'] ?? [])->pluck('competencia_id'))->pluck('id')->all();
            $puesto->competencias()->delete();
            $competencias = collect($propuesta['competencias'] ?? [])
                ->filter(fn ($c) => in_array($c['competencia_id'] ?? null, $vigentes, true))
                ->unique('competencia_id');
            foreach ($competencias as $competencia) {
                $puesto->competencias()->create([
                    'competencia_id' => $competencia['competencia_id'],
                    'nivel'          => $competencia['nivel'],
                ]);
            }

            $ficha->update(['puesto_id' => $puesto->id, 'cargada_por' => $usuario->id, 'cargada_en' => now()]);

            $cargado = [
                'requisitos'   => count($requisitos),
                'actividades'  => count($propuesta['actividades'] ?? []),
                'competencias' => $competencias->count(),
            ];

            activity()
                ->performedOn($puesto)
                ->causedBy($usuario)
                ->event('updated')
                ->withProperties([
                    'archivo' => $ficha->archivo,
                    'antes'   => $antes,
                    'despues' => ['actividades' => $cargado['actividades'], 'competencias' => $cargado['competencias']],
                    'causa'   => 'Manual de Puestos',
                ])
                ->log('Perfil cargado del Manual de Puestos');

            return $cargado;
        });
    }

    /**
     * Quita el emparejamiento. El perfil que se cargó se queda en el puesto:
     * desde ahí se edita como cualquier otro.
     */
    public function desvincular(FichaManualPuestos $ficha): void
    {
        $ficha->update(['puesto_id' => null, 'cargada_por' => null, 'cargada_en' => null]);
    }

    /** @return Collection<int, FichaManualPuestos> */
    private function todas(): Collection
    {
        return FichaManualPuestos::query()
            ->with('cargadaPor.servidor')
            ->orderBy('archivo')
            ->get();
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, Puesto>
     */
    private function puestos(array $ids): Collection
    {
        return Puesto::withTrashed()
            ->whereIn('id', $ids)
            ->with(['cargo:id,nombre', 'unidadAdministrativa:id,nombre', 'grupoOcupacional:id,grado_numerico,grupo'])
            ->get()
            ->keyBy('id');
    }

    /**
     * Lo que cada puesto ya tiene y se reemplazaría al cargar.
     *
     * @param  list<int>  $ids
     * @return array<int, array{mision: bool, requisitos: int, actividades: int, competencias: int}>
     */
    private function contenidos(array $ids): array
    {
        return Puesto::withTrashed()
            ->whereIn('id', $ids)
            ->withCount(['actividades', 'competencias'])
            ->with('perfil.requisitos')
            ->get()
            ->mapWithKeys(fn (Puesto $p) => [$p->id => [
                'mision'       => filled($p->perfil?->mision),
                'requisitos'   => $p->perfil?->requisitos->count() ?? 0,
                'actividades'  => (int) $p->actividades_count,
                'competencias' => (int) $p->competencias_count,
            ]])
            ->all();
    }

    /**
     * @param  array{gestion: string, unidad: ?array<string, mixed>, sugerido: ?int, estado: string, motivo: string}  $analisis
     * @param  Collection<int, Puesto>  $puestos
     * @return array<string, mixed>
     */
    private function fila(FichaManualPuestos $ficha, array $analisis, Collection $puestos): array
    {
        $puesto = $analisis['sugerido'] ? $puestos->get($analisis['sugerido']) : null;

        return [
            'id'                => $ficha->id,
            'archivo'           => $ficha->archivo,
            'proceso'           => $ficha->proceso,
            'denominacion'      => $ficha->denominacion,
            'unidad'            => $ficha->unidad,
            'gestion'           => $analisis['gestion'],
            'subproceso'        => $ficha->propuesta['subproceso'] ?? null,
            'grupo_ocupacional' => $ficha->grupo_ocupacional,
            'grado'             => $ficha->grado,
            'plazas'            => $ficha->plazas,
            'avisos_count'      => count($ficha->avisos ?? []),
            'estado'            => $analisis['estado'],
            'motivo'            => $analisis['motivo'],
            'unidad_sgth'       => $analisis['unidad'],
            'puesto'            => $puesto ? [
                'id'       => $puesto->id,
                'cargo'    => $puesto->cargo?->nombre,
                'unidad'   => $puesto->unidadAdministrativa?->nombre,
                'grado'    => $puesto->grupoOcupacional?->grado_numerico,
                'eliminado' => $puesto->trashed(),
            ] : null,
            'cargada_en'        => $ficha->cargada_en?->toIso8601String(),
            'cargada_por'       => $ficha->cargadaPor?->nombre_completo,
        ];
    }
}
