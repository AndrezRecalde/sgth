<?php

/*
| Los compañeros de unidad del autoservicio: de dónde elige el servidor a su
| jefe inmediato al registrar un permiso desde el portal.
|
| El listado de expedientes está cerrado a Talento Humano, así que para
| cualquier otro el selector de jefe salía vacío. Esta ruta devuelve solo la
| unidad del propio servidor, y solo lo necesario para elegir.
*/

use App\Enums\RegimenLaboral;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Servidor::unguard();
    Role::firstOrCreate(['name' => 'servidor', 'guard_name' => 'sanctum']);

    $unidad     = unidadDePrueba(['nombre' => 'Dirección de Tecnología']);
    $otraUnidad = unidadDePrueba(['nombre' => 'Dirección Financiera']);

    $analista = puestoDePrueba($unidad, 'Analista');
    $director = puestoJefeDePrueba($unidad, 'Director de Tecnología');

    $cedula   = 900007000;
    $servidor = function (
        string $apellido,
        Puesto $puesto,
        UnidadAdministrativa $unidad,
        bool $activo = true,
    ) use (&$cedula) {
        return Servidor::create([
            'cedula'                   => '0'.(++$cedula),
            'nombre'                   => 'Ana',
            'apellido'                 => $apellido,
            'puesto_id'                => $puesto->id,
            'unidad_administrativa_id' => $unidad->id,
            'regimen_laboral'          => RegimenLaboral::LOSEP,
            'estado'                   => $activo,
        ]);
    };

    $this->titular = $servidor('Titular', $analista, $unidad);
    $this->jefe    = $servidor('Zambrano', $director, $unidad);
    $this->colega  = $servidor('Andrade', $analista, $unidad);
    $servidor('Retirada', $analista, $unidad, false);
    $servidor('Ajena', puestoDePrueba($otraUnidad), $otraUnidad);

    $this->usuario = User::factory()->create(['servidor_id' => $this->titular->id]);
    $this->usuario->assignRole('servidor');

    $this->companeros = fn (User $usuario) => $this->actingAs($usuario, 'sanctum')
        ->getJson('/api/v1/autoservicio/companeros-de-unidad');
});

test('devuelve a los activos de su unidad, sin él mismo y con los jefes primero', function () {
    $datos = ($this->companeros)($this->usuario)->assertOk()->json('datos');

    // Ni el propio servidor, ni la retirada, ni la de otra unidad.
    expect(array_column($datos, 'id'))->toBe([$this->jefe->id, $this->colega->id]);

    expect($datos[0])->toBe([
        'id'      => $this->jefe->id,
        'nombre'  => 'Zambrano Ana',
        'cargo'   => 'Director de Tecnología',
        'es_jefe' => true,
    ]);
});

test('no expone la cédula ni otros datos personales de los compañeros', function () {
    $datos = ($this->companeros)($this->usuario)->assertOk()->json('datos');

    foreach ($datos as $companero) {
        expect(array_keys($companero))->toBe(['id', 'nombre', 'cargo', 'es_jefe']);
    }
});

test('un usuario sin servidor vinculado recibe la lista vacía', function () {
    $sinServidor = User::factory()->create(['servidor_id' => null]);
    $sinServidor->assignRole('servidor');

    ($this->companeros)($sinServidor)
        ->assertOk()
        ->assertJsonPath('datos', []);
});
