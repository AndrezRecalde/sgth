<?php

namespace App\Contracts\Reporte;

use App\Enums\ClaseAccionPersonal;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * El reporte de apoyo a la transcripción al SIITH y al SUT (fase 7.5). No es
 * una integración: los portales del Ministerio no tienen API, se transcribe a
 * mano, y esto ordena los datos para transcribir sin errores.
 */
interface ReporteSiithSutServiceInterface
{
    /**
     * Los actos del portal y del mes, con lo que hay que transcribir.
     *
     * @param  'siith'|'sut'  $portal
     * @param  'pendientes'|'transcritos'|'todos'  $situacion
     */
    public function reporte(string $portal, string $periodo, string $situacion): array;

    /** Las filas del Excel, con las mismas columnas. */
    public function filasDelExcel(string $portal, string $periodo, string $situacion): Collection;

    /**
     * Marca como transcritos los actos, o su anulación si se anularon después.
     *
     * @param  list<int>  $movimientoIds
     */
    public function transcribir(string $portal, array $movimientoIds, User $usuario): int;

    /** Qué clase se reporta a cada portal. */
    public function configuracion(): array;

    /** @param  array{reportable_siith?: bool, reportable_sut?: bool}  $marcas */
    public function actualizarConfiguracion(ClaseAccionPersonal $clase, array $marcas, User $usuario): array;
}
