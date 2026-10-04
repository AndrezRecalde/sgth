<?php

namespace App\Services\Dispensario\Reportes;

/**
 * Lo que casi todos los reportes comparten: usan el período y no agrupan.
 * Quien agrupe o mire una foto de hoy lo declara sobreescribiendo esto.
 */
abstract class ReporteBase implements ReporteDispensario
{
    public function agrupaciones(): array
    {
        return [];
    }

    public function usaPeriodo(): bool
    {
        return true;
    }

    public function formatos(): array
    {
        return ['excel'];
    }

    /**
     * La agrupación pedida si este reporte la tiene; si no, la primera suya.
     * El filtro es común a la pantalla y puede llegar el de otro reporte.
     */
    protected function agrupacion(FiltrosReporte $filtros): ?string
    {
        $propias = array_keys($this->agrupaciones());

        return in_array($filtros->agrupacion, $propias, true)
            ? $filtros->agrupacion
            : ($propias[0] ?? $filtros->agrupacion);
    }

    /** @param list<array{0: string, 1: string}> $pares */
    protected static function columnasDe(array $pares): array
    {
        return array_map(fn ($c) => ['clave' => $c[0], 'titulo' => $c[1]], $pares);
    }
}
