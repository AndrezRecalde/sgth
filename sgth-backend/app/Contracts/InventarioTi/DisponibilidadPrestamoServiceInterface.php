<?php

namespace App\Contracts\InventarioTi;

/** Qué equipos prestables hay, cómo están ahora y qué horarios tienen ocupados. */
interface DisponibilidadPrestamoServiceInterface
{
    /**
     * Cada equipo prestable con su estado ahora y los bloques ocupados entre
     * `desde` y `hasta`. Con `$paraTic`, cada bloque dice el folio y quién lo
     * tiene; el Portal solo ve que está ocupado (D15).
     *
     * @return list<array<string, mixed>>
     */
    public function equipos(string $desde, string $hasta, bool $paraTic, ?int $tipoBienId = null): array;
}
