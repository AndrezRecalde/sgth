<?php

namespace App\Services\Estructura\Manual;

use App\Enums\TipoPuesto;
use App\Models\Estructura\EquivalenciaUnidadManual;
use App\Models\Estructura\FichaManualPuestos;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * El puesto que se sugiere para cada ficha del manual
 * (docs/diseno-manual-de-puestos.md, P4; PR 12).
 *
 * Dos pasos:
 * 1. La gestión de la ficha («Gestión Administrativa/Bodega y Activos» →
 *    «Gestión Administrativa») va a una unidad de hoy: la que eligió TH en las
 *    equivalencias o, si no eligió, la de nombre más parecido.
 * 2. Entre los puestos de plantilla de esa unidad, el que coincide en el rol
 *    («Analista»), en el grado y, si hay varios, en la especialidad («Bodega»).
 *
 * El manual nombra los puestos por su rol, y el SGTH por el cargo: «Analista»
 * de grado 12 en Bodega y Activos es «Analista de Bodega». Por eso el grado
 * pesa tanto como el nombre.
 *
 * Nada de esto carga un perfil: es lo que la pantalla de revisión propone.
 */
final class SugerenciaDePuestos
{
    /** Las palabras que no distinguen una unidad ni un cargo de otro. */
    private const VACIAS = ['de', 'la', 'y', 'e', 'del', 'los', 'las', 'el', 'en', 'a', 'o', 'para', 'gestion', 'unidad', 'direccion'];

    /** Desde cuánto parecido (0 a 1) se sugiere una unidad por el nombre. */
    private const PARECIDO_MINIMO = 0.7;

    /** @var array<string, int> clave de la gestión → unidad elegida por TH */
    private array $elegidas;

    /** @var array<int, FichaManualPuestos> puesto → la ficha que tiene cargada */
    private array $cargadas;

    /**
     * @param  Collection<int, UnidadAdministrativa>  $unidades  Las que pueden tener puestos.
     * @param  Collection<int, Puesto>  $puestos  Con `cargo` y `grupoOcupacional`.
     * @param  Collection<int, EquivalenciaUnidadManual>  $equivalencias
     * @param  Collection<int, FichaManualPuestos>  $fichas  Todas: para ver qué puesto ya tiene ficha.
     */
    public function __construct(
        private readonly Collection $unidades,
        private readonly Collection $puestos,
        Collection $equivalencias,
        Collection $fichas,
    ) {
        $this->elegidas = $equivalencias->pluck('unidad_administrativa_id', 'gestion')->all();
        $this->cargadas = $fichas->whereNotNull('puesto_id')->keyBy('puesto_id')->all();
    }

    /** Con lo que hay en la base. */
    public static function desdeLaBase(Collection $fichas): self
    {
        return new self(
            UnidadAdministrativa::query()->where('estado', true)->where('nivel', '<=', 2)->orderBy('nombre')->get(),
            Puesto::query()
                ->where('activo', true)
                ->where(fn ($q) => $q->whereNull('tipo')->orWhere('tipo', TipoPuesto::PLANTILLA->value))
                ->with(['cargo:id,nombre', 'grupoOcupacional:id,grado_numerico,grupo'])
                ->get(),
            EquivalenciaUnidadManual::all(),
            $fichas,
        );
    }

    /**
     * La gestión de la ficha: la de su unidad o, si la ficha no la escribe
     * (7 del manual), la de su carpeta: «1.GESTION DE SECRETARIA GENERAL».
     */
    public static function gestionDe(FichaManualPuestos $ficha): string
    {
        $gestion = ImportacionDelManualService::partes((string) $ficha->unidad)['gestion'];
        if ($gestion !== '') {
            return $gestion;
        }

        $carpeta = explode('/', (string) $ficha->carpeta)[0];

        return trim((string) preg_replace('/^\s*\d+\s*[.\-]?\s*/u', '', $carpeta));
    }

    /** La clave de una gestión: sin tildes, en minúsculas y sin signos. */
    public static function clave(string $texto): string
    {
        $texto = preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($texto)));

        return trim((string) preg_replace('/\s+/', ' ', (string) $texto));
    }

    /**
     * La unidad de hoy de una gestión del manual.
     *
     * @return array{id: int, nombre: string, origen: 'elegida'|'sugerida'}|null
     */
    public function unidadDe(string $gestion): ?array
    {
        $clave = self::clave($gestion);
        if ($clave === '') {
            return null;
        }

        if (isset($this->elegidas[$clave])) {
            $unidad = $this->unidades->firstWhere('id', $this->elegidas[$clave])
                ?? UnidadAdministrativa::find($this->elegidas[$clave]);

            return $unidad ? ['id' => $unidad->id, 'nombre' => $unidad->nombre, 'origen' => 'elegida'] : null;
        }

        $unidad = $this->sugerirUnidad($clave);

        return $unidad ? ['id' => $unidad->id, 'nombre' => $unidad->nombre, 'origen' => 'sugerida'] : null;
    }

    /**
     * Lo que la revisión muestra de cada ficha: su unidad, los puestos de esa
     * unidad ordenados por parecido, el sugerido y el estado.
     *
     * @param  Collection<int, FichaManualPuestos>  $fichas
     * @return array<int, array{gestion: string, unidad: ?array{id: int, nombre: string, origen: string}, candidatos: list<array<string, mixed>>, sugerido: ?int, estado: string, motivo: string}>
     */
    public function analizar(Collection $fichas): array
    {
        $analisis = [];
        foreach ($fichas as $ficha) {
            $analisis[$ficha->id] = $this->analizarUna($ficha);
        }

        // Dos fichas que apuntan al mismo puesto no están listas: el perfil
        // es uno por puesto, y TH tiene que decir cuál va.
        $porPuesto = collect($analisis)
            ->filter(fn ($a) => $a['estado'] === 'lista')
            ->groupBy('sugerido', preserveKeys: true);
        foreach ($porPuesto as $mismas) {
            if ($mismas->count() > 1) {
                foreach ($mismas->keys() as $fichaId) {
                    $analisis[$fichaId]['estado'] = 'para_revisar';
                    $analisis[$fichaId]['motivo'] = "Otras fichas apuntan al mismo puesto ({$mismas->count()} en total): el perfil es uno por puesto.";
                }
            }
        }

        return $analisis;
    }

    /** @return array{gestion: string, unidad: ?array{id: int, nombre: string, origen: string}, candidatos: list<array<string, mixed>>, sugerido: ?int, estado: string, motivo: string} */
    private function analizarUna(FichaManualPuestos $ficha): array
    {
        $gestion    = self::gestionDe($ficha);
        $subproceso = ImportacionDelManualService::partes((string) $ficha->unidad)['subproceso'];
        $unidad     = $this->unidadDe($gestion);
        $base       = ['gestion' => $gestion, 'unidad' => $unidad, 'candidatos' => [], 'sugerido' => null];

        if ($ficha->puesto_id !== null) {
            return [...$base, 'sugerido' => $ficha->puesto_id, 'estado' => 'cargada', 'motivo' => 'El perfil ya se cargó en el puesto.'];
        }

        if ($unidad === null) {
            return [...$base, 'estado' => 'sin_unidad', 'motivo' => $gestion === ''
                ? 'La ficha no dice su unidad.'
                : "No se sabe a qué unidad de hoy corresponde «{$gestion}»: elíjala en Unidades del manual."];
        }

        $candidatos = $this->candidatos($ficha, $unidad['id'], $subproceso);
        $base['candidatos'] = $candidatos;
        ['rol' => $rol, 'apoyo' => $apoyo] = $this->rol((string) $ficha->denominacion);
        // «asistente de apoyo»: el motivo nombra el rol entero.
        $rol = $apoyo && ! str_contains(Str::lower(Str::ascii($rol)), 'apoyo') ? "{$rol} de apoyo" : $rol;

        if ($candidatos === []) {
            return [...$base, 'estado' => 'sin_puesto', 'motivo' => "{$unidad['nombre']} no tiene puestos de plantilla."];
        }

        $mejor = $candidatos[0];
        if ($mejor['puntaje'] < 2) {
            return [...$base, 'estado' => 'sin_puesto', 'motivo' => sprintf(
                'En %s no hay un puesto de %s ni de grado %s.',
                $unidad['nombre'], $rol === '' ? 'ese rol' : Str::lower($rol), $ficha->grado ?? '—',
            )];
        }

        $base['sugerido'] = $mejor['puesto_id'];
        $empatados = collect($candidatos)->where('puntaje', $mejor['puntaje'])->count();

        $motivo = match (true) {
            $mejor['ficha_cargada'] !== null   => "El puesto ya tiene cargada la ficha «{$mejor['ficha_cargada']['archivo']}».",
            ! $mejor['coincide']['rol']        => 'El grado coincide, pero el cargo no es de '.Str::lower($rol).'.',
            ! $mejor['coincide']['grado']      => "El cargo coincide, pero el puesto es de grado {$mejor['grado']} y la ficha de {$ficha->grado}.",
            $empatados > 1                     => "Hay {$empatados} puestos parecidos en {$unidad['nombre']}: elija el que corresponde.",
            default                            => null,
        };

        return $motivo === null
            ? [...$base, 'estado' => 'lista', 'motivo' => "Mismo cargo y grado en {$unidad['nombre']}."]
            : [...$base, 'estado' => 'para_revisar', 'motivo' => $motivo];
    }

    /**
     * Los puestos de plantilla de la unidad, del más parecido al menos.
     * Puntaje: rol 2, grado 2, especialidad 1.
     *
     * @return list<array{puesto_id: int, cargo: string, grado: ?int, grupo: ?string, plazas: ?int, puntaje: int, coincide: array{rol: bool, grado: bool, especialidad: bool}, ficha_cargada: ?array{id: int, archivo: string}}>
     */
    private function candidatos(FichaManualPuestos $ficha, int $unidadId, ?string $subproceso): array
    {
        ['rol' => $rol, 'apoyo' => $apoyo, 'especialidad' => $especialidad] = $this->rol((string) $ficha->denominacion);
        $palabras = self::palabras(trim($especialidad.' '.$subproceso));

        return $this->puestos
            ->where('unidad_administrativa_id', $unidadId)
            ->map(function (Puesto $puesto) use ($ficha, $rol, $apoyo, $palabras) {
                $cargo   = (string) $puesto->cargo?->nombre;
                $delCargo = self::palabras($cargo);
                $grado   = $puesto->grupoOcupacional?->grado_numerico;

                $coincide = [
                    'rol'          => $rol !== '' && $delCargo !== []
                        && self::parecidas(self::palabras($rol)[0] ?? '', $delCargo[0])
                        && $apoyo === in_array('apoyo', $delCargo, true),
                    'grado'        => $ficha->grado !== null && $grado !== null && (int) $grado === $ficha->grado,
                    'especialidad' => $palabras !== [] && collect(array_slice($delCargo, 1))
                        ->contains(fn ($p) => collect($palabras)->contains(fn ($q) => self::parecidas($p, $q))),
                ];
                $cargada = $this->cargadas[$puesto->id] ?? null;

                return [
                    'puesto_id'     => $puesto->id,
                    'cargo'         => $cargo,
                    'grado'         => $grado === null ? null : (int) $grado,
                    'grupo'         => $puesto->grupoOcupacional?->grupo,
                    'plazas'        => $puesto->plazas,
                    'puntaje'       => ($coincide['rol'] ? 2 : 0) + ($coincide['grado'] ? 2 : 0) + ($coincide['especialidad'] ? 1 : 0),
                    'coincide'      => $coincide,
                    'ficha_cargada' => $cargada && $cargada->id !== $ficha->id ? ['id' => $cargada->id, 'archivo' => $cargada->archivo] : null,
                ];
            })
            ->sortBy([['puntaje', 'desc'], ['cargo', 'asc'], ['puesto_id', 'asc']])
            ->values()
            ->all();
    }

    /**
     * «Analista/Odontólogo-a» → rol «Analista», especialidad «Odontólogo-a».
     * «Asistente de Apoyo/Servicio Médico» es asistente de apoyo: no es el
     * mismo puesto que un asistente.
     *
     * @return array{rol: string, apoyo: bool, especialidad: string}
     */
    private function rol(string $denominacion): array
    {
        $partes = preg_split('/\s*[\/,]\s*/u', trim($denominacion), 2) ?: [''];
        $nombre = trim($partes[0]);
        $resto  = trim($partes[1] ?? '');

        // «Coordinador de Mantenimiento Vial»: el rol es la primera palabra y
        // lo demás, la especialidad.
        $palabras = preg_split('/\s+/u', $nombre) ?: [];
        $apoyo    = in_array('apoyo', self::palabras($nombre), true);

        return [
            'rol'          => $palabras[0] ?? '',
            'apoyo'        => $apoyo,
            'especialidad' => trim(implode(' ', array_slice($palabras, 1)).' '.$resto),
        ];
    }

    /** La unidad de nombre más parecido, si se parece lo bastante. */
    private function sugerirUnidad(string $clave): ?UnidadAdministrativa
    {
        $deLaGestion = self::palabras($clave);
        if ($deLaGestion === []) {
            return null;
        }

        $mejor   = null;
        $parecido = 0.0;
        foreach ($this->unidades as $unidad) {
            $deLaUnidad = self::palabras($unidad->nombre);
            if ($deLaUnidad === []) {
                continue;
            }
            // Cuánto de cada nombre está en el otro: «Talento Humano» está
            // entero en «Talento Humano y Riesgos Laborales», pero no al revés.
            $p = (self::cobertura($deLaGestion, $deLaUnidad) + self::cobertura($deLaUnidad, $deLaGestion)) / 2;
            if ($p > $parecido) {
                [$mejor, $parecido] = [$unidad, $p];
            }
        }

        return $parecido >= self::PARECIDO_MINIMO ? $mejor : null;
    }

    /**
     * @param  list<string>  $unas
     * @param  list<string>  $otras
     */
    private static function cobertura(array $unas, array $otras): float
    {
        $halladas = collect($unas)->filter(fn ($p) => collect($otras)->contains(fn ($q) => self::parecidas($p, $q)))->count();

        return $halladas / count($unas);
    }

    /** Dos palabras que son la misma, con alguna errata: «Fiiscalizacón». */
    private static function parecidas(string $una, string $otra): bool
    {
        if ($una === $otra) {
            return true;
        }
        similar_text($una, $otra, $porcentaje);

        return $porcentaje >= 80;
    }

    /** @return list<string> */
    private static function palabras(string $texto): array
    {
        return array_values(array_diff(explode(' ', self::clave($texto)), self::VACIAS, ['']));
    }
}
