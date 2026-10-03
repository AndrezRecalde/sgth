<?php

use App\Models\Dispensario\FichaSaludOcupacional;
use App\Models\Dispensario\SolicitudCertificacionMedica;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
| Lactancia es el quinto grupo de atención prioritaria del formato que imprime
| el Dispensario del GADPE (el impreso del MSP en blanco trae cuatro). Como
| «embarazada», solo aplica a pacientes mujeres.
*/

function medicoFemoLactancia(): User
{
    $usuario = User::factory()->create();
    $usuario->assignRole(
        Role::firstOrCreate(['name' => 'medico', 'guard_name' => 'sanctum'])
    );

    return $usuario;
}

function servidorFemoLactancia(string $cedula, ?string $genero): Servidor
{
    Servidor::unguard();

    return Servidor::create([
        'cedula' => $cedula,
        'nombre' => 'Paciente',
        'apellido' => 'Prueba',
        'genero' => $genero,
    ]);
}

function solicitudFemoLactancia(User $medico, Servidor $servidor): SolicitudCertificacionMedica
{
    return SolicitudCertificacionMedica::create([
        'tipo_evento' => 'periodica',
        'origen' => 'expediente',
        'servidor_id' => $servidor->id,
        'cedula_paciente' => $servidor->cedula,
        'nombres_paciente' => "{$servidor->nombre} {$servidor->apellido}",
        'solicitado_por' => $medico->id,
        'estado' => 'en_proceso',
        'fecha_limite' => now()->addDays(7),
    ]);
}

function fichaFemoLactancia(User $medico, Servidor $servidor, array $extra = []): array
{
    return [
        'solicitud_id' => solicitudFemoLactancia($medico, $servidor)->id,
        'ficha' => [
            'fecha_evaluacion' => '2026-10-01',
            'aptitud' => 'apto',
            ...$extra,
        ],
    ];
}

test('lactancia se guarda en la ficha', function () {
    $medico = medicoFemoLactancia();
    $paciente = servidorFemoLactancia('0804258986', 'femenino');

    $id = $this->actingAs($medico, 'sanctum')
        ->postJson('/api/v1/dispensario/fichas-sso', fichaFemoLactancia($medico, $paciente, ['grupo_lactancia' => true]))
        ->assertCreated()
        ->json('datos.id');

    expect(FichaSaludOcupacional::findOrFail($id)->grupo_lactancia)->toBeTrue();
});

test('un paciente hombre no puede registrarse en lactancia ni embarazado', function (string $campo) {
    $medico = medicoFemoLactancia();
    $paciente = servidorFemoLactancia('0802704171', 'masculino');

    $this->actingAs($medico, 'sanctum')
        ->postJson('/api/v1/dispensario/fichas-sso', fichaFemoLactancia($medico, $paciente, [$campo => true]))
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ["ficha.{$campo}"]]);

    expect(FichaSaludOcupacional::count())->toBe(0);
})->with(['grupo_lactancia', 'grupo_embarazada']);

test('al editar tampoco se marca lactancia a un hombre', function () {
    $medico = medicoFemoLactancia();
    $paciente = servidorFemoLactancia('0802704171', 'masculino');

    $id = $this->actingAs($medico, 'sanctum')
        ->postJson('/api/v1/dispensario/fichas-sso', fichaFemoLactancia($medico, $paciente))
        ->assertCreated()
        ->json('datos.id');

    $this->actingAs($medico, 'sanctum')
        ->patchJson("/api/v1/dispensario/fichas-sso/{$id}", [
            'ficha' => ['grupo_lactancia' => true],
        ])
        ->assertStatus(422);

    expect(FichaSaludOcupacional::findOrFail($id)->grupo_lactancia)->toBeFalse();
});

test('con el sexo sin registrar se acepta, como lo muestra el asistente', function () {
    $medico = medicoFemoLactancia();
    $paciente = servidorFemoLactancia('0804258986', null);

    $this->actingAs($medico, 'sanctum')
        ->postJson('/api/v1/dispensario/fichas-sso', fichaFemoLactancia($medico, $paciente, ['grupo_lactancia' => true]))
        ->assertCreated();
});
