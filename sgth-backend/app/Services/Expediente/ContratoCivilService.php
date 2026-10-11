<?php

namespace App\Services\Expediente;

use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estructura\Puesto;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use Carbon\Carbon;

/**
 * El contrato civil de Servicios Profesionales (diseño de Acciones de
 * Personal, 9.10; TH N6 y 25; fase 4.2).
 *
 * Quien lo tiene no es servidor ni tiene relación de dependencia (Reglamento a
 * la LOSEP, Art. 148): no se le emite acción de personal. Su contrato, sus
 * adendas y su terminación los firman las partes, y aquí se registran: el
 * contrato con su número, y lo demás con el correlativo CC-AAAA-NNNN y el
 * número del documento firmado.
 *
 * La adenda cambia el vínculo en lo que dice —la unidad y el puesto, el plazo,
 * los honorarios—, y lo que no cambia queda vacío en ella. El plazo no sale del
 * año calendario del contrato [TH 2026-07-27]: para el siguiente se firma otro.
 */
class ContratoCivilService
{
    /**
     * Valida la adenda y deja en ella solo lo que cambia, con el plazo que
     * tenía el contrato para devolvérselo si se anula. Al preparar y al
     * corregir el borrador.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public function completarAdenda(Servidor $servidor, array $datos): array
    {
        $vigente = $this->exigirContratoCivil($servidor->contratoVigente);

        [$puesto, $unidad] = $this->destino(
            $vigente,
            ! empty($datos['puesto_destino_id']) ? (int) $datos['puesto_destino_id'] : null,
            ! empty($datos['unidad_destino_id']) ? (int) $datos['unidad_destino_id'] : null,
        );

        $honorarios = isset($datos['remuneracion_propuesta']) ? round((float) $datos['remuneracion_propuesta'], 2) : null;

        if ($honorarios !== null && $vigente->remuneracion !== null
            && $honorarios === round((float) $vigente->remuneracion, 2)
        ) {
            $honorarios = null;
        }

        if ($honorarios !== null && $honorarios <= 0) {
            throw new ReglaNegocioException('Los honorarios de la adenda tienen que ser mayores que cero.');
        }

        $plazo = ! empty($datos['fecha_fin_propuesta'])
            ? Carbon::parse($datos['fecha_fin_propuesta'])->toDateString()
            : null;

        if ($plazo === $vigente->fecha_fin?->toDateString()) {
            $plazo = null;
        }

        if ($plazo) {
            $this->validarPlazo($vigente, $plazo, $datos['fecha_efectiva'] ?? null);
        }

        if (! $puesto && $honorarios === null && ! $plazo) {
            throw new ReglaNegocioException(
                'La adenda no cambia nada del contrato: indique la unidad y el puesto, el plazo o los honorarios '
                    .'que cambia.'
            );
        }

        return [
            ...$datos,
            'puesto_destino_id'      => $puesto?->id,
            'unidad_destino_id'      => $unidad,
            'remuneracion_propuesta' => $honorarios,
            'fecha_fin_propuesta'    => $plazo,
            'fecha_fin_origen'       => $vigente->fecha_fin?->toDateString(),
            // Lo que no significa nada en una adenda: no hay contrato nuevo, y
            // la partida la fija la modalidad.
            'tipo_nombramiento_propuesto' => null,
            'numero_contrato'             => null,
            'partida_presupuestaria_id'   => null,
            'cubre_movimiento_id'         => null,
        ];
    }

    /**
     * Lo mismo, otra vez, al registrarla: el contrato o el puesto pueden haber
     * cambiado desde el borrador.
     */
    public function validarAlRegistrar(MovimientoPersonal $adenda): void
    {
        $vigente = $this->exigirContratoCivil(
            Servidor::with('contratoVigente')->find($adenda->servidor_id)?->contratoVigente
        );

        if ($adenda->puesto_destino_id) {
            $this->destino($vigente, (int) $adenda->puesto_destino_id, $adenda->unidad_destino_id ? (int) $adenda->unidad_destino_id : null);
        }

        if ($adenda->fecha_fin_propuesta) {
            $this->validarPlazo($vigente, $adenda->fecha_fin_propuesta->toDateString(), $adenda->fecha_efectiva?->toDateString());
        }
    }

    /**
     * Lo que no sea el contrato mismo ni su vencimiento lleva el número del
     * documento que firmaron las partes —la adenda, el acta de terminación, la
     * partida de defunción—, en «Número de resolución». Se pide al suscribir: el
     * borrador se prepara antes de tenerlo, y el de la salida de un reemplazo
     * lo prepara el sistema.
     */
    public function exigirDocumentoFirmado(MovimientoPersonal $movimiento): void
    {
        if ($movimiento->tipo_movimiento->creaVinculo()
            || $movimiento->subtipoEfectivo() === SubtipoMovimientoPersonal::CONTRATO_FINALIZADO
            || filled($movimiento->resolucion_numero)
        ) {
            return;
        }

        throw new ReglaNegocioException(
            'No se puede suscribir sin el número del documento firmado: la adenda, el acta de terminación o la '
                .'partida de defunción, en «Documento firmado N.º».'
        );
    }

    private function exigirContratoCivil(?ContratoServidor $vigente): ContratoServidor
    {
        if (! $vigente || $vigente->tipo_nombramiento !== TipoNombramiento::SERVICIOS_PROFESIONALES) {
            throw new ReglaNegocioException(
                'La adenda es del contrato de Servicios Profesionales, y el servidor no lo tiene vigente.'
            );
        }

        return $vigente;
    }

    /**
     * La unidad y el puesto a los que pasa, si cambian: un puesto activo de la
     * unidad indicada. La unidad sola no dice a qué puesto. Quien tiene un
     * contrato civil no ocupa plaza, así que no hay vacante que comprobar.
     *
     * @return array{0: ?Puesto, 1: ?int}
     */
    private function destino(ContratoServidor $vigente, ?int $puestoId, ?int $unidadId): array
    {
        if (! $puestoId || $puestoId === (int) $vigente->puesto_id) {
            if ($unidadId && $unidadId !== (int) $vigente->unidad_administrativa_id) {
                throw new ReglaNegocioException('Indique el puesto de la unidad a la que pasa.');
            }

            return [null, null];
        }

        $puesto = Puesto::with(['cargo:id,nombre', 'unidadAdministrativa:id,nombre'])->find($puestoId);

        if (! $puesto) {
            throw new ReglaNegocioException('El puesto de la adenda no existe.');
        }

        $cual = $puesto->cargo?->nombre ? " ({$puesto->cargo->nombre})" : '';

        if (! $puesto->activo) {
            throw new ReglaNegocioException("El puesto de la adenda{$cual} está inactivo.");
        }

        if ($unidadId && (int) $puesto->unidad_administrativa_id !== $unidadId) {
            throw new ReglaNegocioException(
                "El puesto de la adenda{$cual} no es de la unidad indicada: es de "
                    .($puesto->unidadAdministrativa?->nombre ?? 'otra unidad').'.'
            );
        }

        return [$puesto, (int) $puesto->unidad_administrativa_id];
    }

    /**
     * El plazo nuevo: no antes de que rija la adenda ni del inicio del
     * contrato, y no después del fin de su año calendario.
     */
    private function validarPlazo(ContratoServidor $vigente, string $plazo, ?string $rige): void
    {
        if ($vigente->fecha_inicio && $plazo < $vigente->fecha_inicio->toDateString()) {
            throw new ReglaNegocioException(
                'El plazo nuevo no puede terminar antes de que empiece el contrato, el '
                    .$vigente->fecha_inicio->format('d/m/Y').'.'
            );
        }

        if ($rige && $plazo < Carbon::parse($rige)->toDateString()) {
            throw new ReglaNegocioException('El plazo nuevo no puede terminar antes de que rija la adenda.');
        }

        $finDelAnio = ($vigente->fecha_inicio ?? Carbon::parse($plazo))->copy()->endOfYear();

        if ($plazo > $finDelAnio->toDateString()) {
            throw new ReglaNegocioException(
                'El contrato de Servicios Profesionales no pasa de su año calendario: el plazo llega a más tardar '
                    .'al '.$finDelAnio->format('d/m/Y').'. Para el año siguiente se firma un contrato nuevo.'
            );
        }
    }
}
