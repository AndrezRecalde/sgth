<?php

/*
| El acto médico es del médico que evalúa (docs/plan-roles-y-accesos.md,
| PR 4; decisión 5a del 2026-10-25).
|
| - La administración del Dispensario, que no tiene permisos clínicos, podía
|   iniciar la FEMO, llenarla y emitir el dictamen de aptitud, y quedaba como
|   evaluadora. Ahora la consulta, nada más.
| - Cualquier médico podía continuar la ficha de un colega o cerrarle la
|   evaluación con su dictamen. Ahora la continúa y la cierra quien la creó.
| - El certificado de reposo se podía emitir sobre la consulta de otro.
| - El odontograma lo podían escribir el médico y la administración.
*/

use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\FichaSaludOcupacional;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Dispensario\SolicitudCertificacionMedica;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolPermisoSeeder::class);

    $this->conRol = function (string $rol): User {
        $usuario = User::factory()->create();
        $usuario->assignRole($rol);

        return $usuario;
    };

    $this->medico = ($this->conRol)('medico');
    $this->colega = ($this->conRol)('medico');
    $this->administracion = ($this->conRol)('admin-dispensario');

    $this->paciente = Servidor::forceCreate([
        'cedula' => '0808181801', 'nombre' => 'Rafael', 'apellido' => 'Quinde',
    ]);

    $this->solicitud = fn (string $estado = 'en_proceso') => SolicitudCertificacionMedica::create([
        'tipo_evento' => 'periodica',
        'origen' => 'expediente',
        'servidor_id' => $this->paciente->id,
        'cedula_paciente' => $this->paciente->cedula,
        'nombres_paciente' => 'Rafael Quinde',
        'solicitado_por' => $this->administracion->id,
        'estado' => $estado,
        'fecha_limite' => now()->addDays(7),
    ]);

    // La ficha que crea un médico al guardar el primer borrador.
    $this->fichaDe = function (User $medico, SolicitudCertificacionMedica $solicitud, array $ficha = []): int {
        return $this->actingAs($medico, 'sanctum')
            ->postJson('/api/v1/dispensario/fichas-sso', [
                'solicitud_id' => $solicitud->id,
                'ficha' => ['fecha_evaluacion' => '2026-10-20', ...$ficha],
            ])
            ->assertCreated()
            ->json('datos.id');
    };
});

// ── Salud ocupacional ───────────────────────────────────────────

it('la administración del Dispensario no inicia, no llena ni dictamina', function () {
    $pendiente = ($this->solicitud)('pendiente');
    $enCurso = ($this->solicitud)();

    $this->actingAs($this->administracion, 'sanctum');

    $this->patchJson("/api/v1/dispensario/solicitudes-certificacion/{$pendiente->id}/iniciar")->assertForbidden();
    $this->postJson('/api/v1/dispensario/fichas-sso', [
        'solicitud_id' => $enCurso->id,
        'ficha' => ['fecha_evaluacion' => '2026-10-20'],
    ])->assertForbidden();
    $this->patchJson("/api/v1/dispensario/solicitudes-certificacion/{$enCurso->id}/completar")->assertForbidden();

    expect($enCurso->fresh()->ficha_femo_id)->toBeNull();
});

it('la administración del Dispensario sí consulta las fichas y la bandeja', function () {
    $id = ($this->fichaDe)($this->medico, ($this->solicitud)());

    $this->actingAs($this->administracion, 'sanctum');

    $this->getJson("/api/v1/dispensario/fichas-sso/{$id}")->assertOk();
    $this->getJson('/api/v1/dispensario/fichas-sso')->assertOk();
    $this->getJson('/api/v1/dispensario/solicitudes-certificacion?estado=activas')->assertOk();
});

it('otro médico no continúa la ficha de un colega ni le emite el dictamen', function () {
    $solicitud = ($this->solicitud)();
    $id = ($this->fichaDe)($this->medico, $solicitud, ['aptitud' => 'apto']);

    $this->actingAs($this->colega, 'sanctum');

    $this->patchJson("/api/v1/dispensario/fichas-sso/{$id}", [
        'ficha' => ['aptitud' => 'no_apto', 'restricciones' => 'Cambiada por otro'],
    ])->assertForbidden();
    $this->patchJson("/api/v1/dispensario/solicitudes-certificacion/{$solicitud->id}/completar")
        ->assertForbidden();

    expect(FichaSaludOcupacional::findOrFail($id)->aptitud->value)->toBe('apto')
        ->and($solicitud->fresh()->estado)->toBe('en_proceso');
});

it('quien creó la ficha la continúa y emite el dictamen', function () {
    $solicitud = ($this->solicitud)();
    $id = ($this->fichaDe)($this->medico, $solicitud);

    $this->actingAs($this->medico, 'sanctum');

    $this->patchJson("/api/v1/dispensario/fichas-sso/{$id}", ['ficha' => ['aptitud' => 'apto']])->assertOk();
    $this->patchJson("/api/v1/dispensario/solicitudes-certificacion/{$solicitud->id}/completar")->assertOk();

    expect($solicitud->fresh()->estado)->toBe('completada');
});

it('la bandeja dice quién está llenando cada ficha', function () {
    $solicitud = ($this->solicitud)();
    ($this->fichaDe)($this->medico, $solicitud);

    $fila = $this->actingAs($this->colega, 'sanctum')
        ->getJson('/api/v1/dispensario/solicitudes-certificacion?estado=activas')
        ->assertOk()
        ->collect('datos.data')
        ->firstWhere('id', $solicitud->id);

    expect($fila['ficha_salud_ocupacional']['evaluador_id'])->toBe($this->medico->id);
});

// ── Certificado de reposo ───────────────────────────────────────

it('el certificado de reposo lo emite solo quien atendió la consulta', function () {
    $historia = HistoriaClinica::forceCreate(['servidor_id' => $this->paciente->id]);
    $consulta = ConsultaMedica::forceCreate([
        'especialidad' => 'medicina_general',
        'historia_clinica_id' => $historia->id,
        'medico_id' => $this->medico->id,
        'fecha_consulta' => now()->toDateString(),
        'hora_consulta' => '10:00:00',
        'motivo_consulta' => 'Dolor',
    ]);

    $certificado = ['consulta_medica_id' => $consulta->id, 'dias_reposo' => 1];

    $this->actingAs($this->colega, 'sanctum')
        ->postJson('/api/v1/dispensario/certificados-medicos', $certificado)
        ->assertForbidden();

    $this->actingAs($this->medico, 'sanctum')
        ->postJson('/api/v1/dispensario/certificados-medicos', $certificado)
        ->assertCreated();
});

// ── Odontograma ─────────────────────────────────────────────────

it('el odontograma lo escribe solo el odontólogo', function (string $rol) {
    $this->actingAs(($this->conRol)($rol), 'sanctum')
        ->postJson('/api/v1/dispensario/odontograma/procedimientos', [])
        ->assertForbidden();
})->with(['medico', 'admin-dispensario', 'enfermera']);

it('al odontólogo el odontograma le llega hasta la validación', function () {
    // Sin datos: un 422 prueba que el rol pasó y que quien responde es la
    // validación del procedimiento.
    $this->actingAs(($this->conRol)('odontologo'), 'sanctum')
        ->postJson('/api/v1/dispensario/odontograma/procedimientos', [])
        ->assertUnprocessable();
});
