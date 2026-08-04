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
     * Campos con relevancia legal para el expediente (los que
     * ContratoServidorService::sincronizarRegimenServidor() sincroniza).
     * Cambios a otros campos (contacto, discapacidad, etc.) no generan
     * entrada de auditoría aquí.
     */
    private const CAMPOS_LEGALES = [
        'puesto_id',
        'unidad_administrativa_id',
        'tipo_nombramiento',
        'regimen_laboral',
    ];

    /**
     * Handle the Servidor "updated" event.
     */
    public function updated(Servidor $servidor): void
    {
        $this->limpiarCachesRelacionados();

        $cambios = array_intersect_key($servidor->getChanges(), array_flip(self::CAMPOS_LEGALES));

        if (empty($cambios)) {
            return;
        }

        activity()
            ->performedOn($servidor)
            ->withProperties([
                'antes'   => array_intersect_key($servidor->getOriginal(), array_flip(array_keys($cambios))),
                'despues' => $cambios,
            ])
            ->event('updated')
            ->log('Datos laborales del servidor sincronizados');
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
