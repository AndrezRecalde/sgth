<?php

namespace App\Contracts\InventarioTi;

use App\Models\InventarioTi\PrestamoEquipo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Pedir y registrar préstamos de equipos, y consultarlos. */
interface PrestamoServiceInterface
{
    /**
     * La bandeja de TIC. `vista`: por_aprobar, por_entregar_hoy, en_curso,
     * vencidos o historial.
     *
     * @param  array<string, mixed>  $filtros
     */
    public function listar(array $filtros): LengthAwarePaginator;

    /** @param  array<string, mixed>  $filtros */
    public function deServidor(int $servidorId, array $filtros): LengthAwarePaginator;

    public function obtener(int $id): PrestamoEquipo;

    /**
     * La solicitud del servidor desde el Portal: queda `solicitado`.
     *
     * @param  array<string, mixed>  $datos
     */
    public function solicitar(int $servidorId, array $datos): PrestamoEquipo;

    /**
     * El préstamo directo en el mostrador: se registra y se entrega en el mismo
     * paso, desde ahora.
     *
     * @param  array<string, mixed>  $datos
     */
    public function registrarDirecto(array $datos): PrestamoEquipo;
}
