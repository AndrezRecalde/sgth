<?php

namespace App\Services\Expediente;

use App\Enums\ClaseAccionPersonal;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estructura\Puesto;
use App\Models\Expediente\ContratoServidor;

/**
 * Las reglas del traslado (LOSEP Arts. 35 y 36; diseño de Acciones de
 * Personal, 4.2; TH N1 y 9; fase 3.1): la persona pasa a otro puesto vacante de
 * la misma institución, con igual remuneración y previo informe de la UATH.
 *
 * La clase «Traslado» se guarda todavía de dos formas —«Traspaso» en los
 * permanentes y «Prestación de servicios» en los demás— y las dos pasan por
 * aquí. Servicios Profesionales ya no la tiene: su cambio de unidad es la
 * adenda del contrato civil (fase 4.2).
 */
class TrasladoService
{
    /** ¿Es un traslado al que se le aplican estas reglas? */
    public static function aplica(
        TipoMovimientoPersonal $tipo,
        ?SubtipoMovimientoPersonal $subtipo,
        ?TipoNombramiento $nombramiento,
    ): bool {
        return ClaseAccionPersonal::desde($tipo, $subtipo) === ClaseAccionPersonal::TRASLADO
            && in_array($nombramiento, ClaseAccionPersonal::TRASLADO->pideInformeUathPara(), true);
    }

    /**
     * El informe de la UATH: se pide al preparar y al corregir el borrador.
     * Lo ya suscrito antes de que existiera el campo se registra sin él.
     */
    public function exigirInforme(?string $informe): void
    {
        if (blank($informe)) {
            throw new ReglaNegocioException(
                'Indique el número del informe técnico de la UATH: el traslado lo exige (LOSEP Art. 35), '
                    .'y es donde consta que el servidor cumple el perfil del puesto (Art. 36).'
            );
        }
    }

    /**
     * El puesto de destino: activo, distinto del que ocupa, de la unidad
     * indicada y con igual remuneración. Se comprueba al preparar, al corregir y
     * al registrar, porque el puesto o su escala pueden cambiar entre medias.
     */
    public function validarDestino(ContratoServidor $vigente, ?int $puestoId, ?int $unidadId): void
    {
        if (! $puestoId) {
            return;
        }

        $puesto = Puesto::with(['grupoOcupacional', 'cargo:id,nombre', 'unidadAdministrativa:id,nombre'])->find($puestoId);

        if (! $puesto) {
            return;
        }

        // La denominación del puesto, para que el mensaje diga cuál.
        $cual = $puesto->cargo?->nombre ? " ({$puesto->cargo->nombre})" : '';

        if (! $puesto->activo) {
            throw new ReglaNegocioException("El puesto de destino{$cual} está inactivo.");
        }

        if ((int) $vigente->puesto_id === (int) $puesto->id) {
            throw new ReglaNegocioException('El puesto de destino es el mismo que ocupa: no hay traslado.');
        }

        if ($unidadId && (int) $puesto->unidad_administrativa_id !== $unidadId) {
            throw new ReglaNegocioException(
                "El puesto de destino{$cual} no es de la unidad indicada: es de "
                    .($puesto->unidadAdministrativa?->nombre ?? 'otra unidad').'.'
            );
        }

        $this->validarIgualRemuneracion($vigente, $puesto, $cual);
    }

    /**
     * Igual remuneración (Art. 35): la que cobra hoy frente a la de la escala
     * del puesto de destino. Sin escala no hay cómo comprobarla.
     */
    private function validarIgualRemuneracion(ContratoServidor $vigente, Puesto $destino, string $cual): void
    {
        $rmuDestino = $destino->rmu;

        if ($rmuDestino === null) {
            throw new ReglaNegocioException(
                "El puesto de destino{$cual} no tiene grupo ocupacional, así que no se puede comprobar "
                    .'la igual remuneración que exige el traslado (LOSEP Art. 35). Asígnelo en Estructura.'
            );
        }

        $actual = $vigente->remuneracion !== null
            ? (float) $vigente->remuneracion
            : $vigente->puesto?->rmu;

        if ($actual === null) {
            throw new ReglaNegocioException(
                'El vínculo actual no tiene remuneración registrada, así que no se puede comprobar la igual '
                    .'remuneración que exige el traslado (LOSEP Art. 35).'
            );
        }

        if (round($actual, 2) !== round($rmuDestino, 2)) {
            throw new ReglaNegocioException(
                'El traslado es a un puesto con igual remuneración (LOSEP Art. 35): hoy cobra $ '
                    .number_format($actual, 2).' y el puesto de destino paga $ '.number_format($rmuDestino, 2).'.'
            );
        }
    }
}
