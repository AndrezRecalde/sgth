<?php

namespace Tests\Feature\Expediente;

use App\Http\Resources\Expediente\MovimientoPersonalResource;
use App\Http\Resources\Expediente\ServidorResource;
use App\Http\Resources\Sso\AccidenteTrabajoResource;
use App\Http\Resources\Sso\CapacitacionSsoResource;
use App\Http\Resources\Sso\EquipoProteccionResource;
use App\Http\Resources\Sso\InspeccionSsoResource;
use App\Http\Resources\Sso\RiesgoLaboralResource;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\Sso\AccidenteTrabajo;
use App\Models\Sso\CapacitacionSso;
use App\Models\Sso\EquipoProteccion;
use App\Models\Sso\InspeccionSso;
use App\Models\Sso\RiesgoLaboral;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Estos recursos enumeran su arreglo campo por campo en vez de delegar en
 * `parent::toArray()`, porque Scramble solo infiere la forma cuando el arreglo
 * es literal. El precio de enumerar es que una columna nueva ya no aparece
 * sola: hay que agregarla al recurso.
 *
 * Esta prueba es la que cobra ese precio. Si agregas una columna y olvidas el
 * recurso, falla aquí y no en producción con un campo que nunca llegó.
 */
dataset('recursos_enumerados', [
    'ServidorResource'           => [ServidorResource::class, Servidor::class],
    'MovimientoPersonalResource' => [MovimientoPersonalResource::class, MovimientoPersonal::class],
    'AccidenteTrabajoResource'   => [AccidenteTrabajoResource::class, AccidenteTrabajo::class],
    'EquipoProteccionResource'   => [EquipoProteccionResource::class, EquipoProteccion::class],
    'RiesgoLaboralResource'      => [RiesgoLaboralResource::class, RiesgoLaboral::class],
    'CapacitacionSsoResource'    => [CapacitacionSsoResource::class, CapacitacionSso::class],
    'InspeccionSsoResource'      => [InspeccionSsoResource::class, InspeccionSso::class],
]);

test('el recurso emite todas las columnas de su tabla', function (
    string $recurso,
    string $modelo,
) {
    /** @var \Illuminate\Database\Eloquent\Model $instancia */
    $instancia = new $modelo();

    $columnas = $instancia->getConnection()
        ->getSchemaBuilder()
        ->getColumnListing($instancia->getTable());

    // Un modelo "vacío" pero con cada atributo presente: basta para leer las
    // claves que el recurso arma, sin tocar la base.
    foreach ($columnas as $columna) {
        $instancia->setAttribute($columna, null);
    }
    $instancia->exists = true;

    $emitidas = array_keys((new $recurso($instancia))->toArray(request()));

    $olvidadas = array_values(array_diff($columnas, $emitidas));

    expect($olvidadas)->toBe([], sprintf(
        '%s no emite: %s. Al enumerar campo por campo, una columna nueva hay '
            .'que agregarla al recurso a mano.',
        class_basename($recurso),
        implode(', ', $olvidadas),
    ));
})->with('recursos_enumerados');
