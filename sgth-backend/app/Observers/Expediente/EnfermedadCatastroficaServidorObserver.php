<?php

namespace App\Observers\Expediente;

use App\Models\Expediente\EnfermedadCatastroficaServidor;

class EnfermedadCatastroficaServidorObserver
{
    public function created(EnfermedadCatastroficaServidor $enfermedad): void
    {
        $enfermedad->servidor->update([
            'tiene_enfermedad_catastrofica' => true
        ]);
    }

    public function deleted(EnfermedadCatastroficaServidor $enfermedad): void
    {
        $tieneOtras = EnfermedadCatastroficaServidor::where('servidor_id', $enfermedad->servidor_id)
            ->whereNull('deleted_at')
            ->exists();

        $enfermedad->servidor->update([
            'tiene_enfermedad_catastrofica' => $tieneOtras
        ]);
    }
}
