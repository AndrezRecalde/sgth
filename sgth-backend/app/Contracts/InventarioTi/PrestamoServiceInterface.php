<?php

namespace App\Contracts\InventarioTi;

use App\Models\InventarioTi\PrestamoEquipo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Pedir y registrar préstamos de equipos, y consultarlos. */
interface PrestamoServiceInterface
{
    /**
     * La bandeja de TIC. `vista`: por_aprobar, reservados, por_entregar_hoy,
     * en_curso, vencidos o historial.
     *
     * @param  array<string, mixed>  $filtros
     */
    public function listar(array $filtros): LengthAwarePaginator;

    /** @param  array<string, mixed>  $filtros */
    public function deServidor(int $servidorId, array $filtros): LengthAwarePaginator;

    public function obtener(int $id): PrestamoEquipo;

    /**
     * Cuántos hay en cada pestaña de la bandeja que pide atención.
     *
     * @return array{por_aprobar: int, reservados: int, por_entregar_hoy: int, en_curso: int, vencidos: int}
     */
    public function resumen(): array;

    /**
     * El acta del préstamo en PDF (D17), una vez entregado.
     *
     * @return array{content: string, filename: string}
     */
    public function generarActa(int $id): array;

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
