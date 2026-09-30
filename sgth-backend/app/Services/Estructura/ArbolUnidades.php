<?php

namespace App\Services\Estructura;

use App\Models\Estructura\UnidadAdministrativa;

/**
 * Recorridos del árbol del organigrama.
 *
 * Vivía dentro de `ExpedienteService` como método privado, que es donde hizo
 * falta primero. Al necesitarlo también el consolidado de permisos, copiarlo
 * habría dejado dos versiones de la misma pregunta —«¿qué cuelga de esta
 * unidad?»— que con el tiempo se responden distinto.
 */
final class ArbolUnidades
{
    /**
     * La unidad pedida y todo lo que cuelga de ella.
     *
     * Una dirección incluye a sus jefaturas y subprocesos: filtrar por
     * «Gestión Administrativa» tiene que traer también a quien está en las
     * unidades que dependen de ella, porque si no el informe sale más corto
     * sin decir por qué.
     *
     * El organigrama tiene tres niveles y unas decenas de filas, así que se
     * recorre en memoria con una sola consulta en vez de una recursiva por
     * nivel.
     *
     * @return list<int>
     */
    public static function conDescendientes(int $unidadId): array
    {
        $porPadre = UnidadAdministrativa::query()
            ->select('id', 'unidad_padre_id')
            ->get()
            ->groupBy('unidad_padre_id');

        $ids = [];
        $pendientes = [$unidadId];

        while ($pendientes) {
            $actual = array_pop($pendientes);

            if (in_array($actual, $ids, true)) {
                continue;
            }

            $ids[] = $actual;

            foreach ($porPadre->get($actual, collect()) as $hija) {
                $pendientes[] = (int) $hija->id;
            }
        }

        return $ids;
    }
}
