<?php
namespace App\Enums;

enum MotivoVacacion: string
{
    case VACACIONES_ANUALES        = 'vacaciones_anuales';
    case PERMISO_CARGO_VACACIONES  = 'permiso_cargo_vacaciones';
    case LICENCIA_SIN_GOCE         = 'licencia_sin_goce';
    case MATRIMONIO                = 'matrimonio';
    case CAPACITACION              = 'capacitacion';
    case ENFERMEDAD                = 'enfermedad';
    case MATERNIDAD                = 'maternidad';
    case PATERNIDAD                = 'paternidad';
    case ESTUDIOS_SIN_REMUNERACION = 'estudios_sin_remuneracion';
    case CALAMIDAD_DOMESTICA       = 'calamidad_domestica';
    case LICENCIA_CON_GOCE         = 'licencia_con_goce';

    public function etiqueta(): string
    {
        return match($this) {
            self::VACACIONES_ANUALES        => 'Vacaciones Anuales (mayor a 5 días)',
            self::PERMISO_CARGO_VACACIONES  => 'Permiso con Cargo a Vacaciones (máx. 5 días)',
            self::LICENCIA_SIN_GOCE         => 'Licencia sin Goce de Haberes',
            self::MATRIMONIO                => 'Matrimonio',
            self::CAPACITACION              => 'Capacitación y/o Adiestramiento',
            self::ENFERMEDAD                => 'Enfermedad',
            self::MATERNIDAD                => 'Maternidad',
            self::PATERNIDAD                => 'Paternidad',
            self::ESTUDIOS_SIN_REMUNERACION => 'Permiso para Realizar Estudios sin Remuneración',
            self::CALAMIDAD_DOMESTICA       => 'Calamidad Doméstica',
            self::LICENCIA_CON_GOCE         => 'Licencia con Goce de Sueldo',
        };
    }

    public function descuentaVacaciones(): bool
    {
        return in_array($this, [
            self::VACACIONES_ANUALES,
            self::PERMISO_CARGO_VACACIONES,
        ]);
    }
}
