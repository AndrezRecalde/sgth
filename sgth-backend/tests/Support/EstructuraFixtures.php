<?php

use App\Models\Estructura\Cargo;
use App\Models\Estructura\GrupoOcupacional;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;

/*
|--------------------------------------------------------------------------
| Fixtures de estructura organizacional para los tests
|--------------------------------------------------------------------------
|
| La tabla `puestos` se reestructuró: lo que antes eran columnas planas
| (`denominacion`, `grupo_ocupacional`, `grado_rmu`, `rmu`, `codigo`,
| `nivel`, `estado`) pasó a ser relaciones — `cargo_id` para la
| denominación y `grupo_ocupacional_id` para el grado y la R.M.U.
|
| Siete archivos de tests de otros módulos seguían creando puestos con el
| esquema viejo, y para forzarlos llamaban a Model::unguard(). Estas
| funciones existen para que ninguno tenga que volver a saber cómo está
| armada la estructura: piden lo que el test de verdad necesita —un puesto
| de jefe, uno de analista— y el resto se resuelve aquí.
|
*/

/**
 * Unidad administrativa con código único, para que dos tests del mismo
 * archivo no choquen contra el índice.
 *
 * El código va corto a propósito: CodigoViaticoService lo usa como prefijo de
 * `viaticos.codigo_viatico`, que es varchar(20). Un uniqid() completo lo
 * desbordaba.
 */
function unidadDePrueba(array $atributos = []): UnidadAdministrativa
{
    return UnidadAdministrativa::create(array_merge([
        'codigo' => 'U'.strtoupper(substr(uniqid(), -4)),
        'nombre' => 'Dirección de Prueba',
        'nivel'  => 1,
        'estado' => true,
    ], $atributos));
}

/**
 * Puesto armado sobre el esquema vigente.
 *
 * `cargo` es la denominación (antes `puestos.denominacion`) y `rmu` viaja
 * al grupo ocupacional (antes `puestos.rmu`), que es donde vive hoy: el
 * accesor `Puesto::rmu` la lee de ahí.
 */
function puestoDePrueba(
    UnidadAdministrativa $unidad,
    string $cargo = 'Analista',
    array $atributos = [],
): Puesto {
    $esJefe = $atributos['es_jefe'] ?? false;
    $rmu    = $atributos['rmu'] ?? ($esJefe ? 2000.00 : 1000.00);

    unset($atributos['rmu']);

    $grupo = GrupoOcupacional::firstOrCreate(
        ['grado_codigo' => $esJefe ? 'SP15' : 'SP10'],
        [
            'grado_numerico'        => $esJefe ? 15 : 10,
            'grupo'                 => $esJefe ? 'Directivo' : 'Profesional',
            'denominacion_generica' => $esJefe ? 'Directivo' : 'Profesional',
            'rmu'                   => $rmu,
            'regimen'               => 'losep',
            'activo'                => true,
        ],
    );

    return Puesto::create(array_merge([
        'unidad_administrativa_id' => $unidad->id,
        'cargo_id'                 => Cargo::firstOrCreate(['nombre' => $cargo])->id,
        'grupo_ocupacional_id'     => $grupo->id,
        'plazas'                   => 1,
        'es_jefe'                  => $esJefe,
        'activo'                   => true,
    ], $atributos));
}

/** Atajo para el caso más repetido: el jefe de una unidad. */
function puestoJefeDePrueba(
    UnidadAdministrativa $unidad,
    string $cargo = 'Director',
): Puesto {
    return puestoDePrueba($unidad, $cargo, ['es_jefe' => true]);
}
