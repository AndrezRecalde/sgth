<?php

use App\Enums\Permiso;
use App\Models\Dispensario\SolicitudCertificacionMedica;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
| Retirar una solicitud de certificación médica pedida por error.
|
| El enum de `estado` admitía `cancelada` desde que se creó la tabla y nada en
| la aplicación la escribía: los selectores de estado ofrecían un filtro de un
| estado imposible, y un lote mal lanzado no tenía vuelta atrás.
*/

function usuarioRrhhCancelacion(bool $conPermiso = true): User
{
    $rol = Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);

    if ($conPermiso) {
        $rol->givePermissionTo(Permission::firstOrCreate([
            'name' => Permiso::SOLICITAR_CERTIFICACION_MEDICA->value,
            'guard_name' => 'sanctum',
        ]));
    }

    $usuario = User::factory()->create();
    $usuario->assignRole($rol);

    return $usuario;
}

function servidorCancelacion(string $cedula): Servidor
{
    return Servidor::create([
        'cedula' => $cedula,
        'nombre' => 'Jorge',
        'apellido' => 'Castillo',
        'regimen_laboral' => 'losep',
        'estado' => true,
    ]);
}

function solicitudCancelacion(User $solicitante, Servidor $servidor, string $estado = 'pendiente'): SolicitudCertificacionMedica
{
    return SolicitudCertificacionMedica::create([
        'tipo_evento' => 'periodica',
        'origen' => 'expediente',
        'servidor_id' => $servidor->id,
        'cedula_paciente' => $servidor->cedula,
        'nombres_paciente' => trim("{$servidor->nombre} {$servidor->apellido}"),
        'solicitado_por' => $solicitante->id,
        'estado' => $estado,
        'fecha_limite' => now()->addDays(7),
    ]);
}

test('una solicitud pendiente se cancela con motivo y deja rastro de quién y cuándo', function () {
    $usuario = usuarioRrhhCancelacion();
    $solicitud = solicitudCancelacion($usuario, servidorCancelacion('0803344551'));

    $this->actingAs($usuario, 'sanctum')
        ->patchJson(
            "/api/v1/dispensario/solicitudes-certificacion/{$solicitud->id}/cancelar",
            ['motivo' => 'Lote lanzado con el tipo de evento equivocado.']
        )
        ->assertStatus(200);

    $solicitud->refresh();

    expect($solicitud->estado)->toBe('cancelada')
        ->and($solicitud->motivo_cancelacion)->toBe('Lote lanzado con el tipo de evento equivocado.')
        ->and($solicitud->cancelada_por)->toBe($usuario->id)
        ->and($solicitud->cancelada_en)->not->toBeNull();
});

// El manejador del proyecto envuelve los errores de validación en `errores`
// (ver bootstrap/app.php), no en el `errors` que mira assertJsonValidationErrors.
test('el motivo es obligatorio y no vale una palabra suelta', function () {
    $usuario = usuarioRrhhCancelacion();
    $solicitud = solicitudCancelacion($usuario, servidorCancelacion('0803344552'));

    $this->actingAs($usuario, 'sanctum')
        ->patchJson("/api/v1/dispensario/solicitudes-certificacion/{$solicitud->id}/cancelar", [])
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['motivo']]);

    $this->actingAs($usuario, 'sanctum')
        ->patchJson(
            "/api/v1/dispensario/solicitudes-certificacion/{$solicitud->id}/cancelar",
            ['motivo' => 'eh']
        )
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['motivo']]);

    expect($solicitud->fresh()->estado)->toBe('pendiente');
});

test('una evaluación ya iniciada no se cancela desde Talento Humano', function () {
    $usuario = usuarioRrhhCancelacion();
    $solicitud = solicitudCancelacion(
        $usuario, servidorCancelacion('0803344553'), 'en_proceso'
    );

    $this->actingAs($usuario, 'sanctum')
        ->patchJson(
            "/api/v1/dispensario/solicitudes-certificacion/{$solicitud->id}/cancelar",
            ['motivo' => 'Se pidió por error, el servidor ya cesó funciones.']
        )
        ->assertStatus(422);

    expect($solicitud->fresh()->estado)->toBe('en_proceso');
});

test('una solicitud ya completada no se cancela: su dictamen ya existe', function () {
    $usuario = usuarioRrhhCancelacion();
    $solicitud = solicitudCancelacion(
        $usuario, servidorCancelacion('0803344554'), 'completada'
    );

    $this->actingAs($usuario, 'sanctum')
        ->patchJson(
            "/api/v1/dispensario/solicitudes-certificacion/{$solicitud->id}/cancelar",
            ['motivo' => 'Cambio de criterio sobre la periódica de este año.']
        )
        ->assertStatus(422);

    expect($solicitud->fresh()->estado)->toBe('completada');
});

test('sin el permiso de solicitar certificaciones no se puede cancelar', function () {
    $usuario = usuarioRrhhCancelacion(conPermiso: false);
    $solicitud = solicitudCancelacion($usuario, servidorCancelacion('0803344555'));

    $this->actingAs($usuario, 'sanctum')
        ->patchJson(
            "/api/v1/dispensario/solicitudes-certificacion/{$solicitud->id}/cancelar",
            ['motivo' => 'Prueba de que el permiso hace falta.']
        )
        ->assertStatus(403);

    expect($solicitud->fresh()->estado)->toBe('pendiente');
});

test('el médico no cancela: el trámite no es suyo', function () {
    $rrhh = usuarioRrhhCancelacion();
    $solicitud = solicitudCancelacion($rrhh, servidorCancelacion('0803344556'));

    $medico = User::factory()->create();
    $medico->assignRole(Role::firstOrCreate(['name' => 'medico', 'guard_name' => 'sanctum']));

    $this->actingAs($medico, 'sanctum')
        ->patchJson(
            "/api/v1/dispensario/solicitudes-certificacion/{$solicitud->id}/cancelar",
            ['motivo' => 'El médico intenta retirar una solicitud de RRHH.']
        )
        ->assertStatus(403);

    expect($solicitud->fresh()->estado)->toBe('pendiente');
});

test('tras cancelar, el servidor vuelve a admitir una solicitud nueva', function () {
    $usuario = usuarioRrhhCancelacion();
    $servidor = servidorCancelacion('0803344557');
    $solicitud = solicitudCancelacion($usuario, $servidor);

    // Con la solicitud viva, el lote lo omite por tener una activa.
    $this->actingAs($usuario, 'sanctum')
        ->postJson('/api/v1/dispensario/solicitudes-certificacion/lote', [
            'servidor_ids' => [$servidor->id],
            'tipo_evento' => 'retiro',
        ])
        ->assertStatus(200)
        ->assertJsonPath('datos.omitidas.0.motivo', 'Ya tiene una solicitud activa.');

    $this->actingAs($usuario, 'sanctum')
        ->patchJson(
            "/api/v1/dispensario/solicitudes-certificacion/{$solicitud->id}/cancelar",
            ['motivo' => 'El tipo de evento correcto era retiro, no periódica.']
        )
        ->assertStatus(200);

    $this->actingAs($usuario, 'sanctum')
        ->postJson('/api/v1/dispensario/solicitudes-certificacion/lote', [
            'servidor_ids' => [$servidor->id],
            'tipo_evento' => 'retiro',
        ])
        ->assertStatus(200)
        ->assertJsonCount(1, 'datos.creadas');

    expect(
        SolicitudCertificacionMedica::where('servidor_id', $servidor->id)->count()
    )->toBe(2);
});

test('el lote omite a un servidor inactivo', function () {
    $usuario = usuarioRrhhCancelacion();
    $activo = servidorCancelacion('0803344561');
    $inactivo = servidorCancelacion('0803344562');
    $inactivo->update(['estado' => false]);

    $this->actingAs($usuario, 'sanctum')
        ->postJson('/api/v1/dispensario/solicitudes-certificacion/lote', [
            'servidor_ids' => [$activo->id, $inactivo->id],
            'tipo_evento' => 'periodica',
        ])
        ->assertStatus(200)
        ->assertJsonCount(1, 'datos.creadas')
        ->assertJsonPath('datos.omitidas.0.servidor_id', $inactivo->id)
        ->assertJsonPath('datos.omitidas.0.motivo', 'El servidor no está activo.');
});
