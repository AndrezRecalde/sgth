<?php

namespace App\Contracts\Comunicados;

use App\Models\Comunicados\Comunicado;
use App\Models\Comunicados\ComunicadoAdjunto;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Las noticias y comunicados que le corresponden a un servidor, en el Portal
 * (docs/diseno-noticias-comunicados.md, 5). Lo que no le corresponde no
 * existe para él: se responde 404, no 403.
 */
interface MisComunicadosServiceInterface
{
    /**
     * Las vigentes, o las vencidas con `archivo`, con su lectura.
     *
     * @param  array{archivo?: bool, tipo?: string, lectura?: string, buscar?: string, per_page?: int}  $filtros
     */
    public function listar(User $usuario, array $filtros): LengthAwarePaginator;

    /**
     * Lo del inicio y la campana: las cinco primeras, las no leídas y las cifras.
     *
     * @return array{recientes: \Illuminate\Support\Collection<int, Comunicado>, no_leidos: \Illuminate\Support\Collection<int, Comunicado>, sin_leer: int, por_confirmar: int}
     */
    public function resumen(User $usuario): array;

    /** La abre y deja registrado que la leyó, la primera vez. */
    public function abrir(User $usuario, int $comunicadoId): Comunicado;

    /** Confirma que la leyó. No se deshace; confirmar otra vez no cambia la fecha. */
    public function confirmar(User $usuario, int $comunicadoId): Comunicado;

    public function adjunto(User $usuario, int $comunicadoId, int $adjuntoId): ComunicadoAdjunto;
}
