<?php

namespace App\Observers\Expediente;

use App\Models\Expediente\ContratoServidor;

class ContratoServidorObserver
{
    public function created(ContratoServidor $contrato): void
    {
        // Al crear un nuevo contrato actualizar la unidad
        // y puesto actual del servidor automáticamente
        $contrato->servidor->update([
            'unidad_administrativa_id' => $contrato->unidad_administrativa_id,
            'puesto_id'                => $contrato->puesto_id,
        ]);
    }

    public function updated(ContratoServidor $contrato): void
    {
        // Si el contrato vigente cambia de unidad o puesto
        // actualizar también en la tabla servidores
        $esVigente = $contrato->estado->value === 'vigente'
            && (is_null($contrato->fecha_fin)
                || $contrato->fecha_fin->gte(now()));

        if ($esVigente && (
            $contrato->wasChanged('unidad_administrativa_id') ||
            $contrato->wasChanged('puesto_id')
        )) {
            $contrato->servidor->update([
                'unidad_administrativa_id' => $contrato->unidad_administrativa_id,
                'puesto_id'                => $contrato->puesto_id,
            ]);
        }
    }

    public function deleted(ContratoServidor $contrato): void
    {
        // Al eliminar el contrato vigente buscar el
        // siguiente contrato más reciente y actualizar
        $contratoAnterior = ContratoServidor::where('servidor_id',
                $contrato->servidor_id)
            ->where('id', '!=', $contrato->id)
            ->whereNull('deleted_at')
            ->orderBy('fecha_inicio', 'desc')
            ->first();

        if ($contratoAnterior) {
            $contrato->servidor->update([
                'unidad_administrativa_id' =>
                    $contratoAnterior->unidad_administrativa_id,
                'puesto_id' => $contratoAnterior->puesto_id,
            ]);
        }
    }
}
