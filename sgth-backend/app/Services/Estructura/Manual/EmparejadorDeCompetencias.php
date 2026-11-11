<?php

namespace App\Services\Estructura\Manual;

use App\Enums\NivelCompetencia;
use App\Enums\TipoCompetencia;
use App\Models\Estructura\Competencia;
use Illuminate\Support\Str;

/**
 * Empareja las competencias de una ficha del Manual de Puestos con el
 * diccionario del SGTH (docs/diseno-manual-de-puestos.md, P4).
 *
 * - **Por nombre**, sin tildes ni mayúsculas. Si no coincide, por el más
 *   parecido: el manual trae erratas como «Contrucciones de Relaciones» en 94
 *   filas.
 * - **El número se recalcula** con el del diccionario: es el que imprime la
 *   ficha y el que pide la evaluación.
 * - **Las repetidas se descartan**, y la que está en la sección que no le toca
 *   se avisa: el tipo lo dice el diccionario.
 * - **Sin nivel**, se deduce del número de la ficha y, si no, del
 *   comportamiento observable. Con aviso.
 */
final class EmparejadorDeCompetencias
{
    /** @var array<string, Competencia> */
    private array $porClave = [];

    /** @param iterable<Competencia> $competencias */
    public function __construct(iterable $competencias)
    {
        foreach ($competencias as $competencia) {
            $this->porClave[self::clave($competencia->nombre)] = $competencia;
        }
        // Las más largas primero: para separar dos nombres pegados.
        uksort($this->porClave, fn (string $a, string $b) => strlen($b) <=> strlen($a));
    }

    /**
     * «Habilidad Analítica (análisis de prioridad…» y «habilidad analitica»
     * son la misma: sin tildes, sin lo que va entre paréntesis (aunque no se
     * cierre) y sin signos.
     */
    public static function clave(string $nombre): string
    {
        $k = Str::lower(Str::ascii($nombre));
        $k = (string) preg_replace('/\(.*$/', ' ', $k);
        $k = (string) preg_replace('/[^a-z0-9]+/', ' ', $k);

        return trim((string) preg_replace('/\s+/', ' ', $k));
    }

    /**
     * La competencia del diccionario y cómo se encontró: «exacta»,
     * «aproximada» (una errata) o «prefijo» (dos nombres en una celda).
     *
     * @return array{0: ?Competencia, 1: ?string}
     */
    public function buscar(string $nombre): array
    {
        $k = self::clave($nombre);
        if ($k === '') {
            return [null, null];
        }
        if (isset($this->porClave[$k])) {
            return [$this->porClave[$k], 'exacta'];
        }

        $mejor     = null;
        $distancia = PHP_INT_MAX;
        foreach ($this->porClave as $clave => $competencia) {
            $d = levenshtein($k, $clave);
            if ($d < $distancia) {
                [$mejor, $distancia] = [$competencia, $d];
            }
        }
        // Dos letras, o una por cada seis: «Trabjo en Equipo» sí; «Alto», no.
        if ($mejor !== null && $distancia <= max(2, intdiv(strlen($k), 6))) {
            return [$mejor, 'aproximada'];
        }

        foreach ($this->porClave as $clave => $competencia) {
            if (str_starts_with($k, $clave . ' ')) {
                return [$competencia, 'prefijo'];
            }
        }

        return [null, null];
    }

    /**
     * @param  list<array{nombre?: string, nivel?: ?string, numero?: ?int, comportamiento?: ?string}>  $tecnicas
     * @param  list<array{nombre?: string, nivel?: ?string, numero?: ?int, comportamiento?: ?string}>  $conductuales
     * @return array{
     *     competencias: list<array{competencia_id: int, tipo: string, nombre: string, nivel: string, numero: int, nombre_en_ficha: string, coincidencia: string}>,
     *     avisos: list<string>,
     *     conteo: array{filas: int, exactas: int, aproximadas: int, repetidas: int, sin_reconocer: int, renumeradas: int}
     * }
     */
    public function emparejar(array $tecnicas, array $conductuales): array
    {
        $competencias = [];
        $avisos       = [];
        $conteo       = ['filas' => 0, 'exactas' => 0, 'aproximadas' => 0, 'repetidas' => 0, 'sin_reconocer' => 0, 'renumeradas' => 0];
        $vistas       = [];

        foreach ([[TipoCompetencia::TECNICA, $tecnicas], [TipoCompetencia::CONDUCTUAL, $conductuales]] as [$seccion, $filas]) {
            foreach ($filas as $fila) {
                $conteo['filas']++;
                $nombre = trim((string) ($fila['nombre'] ?? ''));

                [$competencia, $como] = $this->buscar($nombre);
                $nivelDelTexto        = null;
                if ($competencia === null) {
                    // La celda del nombre trae el comportamiento: la tabla se corrió.
                    [$competencia, $nivelDelTexto] = $this->porComportamiento([$nombre, (string) ($fila['comportamiento'] ?? '')]);
                    if ($competencia === null) {
                        $avisos[] = 'No se reconoció la competencia «' . Str::limit($nombre, 80, '…') . '»: se descartó.';
                        $conteo['sin_reconocer']++;

                        continue;
                    }
                    $como     = 'comportamiento';
                    $avisos[] = '«' . Str::limit($nombre, 80, '…') . "» no es el nombre de una competencia: por el comportamiento observable es «{$competencia->nombre}», nivel " . Str::lower($nivelDelTexto->etiqueta()) . '.';
                }
                if ($como === 'prefijo') {
                    $avisos[] = "«{$nombre}» parece traer dos competencias: se tomó «{$competencia->nombre}».";
                }

                [$nivel, $avisoNivel] = $nivelDelTexto !== null ? [$nivelDelTexto, null] : $this->nivel($competencia, $fila);
                if ($avisoNivel !== null) {
                    $avisos[] = $avisoNivel;
                }
                if ($nivel === null) {
                    $conteo['sin_reconocer']++;

                    continue;
                }

                if (isset($vistas[$competencia->id])) {
                    $primera = $vistas[$competencia->id];
                    if ($primera !== $nivel) {
                        $avisos[] = "«{$competencia->nombre}» está dos veces, en nivel {$primera->etiqueta()} y "
                            . "{$nivel->etiqueta()}: se dejó la primera.";
                    }
                    $conteo['repetidas']++;

                    continue;
                }
                $vistas[$competencia->id] = $nivel;

                if ($competencia->tipo !== $seccion) {
                    $avisos[] = "«{$competencia->nombre}» está entre las competencias "
                        . ($seccion === TipoCompetencia::TECNICA ? 'técnicas' : 'conductuales')
                        . ', pero el diccionario la tiene como ' . Str::lower($competencia->tipo->etiqueta()) . '.';
                }

                $numero = $competencia->numeroDeComportamiento($nivel);
                if (isset($fila['numero']) && (int) $fila['numero'] !== $numero) {
                    $conteo['renumeradas']++;
                }
                $conteo[$como === 'exacta' ? 'exactas' : 'aproximadas']++;

                $competencias[] = [
                    'competencia_id'  => $competencia->id,
                    'tipo'            => $competencia->tipo->value,
                    'nombre'          => $competencia->nombre,
                    'nivel'           => $nivel->value,
                    'numero'          => $numero,
                    'nombre_en_ficha' => $nombre,
                    'coincidencia'    => $como,
                ];
            }
        }

        return ['competencias' => $competencias, 'avisos' => $avisos, 'conteo' => $conteo];
    }

    /**
     * La competencia y el nivel cuyo comportamiento observable se parece al
     * texto: cuando la ficha trae el comportamiento donde va el nombre.
     *
     * @param  list<string>  $textos
     * @return array{0: ?Competencia, 1: ?NivelCompetencia}
     */
    private function porComportamiento(array $textos): array
    {
        $mejor    = [null, null];
        $parecido = 0.0;
        foreach ($textos as $texto) {
            $texto = Str::lower(trim($texto));
            if (mb_strlen($texto) < 20) {
                continue;
            }
            foreach ($this->porClave as $competencia) {
                foreach (NivelCompetencia::cases() as $caso) {
                    similar_text($texto, Str::lower($competencia->comportamiento($caso)), $porcentaje);
                    if ($porcentaje > $parecido) {
                        [$mejor, $parecido] = [[$competencia, $caso], $porcentaje];
                    }
                }
            }
        }

        return $parecido >= 70 ? $mejor : [null, null];
    }

    /**
     * @param  array{nivel?: ?string, numero?: ?int, comportamiento?: ?string}  $fila
     * @return array{0: ?NivelCompetencia, 1: ?string}
     */
    private function nivel(Competencia $competencia, array $fila): array
    {
        $nivel = NivelCompetencia::tryFrom(Str::lower(Str::ascii(trim((string) ($fila['nivel'] ?? '')))));
        if ($nivel !== null) {
            return [$nivel, null];
        }

        // El número de la ficha sigue la numeración del diccionario: de tres
        // en tres, alto, medio y bajo.
        if (isset($fila['numero'])) {
            $posicion = (int) $fila['numero'] - ($competencia->numero - 1) * 3;
            foreach (NivelCompetencia::cases() as $caso) {
                if ($caso->posicion() === $posicion) {
                    return [$caso, "«{$competencia->nombre}» no trae nivel: se tomó del número {$fila['numero']} ({$caso->etiqueta()})."];
                }
            }
        }

        $texto = trim((string) ($fila['comportamiento'] ?? ''));
        if ($texto !== '') {
            $mejor     = null;
            $parecido  = 0.0;
            foreach (NivelCompetencia::cases() as $caso) {
                similar_text(Str::lower($texto), Str::lower($competencia->comportamiento($caso)), $porcentaje);
                if ($porcentaje > $parecido) {
                    [$mejor, $parecido] = [$caso, $porcentaje];
                }
            }
            if ($mejor !== null && $parecido >= 60) {
                return [$mejor, "«{$competencia->nombre}» no trae nivel: se tomó del comportamiento observable ({$mejor->etiqueta()})."];
            }
        }

        return [null, "«{$competencia->nombre}» no trae nivel y no se pudo deducir: se descartó."];
    }
}
