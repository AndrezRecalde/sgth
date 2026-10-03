<?php

namespace App\Http\Requests\Dispensario;

use App\Catalogos\FactoresRiesgoMsp;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Carbon;

/**
 * Lo que una ficha FEMO tiene que cumplir entre sus propios campos.
 *
 * Las reglas de cada campo por separado no lo ven: un factor de riesgo
 * existente en el catálogo pero bajo otra categoría, una fecha de ingreso
 * posterior a la evaluación, más partos que gestas. Lo comparten el alta y la
 * edición, que validan la misma ficha.
 */
trait ValidaCoherenciaFemo
{
    /** @return list<callable> */
    public function after(): array
    {
        return [
            fn (Validator $v) => $this->validarFechasDeLaFicha($v),
            fn (Validator $v) => $this->validarFactoresDeRiesgo($v),
            fn (Validator $v) => $this->validarAntecedenteReproductivo($v),
            fn (Validator $v) => $this->validarEmpleosAnteriores($v),
        ];
    }

    private function fecha(mixed $valor): ?Carbon
    {
        if (blank($valor)) {
            return null;
        }

        try {
            return Carbon::parse((string) $valor)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function validarFechasDeLaFicha(Validator $v): void
    {
        $evaluacion = $this->fecha($this->input('ficha.fecha_evaluacion'));
        if (! $evaluacion) {
            return;
        }

        $ingreso = $this->fecha($this->input('ficha.fecha_ingreso_trabajo'));
        if ($ingreso && $ingreso->gt($evaluacion)) {
            $v->errors()->add(
                'ficha.fecha_ingreso_trabajo',
                'La fecha de ingreso al trabajo no puede ser posterior a la fecha de atención.'
            );
        }

        $fum = $this->fecha($this->input('antecedente_reproductivo.fecha_ultima_menstruacion'));
        if ($fum && $fum->gt($evaluacion)) {
            $v->errors()->add(
                'antecedente_reproductivo.fecha_ultima_menstruacion',
                'La fecha de la última menstruación no puede ser posterior a la fecha de atención.'
            );
        }
    }

    /**
     * Cada factor tiene que pertenecer a SU categoría del catálogo del MSP, y
     * colgar de una actividad que exista. Antes «Ruido» entraba como químico y
     * el PDF lo imprimía bajo Químicos; un índice de actividad inexistente se
     * guardaba como factor sin actividad, sin ninguna X en la matriz.
     */
    private function validarFactoresDeRiesgo(Validator $v): void
    {
        $actividades = count((array) $this->input('actividades', []));

        foreach ((array) $this->input('factores_riesgo', []) as $i => $factor) {
            $categoria = (string) ($factor['categoria'] ?? '');
            $nombre = (string) ($factor['factor'] ?? '');

            if ($categoria !== '' && $nombre !== ''
                && ! in_array($nombre, FactoresRiesgoMsp::factoresDe($categoria), true)) {
                $v->errors()->add(
                    "factores_riesgo.{$i}.factor",
                    "«{$nombre}» no es un factor de la categoría {$categoria} en el formulario del MSP."
                );
            }

            $indice = $factor['actividad_index'] ?? null;
            if ($indice !== null && (int) $indice >= $actividades) {
                $v->errors()->add(
                    "factores_riesgo.{$i}.actividad_index",
                    'El factor de riesgo apunta a una actividad que no está en la ficha.'
                );
            }
        }
    }

    private function validarAntecedenteReproductivo(Validator $v): void
    {
        $ant = (array) $this->input('antecedente_reproductivo', []);
        if (! isset($ant['gestas']) || $ant['gestas'] === null) {
            return;
        }

        $desenlaces = (int) ($ant['partos'] ?? 0) + (int) ($ant['cesareas'] ?? 0) + (int) ($ant['abortos'] ?? 0);
        if ($desenlaces > (int) $ant['gestas']) {
            $v->errors()->add(
                'antecedente_reproductivo.gestas',
                'Las gestas no pueden ser menos que la suma de partos, cesáreas y abortos.'
            );
        }
    }

    private function validarEmpleosAnteriores(Validator $v): void
    {
        foreach ((array) $this->input('empleos_anteriores', []) as $i => $empleo) {
            $inicio = $this->fecha($empleo['fecha_inicio'] ?? null);
            $fin = $this->fecha($empleo['fecha_fin'] ?? null);

            if ($inicio && $fin && $fin->lt($inicio)) {
                $v->errors()->add(
                    "empleos_anteriores.{$i}.fecha_fin",
                    'La fecha de fin no puede ser anterior a la de inicio.'
                );
            }
        }
    }
}
