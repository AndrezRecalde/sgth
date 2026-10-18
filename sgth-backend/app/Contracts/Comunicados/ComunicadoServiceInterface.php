<?php

namespace App\Contracts\Comunicados;

use App\Models\Comunicados\Comunicado;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * El ciclo de una noticia o un comunicado en manos de Talento Humano
 * (docs/diseno-noticias-comunicados.md, 3.2 y 4): borrador, publicado,
 * retirado. Lo que no es borrador no se edita: se retira y se duplica.
 */
interface ComunicadoServiceInterface
{
    /**
     * El listado de Talento Humano, con lecturas y destinatarios de cada uno.
     *
     * @param  array{vigencia?: string, tipo?: string, anio?: int, buscar?: string, per_page?: int}  $filtros
     */
    public function listar(array $filtros): LengthAwarePaginator;

    /** @param  array<string, mixed>  $datos  los de GuardarComunicadoRequest */
    public function crear(array $datos, User $usuario): Comunicado;

    /** @param  array<string, mixed>  $datos  los de GuardarComunicadoRequest */
    public function actualizar(Comunicado $comunicado, array $datos): Comunicado;

    /** Solo un borrador; se lleva sus archivos. */
    public function eliminar(Comunicado $comunicado): void;

    /** Lo publica ahora o lo programa para su fecha de inicio. */
    public function publicar(Comunicado $comunicado, User $usuario): Comunicado;

    public function retirar(Comunicado $comunicado, string $motivo, User $usuario): Comunicado;

    /** Un borrador nuevo con todo copiado, archivos incluidos. */
    public function duplicar(Comunicado $comunicado, User $usuario): Comunicado;

    /**
     * Lo único que cambia en lo publicado: el fijado y la fecha de fin.
     *
     * @param  array{fijado?: bool, publicado_hasta?: string|null}  $datos
     */
    public function actualizarPublicacion(Comunicado $comunicado, array $datos): Comunicado;
}
