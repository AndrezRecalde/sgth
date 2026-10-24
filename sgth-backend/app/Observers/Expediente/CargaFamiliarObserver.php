<?php

namespace App\Observers\Expediente;

use App\Models\Dispensario\HistoriaClinica;
use App\Models\Expediente\CargaFamiliar;

/**
 * La historia clínica de un familiar sigue su cédula cuando Talento Humano la
 * corrige: la recepción la busca por ella.
 */
class CargaFamiliarObserver
{
    public function updated(CargaFamiliar $carga): void
    {
        if ($carga->wasChanged('cedula')) {
            HistoriaClinica::seguirCedula(null, $carga->id, $carga->getOriginal('cedula'), $carga->cedula);
        }
    }
}
