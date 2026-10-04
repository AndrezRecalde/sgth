<?php

namespace App\Observers\Expediente;

use App\Models\Expediente\CargaFamiliar;
use App\Models\Expediente\DiscapacidadCargaFamiliar;
use App\Models\Expediente\EnfermedadCatastroficaCargaFamiliar;

/**
 * Las marcas `persona_con_discapacidad` y `posee_enfermedad_catastrofica` de
 * una carga familiar se derivan de sus registros, como `tiene_discapacidad`
 * del servidor.
 *
 * Hasta el 2026-10-03 las ponía a mano un interruptor del formulario. Apagarlo
 * con registros dentro los escondía: la fila dejaba de desplegarse aunque los
 * datos siguieran en la base.
 *
 * Cada registro solo toca su propia marca. Un familiar antiguo marcado sin
 * detalle conserva la otra hasta que Talento Humano la complete.
 */
class CondicionCargaFamiliarObserver
{
    public function created(DiscapacidadCargaFamiliar|EnfermedadCatastroficaCargaFamiliar $condicion): void
    {
        $this->recalcular($condicion);
    }

    public function deleted(DiscapacidadCargaFamiliar|EnfermedadCatastroficaCargaFamiliar $condicion): void
    {
        $this->recalcular($condicion);
    }

    public function restored(DiscapacidadCargaFamiliar|EnfermedadCatastroficaCargaFamiliar $condicion): void
    {
        $this->recalcular($condicion);
    }

    private function recalcular(DiscapacidadCargaFamiliar|EnfermedadCatastroficaCargaFamiliar $condicion): void
    {
        $carga = CargaFamiliar::withTrashed()->find($condicion->carga_familiar_id);
        if (! $carga) {
            return;
        }

        $carga->update($condicion instanceof DiscapacidadCargaFamiliar
            ? ['persona_con_discapacidad' => $carga->discapacidades()->exists()]
            : ['posee_enfermedad_catastrofica' => $carga->enfermedadesCatastroficas()->exists()]);
    }
}
