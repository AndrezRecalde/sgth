<?php

namespace App\Enums;

enum EstadoSubrogacion: string
{
    /**
     * Registrada, pero su Acción de Personal todavía no se aprueba. No surte
     * ningún efecto: el subrogante no asume el puesto ni adquiere la facultad
     * de firmar mientras esté aquí.
     */
    case PENDIENTE  = 'pendiente';

    /** Acción registrada: el subrogante asume el puesto y puede firmar. */
    case ACTIVA     = 'activa';

    case FINALIZADA = 'finalizada';
    case CANCELADA  = 'cancelada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::PENDIENTE  => 'Pendiente de aprobación',
            self::ACTIVA     => 'Activa',
            self::FINALIZADA => 'Finalizada',
            self::CANCELADA  => 'Cancelada',
        };
    }

    /**
     * ¿Surte efectos jurídicos? Es lo que decide si el subrogante reemplaza
     * al titular en la resolución de firmantes y en los listados de vigentes.
     */
    public function surteEfecto(): bool
    {
        return $this === self::ACTIVA;
    }
}
