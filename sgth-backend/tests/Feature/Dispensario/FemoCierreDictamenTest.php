<?php

use App\Models\Dispensario\FichaSaludOcupacional;
use App\Models\Dispensario\SolicitudCertificacionMedica;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
| El cierre de una evaluación médica ocupacional.
|
| Antes el médico elegía la aptitud en la ficha (cuatro valores) y después un
| dictamen en otro modal (tres valores), que podían contradecirse. Cerrar el
| modal dejaba la ficha suelta y «Continuar FEMO» creaba otra. Y `completar`
| aceptaba cualquier estado y la ficha de cualquier persona.
|
| Ahora: la ficha es el borrador de su solicitud, el dictamen ES la aptitud de
| la ficha, y emitirlo cierra la ficha.
*/

function medicoCierreFemo(): User
{
    $usuario = User::factory()->create();
    $usuario->assignRole(
        Role::firstOrCreate(['name' => 'medico', 'guard_name' => 'sanctum'])
    );

    return $usuario;
}

function solicitudCierreFemo(User $medico, string $cedula, string $estado = 'en_proceso'): SolicitudCertificacionMedica
{
    Servidor::unguard();
    $servidor = Servidor::create([
        'cedula' => $cedula, 'nombre' => 'Rafael', 'apellido' => 'Quinde',
    ]);

    return SolicitudCertificacionMedica::create([
        'tipo_evento' => 'retiro',
        'origen' => 'expediente',
        'servidor_id' => $servidor->id,
        'cedula_paciente' => $cedula,
        'nombres_paciente' => 'Rafael Quinde',
        'solicitado_por' => $medico->id,
        'estado' => $estado,
        'fecha_limite' => now()->addDays(7),
    ]);
}

function guardarFichaCierreFemo($test, User $medico, SolicitudCertificacionMedica $solicitud, array $ficha = [])
{
    return $test->actingAs($medico, 'sanctum')
        ->postJson('/api/v1/dispensario/fichas-sso', [
            'solicitud_id' => $solicitud->id,
            'ficha' => ['fecha_evaluacion' => '2026-10-01', ...$ficha],
        ]);
}

function completarCierreFemo($test, User $medico, SolicitudCertificacionMedica $solicitud)
{
    return $test->actingAs($medico, 'sanctum')
        ->patchJson("/api/v1/dispensario/solicitudes-certificacion/{$solicitud->id}/completar");
}

beforeEach(function () {
    $this->medico = medicoCierreFemo();
    $this->solicitud = solicitudCierreFemo($this->medico, '0804258986');
});

test('guardar la ficha la enlaza a su solicitud como borrador', function () {
    $id = guardarFichaCierreFemo($this, $this->medico, $this->solicitud)
        ->assertCreated()
        ->json('datos.id');

    $solicitud = $this->solicitud->fresh();
    expect($solicitud->ficha_femo_id)->toBe($id)
        ->and($solicitud->estado)->toBe('en_proceso')
        ->and(FichaSaludOcupacional::findOrFail($id)->aptitud)->toBeNull();
});

test('la persona y el tipo de evaluación los fija la solicitud', function () {
    $otra = solicitudCierreFemo($this->medico, '0802704171', 'pendiente');

    $id = guardarFichaCierreFemo($this, $this->medico, $this->solicitud, [
        'servidor_id' => $otra->servidor_id,
        'tipo_ficha' => 'ingreso',
    ])->assertCreated()->json('datos.id');

    $ficha = FichaSaludOcupacional::findOrFail($id);
    expect($ficha->servidor_id)->toBe($this->solicitud->servidor_id)
        ->and($ficha->tipo_ficha->value)->toBe('retiro');
});

test('una solicitud no tiene dos fichas', function () {
    guardarFichaCierreFemo($this, $this->medico, $this->solicitud)->assertCreated();

    guardarFichaCierreFemo($this, $this->medico, $this->solicitud)
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['solicitud_id']]);

    expect(FichaSaludOcupacional::count())->toBe(1);
});

test('no se registra la ficha de una solicitud que no está en curso', function (string $estado) {
    $solicitud = solicitudCierreFemo($this->medico, '0802704171', $estado);

    guardarFichaCierreFemo($this, $this->medico, $solicitud)
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['solicitud_id']]);
})->with(['pendiente', 'completada', 'cancelada']);

test('el dictamen es la aptitud de la ficha y su observación las restricciones', function (string $aptitud) {
    guardarFichaCierreFemo($this, $this->medico, $this->solicitud, [
        'aptitud' => $aptitud,
        'restricciones' => 'No levantar más de 10 kg.',
    ])->assertCreated();

    completarCierreFemo($this, $this->medico, $this->solicitud)->assertOk();

    $solicitud = $this->solicitud->fresh();
    expect($solicitud->estado)->toBe('completada')
        ->and($solicitud->dictamen)->toBe($aptitud)
        ->and($solicitud->observacion_medica)->toBe('No levantar más de 10 kg.');
})->with(['apto', 'apto_con_restricciones', 'en_observacion', 'no_apto']);

test('sin aptitud elegida no se emite el dictamen', function () {
    guardarFichaCierreFemo($this, $this->medico, $this->solicitud)->assertCreated();

    completarCierreFemo($this, $this->medico, $this->solicitud)
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['aptitud']]);

    expect($this->solicitud->fresh()->estado)->toBe('en_proceso');
});

test('con restricciones o no apto hay que describir el motivo', function (string $aptitud) {
    guardarFichaCierreFemo($this, $this->medico, $this->solicitud, ['aptitud' => $aptitud])
        ->assertCreated();

    completarCierreFemo($this, $this->medico, $this->solicitud)
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['restricciones']]);
})->with(['apto_con_restricciones', 'no_apto']);

test('sin ficha guardada no se emite el dictamen', function () {
    completarCierreFemo($this, $this->medico, $this->solicitud)->assertStatus(422);

    expect($this->solicitud->fresh()->estado)->toBe('en_proceso');
});

test('no se completa una solicitud que no está en curso', function (string $estado) {
    $solicitud = solicitudCierreFemo($this->medico, '0802704171', $estado);

    completarCierreFemo($this, $this->medico, $solicitud)->assertStatus(422);

    expect($solicitud->fresh()->estado)->toBe($estado);
})->with(['pendiente', 'completada', 'cancelada']);

test('emitido el dictamen, la ficha ya no se edita', function () {
    $id = guardarFichaCierreFemo($this, $this->medico, $this->solicitud, ['aptitud' => 'no_apto', 'restricciones' => 'Hipoacusia severa.'])
        ->assertCreated()
        ->json('datos.id');
    completarCierreFemo($this, $this->medico, $this->solicitud)->assertOk();

    $this->actingAs($this->medico, 'sanctum')
        ->patchJson("/api/v1/dispensario/fichas-sso/{$id}", ['ficha' => ['aptitud' => 'apto']])
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['ficha']]);

    expect(FichaSaludOcupacional::findOrFail($id)->aptitud->value)->toBe('no_apto');
});

test('el detalle de la ficha dice a qué solicitud pertenece y en qué estado está', function () {
    $id = guardarFichaCierreFemo($this, $this->medico, $this->solicitud)->json('datos.id');

    $this->actingAs($this->medico, 'sanctum')
        ->getJson("/api/v1/dispensario/fichas-sso/{$id}")
        ->assertOk()
        ->assertJsonPath('datos.solicitud.id', $this->solicitud->id)
        ->assertJsonPath('datos.solicitud.estado', 'en_proceso');
});
