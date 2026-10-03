<?php

use App\Models\Dispensario\FichaSaludOcupacional;
use App\Models\Dispensario\SolicitudCertificacionMedica;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
| Editar una ficha FEMO respondía 500 a todo el mundo: la validación del PATCH
| usaba el catálogo de factores del MSP sin importarlo. Ninguna prueba hacía
| esa petición por HTTP, así que nadie se enteró en un mes.
|
| Además, el asistente capturaba nueve campos que nunca llegaban al servidor.
| Lo que se fija aquí es que viajan de ida (POST) y de vuelta (PATCH).
*/

function medicoFemoEdicion(): User
{
    $usuario = User::factory()->create();
    $usuario->assignRole(
        Role::firstOrCreate(['name' => 'medico', 'guard_name' => 'sanctum'])
    );

    return $usuario;
}

function servidorFemoEdicion(string $cedula): Servidor
{
    Servidor::unguard();

    return Servidor::create([
        'cedula' => $cedula,
        'nombre' => 'Francis',
        'apellido' => 'Quinde',
    ]);
}

function solicitudFemoEdicion(User $medico, Servidor $servidor, string $tipo = 'retiro'): SolicitudCertificacionMedica
{
    return SolicitudCertificacionMedica::create([
        'tipo_evento' => $tipo,
        'origen' => 'expediente',
        'servidor_id' => $servidor->id,
        'cedula_paciente' => $servidor->cedula,
        'nombres_paciente' => "{$servidor->nombre} {$servidor->apellido}",
        'solicitado_por' => $medico->id,
        'estado' => 'en_proceso',
        'fecha_limite' => now()->addDays(7),
    ]);
}

/** El borrador de la ficha de una solicitud en curso, creado por HTTP. */
function borradorFemoEdicion($test, User $medico, SolicitudCertificacionMedica $solicitud): FichaSaludOcupacional
{
    $id = $test->actingAs($medico, 'sanctum')
        ->postJson('/api/v1/dispensario/fichas-sso', [
            'solicitud_id' => $solicitud->id,
            'ficha' => ['fecha_evaluacion' => '2026-10-01'],
        ])
        ->assertCreated()
        ->json('datos.id');

    return FichaSaludOcupacional::findOrFail($id);
}

/** Los campos que el asistente pedía y no enviaba. */
function camposAntesPerdidosFemo(): array
{
    return [
        'lateralidad' => 'izquierda',
        'grupo_enfermedad_catastrofica' => true,
        'grupo_adulto_mayor' => true,
        'autoriza_transfusion' => false,
        'tratamiento_hormonal' => true,
        'tratamiento_hormonal_cual' => 'Levotiroxina',
        // La solicitud de estas pruebas es de retiro: el último día laboral
        // aplica; la fecha de reintegro se prueba con su propio tipo en
        // FemoPuestoTest.
        'fecha_ultimo_dia_laboral' => '2026-09-30',
    ];
}

beforeEach(function () {
    $this->medico = medicoFemoEdicion();
    $this->servidor = servidorFemoEdicion('0804258986');
    $this->solicitud = solicitudFemoEdicion($this->medico, $this->servidor);
});

test('la ficha guarda los campos de A, B y C que antes se perdían', function () {
    $respuesta = $this->actingAs($this->medico, 'sanctum')
        ->postJson('/api/v1/dispensario/fichas-sso', [
            'solicitud_id' => $this->solicitud->id,
            'ficha' => [
                'fecha_evaluacion' => '2026-10-01',
                'aptitud' => 'apto',
                ...camposAntesPerdidosFemo(),
            ],
        ])
        ->assertCreated();

    $ficha = FichaSaludOcupacional::findOrFail($respuesta->json('datos.id'));

    expect($ficha->lateralidad)->toBe('izquierda')
        ->and($ficha->grupo_enfermedad_catastrofica)->toBeTrue()
        ->and($ficha->grupo_adulto_mayor)->toBeTrue()
        ->and($ficha->autoriza_transfusion)->toBeFalse()
        ->and($ficha->tratamiento_hormonal)->toBeTrue()
        ->and($ficha->tratamiento_hormonal_cual)->toBe('Levotiroxina')
        ->and($ficha->fecha_ultimo_dia_laboral->toDateString())->toBe('2026-09-30');
});

test('editar una ficha por HTTP funciona y guarda lo editado', function () {
    $ficha = borradorFemoEdicion($this, $this->medico, $this->solicitud);

    $this->actingAs($this->medico, 'sanctum')
        ->patchJson("/api/v1/dispensario/fichas-sso/{$ficha->id}", [
            'ficha' => [
                'enfermedad_actual' => 'Lumbalgia mecánica',
                ...camposAntesPerdidosFemo(),
            ],
            'factores_riesgo' => [
                ['categoria' => 'fisico', 'factor' => 'Ruido', 'presente' => true],
            ],
            'antecedente_reproductivo' => null,
            'antecedentes' => null,
        ])
        ->assertOk();

    $ficha->refresh();

    expect($ficha->enfermedad_actual)->toBe('Lumbalgia mecánica')
        ->and($ficha->lateralidad)->toBe('izquierda')
        ->and($ficha->tratamiento_hormonal_cual)->toBe('Levotiroxina')
        ->and($ficha->factoresRiesgo()->count())->toBe(1);
});

test('vaciar el bloque reproductivo al editar lo borra en vez de dar 500', function () {
    $ficha = borradorFemoEdicion($this, $this->medico, $this->solicitud);
    $ficha->antecedenteReproductivo()->create(['gestas' => 2]);

    $this->actingAs($this->medico, 'sanctum')
        ->patchJson("/api/v1/dispensario/fichas-sso/{$ficha->id}", [
            'ficha' => ['aptitud' => 'apto'],
            'antecedente_reproductivo' => null,
        ])
        ->assertOk();

    expect($ficha->antecedenteReproductivo()->exists())->toBeFalse();
});

test('editar una ficha no la cambia de persona', function () {
    $otro = servidorFemoEdicion('0802704171');
    $ficha = borradorFemoEdicion($this, $this->medico, $this->solicitud);

    $this->actingAs($this->medico, 'sanctum')
        ->patchJson("/api/v1/dispensario/fichas-sso/{$ficha->id}", [
            'ficha' => ['servidor_id' => $otro->id, 'postulante_id' => null],
        ])
        ->assertOk();

    expect($ficha->refresh()->servidor_id)->toBe($this->servidor->id);
});
