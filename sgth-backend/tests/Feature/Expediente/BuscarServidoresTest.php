<?php

/*
| El buscador de servidores de los formularios (docs/plan-roles-y-accesos.md,
| PR 2).
|
| Pedía el listado del Expediente, que ServidorPolicy solo sirve a Talento
| Humano: TIC recibía 403 al registrar un ticket, entregar o prestar un equipo,
| y el auditor no podía filtrar por servidor. Ahora tiene su permiso y devuelve
| lo justo para elegir a una persona; lo del vínculo, solo a Talento Humano.
*/

use App\Enums\TipoNombramiento;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolPermisoSeeder::class);

    $this->unidad = unidadDePrueba(['nombre' => 'Gestión de Prueba del Buscador']);
    $puesto = puestoDePrueba($this->unidad, 'Analista de Prueba', ['rmu' => 1212.00]);

    $this->juan = Servidor::forceCreate([
        'cedula' => '0807171701', 'nombre' => 'Juan', 'apellido' => 'Pérez',
        'unidad_administrativa_id' => $this->unidad->id, 'puesto_id' => $puesto->id,
        'regimen_laboral' => 'losep', 'estado' => true, 'puede_marcar' => true,
        'telefono_celular' => '0999999999', 'direccion_domicilio' => 'Calle de prueba',
    ]);

    ContratoServidor::create([
        'servidor_id'              => $this->juan->id,
        'tipo_nombramiento'        => TipoNombramiento::PERMANENTE->value,
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id'                => $puesto->id,
        'fecha_inicio'             => '2020-01-01',
        'estado'                   => 'vigente',
        'remuneracion'             => 1212.00,
    ]);

    // Homónimo de nombre, para que buscar por nombre completo tenga que cruzar
    // las dos columnas.
    Servidor::forceCreate([
        'cedula' => '0807171702', 'nombre' => 'Juan', 'apellido' => 'Quiñónez',
        'unidad_administrativa_id' => $this->unidad->id,
        'regimen_laboral' => 'losep', 'estado' => true,
    ]);

    $this->conRol = function (string $rol): User {
        $usuario = User::factory()->create();
        $usuario->assignRole($rol);

        return $usuario;
    };
});

it('lo pueden usar Talento Humano, TIC y el auditor', function (string $rol) {
    $this->actingAs(($this->conRol)($rol), 'sanctum')
        ->getJson('/api/v1/expediente/servidores/buscar?search=juan')
        ->assertOk()
        ->assertJsonCount(2, 'datos');
})->with(['admin-uath', 'asistente-uath', 'tecnico-dtic', 'jefe-tic', 'admin-ti', 'auditor']);

it('al resto le responde 403', function (string $rol) {
    $this->actingAs(($this->conRol)($rol), 'sanctum')
        ->getJson('/api/v1/expediente/servidores/buscar?search=juan')
        ->assertForbidden();

    $this->getJson("/api/v1/expediente/servidores/buscar/{$this->juan->id}")->assertForbidden();
})->with(['servidor', 'director', 'jefe-unidad', 'medico', 'financiero', 'recepcion', 'comunicacion-social']);

it('a TIC le devuelve lo justo para elegir: sin contacto ni vínculo', function () {
    $respuesta = $this->actingAs(($this->conRol)('tecnico-dtic'), 'sanctum')
        ->getJson('/api/v1/expediente/servidores/buscar?search=juan pérez')
        ->assertOk()
        ->assertJsonCount(1, 'datos');

    $juan = $respuesta->json('datos.0');

    expect($juan)->toMatchArray([
        'id'           => $this->juan->id,
        'cedula'       => '0807171701',
        'nombre'       => 'Juan',
        'apellido'     => 'Pérez',
        'estado'       => true,
        'puede_marcar' => true,
        'unidad_administrativa' => ['id' => $this->unidad->id, 'nombre' => 'Gestión de Prueba del Buscador'],
    ])->and($juan)->not->toHaveKeys([
        'contrato_vigente', 'puesto', 'pendiente_vinculacion', 'ultimo_nombramiento',
        'telefono_celular', 'direccion_domicilio', 'tiene_discapacidad',
    ]);
});

it('a Talento Humano le añade lo del vínculo', function () {
    $juan = $this->actingAs(($this->conRol)('admin-uath'), 'sanctum')
        ->getJson('/api/v1/expediente/servidores/buscar?search=0807171701')
        ->assertOk()
        ->json('datos.0');

    expect($juan['contrato_vigente']['tipo_nombramiento'])->toBe(TipoNombramiento::PERMANENTE->value)
        ->and((float) $juan['contrato_vigente']['remuneracion'])->toBe(1212.0)
        ->and((float) $juan['puesto']['rmu'])->toBe(1212.0)
        ->and($juan['pendiente_vinculacion'])->toBeFalse()
        ->and($juan['ultimo_nombramiento'])->toBe(TipoNombramiento::PERMANENTE->value);
});

it('admin-ti busca, pero no ve lo del vínculo: no entra al Expediente', function () {
    $juan = $this->actingAs(($this->conRol)('admin-ti'), 'sanctum')
        ->getJson('/api/v1/expediente/servidores/buscar?search=0807171701')
        ->assertOk()
        ->json('datos.0');

    expect($juan)->not->toHaveKeys(['contrato_vigente', 'puesto', 'pendiente_vinculacion']);
});

it('devuelve al ya elegido por su id, para enseñar su nombre', function () {
    $this->actingAs(($this->conRol)('jefe-tic'), 'sanctum')
        ->getJson("/api/v1/expediente/servidores/buscar/{$this->juan->id}")
        ->assertOk()
        ->assertJsonPath('datos.apellido', 'Pérez')
        ->assertJsonMissingPath('datos.contrato_vigente');

    $this->getJson('/api/v1/expediente/servidores/buscar/999999')->assertNotFound();
});

it('pide al menos dos letras y no devuelve más de veinte', function () {
    $this->actingAs(($this->conRol)('auditor'), 'sanctum');

    $this->getJson('/api/v1/expediente/servidores/buscar?search=j')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['search'], 'errores');

    $this->getJson('/api/v1/expediente/servidores/buscar?search=juan&per_page=50')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['per_page'], 'errores');
});

it('el seeder da el permiso a los mismos roles que la migración', function () {
    $migracion = require database_path('migrations/2026_11_26_000000_permiso_buscar_servidores.php');
    $roles = (new ReflectionClassConstant($migracion, 'ROLES'))->getValue();

    // Los roles que nacieron después ya con el permiso no los toca esta
    // migración: se lo da la suya. El técnico de SSO, en la 2026_11_30 (PR 12).
    $posteriores = ['tecnico-sso'];

    $conPermiso = Role::where('guard_name', 'sanctum')
        ->whereHas('permissions', fn ($q) => $q->where('name', 'buscar-servidores'))
        ->pluck('name');

    expect($conPermiso->sort()->values()->all())
        ->toBe(collect([...$roles, ...$posteriores])->sort()->values()->all());
});
