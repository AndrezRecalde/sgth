<?php

namespace App\Services\InventarioTi\Carga;

use App\Models\Expediente\Servidor;

/**
 * Lo que la carga hizo con cada fila del Excel, para que TIC lo revise antes
 * de la carga real y después de ella.
 */
final class InformeCarga
{
    /** @var list<array<string, mixed>> */
    public array $equipos = [];

    /** @var list<array<string, mixed>> */
    public array $sinEmparejar = [];

    /** @var list<array<string, mixed>> */
    public array $contratos = [];

    public function cargado(array $fila, string $codigo, ?Servidor $custodio, ?Servidor $usuario, array $avisos): void
    {
        $this->equipos[] = [
            'fila'      => $fila['fila'],
            'codigo'    => $codigo,
            'resultado' => 'Cargado',
            'custodio'  => $custodio ? $this->nombre($custodio) : 'Sin custodio',
            'usuario'   => $usuario ? $this->nombre($usuario) : '',
            'avisos'    => implode(' ', $avisos),
        ];
    }

    public function omitido(array $fila, string $codigo, string $motivo): void
    {
        $this->equipos[] = [
            'fila' => $fila['fila'], 'codigo' => $codigo, 'resultado' => 'Omitido',
            'custodio' => '', 'usuario' => '', 'avisos' => $motivo,
        ];
    }

    public function sinEmparejar(array $fila, string $codigo, string $columna, string $nombre, ?string $motivo): void
    {
        $this->sinEmparejar[] = [
            'fila' => $fila['fila'], 'codigo' => $codigo, 'columna' => $columna,
            'nombre_en_el_excel' => $nombre, 'motivo' => $motivo ?? '',
        ];
    }

    /** @return array{equipos: list<array>, sin_emparejar: list<array>, contratos: list<array>, resumen: array<string, int>} */
    public function comoArreglo(): array
    {
        $cargados = array_filter($this->equipos, fn (array $e) => $e['resultado'] === 'Cargado');

        return [
            'resumen' => [
                'filas'            => count($this->equipos),
                'cargados'         => count($cargados),
                'omitidos'         => count($this->equipos) - count($cargados),
                'con_custodio'     => count(array_filter($cargados, fn (array $e) => $e['custodio'] !== 'Sin custodio')),
                'sin_emparejar'    => count($this->sinEmparejar),
                'contratos_nuevos' => count($this->contratos),
            ],
            'equipos'       => $this->equipos,
            'sin_emparejar' => $this->sinEmparejar,
            'contratos'     => $this->contratos,
        ];
    }

    private function nombre(Servidor $s): string
    {
        return trim("{$s->apellido} {$s->nombre}") . " ({$s->cedula})";
    }
}
