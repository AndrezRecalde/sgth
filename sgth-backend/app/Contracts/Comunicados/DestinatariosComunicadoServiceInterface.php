<?php

namespace App\Contracts\Comunicados;

use App\Enums\AudienciaComunicado;
use App\Models\Comunicados\Comunicado;
use Illuminate\Database\Eloquent\Builder;

/** A quién llega una publicación (docs/diseno-noticias-comunicados.md, 3.3). */
interface DestinatariosComunicadoServiceInterface
{
    /**
     * Los usuarios que la verían hoy.
     *
     * @param  list<int>  $unidadIds  ya con sus dependencias
     * @param  list<string>  $regimenes
     * @return Builder<\App\Models\User>
     */
    public function usuarios(AudienciaComunicado $audiencia, array $unidadIds, array $regimenes): Builder;

    /** Cuántos la verían: un borrador con sus dependencias de hoy; lo publicado, con las congeladas. */
    public function contar(Comunicado $comunicado): int;

    /**
     * Cuántos la verían, desde el formulario, antes de guardar.
     *
     * @param  list<array{id: int, incluye_dependencias?: bool}>  $unidades
     * @param  list<string>  $regimenes
     */
    public function contarSeleccion(AudienciaComunicado $audiencia, array $unidades, array $regimenes): int;

    /**
     * Las unidades elegidas y, donde se pidió, todo lo que cuelga de ellas.
     *
     * @param  list<array{id: int, incluye_dependencias?: bool}>  $unidades
     * @return list<int>
     */
    public function expandir(array $unidades): array;
}
