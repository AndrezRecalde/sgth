<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * El informe de la carga del Excel de TIC (fase 1.4), en cuatro pestañas:
 * el resumen, cada equipo, los nombres que no se emparejaron y los contratos
 * de vigencia que se crearon. Es lo que TIC revisa antes de la carga real.
 */
final class InformeCargaInventarioExport implements WithMultipleSheets
{
    /** @param  array{resumen: array<string, int>, equipos: list<array>, sin_emparejar: list<array>, contratos: list<array>}  $informe */
    public function __construct(private readonly array $informe, private readonly bool $simulado) {}

    public function sheets(): array
    {
        $resumen = [['Modo', $this->simulado ? 'Simulación: no se guardó nada' : 'Carga real']];
        foreach ($this->informe['resumen'] as $clave => $valor) {
            $resumen[] = [ucfirst(str_replace('_', ' ', $clave)), $valor];
        }

        return [
            $this->hoja('Resumen', ['Concepto', 'Valor'], $resumen),
            $this->hoja('Equipos', ['Fila', 'Código', 'Resultado', 'Custodio', 'Usuario', 'Avisos'], $this->informe['equipos']),
            $this->hoja('Sin emparejar', ['Fila', 'Código', 'Columna', 'Nombre en el Excel', 'Motivo'], $this->informe['sin_emparejar']),
            $this->hoja('Contratos', ['Proceso', 'Administrador', 'Ventanas', 'Texto del Excel'], $this->informe['contratos']),
        ];
    }

    /** `WithStrictNullComparison`: sin él, un 0 se escribe como celda vacía. */
    private function hoja(string $titulo, array $cabeceras, array $filas): object
    {
        return new class($titulo, $cabeceras, $filas) implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithStrictNullComparison
        {
            public function __construct(private string $titulo, private array $cabeceras, private array $filas) {}

            public function array(): array
            {
                return array_map('array_values', $this->filas);
            }

            public function headings(): array
            {
                return $this->cabeceras;
            }

            public function title(): string
            {
                return $this->titulo;
            }
        };
    }
}
