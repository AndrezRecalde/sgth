<?php

namespace App\Contracts\InventarioTi;

use App\Models\InventarioTi\BajaBien;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;

/** La baja de los equipos: el informe técnico, la aprobación y la entrega a Activos Fijos. */
interface BajaServiceInterface
{
    /** @param  array<string, mixed>  $filtros */
    public function listar(array $filtros): LengthAwarePaginator;

    public function obtener(int $id): BajaBien;

    /** @param  array<string, mixed>  $datos */
    public function proponer(array $datos): BajaBien;

    /** @param  array<string, mixed>  $datos */
    public function aprobar(BajaBien $baja, array $datos): BajaBien;

    public function rechazar(BajaBien $baja, string $motivo): BajaBien;

    public function adjuntarMemorando(BajaBien $baja, UploadedFile $archivo): BajaBien;

    public function registrarEntregaActivos(BajaBien $baja, string $fecha): BajaBien;

    /** @return array{content: string, filename: string} */
    public function generarInforme(int $id): array;
}
