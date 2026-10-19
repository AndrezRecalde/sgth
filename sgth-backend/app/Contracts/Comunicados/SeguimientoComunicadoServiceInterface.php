<?php

namespace App\Contracts\Comunicados;

use App\Models\Comunicados\Comunicado;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Quién leyó y quién confirmó una publicación
 * (docs/diseno-noticias-comunicados.md, 6.4). Todo se cuenta sobre los
 * destinatarios de hoy, la misma regla con que el Portal decide quién la ve.
 */
interface SeguimientoComunicadoServiceInterface
{
    /**
     * Las cifras de la pestaña: destinatarios, leídos, confirmados y pendientes.
     *
     * @return array{destinatarios: int, leidos: int, confirmados: int, pendientes: int}
     */
    public function resumen(Comunicado $comunicado): array;

    /**
     * Cada destinatario con su lectura, filtrado y paginado.
     *
     * @param  array{estado?: string, unidad_administrativa_id?: int, buscar?: string, per_page?: int}  $filtros
     */
    public function listar(Comunicado $comunicado, array $filtros): LengthAwarePaginator;

    /**
     * Quienes faltan: si pide confirmación, los que no confirmaron; si no, los
     * que no la abrieron, de toda la audiencia o de una dirección con sus
     * dependencias. Es lo que va al Excel.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function pendientes(Comunicado $comunicado, ?int $unidadId = null): Collection;
}
