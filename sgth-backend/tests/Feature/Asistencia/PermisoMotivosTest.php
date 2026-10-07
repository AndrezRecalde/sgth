<?php

/*
| Los motivos de rechazar, anular y revertir (re-auditoría 2026-10-07).
|
| Revertir guardaba su motivo en `motivo_rechazo`: un permiso de vuelta en
| pendiente aparecía como rechazado, y de quién lo revirtió no quedaba nada.
| Ahora tiene sus propias columnas, y el titular ve en «Mis permisos» por qué
| le rechazaron el suyo.
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
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();
    PermisoServidor::unguard();

    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $this->unidad = unidadDePrueba(['nombre' => 'Dirección de Motivos']);
    $this->servidor = Servidor::create([
        'cedula'                   => '0803000001',
        'nombre'                   => 'Marta',
        'apellido'                 => 'Motivo',
        'puesto_id'                => puestoDePrueba($this->unidad)->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'regimen_laboral'          => RegimenLaboral::LOSEP,
        'estado'                   => true,
    ]);

    $this->uath = usuarioMotivos('admin-uath');
});

function usuarioMotivos(string $rol, ?Servidor $servidor = null): User
{
    $usuario = User::create([
        'email'        => uniqid('motivo').'@example.com',
        'usuario_ti'   => uniqid('mot'),
        'password'     => bcrypt('123456'),
        'primer_login' => false,
        'servidor_id'  => $servidor?->id,
    ]);
    $usuario->assignRole($rol);

    return $usuario;
}

/** Oficial: no descuenta vacaciones, así que revertir no necesita períodos. */
function permisoMotivos(Servidor $servidor, string $folio, EstadoPermiso $estado, array $extra = []): PermisoServidor
{
    return PermisoServidor::create(array_merge([
        'servidor_id'              => $servidor->id,
        'unidad_administrativa_id' => $servidor->unidad_administrativa_id,
        'tipo'                     => TipoPermiso::OFICIAL->value,
        'fecha'                    => now()->toDateString(),
        'hora_inicio'              => '08:00',
        'hora_fin'                 => '10:00',
        'observacion'              => 'Diligencia',
        'estado'                   => $estado->value,
        'folio'                    => $folio,
        'vence_en'                 => now()->addDays(3),
    ], $extra));
}

test('revertir guarda quién, cuándo y por qué, sin tocar el motivo de rechazo', function () {
    $permiso = permisoMotivos($this->servidor, 'PER-2026-80001', EstadoPermiso::ACTIVO);

    $this->actingAs($this->uath, 'sanctum')
        ->postJson("/api/v1/asistencia/permisos/{$permiso->id}/revertir-confirmacion", [
            'motivo' => 'Se confirmó el folio equivocado.',
        ])->assertOk();

    $permiso->refresh();

    expect($permiso->estado)->toBe(EstadoPermiso::PENDIENTE)
        ->and($permiso->motivo_reversion)->toBe('Se confirmó el folio equivocado.')
        ->and($permiso->revertido_por)->toBe($this->uath->id)
        ->and($permiso->revertido_en)->not->toBeNull()
        ->and($permiso->motivo_rechazo)->toBeNull();
});

test('el titular ve en «Mis permisos» por qué se lo rechazaron', function () {
    permisoMotivos($this->servidor, 'PER-2026-80002', EstadoPermiso::RECHAZADO, [
        'motivo_rechazo' => 'Llegó sin la firma del jefe inmediato.',
    ]);

    $this->actingAs(usuarioMotivos('servidor', $this->servidor), 'sanctum')
        ->getJson('/api/v1/autoservicio/mis-permisos')
        ->assertOk()
        ->assertJsonPath('datos.data.0.motivo_rechazo', 'Llegó sin la firma del jefe inmediato.');
});

test('la migración traslada las reversiones antiguas y deja los rechazos', function () {
    $migracion = require database_path(
        'migrations/2026_10_07_100000_separar_reversion_de_rechazo_en_permisos_servidor.php'
    );

    // La base como estaba antes: sin las columnas nuevas.
    $migracion->down();

    $revertido = permisoMotivos($this->servidor, 'PER-2026-80003', EstadoPermiso::PENDIENTE);
    $rechazado = permisoMotivos($this->servidor, 'PER-2026-80004', EstadoPermiso::RECHAZADO);
    DB::table('permisos_servidor')->where('id', $revertido->id)->update(['motivo_rechazo' => 'Folio equivocado.']);
    DB::table('permisos_servidor')->where('id', $rechazado->id)->update(['motivo_rechazo' => 'Sin firma.']);

    $migracion->up();

    $filaRevertida = DB::table('permisos_servidor')->find($revertido->id);
    $filaRechazada = DB::table('permisos_servidor')->find($rechazado->id);

    expect($filaRevertida->motivo_reversion)->toBe('Folio equivocado.')
        ->and($filaRevertida->motivo_rechazo)->toBeNull()
        ->and($filaRechazada->motivo_rechazo)->toBe('Sin firma.')
        ->and($filaRechazada->motivo_reversion)->toBeNull();
});
