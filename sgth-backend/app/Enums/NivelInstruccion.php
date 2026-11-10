<?php

namespace App\Enums;

/**
 * La instrucción formal que pide un puesto, como la escribe el Manual de Puestos
 * (docs/diseno-manual-de-puestos.md, P2). Es el nivel de cada alternativa de los
 * requisitos del perfil (PR 8), de menor a mayor.
 *
 * No es `NivelEstudio`, que es lo que tiene un servidor: el manual pide además
 * «estudios superiores en curso» —el tercer año o sexto semestre aprobado—, que
 * no es un título, y no pide primaria. `nivelDeEstudio()` dice con qué título se
 * compara cada uno.
 */
enum NivelInstruccion: string
{
    case BACHILLER                    = 'bachiller';
    case TECNICO_SUPERIOR             = 'tecnico_superior';
    case TECNOLOGICO_SUPERIOR         = 'tecnologico_superior';
    case ESTUDIOS_SUPERIORES_EN_CURSO = 'estudios_superiores_en_curso';
    case TERCER_NIVEL                 = 'tercer_nivel';
    case CUARTO_NIVEL                 = 'cuarto_nivel';

    public function etiqueta(): string
    {
        return match ($this) {
            self::BACHILLER                    => 'Bachiller',
            self::TECNICO_SUPERIOR             => 'Técnico superior',
            self::TECNOLOGICO_SUPERIOR         => 'Tecnológico superior',
            self::ESTUDIOS_SUPERIORES_EN_CURSO => 'Estudios superiores en curso',
            self::TERCER_NIVEL                 => 'Tercer nivel de grado',
            self::CUARTO_NIVEL                 => 'Cuarto nivel (posgrado)',
        };
    }

    /**
     * El título del historial académico que corresponde a este requisito. Los
     * estudios en curso no tienen: lo que se aprobó se dice con texto («tercer
     * año o sexto semestre»), y no se compara con un nivel.
     */
    public function nivelDeEstudio(): ?NivelEstudio
    {
        return match ($this) {
            self::BACHILLER                    => NivelEstudio::SECUNDARIA,
            self::TECNICO_SUPERIOR             => NivelEstudio::TECNICO_SUPERIOR,
            self::TECNOLOGICO_SUPERIOR         => NivelEstudio::TECNOLOGICO_SUPERIOR,
            self::ESTUDIOS_SUPERIORES_EN_CURSO => null,
            self::TERCER_NIVEL                 => NivelEstudio::TERCER_NIVEL,
            self::CUARTO_NIVEL                 => NivelEstudio::CUARTO_NIVEL,
        };
    }
}
