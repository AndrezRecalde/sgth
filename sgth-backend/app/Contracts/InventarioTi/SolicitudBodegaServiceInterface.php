<?php

namespace App\Contracts\InventarioTi;

use App\Models\InventarioTi\SolicitudBodega;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * La «Solicitud de materiales informáticos a bodega» (fase 5.4c): se genera
 * desde un ticket «Consumibles» o desde el módulo, se imprime con el formato
 * de hoy y, cuando Bodega despacha, lo despachado es la entrega.
 */
interface SolicitudBodegaServiceInterface
{
    /** @param  array{estado?: string|null, ticket_id?: int|null, search?: string|null, per_page?: int|null}  $filtros */
    public function listar(array $filtros): LengthAwarePaginator;

    public function crear(array $datos, User $user): SolicitudBodega;

    /** Lo que despachó Bodega: cada cantidad es la entrega, y el ticket se cierra. */
    public function despachar(SolicitudBodega $solicitud, array $datos, User $user): SolicitudBodega;

    /** Solo la pendiente; la despachada se corrige anulando sus movimientos. */
    public function anular(SolicitudBodega $solicitud, string $motivo, User $user): SolicitudBodega;

    /** @return array{content: string, filename: string} */
    public function pdf(SolicitudBodega $solicitud): array;

    /** @return array<string, mixed> */
    public function presentar(SolicitudBodega $solicitud): array;
}
