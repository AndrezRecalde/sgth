<?php

namespace App\Enums;

/**
 * Las dos especialidades que atiende el dispensario.
 *
 * Los valores son los que ya usaba `agendas_medicas.tipo_atencion`, que hasta
 * ahora era el único sitio donde constaba la especialidad —y solo en el turno,
 * no en la consulta que salía de él.
 *
 * Ojo con el nombre: en `consultas_medicas`, `tipo_atencion` significa otra
 * cosa (primera vez o subsecuente). Por eso la columna de la consulta se llama
 * `especialidad` y no repite aquel nombre.
 */
enum EspecialidadAtencion: string
{
    case MEDICINA_GENERAL = 'medicina_general';
    case ODONTOLOGIA      = 'odontologia';

    public function etiqueta(): string
    {
        return match ($this) {
            self::MEDICINA_GENERAL => 'Medicina general',
            self::ODONTOLOGIA      => 'Odontología',
        };
    }

    /** El rol que atiende esta especialidad. */
    public function rol(): string
    {
        return match ($this) {
            self::MEDICINA_GENERAL => 'medico',
            self::ODONTOLOGIA      => 'odontologo',
        };
    }

    /** @return list<string> */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
