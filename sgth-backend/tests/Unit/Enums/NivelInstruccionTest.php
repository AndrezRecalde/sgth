<?php

/*
| La instrucción que pide un puesto y el título con que se compara
| (docs/diseno-manual-de-puestos.md, P2), sin base de datos.
*/

use App\Enums\NivelEstudio;
use App\Enums\NivelInstruccion;

test('cada requisito del manual se compara con su título, salvo los estudios en curso', function () {
    expect(NivelInstruccion::BACHILLER->nivelDeEstudio())->toBe(NivelEstudio::SECUNDARIA)
        ->and(NivelInstruccion::TECNICO_SUPERIOR->nivelDeEstudio())->toBe(NivelEstudio::TECNICO_SUPERIOR)
        ->and(NivelInstruccion::TECNOLOGICO_SUPERIOR->nivelDeEstudio())->toBe(NivelEstudio::TECNOLOGICO_SUPERIOR)
        ->and(NivelInstruccion::TERCER_NIVEL->nivelDeEstudio())->toBe(NivelEstudio::TERCER_NIVEL)
        ->and(NivelInstruccion::CUARTO_NIVEL->nivelDeEstudio())->toBe(NivelEstudio::CUARTO_NIVEL)
        // «Tercer año o sexto semestre aprobado» no es un título.
        ->and(NivelInstruccion::ESTUDIOS_SUPERIORES_EN_CURSO->nivelDeEstudio())->toBeNull();
});

test('los niveles van de menor a mayor, con el técnico y el tecnológico antes del grado', function () {
    expect(array_map(fn (NivelEstudio $n) => $n->value, NivelEstudio::cases()))->toBe([
        'primaria', 'secundaria', 'tecnico_superior', 'tecnologico_superior', 'tercer_nivel', 'cuarto_nivel',
    ]);
});
