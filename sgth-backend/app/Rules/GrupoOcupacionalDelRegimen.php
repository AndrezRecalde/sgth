<?php

namespace App\Rules;

use App\Models\Estructura\GrupoOcupacional;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * El grupo ocupacional de un puesto tiene que ser de su mismo régimen.
 *
 * Los grados de la escala LOSEP y los del Código del Trabajo son escalas
 * distintas, y de ahí sale la RMU del puesto. Nada impedía elegir un grado
 * LOSEP para un puesto de obreros: las dos tablas guardan su propio `regimen`
 * y nadie los comparaba, así que la incoherencia se guardaba en silencio y
 * aparecía después como una remuneración que no cuadra.
 *
 * Un puesto sin grupo sigue siendo válido: bajo Código del Trabajo la
 * remuneración se pacta en cada contrato y el puesto no define ninguna.
 */
class GrupoOcupacionalDelRegimen implements ValidationRule
{
    public function __construct(private readonly ?string $regimenDelPuesto)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $this->regimenDelPuesto === null) {
            return;
        }

        $grupo = GrupoOcupacional::find($value);

        // Que exista lo comprueba la regla `exists`; aquí no se opina.
        if (! $grupo || $grupo->regimen === $this->regimenDelPuesto) {
            return;
        }

        $fail(sprintf(
            'El grado %s pertenece a la escala de %s y el puesto es de %s.',
            $grupo->grado_codigo,
            $this->etiqueta($grupo->regimen),
            $this->etiqueta($this->regimenDelPuesto)
        ));
    }

    private function etiqueta(?string $regimen): string
    {
        return match ($regimen) {
            'losep'          => 'LOSEP',
            'codigo_trabajo' => 'Código del Trabajo',
            default          => (string) $regimen,
        };
    }
}
