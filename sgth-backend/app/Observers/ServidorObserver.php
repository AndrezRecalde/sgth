<?php

namespace App\Observers;

use App\Models\Expediente\Servidor;
use Illuminate\Support\Facades\Cache;

class ServidorObserver
{
    /**
     * Handle the Servidor "created" event.
     */
    public function created(Servidor $servidor): void
    {
        $this->limpiarCachesRelacionados();
    }

    /**
     * Handle the Servidor "updated" event.
     */
    public function updated(Servidor $servidor): void
    {
        $this->limpiarCachesRelacionados();
    }

    /**
     * Handle the Servidor "deleted" event.
     */
    public function deleted(Servidor $servidor): void
    {
        $this->limpiarCachesRelacionados();
    }

    /**
     * Invalida los cachés del dashboard y los reportes que dependan del personal.
     */
    private function limpiarCachesRelacionados(): void
    {
        // Limpiamos explícitamente el dashboard ejecutivo
        Cache::forget('sgth:dashboard:kpis');

        // Como usamos tags en Redis, podemos limpiar todos los reportes de una sola vez
        if (Cache::supportsTags()) {
            Cache::tags(['reporteria'])->flush();
        }
    }
}
