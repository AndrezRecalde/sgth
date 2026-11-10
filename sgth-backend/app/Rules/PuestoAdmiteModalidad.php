<?php

namespace App\Rules;

use App\Enums\TipoNombramiento;
use App\Enums\TipoPuesto;
use App\Models\Estructura\Puesto;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * El puesto de una convocatoria tiene que admitir la modalidad con que se va a
 * vincular a quien gane (P9 del diseño del Manual de Puestos).
 *
 * - El concurso formal es de méritos y oposición para un nombramiento: va
 *   sobre un puesto de plantilla.
 * - El proceso express dice su modalidad prevista: provisional y obrero, en
 *   plantilla; ocasional y servicios profesionales, en un puesto de contrato.
 *
 * Al incorporar al ganador, `ContratoServidorService::validarVacante()` lo
 * rechazaría igual; esta regla lo dice antes de abrir el proceso.
 */
class PuestoAdmiteModalidad implements ValidationRule
{
    /** @param  TipoNombramiento|null  $modalidad  Nula en un concurso formal. */
    public function __construct(private readonly ?TipoNombramiento $modalidad)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $puesto = Puesto::find($value);

        // Que exista lo comprueba la regla `exists`.
        if (! $puesto) {
            return;
        }

        $tipo = $puesto->tipo ?? TipoPuesto::PLANTILLA;

        if ($this->modalidad === null) {
            if ($tipo !== TipoPuesto::PLANTILLA) {
                $fail('Un concurso es para un puesto de plantilla, y este es de contrato.');
            }

            return;
        }

        if (! $this->modalidad->admitePuesto($tipo)) {
            $fail($tipo === TipoPuesto::CONTRATO
                ? "Un vínculo «{$this->modalidad->etiqueta()}» va en un puesto de plantilla, y este es de contrato."
                : "Un vínculo «{$this->modalidad->etiqueta()}» va en un puesto de contrato, y este es de plantilla.");
        }
    }
}
