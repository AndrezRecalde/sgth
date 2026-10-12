<?php

namespace App\Services\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\InstrumentoActo;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Models\Estructura\Puesto;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Quién ocupa un puesto y qué impide un acto sobre él por sus ocupantes: lo
 * comparten el traspaso (fase 7.1a) y la revisión de la clasificación (7.1b),
 * que emiten un acto por ocupante.
 */
final class OcupantesDelPuesto
{
    public const AUTORIDAD = 'Es el puesto de una autoridad de elección popular: su dignidad y su remuneración las fija '
        .'la ley (Constitución y COOTAD), no la autoridad nominadora.';

    /**
     * Todo contrato vigente sobre el puesto, de cualquier modalidad, también
     * el reemplazo. El ascendido que lo reserva no: no trabaja ahí, y si
     * vuelve, vuelve al puesto como esté.
     *
     * @return Collection<int, ContratoServidor>
     */
    public static function de(Puesto $puesto): Collection
    {
        return ContratoServidor::query()
            ->where('estado', 'vigente')
            ->where('puesto_id', $puesto->id)
            ->with('servidor:id,nombre,segundo_nombre,apellido,segundo_apellido,cedula')
            ->orderBy('id')
            ->get();
    }

    /**
     * Lo que impide el acto por el puesto y sus ocupantes, en este orden:
     * inactivo, lo que el puesto es (`$porElPuesto`), una autoridad que lo
     * ocupe, ocupantes que no reciben acción de personal, vacante, el usuario
     * entre ellos y lo que está en curso.
     *
     * @param  Collection<int, ContratoServidor>  $ocupantes
     * @return list<string>
     */
    public static function impedimentos(
        Puesto $puesto,
        Collection $ocupantes,
        ?User $usuario,
        TipoMovimientoPersonal $tipo,
        ?string $porElPuesto,
        string $siEstaVacante,
    ): array {
        $impedimentos = [];

        if (! $puesto->activo) {
            $impedimentos[] = 'El puesto está inactivo.';
        }

        if ($porElPuesto) {
            $impedimentos[] = $porElPuesto;
        } elseif ($ocupantes->contains(fn (ContratoServidor $c) => $c->tipo_nombramiento === TipoNombramiento::ELECCION_POPULAR)) {
            // Una autoridad en un puesto que no está marcado como dignatario.
            $impedimentos[] = self::AUTORIDAD;
        }

        // La adenda es del obrero y de Servicios Profesionales; la autoridad ya
        // salió arriba.
        $conOtroDocumento = $ocupantes->filter(fn (ContratoServidor $c) => ! in_array(
            InstrumentoActo::para($tipo, $c->tipo_nombramiento),
            [InstrumentoActo::ACCION_PERSONAL, InstrumentoActo::ACTO_AUTORIDAD],
            true,
        ));

        if ($conOtroDocumento->isNotEmpty()) {
            $impedimentos[] = 'El acto es una acción de personal, y no la reciben '
                .$conOtroDocumento->map(fn (ContratoServidor $c) => self::nombre($c->servidor)." ({$c->tipo_nombramiento?->etiqueta()})")->implode(', ')
                .': lo suyo va por la adenda de su contrato. Regístrela antes.';
        }

        if ($ocupantes->isEmpty()) {
            $impedimentos[] = $siEstaVacante;
        }

        if ($usuario?->servidor_id && $ocupantes->contains('servidor_id', (int) $usuario->servidor_id)) {
            $impedimentos[] = 'Usted ocupa este puesto: lo prepara otra persona de Talento Humano.';
        }

        $enCurso = self::accionesEnCurso($puesto, $ocupantes);
        if ($enCurso->isNotEmpty()) {
            $impedimentos[] = 'Hay acciones en trámite o por regir sobre el puesto o sus ocupantes ('
                .$enCurso->map(fn (MovimientoPersonal $m) => $m->codigo_registro ?? "{$m->etiqueta()} #{$m->id}")->implode(', ')
                .'): termínelas o anúlelas antes.';
        }

        return $impedimentos;
    }

    /**
     * Lo que podría chocar con el acto: lo que tiene el puesto de destino, y
     * lo que toca el vínculo de un ocupante, en trámite o registrado y por
     * regir.
     *
     * @param  Collection<int, ContratoServidor>  $ocupantes
     * @return Collection<int, MovimientoPersonal>
     */
    public static function accionesEnCurso(Puesto $puesto, Collection $ocupantes): Collection
    {
        return MovimientoPersonal::query()
            ->where(fn ($q) => $q
                ->where('puesto_destino_id', $puesto->id)
                ->orWhereIn('servidor_id', $ocupantes->pluck('servidor_id')))
            ->where(fn ($q) => $q
                ->whereIn('estado', [EstadoAccionPersonal::BORRADOR->value, EstadoAccionPersonal::SUSCRITA->value])
                ->orWhere(fn ($p) => $p
                    ->whereIn('estado', [EstadoAccionPersonal::REGISTRADA->value, EstadoAccionPersonal::NOTIFICADA->value])
                    ->whereNotNull('codigo_registro')
                    ->whereNull('efecto_aplicado_en')))
            ->orderBy('id')
            ->get()
            ->filter(fn (MovimientoPersonal $m) => (int) $m->puesto_destino_id === (int) $puesto->id || $m->tocaElVinculo())
            ->values();
    }

    public static function nombre(?Servidor $s): string
    {
        return trim(implode(' ', array_filter([$s?->apellido, $s?->segundo_apellido, $s?->nombre, $s?->segundo_nombre])))
            ?: '—';
    }
}
