<?php

/*
| «Mis permisos» del autoservicio: lo que ve el propio servidor.
|
| El motivo de los permisos personales se tapaba con «Confidencial», pero la
| condición comparaba el enum del modelo con un texto y nunca se cumplía. Se
| quitó: la lista es del propio servidor, y quién ve el motivo de un permiso
| ajeno lo decide la policy. El endpoint pasa a paginar, como el resto.
*/

use App\Enums\EstadoPermiso;
use App\Enums\RegimenLaboral;
use App\Enums\TipoPermiso;
use App\Models\Asistencia\PermisoServidor;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();
    PermisoServidor::unguard();

    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $unidad = unidadDePrueba();
    $puesto = puestoDePrueba($unidad);

    $cedula = 800006000;
    $nuevoServidor = function (string $apellido) use ($unidad, $puesto, &$cedula) {
        return Servidor::create([
            'cedula'                   => '0'.(++$cedula),
            'nombre'                   => 'Marta',
            'apellido'                 => $apellido,
            'puesto_id'                => $puesto->id,
            'unidad_administrativa_id' => $unidad->id,
            'regimen_laboral'          => RegimenLaboral::LOSEP,
            'estado'                   => true,
        ]);
    };

    $this->titular = $nuevoServidor('Titular');
    $this->otro    = $nuevoServidor('Ajena');

    $this->usuario = User::create([
        'email'        => 'titular-portal@example.com',
        'usuario_ti'   => 'mtitular',
        'password'     => bcrypt('123456'),
        'primer_login' => false,
        'servidor_id'  => $this->titular->id,
    ]);
    $this->usuario->assignRole('servidor');

    $numero = 0;
    $this->permiso = function (Servidor $servidor, array $atributos = []) use (&$numero) {
        return PermisoServidor::create(array_merge([
            'servidor_id'              => $servidor->id,
            'unidad_administrativa_id' => $servidor->unidad_administrativa_id,
            'tipo'                     => TipoPermiso::PERSONAL->value,
            'fecha'                    => now()->toDateString(),
            'hora_inicio'              => '08:00',
            'hora_fin'                 => '10:00',
            'estado'                   => EstadoPermiso::PENDIENTE->value,
            'vence_en'                 => now()->addDays(3),
            'folio'                    => sprintf('PER-%d-%05d', now()->year, ++$numero),
        ], $atributos));
    };

    $this->mios = fn (array $consulta = []) => $this->actingAs($this->usuario, 'sanctum')
        ->getJson('/api/v1/autoservicio/mis-permisos?'.http_build_query($consulta));
});

test('solo devuelve los permisos propios, del más reciente al más antiguo', function () {
    $viejo = ($this->permiso)($this->titular, ['fecha' => now()->subDays(10)->toDateString()]);
    $nuevo = ($this->permiso)($this->titular, ['fecha' => now()->subDay()->toDateString()]);
    ($this->permiso)($this->otro);

    $respuesta = ($this->mios)()->assertOk();

    expect(array_column($respuesta->json('datos.data'), 'id'))->toBe([$nuevo->id, $viejo->id])
        ->and($respuesta->json('datos.total'))->toBe(2);
});

test('el motivo de un permiso personal se muestra completo: es del propio servidor', function () {
    ($this->permiso)($this->titular, ['observacion' => 'Trámite en el Registro Civil']);

    expect(($this->mios)()->json('datos.data.0.observacion'))->toBe('Trámite en el Registro Civil');
});

test('filtra por estado y por año', function () {
    ($this->permiso)($this->titular, ['estado' => EstadoPermiso::ACTIVO->value]);
    ($this->permiso)($this->titular);
    ($this->permiso)($this->titular, [
        'fecha'  => now()->subYear()->toDateString(),
        'estado' => EstadoPermiso::ACTIVO->value,
    ]);

    expect(($this->mios)(['estado' => 'activo', 'anio' => now()->year])->json('datos.total'))->toBe(1)
        ->and(($this->mios)(['anio' => now()->year - 1])->json('datos.total'))->toBe(1)
        ->and(($this->mios)()->json('datos.total'))->toBe(3);
});

test('pagina de a 15 sin repetir filas entre páginas', function () {
    // Todos el mismo día y a la misma hora: sin desempate por id, Postgres
    // ordenaría cada página a su manera.
    foreach (range(1, 16) as $_) {
        ($this->permiso)($this->titular);
    }

    $primera = ($this->mios)()->json('datos');
    $segunda = ($this->mios)(['page' => 2])->json('datos');

    expect($primera['total'])->toBe(16)
        ->and($primera['data'])->toHaveCount(15)
        ->and($segunda['data'])->toHaveCount(1)
        ->and(array_intersect(
            array_column($primera['data'], 'id'),
            array_column($segunda['data'], 'id')
        ))->toBe([]);
});

test('un usuario sin servidor vinculado recibe una lista vacía', function () {
    ($this->permiso)($this->titular);

    $sinServidor = User::create([
        'email'        => 'sin-servidor@example.com',
        'usuario_ti'   => 'sinservidor',
        'password'     => bcrypt('123456'),
        'primer_login' => false,
    ]);
    $sinServidor->assignRole('servidor');

    $this->actingAs($sinServidor, 'sanctum')
        ->getJson('/api/v1/autoservicio/mis-permisos')
        ->assertOk()
        ->assertJsonPath('datos.total', 0);
});
