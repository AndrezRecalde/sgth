<?php

namespace App\Enums;

/**
 * Marco legal bajo el que se vincula una persona con la institución.
 *
 * `SERVICIOS_PROFESIONALES` se agregó el 2026-08-29. Hasta entonces solo había
 * dos valores y los contratos de servicios profesionales se metían en
 * `CODIGO_TRABAJO`, porque era el cajón de «lo que no es LOSEP». El efecto
 * visible era que un profesional contratado sobre un puesto LOSEP aparecía con
 * régimen Código de Trabajo sin que nadie lo hubiera elegido.
 *
 * No es una distinción cosmética: un contrato de servicios profesionales es
 * civil, no laboral. No hay relación de dependencia, y de ahí se siguen las
 * reglas de abajo — no genera vacaciones, no accede a permisos y no marca.
 */
enum RegimenLaboral: string
{
    case LOSEP                  = 'losep';
    case CODIGO_TRABAJO         = 'codigo_trabajo';
    case SERVICIOS_PROFESIONALES = 'servicios_profesionales';

    public function etiqueta(): string
    {
        return match ($this) {
            self::LOSEP => 'LOSEP',
            self::CODIGO_TRABAJO => 'Código del Trabajo',
            self::SERVICIOS_PROFESIONALES => 'Servicios Profesionales',
        };
    }

    /**
     * ¿Es una relación laboral de dependencia?
     *
     * Servicios profesionales no lo es: se rige por contrato civil. Casi todas
     * las prestaciones del sistema cuelgan de esta pregunta.
     */
    public function esRelacionLaboral(): bool
    {
        return $this !== self::SERVICIOS_PROFESIONALES;
    }

    /**
     * ¿Genera vacaciones?
     *
     * LOSEP y Código del Trabajo sí, con escalas distintas. Un contrato civil
     * no: se pacta un entregable, no una jornada.
     */
    public function generaVacaciones(): bool
    {
        return $this->esRelacionLaboral();
    }

    /**
     * Los regímenes que NO generan vacaciones, para las consultas que necesitan
     * excluirlos en SQL.
     *
     * @return list<string>
     */
    public static function valoresSinVacaciones(): array
    {
        return array_values(array_map(
            fn (self $caso) => $caso->value,
            array_filter(self::cases(), fn (self $caso) => ! $caso->generaVacaciones())
        ));
    }

    /**
     * ¿Accede al módulo de permisos?
     *
     * Solo LOSEP. Los obreros del Código del Trabajo se rigen por su propio
     * contrato colectivo y los servicios profesionales no tienen jornada que
     * permisar.
     */
    public function accedeAPermisos(): bool
    {
        return $this === self::LOSEP;
    }
}
