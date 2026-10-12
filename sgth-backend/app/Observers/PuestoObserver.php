<?php

namespace App\Observers;

use App\Models\Estructura\Puesto;

/**
 * El registro de auditoría de los puestos. Desde la fase 7.1a la actualización
 * guarda qué cambió —el antes y el después— y por qué: la edición directa en
 * Estructura, o el acto o el lote que lo cambió (diseño, 8.4). Hasta aquí solo
 * decía «actualizado».
 */
class PuestoObserver
{
    /** Por qué cambia el puesto mientras corre `conCausa()`. */
    private static ?string $causa = null;

    /**
     * Cambia el puesto diciendo por qué: lo usan el traspaso y su anulación.
     *
     * @template T
     *
     * @param  callable(): T  $cambio
     * @return T
     */
    public static function conCausa(string $causa, callable $cambio): mixed
    {
        $anterior = self::$causa;
        self::$causa = $causa;

        try {
            return $cambio();
        } finally {
            self::$causa = $anterior;
        }
    }

    public function created(Puesto $puesto): void
    {
        activity()
            ->performedOn($puesto)
            ->event('created')
            ->log('Puesto orgánico creado');
    }

    public function updated(Puesto $puesto): void
    {
        $despues = array_diff_key($puesto->getChanges(), array_flip(['updated_at']));

        activity()
            ->performedOn($puesto)
            ->event('updated')
            ->withProperties([
                // En `updated` el original todavía es el de antes de guardar.
                'antes'   => array_intersect_key($puesto->getRawOriginal(), $despues),
                'despues' => $despues,
                'causa'   => self::$causa ?? 'Edición directa en Estructura',
            ])
            ->log('Puesto orgánico actualizado');
    }

    public function deleted(Puesto $puesto): void
    {
        activity()
            ->performedOn($puesto)
            ->event('deleted')
            ->log('Puesto orgánico eliminado');
    }

    public function restored(Puesto $puesto): void
    {
        activity()
            ->performedOn($puesto)
            ->event('restored')
            ->log('Puesto orgánico restaurado');
    }
}
