<?php

/*
| Quién puede registrar un permiso, y a nombre de quién.
|
| El alta no comprobaba nada: el `servidor_id` llegaba en la petición y se
| aceptaba. Un servidor podía registrarle un permiso personal a otro y, al
| confirmarlo Recepción, descontarle las horas de su saldo de vacaciones.
|
| La regla: el propio, cualquiera; el de otro servidor, solo Talento Humano
| (`registrar-permisos-servidores`, que tienen admin-uath y asistente-uath).
| Ver no es emitir: máxima autoridad y auditoría ven los permisos de todos,
| pero no los registran.
|
| Ningún usuario de estos tests es admin-ti: su `Gate::before` se salta la
| policy y no probaría nada.
*/

use App\Enums\Permiso;
use App\Enums\RegimenLaboral;
use App\Models\Asistencia\PermisoServidor;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();

    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $unidad = unidadDePrueba();
    $cedula = 800003000;
    $this->servidor = function (bool $jefe = false) use ($unidad, &$cedula) {
        return Servidor::create([
            'cedula'                   => '0'.(++$cedula),
            'nombre'                   => 'Alba',
            'apellido'                 => 'Alta',
            'puesto_id'                => ($jefe ? puestoJefeDePrueba($unidad) : puestoDePrueba($unidad))->id,
            'unidad_administrativa_id' => $unidad->id,
            'regimen_laboral'          => RegimenLaboral::LOSEP,
            'estado'                   => true,
        ]);
    };

    $this->usuario = function (string $rol, ?Servidor $servidor = null) {
        $usuario = User::create([
            'email'        => uniqid('alta').'@example.com',
            'usuario_ti'   => uniqid('alta'),
            'password'     => bcrypt('123456'),
            'primer_login' => false,
            'servidor_id'  => $servidor?->id,
        ]);
        $usuario->assignRole($rol);

        return $usuario;
    };

    $this->otro = ($this->servidor)();

    // Oficial: no descuenta vacaciones, así que no hacen falta períodos.
    $this->pedir = fn (User $quien, array $extra = []) => $this->actingAs($quien, 'sanctum')
        ->postJson('/api/v1/asistencia/permisos', array_merge([
            'tipo'        => 'oficial',
            'observacion' => 'Diligencia en la Contraloría',
            'fecha'       => now()->next(Carbon::MONDAY)->toDateString(),
            'hora_inicio' => '08:00',
            'hora_fin'    => '10:00',
        ], $extra));
});

test('un servidor registra su propio permiso, con o sin decir su servidor_id', function () {
    $propio = ($this->servidor)();
    $usuario = ($this->usuario)('servidor', $propio);

    ($this->pedir)($usuario)->assertCreated();
    ($this->pedir)($usuario, [
        'servidor_id' => $propio->id,
        'fecha'       => now()->next(Carbon::TUESDAY)->toDateString(),
    ])->assertCreated();

    expect(PermisoServidor::where('servidor_id', $propio->id)->count())->toBe(2);
});

test('un servidor no registra un permiso a nombre de otro', function () {
    $usuario = ($this->usuario)('servidor', ($this->servidor)());

    ($this->pedir)($usuario, ['servidor_id' => $this->otro->id])->assertForbidden();

    expect(PermisoServidor::where('servidor_id', $this->otro->id)->exists())->toBeFalse();
});

test('un jefe de unidad tampoco registra el de un subordinado', function () {
    $jefe = ($this->usuario)('jefe-unidad', ($this->servidor)(jefe: true));

    ($this->pedir)($jefe, ['servidor_id' => $this->otro->id])->assertForbidden();
});

test('ver los permisos de todos no es poder emitirlos', function (string $rol) {
    $usuario = ($this->usuario)($rol, ($this->servidor)());

    ($this->pedir)($usuario, ['servidor_id' => $this->otro->id])->assertForbidden();
})->with(['maxima-autoridad', 'auditor', 'recepcion']);

test('Talento Humano registra permisos a nombre de cualquier servidor', function (string $rol) {
    $usuario = ($this->usuario)($rol);

    $respuesta = ($this->pedir)($usuario, ['servidor_id' => $this->otro->id]);

    $respuesta->assertCreated();
    expect($respuesta->json('datos.servidor_id'))->toBe($this->otro->id)
        ->and($respuesta->json('datos.creado_por'))->toBe($usuario->id);
})->with(['admin-uath', 'asistente-uath']);

test('sin servidor vinculado ni permiso de Talento Humano no hay a nombre de quién registrar', function () {
    $sinServidor = ($this->usuario)('jefe-unidad');

    ($this->pedir)($sinServidor)->assertStatus(422);
});

test('la migración da el permiso a los roles de Talento Humano que ya existen, y a nadie más', function () {
    // Una base que ya estaba en producción: los roles existen pero sin el
    // permiso nuevo, que es lo que la migración tiene que resolver sin volver
    // a correr el seeder.
    foreach (['admin-uath', 'asistente-uath'] as $rol) {
        Role::findByName($rol, 'sanctum')->revokePermissionTo(Permiso::REGISTRAR_PERMISOS_SERVIDORES->value);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $migracion = require database_path('migrations/2026_09_10_160000_crear_permiso_registrar_permisos_servidores.php');
    $migracion->up();

    $tiene = fn (string $rol) => Role::findByName($rol, 'sanctum')
        ->hasPermissionTo(Permiso::REGISTRAR_PERMISOS_SERVIDORES->value);

    expect($tiene('admin-uath'))->toBeTrue()
        ->and($tiene('asistente-uath'))->toBeTrue()
        ->and($tiene('jefe-unidad'))->toBeFalse()
        ->and($tiene('maxima-autoridad'))->toBeFalse();
});
