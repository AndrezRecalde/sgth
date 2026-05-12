<?php

namespace App\Observers;

use App\Models\Expediente\DocumentoServidor;

class DocumentoServidorObserver
{
    public function created(DocumentoServidor $documento): void
    {
        activity()->performedOn($documento)->log('creado');
    }

    public function updated(DocumentoServidor $documento): void
    {
        activity()->performedOn($documento)->log('actualizado');
    }

    public function deleted(DocumentoServidor $documento): void
    {
        activity()->performedOn($documento)->log('eliminado');
    }
}
