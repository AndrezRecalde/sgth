<?php

namespace App\Contracts\InventarioTi;

use App\Models\InventarioTi\AsignacionBien;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

/** La custodia de los equipos: entregar, traspasar, devolver y su acta. */
interface CustodiaServiceInterface
{
    /** @param  array<string, mixed>  $filtros */
    public function listar(array $filtros): LengthAwarePaginator;

    public function obtener(int $id): AsignacionBien;

    /** @param  array<string, mixed>  $datos */
    public function entregar(array $datos): AsignacionBien;

    /** @param  array<string, mixed>  $datos */
    public function traspasar(AsignacionBien $actual, array $datos): AsignacionBien;

    public function devolver(AsignacionBien $asignacion, string $fecha, ?string $observaciones, ?string $soporteAnterior = null): AsignacionBien;

    public function adjuntarMemorando(AsignacionBien $asignacion, UploadedFile $archivo): AsignacionBien;

    /** Los equipos que el servidor tiene a su cargo o usa, para «Mis equipos». */
    public function deServidor(int $servidorId): Collection;

    /** @return array{content: string, filename: string} */
    public function generarActa(int $id): array;

    /** Si todavía se acepta el número de soporte del sistema antiguo (D1). */
    public function aceptaSoporteAnterior(): bool;
}
