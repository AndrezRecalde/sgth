<?php

/*
| Coherencia del alta y del listado de permisos (re-auditoría 2026-10-07).
|
| - Nadie firma su propio permiso: se comprobaba solo al dirigirlo a Talento
|   Humano, y TH podía elegir al jefe de una unidad como firmante de su propio
|   permiso.
| - La unidad del permiso es la del servidor. Se aceptaba la que llegara en la
|   petición, y un servidor podía sacar el suyo de la vista de su jefe.
| - Los filtros del listado se validan: una fecha mal escrita daba un 500.
*/

use App\Enums\RegimenLaboral;
use App\Models\Asistencia\PermisoServidor;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();

    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $this->unidad = unidadDePrueba(['nombre' => 'Dirección de Obras Públicas']);
    $this->otraUnidad = unidadDePrueba(['nombre' => 'Dirección Financiera']);

    $this->jefe = servidorAltaCoherente($this->unidad, puestoJefeDePrueba($this->unidad), '0802000001');
    $this->servidor = servidorAltaCoherente($this->unidad, puestoDePrueba($this->unidad), '0802000002');

    $this->uath = usuarioAltaCoherente('admin-uath');
});

function servidorAltaCoherente(UnidadAdministrativa $unidad, Puesto $puesto, string $cedula): Servidor
{
    return Servidor::create([
        'cedula'                   => $cedula,
        'nombre'                   => 'Carla',
        'apellido'                 => 'Coherente',
        'puesto_id'                => $puesto->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral'          => RegimenLaboral::LOSEP,
        'estado'                   => true,
    ]);
}

function usuarioAltaCoherente(string $rol, ?Servidor $servidor = null): User
{
    $usuario = User::create([
        'email'        => uniqid('coherente').'@example.com',
        'usuario_ti'   => uniqid('coh'),
        'password'     => bcrypt('123456'),
        'primer_login' => false,
        'servidor_id'  => $servidor?->id,
    ]);
    $usuario->assignRole($rol);

    return $usuario;
}

function pedirAltaCoherente(User $quien, array $datos): TestResponse
{
    // Oficial: no descuenta vacaciones, así que no hacen falta períodos.
    return test()->actingAs($quien, 'sanctum')
        ->postJson('/api/v1/asistencia/permisos', array_merge([
            'tipo'        => 'oficial',
            'observacion' => 'Diligencia en la Contraloría',
            'fecha'       => now()->next(Carbon::MONDAY)->toDateString(),
            'hora_inicio' => '08:00',
            'hora_fin'    => '10:00',
        ], $datos));
}

test('Talento Humano no puede poner al jefe como firmante de su propio permiso', function () {
    pedirAltaCoherente($this->uath, [
        'servidor_id' => $this->jefe->id,
        'jefe_id'     => $this->jefe->id,
    ])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'Nadie firma su propio permiso: elija a otro jefe inmediato o dirija el permiso a Talento Humano.');

    expect(PermisoServidor::count())->toBe(0);
});

test('el servidor tampoco se elige a sí mismo por la API', function () {
    $usuario = usuarioAltaCoherente('servidor', $this->servidor);

    pedirAltaCoherente($usuario, ['jefe_id' => $this->servidor->id])->assertStatus(422);

    expect(PermisoServidor::count())->toBe(0);
});

test('la unidad del permiso es la del servidor, no la que llegue en la petición', function () {
    $usuario = usuarioAltaCoherente('servidor', $this->servidor);

    pedirAltaCoherente($usuario, [
        'jefe_id'                  => $this->jefe->id,
        'unidad_administrativa_id' => $this->otraUnidad->id,
    ])->assertCreated();

    expect(PermisoServidor::latest('id')->firstOrFail()->unidad_administrativa_id)
        ->toBe($this->unidad->id);
});

test('el autor es quien tiene la sesión aunque llegue otro creado_por', function () {
    $usuario = usuarioAltaCoherente('servidor', $this->servidor);

    pedirAltaCoherente($usuario, [
        'jefe_id'    => $this->jefe->id,
        'creado_por' => $this->uath->id,
    ])->assertCreated();

    expect(PermisoServidor::latest('id')->firstOrFail()->creado_por)->toBe($usuario->id);
});

test('el listado rechaza filtros mal formados con 422 y no con 500', function (array $filtro, string $campo) {
    $this->actingAs($this->uath, 'sanctum')
        ->getJson('/api/v1/asistencia/permisos?'.http_build_query($filtro))
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => [$campo]]);
})->with([
    'fecha inválida'          => [['fecha_desde' => 'ayer'], 'fecha_desde'],
    'rango invertido'         => [['fecha_desde' => '2026-10-10', 'fecha_hasta' => '2026-10-01'], 'fecha_hasta'],
    'estado inexistente'      => [['estado' => 'aprobado'], 'estado'],
    'tipo inexistente'        => [['tipo' => 'vacaciones'], 'tipo'],
]);

test('el listado sigue filtrando con valores válidos', function () {
    $this->actingAs($this->uath, 'sanctum')
        ->getJson('/api/v1/asistencia/permisos?estado=pendiente&tipo=oficial&fecha_desde=2026-01-01&fecha_hasta=2026-12-31')
        ->assertOk();
});
