<?php
namespace App\Enums;

enum ClasificacionPersonal: string
{
    case EMPLEADO   = 'empleado';
    case CONTRATADO = 'contratado';
    case OBRERO     = 'obrero';

    public function etiqueta(): string
    {
        return match($this) {
            self::EMPLEADO   => 'Empleado',
            self::CONTRATADO => 'Contratado',
            self::OBRERO     => 'Obrero',
        };
    }

    public static function fromTipoNombramiento(string $tipo): self
    {
        return match($tipo) {
            'servicios_ocasionales',
            'servicios_profesionales' => self::CONTRATADO,
            'codigo_trabajo'          => self::OBRERO,
            default                   => self::EMPLEADO,
        };
    }
}
