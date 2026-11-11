<?php

namespace App\Services\Estructura\Manual;

use App\Enums\NivelDelPuesto;
use App\Enums\NivelInstruccion;
use App\Models\Estructura\Competencia;
use App\Models\Estructura\FichaManualPuestos;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Carga las fichas del Manual de Puestos 2018-2019 en la tabla de staging
 * (docs/diseno-manual-de-puestos.md, P4; PR 11). Cada ficha llega como la dejó
 * el extractor (`scripts/manual-puestos/`), y aquí se pasa a los términos del
 * perfil:
 * - la misión, las relaciones y los textos de instrucción, experiencia y
 *   capacitación, tal cual;
 * - el subproceso, de la unidad «Gestión / Subproceso» (D2);
 * - cada alternativa de instrucción con su nivel y sus meses de experiencia;
 * - las actividades con sus conocimientos;
 * - las competencias, emparejadas con el diccionario y con su número
 *   recalculado.
 *
 * Lo que necesita un ojo humano queda en los avisos de la ficha. Nada de esto
 * toca un puesto: eso lo hace la revisión de TH (PR 12). Volver a importar
 * actualiza cada ficha por su archivo.
 */
class ImportacionDelManualService
{
    /** El JSON del extractor, dentro de `database/`. */
    public const ARCHIVO = 'data/manual-de-puestos-2018-2019.json';

    /**
     * @param  list<array<string, mixed>>  $fichas
     * @return array{fichas: int, nuevas: int, actualizadas: int, con_avisos: int, competencias: int, exactas: int, aproximadas: int, repetidas: int, sin_reconocer: int, renumeradas: int}
     */
    public function importar(array $fichas, bool $simular = false): array
    {
        $emparejador = new EmparejadorDeCompetencias(Competencia::query()->orderBy('tipo')->orderBy('numero')->get());
        $resumen     = [
            'fichas' => 0, 'nuevas' => 0, 'actualizadas' => 0, 'con_avisos' => 0,
            'competencias' => 0, 'exactas' => 0, 'aproximadas' => 0, 'repetidas' => 0, 'sin_reconocer' => 0, 'renumeradas' => 0,
        ];

        DB::transaction(function () use ($fichas, $simular, $emparejador, &$resumen) {
            foreach ($fichas as $ficha) {
                if (! is_array($ficha) || ! is_string($ficha['archivo'] ?? null) || $ficha['archivo'] === '') {
                    throw new InvalidArgumentException('Cada ficha del JSON necesita su «archivo».');
                }

                $leida = $this->interpretar($ficha, $emparejador);
                $resumen['fichas']++;
                $resumen['con_avisos']   += $leida['avisos'] === [] ? 0 : 1;
                $resumen['competencias'] += $leida['conteo']['filas'];
                foreach (['exactas', 'aproximadas', 'repetidas', 'sin_reconocer', 'renumeradas'] as $clave) {
                    $resumen[$clave] += $leida['conteo'][$clave];
                }

                if ($simular) {
                    continue;
                }

                $fila = FichaManualPuestos::updateOrCreate(['archivo' => $ficha['archivo']], [
                    'proceso'           => Str::limit((string) ($ficha['proceso'] ?? ''), 150, ''),
                    'carpeta'           => Str::limit((string) ($ficha['carpeta'] ?? ''), 300, ''),
                    'denominacion'      => self::texto($ficha['denominacion'] ?? null, 200),
                    'unidad'            => self::texto($ficha['unidad'] ?? null, 300),
                    'grupo_ocupacional' => self::texto($ficha['grupo_ocupacional'] ?? null, 100),
                    'grado'             => preg_match('/^\d{1,3}$/', trim((string) ($ficha['grado'] ?? ''))) ? (int) $ficha['grado'] : null,
                    'plazas'            => max(1, (int) ($ficha['plazas'] ?? 1)),
                    'ficha'             => $ficha,
                    'propuesta'         => $leida['propuesta'],
                    'avisos'            => $leida['avisos'],
                ]);
                $resumen[$fila->wasRecentlyCreated ? 'nuevas' : 'actualizadas']++;
            }
        });

        return $resumen;
    }

    /**
     * Una ficha del extractor, en los términos del perfil del puesto.
     *
     * @param  array<string, mixed>  $ficha
     * @return array{propuesta: array<string, mixed>, avisos: list<string>, conteo: array{filas: int, exactas: int, aproximadas: int, repetidas: int, sin_reconocer: int, renumeradas: int}}
     */
    public function interpretar(array $ficha, EmparejadorDeCompetencias $emparejador): array
    {
        $avisos = array_values(array_filter((array) ($ficha['avisos'] ?? []), 'is_string'));
        if (isset($ficha['error'])) {
            $avisos[] = 'El extractor no pudo leer la ficha.';
        }

        // Instrucción y experiencia: cada alternativa, con su nivel y sus meses.
        $requisitos = [];
        foreach ((array) ($ficha['instruccion'] ?? []) as $alternativa) {
            $texto       = trim((string) ($alternativa['nivel'] ?? ''));
            $experiencia = $alternativa['experiencia'] ?? null;
            $nivel       = LecturaDeRequisitos::nivel($texto);
            $meses       = LecturaDeRequisitos::meses(is_string($experiencia) ? $experiencia : null);
            foreach ([$nivel['aviso'], $meses['aviso']] as $aviso) {
                if ($aviso !== null && ! in_array($aviso, $avisos, true)) {
                    $avisos[] = $aviso;
                }
            }
            $requisitos[] = [
                'nivel_instruccion' => $nivel['nivel']?->value,
                'detalle'           => $nivel['detalle'],
                'meses_experiencia' => $meses['meses'],
                'texto'             => $texto,
                'experiencia'       => is_string($experiencia) ? $experiencia : null,
            ];
        }

        $actividades = [];
        foreach ((array) ($ficha['actividades'] ?? []) as $actividad) {
            $descripcion = self::texto($actividad['descripcion'] ?? null);
            if ($descripcion !== null) {
                $actividades[] = ['descripcion' => $descripcion, 'conocimientos' => self::texto($actividad['conocimientos'] ?? null)];
            }
        }

        $competencias = $emparejador->emparejar(
            array_values((array) ($ficha['tecnicas'] ?? [])),
            array_values((array) ($ficha['conductuales'] ?? [])),
        );
        $avisos = [...$avisos, ...$competencias['avisos']];

        $mision = self::texto($ficha['mision'] ?? null);
        foreach ([
            'La ficha no trae misión.'                    => $mision === null,
            'La ficha no trae instrucción formal.'        => $requisitos === [],
            'La ficha no trae actividades.'               => $actividades === [],
            'La ficha no trae competencias técnicas.'     => ! collect($competencias['competencias'])->contains('tipo', 'tecnica'),
            'La ficha no trae competencias conductuales.' => ! collect($competencias['competencias'])->contains('tipo', 'conductual'),
        ] as $aviso => $falta) {
            if ($falta) {
                $avisos[] = $aviso;
            }
        }

        return [
            'propuesta' => [
                'mision'                    => $mision,
                'relaciones'                => self::texto($ficha['relaciones'] ?? null),
                'nivel'                     => self::nivelDelPuesto((string) ($ficha['nivel'] ?? ''), $requisitos)?->value,
                'area_conocimiento'         => self::texto($ficha['area_conocimiento'] ?? null),
                'especificidad_experiencia' => self::texto($ficha['especificidad'] ?? null),
                'capacitacion'              => self::texto($ficha['capacitacion'] ?? null),
                'subproceso'                => self::subproceso((string) ($ficha['unidad'] ?? '')),
                'requisitos'                => $requisitos,
                'actividades'               => $actividades,
                'competencias'              => $competencias['competencias'],
            ],
            'avisos' => $avisos,
            'conteo' => $competencias['conteo'],
        ];
    }

    /**
     * «Gestión de Talento Humano / Seguridad y Salud Ocupacional»: el puesto
     * está en la gestión y el subproceso es un dato del perfil (D2, opción B).
     */
    public static function subproceso(string $unidad): ?string
    {
        return self::partes($unidad)['subproceso'];
    }

    /**
     * La gestión y el subproceso de la unidad que escribe la ficha. El manual
     * los separa con una barra, y tres fichas con paréntesis: «Gestión de
     * Planificación (Planificación Institucional)».
     *
     * @return array{gestion: string, subproceso: ?string}
     */
    public static function partes(string $unidad): array
    {
        $unidad     = trim($unidad);
        $subproceso = null;
        if (preg_match('/^(.+?)\s*\(([^()]+)\)\s*\.?$/u', $unidad, $m)) {
            [$unidad, $subproceso] = [trim($m[1]), trim($m[2])];
        }

        $partes = array_values(array_filter(array_map('trim', explode('/', $unidad)), fn ($p) => $p !== ''));
        if (count($partes) > 1) {
            $subproceso = implode(' / ', array_slice($partes, 1));
        }

        return [
            'gestion'    => $partes[0] ?? '',
            'subproceso' => $subproceso === null ? null : Str::limit($subproceso, 200, ''),
        ];
    }

    /**
     * El que escribe la ficha; si no lo trae, el que sale de la instrucción.
     *
     * @param  list<array{nivel_instruccion: ?string}>  $requisitos
     */
    private static function nivelDelPuesto(string $texto, array $requisitos): ?NivelDelPuesto
    {
        $k = Str::lower(Str::ascii(trim($texto)));

        return match (true) {
            str_starts_with($k, 'no profesional') => NivelDelPuesto::NO_PROFESIONAL,
            $k === 'profesional'                  => NivelDelPuesto::PROFESIONAL,
            default => NivelDelPuesto::segunInstruccion(collect($requisitos)
                ->pluck('nivel_instruccion')->filter()
                ->map(fn (string $nivel) => NivelInstruccion::from($nivel))),
        };
    }

    private static function texto(mixed $valor, ?int $largo = null): ?string
    {
        $texto = trim((string) preg_replace('/\s+/u', ' ', is_string($valor) ? $valor : ''));
        if ($texto === '') {
            return null;
        }

        return $largo === null ? $texto : Str::limit($texto, $largo, '');
    }
}
