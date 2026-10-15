<?php

namespace App\Contracts\Comunicacion;

use App\Enums\OrigenFotografia;
use App\Models\Comunicacion\FotografiaServidor;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/** Las fotografías de los servidores (docs/diseno-comunicacion-social.md, 5.1). */
interface FotografiaServiceInterface
{
    /**
     * Los servidores con el estado de su foto, para la pantalla Fotografías.
     *
     * @param  array{buscar?: string, unidad_administrativa_id?: int, foto?: string, estado?: string, subida_antes_de?: string, per_page?: int}  $filtros
     */
    public function listar(array $filtros): LengthAwarePaginator;

    /** Procesa la imagen y la deja como la vigente; la anterior pasa al historial. */
    public function subir(Servidor $servidor, string $ruta, User $usuario, OrigenFotografia $origen): FotografiaServidor;

    /** Todas sus fotos, de la más reciente a la más antigua. */
    public function historial(Servidor $servidor): Collection;

    /** Vuelve a dejar vigente una foto del historial. */
    public function restaurar(FotografiaServidor $fotografia, User $usuario): FotografiaServidor;

    /** Deja al servidor sin foto vigente; la foto queda en el historial. */
    public function retirar(Servidor $servidor): FotografiaServidor;

    /** La ruta en el disco privado de la versión completa o de la miniatura. */
    public function rutaImagen(FotografiaServidor $fotografia, bool $miniatura): string;
}
