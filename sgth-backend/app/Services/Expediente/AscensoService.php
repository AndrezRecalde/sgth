<?php

namespace App\Services\Expediente;

use App\Enums\TipoNombramiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estructura\Puesto;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use Carbon\Carbon;

/**
 * Las reglas del ascenso (LOSEP Arts. 17 b.5, 68 y 81; diseño de Acciones de
 * Personal, 4.2 y 9.1; TH N2; fase 3.3).
 *
 * El servidor de carrera que gana un concurso para un puesto de escala
 * superior pasa a él con nombramiento provisional de prueba por seis meses, y el
 * puesto que deja se le reserva. Si no la supera, vuelve a ese puesto con su
 * remuneración anterior; si la supera, recibe el nombramiento definitivo.
 *
 * Hasta aquí Talento Humano lo operaba como una cesación seguida de un ingreso,
 * que le quitaba al servidor el derecho a volver.
 */
class AscensoService
{
    /** A esta edad la carrera llega a su tope y ya no se asciende (LOSEP Art. 81). */
    private const EDAD_TOPE = 65;

    /**
     * Valida el ascenso y fija lo que no se elige: el nombramiento de prueba, la
     * remuneración y, si la acción no la trae, la partida del puesto ganado. Al
     * preparar y al corregir el borrador.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public function completar(Servidor $servidor, array $datos): array
    {
        $vigente = $servidor->contratoVigente;

        $destino = $this->validarDestino(
            $vigente,
            isset($datos['puesto_destino_id']) ? (int) $datos['puesto_destino_id'] : null,
            isset($datos['unidad_destino_id']) ? (int) $datos['unidad_destino_id'] : null,
        );

        $this->validarEdad($servidor, $datos['fecha_efectiva'] ?? null);

        if ($destino) {
            $datos['remuneracion_propuesta'] = $this->remuneracionPara($vigente, $destino);
            $datos['partida_presupuestaria_id'] ??= $destino->partida_presupuestaria_id;
        }

        // Lo que no significa nada en un ascenso: el provisional lo pone el
        // ascenso, no un supuesto elegido, y no hay contrato nuevo.
        return [
            ...$datos,
            'tipo_nombramiento_propuesto'    => TipoNombramiento::PROVISIONAL->value,
            'supuesto_provisional_propuesto' => null,
            'numero_contrato'                => null,
            'fecha_fin_propuesta'            => null,
            'cubre_movimiento_id'            => null,
        ];
    }

    /**
     * El puesto ganado: activo, distinto del que ocupa, de la unidad indicada y
     * de una escala superior. Se comprueba al preparar, al corregir y al
     * registrar, porque el puesto o su escala pueden cambiar entre medias.
     */
    public function validarDestino(?ContratoServidor $vigente, ?int $puestoId, ?int $unidadId): ?Puesto
    {
        if (! $vigente || $vigente->tipo_nombramiento !== TipoNombramiento::PERMANENTE) {
            throw new ReglaNegocioException(
                'El ascenso es del servidor de carrera, con nombramiento permanente (LOSEP Art. 68).'
            );
        }

        if (! $puestoId) {
            return null;
        }

        $puesto = Puesto::with(['grupoOcupacional', 'cargo:id,nombre', 'unidadAdministrativa:id,nombre'])->find($puestoId);

        if (! $puesto) {
            return null;
        }

        $cual = $puesto->cargo?->nombre ? " ({$puesto->cargo->nombre})" : '';

        if (! $puesto->activo) {
            throw new ReglaNegocioException("El puesto del ascenso{$cual} está inactivo.");
        }

        if ((int) $vigente->puesto_id === (int) $puesto->id) {
            throw new ReglaNegocioException('El puesto del ascenso es el mismo que ocupa.');
        }

        if ($unidadId && (int) $puesto->unidad_administrativa_id !== $unidadId) {
            throw new ReglaNegocioException(
                "El puesto del ascenso{$cual} no es de la unidad indicada: es de "
                    .($puesto->unidadAdministrativa?->nombre ?? 'otra unidad').'.'
            );
        }

        $this->validarEscalaSuperior($vigente, $puesto, $cual);

        return $puesto;
    }

    /**
     * Es ascenso si el puesto ganado es de una escala superior a la del que
     * ocupa: se comparan las remuneraciones de sus grupos ocupacionales. Sin
     * grupo en alguno de los dos no hay cómo saberlo.
     */
    private function validarEscalaSuperior(ContratoServidor $vigente, Puesto $destino, string $cual): void
    {
        $rmuDestino = $destino->rmu;
        $rmuActual = $vigente->puesto?->rmu;

        if ($rmuDestino === null || $rmuActual === null) {
            throw new ReglaNegocioException(
                ($rmuDestino === null ? "El puesto del ascenso{$cual}" : 'El puesto que ocupa')
                    .' no tiene grupo ocupacional, así que no se puede comprobar que el ascenso sea a una escala '
                    .'superior. Asígnelo en Estructura.'
            );
        }

        if ($rmuDestino <= $rmuActual) {
            throw new ReglaNegocioException(
                'El ascenso es a un puesto de escala superior: el que ocupa paga $ '.number_format($rmuActual, 2)
                    .' y el ganado $ '.number_format($rmuDestino, 2).'. Si es de igual remuneración, es un traslado.'
            );
        }
    }

    /** A los 65 años la carrera llega a su tope y ya no se asciende (LOSEP Art. 81). */
    public function validarEdad(Servidor $servidor, ?string $fecha): void
    {
        $nacimiento = $servidor->fecha_nacimiento;

        if (! $nacimiento) {
            return;
        }

        $dia = $fecha ? Carbon::parse($fecha) : now();

        if ($nacimiento->copy()->addYears(self::EDAD_TOPE)->lte($dia)) {
            throw new ReglaNegocioException(
                'A los '.self::EDAD_TOPE.' años la carrera llega a su tope y ya no se asciende (LOSEP Art. 81).'
            );
        }
    }

    /**
     * La remuneración del puesto ganado, o la que ya cobra si era mayor: la
     * promoción a un puesto de mayor remuneración no la rebaja (LOSEP,
     * disposición transitoria tercera).
     */
    public function remuneracionPara(ContratoServidor $vigente, Puesto $destino): ?float
    {
        $rmuDestino = $destino->rmu;
        $actual = $vigente->remuneracion !== null ? (float) $vigente->remuneracion : null;

        if ($rmuDestino === null) {
            return $actual;
        }

        return $actual !== null ? max($rmuDestino, $actual) : $rmuDestino;
    }

    /**
     * El ascenso se gana en concurso (LOSEP Art. 68): no se suscribe sin el
     * acta final. Se pide al suscribir, como la del ingreso de prueba, para que
     * Reclutamiento pueda dejar el borrador y Talento Humano la complete.
     */
    public function exigirActa(MovimientoPersonal $ascenso): void
    {
        if (blank($ascenso->acta_concurso_numero) || ! $ascenso->acta_concurso_fecha) {
            throw new ReglaNegocioException(
                'No se puede suscribir el ascenso sin el acta final del concurso (número y fecha): se gana en '
                    .'concurso (LOSEP Art. 68).'
            );
        }
    }

    /**
     * ¿Ganar este puesto es un ascenso para quien tiene este vínculo? Lo
     * pregunta Reclutamiento al incorporar a un ganador interno.
     */
    public static function esAscenso(?ContratoServidor $vigente, Puesto $destino): bool
    {
        $vigente?->loadMissing('puesto.grupoOcupacional');
        $destino->loadMissing('grupoOcupacional');

        return $vigente?->tipo_nombramiento === TipoNombramiento::PERMANENTE
            && (int) $vigente->puesto_id !== (int) $destino->id
            && $destino->rmu !== null
            && $vigente->puesto?->rmu !== null
            && $destino->rmu > $vigente->puesto->rmu;
    }
}
