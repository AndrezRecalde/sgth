<?php

namespace App\Services\Dispensario;

use App\Enums\EspecialidadAtencion;
use App\Models\Dispensario\AgendaMedica;
use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Dispensario\OrdenExamen;
use App\Models\Dispensario\ResultadoMedico;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Hasta dónde llega cada quien en los datos clínicos del Dispensario
 * (docs/plan-roles-y-accesos.md, PR 5; decisión 5b del 2026-10-25).
 *
 * Antes las recetas, las notas de consulta y la bandeja de exámenes se servían
 * enteras a todo el personal del Dispensario: cualquier médico, odontólogo,
 * enfermera o administrativo leía lo de cualquier paciente.
 *
 * - Recetas: el médico y el odontólogo, las suyas; quien despacha —Enfermería
 *   y la administración—, todas, para entregarlas.
 * - Notas de consulta: el médico y el odontólogo, las suyas y las de los
 *   pacientes que atienden. Enfermería y la administración, ninguna.
 * - Exámenes: cada profesional, los de su especialidad; la administración,
 *   todos.
 */
final class AlcanceClinico
{
    /** Quien entrega los medicamentos ve todas las recetas, para despacharlas. */
    public static function despachaRecetas(User $usuario): bool
    {
        return $usuario->hasAnyRole(['enfermera', 'admin-dispensario']);
    }

    /**
     * La especialidad de los exámenes que ve: la suya. `null` es todas: la
     * administración, o quien es a la vez médico y odontólogo.
     */
    public static function especialidadDe(User $usuario): ?EspecialidadAtencion
    {
        $medico = $usuario->hasRole('medico');
        $odontologo = $usuario->hasRole('odontologo');

        if ($usuario->hasRole('admin-dispensario') || ($medico && $odontologo)) {
            return null;
        }

        return $odontologo ? EspecialidadAtencion::ODONTOLOGIA : EspecialidadAtencion::MEDICINA_GENERAL;
    }

    /**
     * Las órdenes de exámenes de una especialidad. Es de odontología la que se
     * pidió en una consulta de odontología; las demás —la del médico, la de la
     * ficha FEMO— son de medicina.
     */
    public static function ordenesDe(Builder $ordenes, ?EspecialidadAtencion $especialidad): Builder
    {
        if ($especialidad === null) {
            return $ordenes;
        }

        $deOdontologia = fn (Builder $q) => $q->where('especialidad', EspecialidadAtencion::ODONTOLOGIA->value);

        return $especialidad === EspecialidadAtencion::ODONTOLOGIA
            ? $ordenes->whereHas('consultaMedica', $deOdontologia)
            : $ordenes->whereDoesntHave('consultaMedica', $deOdontologia);
    }

    /**
     * Los resultados de una especialidad: los de su orden, y los que llegaron
     * sin orden, por la consulta a la que se unieron.
     */
    public static function resultadosDe(Builder $resultados, ?EspecialidadAtencion $especialidad): Builder
    {
        if ($especialidad === null) {
            return $resultados;
        }

        $deOdontologia = fn (Builder $q) => $q->where('especialidad', EspecialidadAtencion::ODONTOLOGIA->value);
        $esDeOdontologia = fn (Builder $q) => $q
            ->whereHas('ordenExamen.consultaMedica', $deOdontologia)
            ->orWhere(fn (Builder $sinOrden) => $sinOrden
                ->whereNull('orden_examen_id')
                ->whereHas('consultaMedica', $deOdontologia));

        return $especialidad === EspecialidadAtencion::ODONTOLOGIA
            ? $resultados->where($esDeOdontologia)
            : $resultados->whereNot($esDeOdontologia);
    }

    /**
     * Si puede actuar sobre esa orden: es de su especialidad. La que no existe
     * cuenta como visible, para que la respuesta siga siendo un 404.
     */
    public static function veOrden(User $usuario, int $ordenId): bool
    {
        return ! OrdenExamen::whereKey($ordenId)->exists()
            || self::ordenesDe(OrdenExamen::whereKey($ordenId), self::especialidadDe($usuario))->exists();
    }

    /** Lo mismo con un resultado. */
    public static function veResultado(User $usuario, int $resultadoId): bool
    {
        return ! ResultadoMedico::whereKey($resultadoId)->exists()
            || self::resultadosDe(ResultadoMedico::whereKey($resultadoId), self::especialidadDe($usuario))->exists();
    }

    /**
     * Si atiende a ese paciente: tuvo un turno con él, le firmó una consulta o
     * el paciente tiene una orden de exámenes de su especialidad, que es lo que
     * le enseña su bandeja y desde donde registra el resultado.
     */
    public static function atiendeHistoria(User $usuario, int $historiaClinicaId): bool
    {
        $historia = HistoriaClinica::find($historiaClinicaId, ['id', 'servidor_id', 'carga_familiar_id']);

        if (! $historia) {
            return false;
        }

        if (ConsultaMedica::where('historia_clinica_id', $historia->id)->where('medico_id', $usuario->id)->exists()) {
            return true;
        }

        // El turno no apunta a la historia sino a la persona: el titular, o su
        // carga familiar. El de una carga también lleva el id del titular.
        $turno = AgendaMedica::where('medico_id', $usuario->id)
            ->when(
                $historia->carga_familiar_id !== null,
                fn (Builder $q) => $q->where('carga_familiar_id', $historia->carga_familiar_id),
                fn (Builder $q) => $q->where('servidor_id', $historia->servidor_id)->whereNull('carga_familiar_id'),
            );

        if ($turno->exists()) {
            return true;
        }

        return self::ordenesDe(
            OrdenExamen::where('historia_clinica_id', $historia->id),
            self::especialidadDe($usuario),
        )->exists();
    }
}
