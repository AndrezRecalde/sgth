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
| Quién puede hacer qué con una solicitud de certificación médica.
|
| Las cinco rutas vivían en un solo grupo con un solo `role:`, y de ahí salían
| dos agujeros opuestos: `asistente-uath` —que lleva el permiso con el que el
| menú pinta «Certificaciones médicas»— no entraba a leer, y Talento Humano sí
| podía cerrar una solicitud emitiendo el dictamen de aptitud.
*/

/** Un usuario con el rol pedido y, si se indican, esos permisos. */
function usuarioDeRolCertificacion(string $rol, array $permisos = []): User
{
    $role = Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum']);

    foreach ($permisos as $permiso) {
        $role->givePermissionTo(
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'sanctum'])
        );
    }

    $usuario = User::factory()->create();
    $usuario->assignRole($role);

    return $usuario;
}

function servidorDePruebaCertificacion(string $cedula): Servidor
{
    return Servidor::create([
        'cedula'          => $cedula,
        'nombre'          => 'Lucía',
        'apellido'        => 'Quiñónez',
        'regimen_laboral' => 'losep',
        'estado'          => true,
    ]);
}

/** `solicitado_por` no admite nulos: toda solicitud nace de alguien de RRHH. */
function solicitudDePruebaCertificacion(User $solicitante): SolicitudCertificacionMedica
{
    $servidor = servidorDePruebaCertificacion('0802233441');

    return SolicitudCertificacionMedica::create([
        'tipo_evento'      => 'periodica',
        'origen'           => 'expediente',
        'servidor_id'      => $servidor->id,
        'cedula_paciente'  => $servidor->cedula,
        'nombres_paciente' => trim("{$servidor->nombre} {$servidor->apellido}"),
        'solicitado_por'   => $solicitante->id,
        'estado'           => 'pendiente',
        'fecha_limite'     => now()->addDays(7),
    ]);
}

test('asistente-uath puede consultar el seguimiento de certificaciones', function () {
    $usuario = usuarioDeRolCertificacion('asistente-uath', [
        Permiso::SOLICITAR_CERTIFICACION_MEDICA->value,
    ]);

    solicitudDePruebaCertificacion($usuario);

    $this->actingAs($usuario, 'sanctum')
        ->getJson('/api/v1/dispensario/solicitudes-certificacion')
        ->assertStatus(200);
});

test('asistente-uath puede solicitar certificaciones en lote desde Expediente', function () {
    $usuario = usuarioDeRolCertificacion('asistente-uath', [
        Permiso::SOLICITAR_CERTIFICACION_MEDICA->value,
    ]);

    $servidor = servidorDePruebaCertificacion('0802233442');

    $this->actingAs($usuario, 'sanctum')
        ->postJson('/api/v1/dispensario/solicitudes-certificacion/lote', [
            'servidor_ids' => [$servidor->id],
            'tipo_evento'  => 'periodica',
        ])
        ->assertStatus(200);

    expect(SolicitudCertificacionMedica::where('servidor_id', $servidor->id)->exists())
        ->toBeTrue();
});

test('el médico sigue leyendo el listado que trabaja', function () {
    $usuario = usuarioDeRolCertificacion('medico');

    solicitudDePruebaCertificacion($usuario);

    $this->actingAs($usuario, 'sanctum')
        ->getJson('/api/v1/dispensario/solicitudes-certificacion')
        ->assertStatus(200);
});

test('Talento Humano no puede emitir el dictamen de aptitud', function () {
    $usuario = usuarioDeRolCertificacion('admin-uath');
    $solicitud = solicitudDePruebaCertificacion($usuario);

    $this->actingAs($usuario, 'sanctum')
        ->patchJson(
            "/api/v1/dispensario/solicitudes-certificacion/{$solicitud->id}/completar",
            ['dictamen' => 'apto']
        )
        ->assertStatus(403);

    expect($solicitud->fresh()->estado)->toBe('pendiente');
});

test('Talento Humano no puede iniciar el FEMO', function () {
    $usuario = usuarioDeRolCertificacion('admin-uath');
    $solicitud = solicitudDePruebaCertificacion($usuario);

    $this->actingAs($usuario, 'sanctum')
        ->patchJson("/api/v1/dispensario/solicitudes-certificacion/{$solicitud->id}/iniciar")
        ->assertStatus(403);
});

test('un rol ajeno al trámite no lee el listado', function () {
    $usuario = usuarioDeRolCertificacion('jefe-unidad');

    $this->actingAs($usuario, 'sanctum')
        ->getJson('/api/v1/dispensario/solicitudes-certificacion')
        ->assertStatus(403);
});
