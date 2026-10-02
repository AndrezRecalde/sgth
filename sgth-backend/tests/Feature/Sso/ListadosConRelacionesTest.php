<?php

/*
| Que el listado siga mandando las relaciones que la pantalla pinta.
|
| Los siete `index` del módulo devolvían el modelo crudo mientras el `show` de
| la misma entidad pasaba por su recurso, así que listado y detalle tenían
| formas distintas y el tipo generado describía solo la del detalle. Al pasar
| los `index` por el recurso había un riesgo concreto: los recursos no
| declaraban ninguna de las relaciones que los listados sí traen cargadas, y
| columnas como «Puesto» o «Factor de riesgo» se habrían quedado vacías.
|
| Estas pruebas fijan esa frontera. Si alguien agrega un `with()` a un listado
| y no declara la relación en el recurso, el dato no llega al navegador y aquí
| no se entera nadie; si alguien quita del recurso una de estas relaciones,
| esto falla.
*/

use App\Models\Estructura\Cargo;
use App\Models\Sso\EquipoProteccion;
use App\Models\Sso\EppEntrega;
use App\Models\Sso\FactorRiesgoCatalogo;
use App\Models\Sso\RiesgoLaboral;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $this->usuario = User::create([
        'email'        => 'sso-listados@example.com',
        'usuario_ti'   => 'ssolistados',
        'password'     => bcrypt('123456'),
        'primer_login' => false,
    ]);
    $this->usuario->assignRole('admin-uath');
    $this->actingAs($this->usuario, 'sanctum');

    $this->unidad = unidadDePrueba();
    $this->puesto = puestoDePrueba($this->unidad, 'Operador de maquinaria pesada');
});

test('la matriz de riesgos manda el puesto con su cargo y el factor', function () {
    $factor = FactorRiesgoCatalogo::create([
        'nombre'    => 'Ruido continuo',
        'categoria' => 'fisico',
        'activo'    => true,
    ]);

    RiesgoLaboral::create([
        'puesto_id'        => $this->puesto->id,
        'factor_riesgo_id' => $factor->id,
        'descripcion'      => 'Exposición a ruido de la maquinaria',
        'estado'           => true,
    ]);

    $respuesta = $this->getJson('/api/v1/sso/riesgos')->assertOk();

    // El nombre del cargo es lo que pinta la columna «Puesto»: el id no sirve.
    $cargo = Cargo::find($this->puesto->cargo_id);
    $respuesta
        ->assertJsonPath('datos.0.puesto.cargo.nombre', $cargo->nombre)
        ->assertJsonPath('datos.0.factor_riesgo.nombre', 'Ruido continuo');
});

test('la bitácora de EPP manda el servidor y el equipo', function () {
    $servidor = Servidor::create([
        'cedula'                   => '0801234567',
        'nombre'                   => 'Rosa',
        'apellido'                 => 'Quiñónez',
        'puesto_id'                => $this->puesto->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'regimen_laboral'          => App\Enums\RegimenLaboral::LOSEP,
        'estado'                   => true,
    ]);

    $equipo = EquipoProteccion::create([
        'codigo' => 'EPP-021',
        'nombre' => 'Casco dieléctrico clase E',
        'tipo'   => 'cabeza',
        'estado' => true,
    ]);

    EppEntrega::create([
        'servidor_id'          => $servidor->id,
        'equipo_proteccion_id' => $equipo->id,
        'fecha_entrega'        => '2026-09-05',
        'cantidad'             => 1,
        'motivo'               => 'entrega',
        'entregado_por'        => $this->usuario->id,
    ]);

    $this->getJson('/api/v1/sso/epp-entregas')
        ->assertOk()
        ->assertJsonPath('datos.0.servidor.apellido', 'Quiñónez')
        ->assertJsonPath('datos.0.equipo_proteccion.nombre', 'Casco dieléctrico clase E');
});

test('el listado y el detalle de un riesgo devuelven la misma forma', function () {
    $factor = FactorRiesgoCatalogo::create([
        'nombre'    => 'Manejo manual de cargas',
        'categoria' => 'ergonomico',
        'activo'    => true,
    ]);

    $riesgo = RiesgoLaboral::create([
        'puesto_id'        => $this->puesto->id,
        'factor_riesgo_id' => $factor->id,
        'descripcion'      => 'Levantamiento repetido de sacos',
        'estado'           => true,
    ]);

    $deListado = $this->getJson('/api/v1/sso/riesgos')->assertOk()->json('datos.0');
    $deDetalle = $this->getJson("/api/v1/sso/riesgos/{$riesgo->id}")->assertOk()->json('datos');

    // Las relaciones aparte: el detalle las carga igual, pero lo que se compara
    // aquí es que las columnas propias del riesgo sean las mismas en los dos
    // sitios, que es lo que antes no pasaba.
    $columnas = fn (array $fila) => collect($fila)
        ->except(['puesto', 'factor_riesgo'])
        ->keys()
        ->sort()
        ->values()
        ->all();

    expect($columnas($deListado))->toBe($columnas($deDetalle));
});
