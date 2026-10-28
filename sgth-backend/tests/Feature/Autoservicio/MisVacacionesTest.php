<?php

/*
| «Mis vacaciones» del portal: lo que el servidor ve y pide de sus vacaciones.
|
| La página no existía —el menú llevaba a un 404— y el autoservicio ya
| devolvía el historial. Al abrirle el alta a cada servidor desde el portal
| apareció lo mismo que en permisos (#372): la solicitud guardaba la unidad
| que mandara el cliente, y esa unidad decide qué jefe la ve.
*/

use App\Enums\RegimenLaboral;
use App\Models\Asistencia\Vacacion;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();
    Vacacion::unguard();

    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $this->unidad = unidadDePrueba(['nombre' => 'Dirección del titular']);
    $this->otraUnidad = unidadDePrueba(['nombre' => 'Dirección ajena']);

    $cedula = 800007100;
    $nuevoServidor = function (UnidadAdministrativa $unidad, string $apellido) use (&$cedula) {
        return Servidor::create([
            'cedula'                       => '0'.(++$cedula),
            'nombre'                       => 'Lucía',
            'apellido'                     => $apellido,
            'puesto_id'                    => puestoDePrueba($unidad)->id,
            'unidad_administrativa_id'     => $unidad->id,
            'regimen_laboral'              => RegimenLaboral::LOSEP,
            'fecha_ingreso_institucion'    => now()->subYears(3),
            'fecha_ingreso_sector_publico' => now()->subYears(3),
            'estado'                       => true,
        ]);
    };

    $this->titular = $nuevoServidor($this->unidad, 'Titular');
    $this->ajeno   = $nuevoServidor($this->otraUnidad, 'Ajeno');

    $usuario = User::create([
        'email'        => 'mis-vacaciones@example.com',
        'usuario_ti'   => 'misvacaciones',
        'password'     => bcrypt('123456'),
        'primer_login' => false,
        'servidor_id'  => $this->titular->id,
    ]);
    $usuario->assignRole('servidor');
    $this->actingAs($usuario, 'sanctum');

    $this->vacacionDe = fn (Servidor $servidor, string $inicio) => Vacacion::create([
        'servidor_id'              => $servidor->id,
        'unidad_administrativa_id' => $servidor->unidad_administrativa_id,
        'fecha_inicio'             => $inicio,
        'fecha_fin'                => $inicio,
        'dias_solicitados'         => 1,
        'tipo_dias'                => 'calendario',
        'estado'                   => 'pendiente',
        'motivo'                   => 'vacaciones_anuales',
    ]);
});

test('el historial trae solo las suyas, de la más nueva a la más vieja', function () {
    // Las dos en el mismo segundo: sin desempate por id, el orden era el que
    // le tocara a la base.
    $vieja = ($this->vacacionDe)($this->titular, now()->addDays(10)->toDateString());
    $nueva = ($this->vacacionDe)($this->titular, now()->addDays(20)->toDateString());
    ($this->vacacionDe)($this->ajeno, now()->addDays(30)->toDateString());

    $respuesta = $this->getJson('/api/v1/autoservicio/mis-vacaciones')->assertOk();

    expect(collect($respuesta->json('datos.historial_solicitudes'))->pluck('id')->all())
        ->toBe([$nueva->id, $vieja->id]);
});

test('lo que pide desde el portal queda en su unidad aunque mande otra', function () {
    periodoVacacionesDePrueba($this->titular);
    $lunes = now()->addWeeks(4)->next(Carbon::MONDAY);

    $this->postJson('/api/v1/asistencia/vacaciones', [
        'motivo'                   => 'vacaciones_anuales',
        'fecha_inicio'             => $lunes->toDateString(),
        'fecha_fin'                => $lunes->copy()->addDays(6)->toDateString(),
        'dias_solicitados'         => 7,
        'tipo_dias'                => 'calendario',
        'unidad_administrativa_id' => $this->otraUnidad->id,
    ])->assertCreated();

    expect(Vacacion::where('servidor_id', $this->titular->id)->sole()->unidad_administrativa_id)
        ->toBe($this->unidad->id);
});

test('una cuenta sin ficha recibe 422 y no un 404 de ruta inexistente', function () {
    // Con el `0` del controlador, el saldo hacía `findOrFail(0)` y salía «La
    // ruta solicitada no existe» (docs/plan-roles-y-accesos.md, PR 15).
    $sinFicha = User::factory()->create(['servidor_id' => null]);
    $sinFicha->assignRole('servidor');

    $this->actingAs($sinFicha, 'sanctum')
        ->getJson('/api/v1/autoservicio/mis-vacaciones')
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'Su cuenta no está vinculada a un servidor: no tiene vacaciones.');
});
