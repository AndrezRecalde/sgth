<?php

namespace App\Services\Dispensario\Reportes;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

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

    public function corte(): ?string
    {
        return null;
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

    /**
     * El nombre de cada usuario como se lee en un reporte: el de su
     * expediente o, si no tiene, su usuario.
     *
     * @param list<int|string|null> $usuarios
     * @return array<int, string>
     */
    protected function nombresDeUsuarios(array $usuarios): array
    {
        return DB::table('users as u')
            ->leftJoin('servidores as s', 's.id', '=', 'u.servidor_id')
            ->whereIn('u.id', array_filter($usuarios))
            ->selectRaw("u.id, COALESCE(NULLIF(TRIM(COALESCE(s.nombre, '') || ' ' || COALESCE(s.apellido, '')), ''), u.usuario_ti) as nombre")
            ->pluck('nombre', 'id')
            ->all();
    }

    /** `2026-10` como se lee en un reporte: «Octubre 2026». */
    protected static function nombreDeMes(string $anioMes): string
    {
        $mes = CarbonImmutable::createFromFormat('!Y-m', $anioMes)->locale('es')->isoFormat('MMMM YYYY');

        return mb_strtoupper(mb_substr($mes, 0, 1)) . mb_substr($mes, 1);
    }
}
