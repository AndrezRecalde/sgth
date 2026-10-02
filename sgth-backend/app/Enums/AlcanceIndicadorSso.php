<?php

namespace App\Enums;

/**
 * Sobre qué población está calculado un indicador del módulo SSO.
 *
 * Existe porque el alcance era implícito y a veces falso.
 * `calcularIndicadoresProactivos()` y `DashboardSsoService::resumen()` aceptan
 * una unidad administrativa, pero casi ningún bloque la usaba: las
 * capacitaciones salen institucionales porque `capacitaciones_sso` no tiene
 * columna de unidad, la cobertura de EPP salía institucional sin motivo, y la
 * respuesta se titulaba con la unidad igual. Eso es el tipo de número que
 * acaba en un informe al Ministerio diciendo una cosa y valiendo otra.
 *
 * La regla, ahora explícita: cada indicador dice sobre qué está calculado, y
 * cuando no puede honrar la unidad que se le pidió, dice además por qué.
 */
enum AlcanceIndicadorSso: string
{
    /** Calculado sobre la unidad administrativa que se pidió. */
    case UNIDAD = 'unidad';

    /** Calculado sobre toda la institución. */
    case INSTITUCIONAL = 'institucional';

    public function etiqueta(): string
    {
        return match ($this) {
            self::UNIDAD        => 'De la unidad consultada',
            self::INSTITUCIONAL => 'De toda la institución',
        };
    }

    /**
     * El alcance de un indicador que SÍ sabe filtrar por unidad: es de la
     * unidad cuando se pidió una, e institucional cuando no.
     */
    public static function segunUnidad(?int $unidadAdministrativaId): self
    {
        return $unidadAdministrativaId !== null ? self::UNIDAD : self::INSTITUCIONAL;
    }

    /**
     * Cómo viaja a la pantalla.
     *
     * La nota solo aparece cuando hay algo que advertir: se pidió una unidad y
     * este indicador no puede dárla. Sin unidad pedida, todo es institucional
     * y no hay nada que explicar.
     *
     * @return array{alcance: string, nota: ?string}
     */
    public function comoRespuesta(?string $nota = null): array
    {
        return [
            'alcance' => $this->value,
            'nota'    => $this === self::INSTITUCIONAL ? $nota : null,
        ];
    }
}
