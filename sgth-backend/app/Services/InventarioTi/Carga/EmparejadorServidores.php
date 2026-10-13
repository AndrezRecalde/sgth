<?php

namespace App\Services\InventarioTi\Carga;

use App\Models\Expediente\Servidor;
use Illuminate\Support\Collection;

/**
 * Busca en el expediente al servidor que el Excel de TIC nombra con texto
 * libre: «MERO BERMEO GABRIELA», «Vega Macias Omar Humberto», «CARLOS
 * BATIOJA».
 *
 * Coinciden si todas las palabras del Excel están entre las de su nombre
 * completo e incluyen su primer apellido. Así se acepta un nombre
 * incompleto, en cualquier orden y sin tildes, y no se confunde a dos Gabrielas.
 * Si más de un servidor encaja, no se elige: se informa para revisarlo a mano.
 * Tampoco gana el que coincide palabra por palabra: el Excel suele omitir el
 * segundo nombre, y «PEREZ GOMEZ LUIS» puede ser Luis Alberto.
 */
final class EmparejadorServidores
{
    /** @var Collection<int, array{servidor: Servidor, palabras: list<string>, apellido: string}> */
    private Collection $indice;

    public function __construct()
    {
        $this->indice = Servidor::query()
            ->get(['id', 'cedula', 'nombre', 'segundo_nombre', 'apellido', 'segundo_apellido', 'estado', 'unidad_administrativa_id'])
            ->map(fn (Servidor $s) => [
                'servidor' => $s,
                'palabras' => $this->palabras("{$s->nombre} {$s->segundo_nombre} {$s->apellido} {$s->segundo_apellido}"),
                'apellido' => $this->palabras((string) $s->apellido)[0] ?? '',
            ]);
    }

    /**
     * @return array{servidor: ?Servidor, motivo: ?string}
     *         `motivo` dice por qué no se emparejó (o un aviso si se emparejó con un inactivo)
     */
    public function buscar(?string $nombre): array
    {
        $buscadas = $this->palabras((string) $nombre);

        if (count($buscadas) < 2) {
            return ['servidor' => null, 'motivo' => $nombre ? "«{$nombre}» no basta para identificar a nadie" : null];
        }

        $candidatos = $this->indice->filter(fn (array $c) => $c['apellido'] !== ''
            && in_array($c['apellido'], $buscadas, true)
            && array_diff($buscadas, $c['palabras']) === []);

        if ($candidatos->isEmpty()) {
            return ['servidor' => null, 'motivo' => "«{$nombre}» no está en el expediente"];
        }

        if ($candidatos->count() > 1) {
            $cedulas = $candidatos->map(fn (array $c) => $c['servidor']->cedula)->implode(', ');

            return ['servidor' => null, 'motivo' => "«{$nombre}» coincide con varios servidores ({$cedulas})"];
        }

        return ['servidor' => $candidatos->first()['servidor'], 'motivo' => null];
    }

    /** @return list<string> */
    private function palabras(string $texto): array
    {
        return array_values(array_filter(explode(' ', Normaliza::texto($texto))));
    }
}
