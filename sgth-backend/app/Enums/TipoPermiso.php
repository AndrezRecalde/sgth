<?php

namespace App\Enums;

enum TipoPermiso: string
{
    case PERSONAL   = 'personal';
    case OFICIAL    = 'oficial';
    case ENFERMEDAD = 'enfermedad';
    case CALAMIDAD  = 'calamidad';

    /**
     * Cómo se llama este tipo cuando lo lee una persona.
     *
     * Estaba escrito cuatro veces —dos mapas del frontend, el selector del
     * consolidado y una constante del controlador— con cuatro redacciones
     * distintas: «Enfermedad» y «Por enfermedad», «Calamidad», «Calamidad
     * doméstica» y «Calamidad Domestica», esta última sin tilde y en la
     * portada del PDF. Aquí queda una.
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::PERSONAL   => 'Personal',
            self::OFICIAL    => 'Oficial',
            self::ENFERMEDAD => 'Por enfermedad',
            self::CALAMIDAD  => 'Calamidad doméstica',
        };
    }

    /** Los valores del enum, para las reglas de validación. */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
