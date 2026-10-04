<?php

namespace App\Services\Dispensario;

use App\Enums\GradoDiscapacidad;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Expediente\CargaFamiliar;
use App\Models\Expediente\Servidor;

/**
 * La discapacidad y la enfermedad catastrófica que constan en el Expediente
 * del paciente —servidor o carga familiar—, para que el médico las vea.
 *
 * Solo lectura y sin rutas de archivo ni carné: lo que sirve para atender.
 * Las registra Talento Humano con su respaldo; lo que el médico encuentre va a
 * los antecedentes de la historia clínica, no aquí. Hasta el 2026-10-03 el
 * Dispensario no leía ninguna de estas tablas, ni del servidor ni del familiar.
 */
final class CondicionDeclaradaService
{
    /**
     * `null` para quien no tiene expediente (un candidato de ingreso).
     *
     * @return array{
     *   discapacidades: list<array{etiqueta: string, porcentaje: float|null, grado: string|null}>,
     *   enfermedades: list<array{nombre: string, codigo_cie10: string|null}>,
     *   discapacidad_sin_detalle: bool,
     *   enfermedad_sin_detalle: bool,
     * }|null
     */
    public function de(HistoriaClinica $historia): ?array
    {
        $titular = $historia->servidor_id
            ? Servidor::find($historia->servidor_id)
            : ($historia->carga_familiar_id ? CargaFamiliar::find($historia->carga_familiar_id) : null);

        if (! $titular) {
            return null;
        }

        $discapacidades = $titular->discapacidades()->orderBy('id')->get();
        $enfermedades   = $titular->enfermedadesCatastroficas()->orderBy('id')->get();

        // El servidor y el familiar nombran distinto sus marcas.
        [$marcaDiscapacidad, $marcaEnfermedad] = $titular instanceof Servidor
            ? [$titular->tiene_discapacidad, $titular->tiene_enfermedad_catastrofica]
            : [$titular->persona_con_discapacidad, $titular->posee_enfermedad_catastrofica];

        return [
            'discapacidades' => $discapacidades->map(fn ($d) => [
                'etiqueta'   => $d->tipo_discapacidad?->etiqueta() ?? 'Discapacidad',
                'porcentaje' => $d->porcentaje !== null ? (float) $d->porcentaje : null,
                'grado'      => GradoDiscapacidad::desdePorcentaje($d->porcentaje)?->etiqueta(),
            ])->values()->all(),
            'enfermedades' => $enfermedades->map(fn ($e) => [
                'nombre'       => $e->tipo_enfermedad,
                'codigo_cie10' => $e->codigo_cie10,
            ])->values()->all(),
            // Una marca puesta a mano antes de existir los registros, sin
            // ninguno: el médico sabe que hay algo aunque no el qué.
            'discapacidad_sin_detalle' => (bool) $marcaDiscapacidad && $discapacidades->isEmpty(),
            'enfermedad_sin_detalle'   => (bool) $marcaEnfermedad && $enfermedades->isEmpty(),
        ];
    }
}
