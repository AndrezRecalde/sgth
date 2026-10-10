<?php

namespace App\Services\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\TipoAnotacionAccion;
use App\Exceptions\ReglaNegocioException;
use App\Models\Disciplinario\VistoBueno;
use App\Models\Expediente\AnotacionAccionPersonal;
use App\Models\Expediente\MovimientoPersonal;

/**
 * Lo que se le anota a una acción de personal después de emitida (fase 1.4).
 * Un acto registrado no se reescribe: lo que pasa después queda aquí, con quién
 * y cuándo, y el documento sigue diciendo lo que se firmó.
 */
class AnotacionAccionPersonalService
{
    /**
     * Una nota de Talento Humano. Es lo que queda en lugar de corregir a mano un
     * acto ya emitido: si el error es de contenido, el camino sigue siendo
     * anular y emitir otro.
     */
    public function anotar(MovimientoPersonal $movimiento, string $texto): AnotacionAccionPersonal
    {
        // Anotar es tramitar: nadie anota en sus propios actos (TH 24).
        TramiteSobreSiMismo::impedir($movimiento->servidor_id);

        if ($movimiento->estado === EstadoAccionPersonal::BORRADOR) {
            throw new ReglaNegocioException(
                'Un borrador se corrige en el formulario: las anotaciones son para lo que ya se suscribió.'
            );
        }

        return AnotacionAccionPersonal::create([
            'movimiento_personal_id' => $movimiento->id,
            'tipo'                   => TipoAnotacionAccion::NOTA,
            'texto'                  => trim($texto),
            'registrado_por'         => auth()->id(),
        ]);
    }

    /**
     * La impugnación de un visto bueno, en la cesación que originó.
     *
     * La cesación sigue en pie —la impugnación no deja sin efecto la resolución
     * del Inspector—, pero Talento Humano no debe registrarla sin saberlo. Se
     * anota una sola vez por visto bueno, esté la acción en borrador o ya
     * registrada. Impugnar un visto bueno negado no tiene cesación que anotar.
     */
    public function anotarImpugnacion(VistoBueno $vistoBueno): ?AnotacionAccionPersonal
    {
        if ($vistoBueno->movimiento_personal_id === null) {
            return null;
        }

        $fecha = $vistoBueno->fecha_impugnacion?->format('d/m/Y');

        return AnotacionAccionPersonal::firstOrCreate(
            [
                'movimiento_personal_id' => $vistoBueno->movimiento_personal_id,
                'visto_bueno_id'         => $vistoBueno->id,
                'tipo'                   => TipoAnotacionAccion::IMPUGNACION_VISTO_BUENO,
            ],
            [
                'texto' => 'Impugnado por el trabajador ('
                    .$vistoBueno->impugnacion_referencia
                    .($fecha ? ", {$fecha}" : '')
                    .'): revísese con Asesoría Jurídica antes de continuar con esta cesación.',
                'registrado_por' => auth()->id(),
            ]
        );
    }
}
