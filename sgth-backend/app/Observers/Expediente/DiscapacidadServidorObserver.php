<?php

namespace App\Observers\Expediente;

use App\Models\Expediente\DiscapacidadServidor;

class DiscapacidadServidorObserver
{
    public function created(DiscapacidadServidor $discapacidad): void
    {
        $discapacidad->servidor->update([
            'tiene_discapacidad' => true
        ]);
    }

    public function deleted(DiscapacidadServidor $discapacidad): void
    {
        $tieneOtras = DiscapacidadServidor::where('servidor_id', $discapacidad->servidor_id)
            ->whereNull('deleted_at')
            ->exists();

        $discapacidad->servidor->update([
            'tiene_discapacidad' => $tieneOtras
        ]);
    }
}
