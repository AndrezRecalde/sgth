<?php

use App\Enums\RegimenLaboral;
use App\Models\Dispensario\AgendaMedica;
use App\Models\Dispensario\Triaje;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
 * Estados del turno y del triaje: lo que antes se decidía mirando solo si el
 * turno estaba `atendido`, o no se miraba en absoluto.
 */

beforeEach(function () {
    $this->enfermera = User::forceCreate([
        'email' => 'enf@example.com', 'usuario_ti' => 'enf',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $this->enfermera->assignRole(Role::firstOrCreate(['name' => 'enfermera', 'guard_name' => 'sanctum']));

    $this->medico = User::forceCreate([
        'email' => 'med@example.com', 'usuario_ti' => 'med',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $this->medico->assignRole(Role::firstOrCreate(['name' => 'medico', 'guard_name' => 'sanctum']));

    $unidad = unidadDePrueba(['nombre' => 'Dirección Turnos']);
    $this->paciente = Servidor::forceCreate([
        'cedula' => '0801111111', 'nombre' => 'Ana', 'apellido' => 'Paz',
        'puesto_id' => puestoDePrueba($unidad, 'Analista Turnos')->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(2),
        'estado' => true,
    ]);

    $this->turno = fn (array $atributos = []) => AgendaMedica::forceCreate([
        'folio' => 'TUR-T-' . uniqid(),
        'servidor_id' => $this->paciente->id,
        'medico_id' => $this->medico->id,
        'tipo_atencion' => 'medicina_general',
        'fecha' => now()->toDateString(),
        'estado' => 'en_espera',
        'requiere_triaje' => true,
        'registrado_en' => now(),
        'estado_registro' => true,
        ...$atributos,
    ]);
});

function signosDeTriajeNormales(): array
{
    return [
        'presion_sistolica' => 120, 'presion_diastolica' => 75,
        'frecuencia_cardiaca' => 70, 'frecuencia_respiratoria' => 16,
        'temperatura_c' => 36.5, 'saturacion_oxigeno' => 98,
        'peso_kg' => 70, 'talla_cm' => 170,
    ];
}

test('no se toma el triaje de un turno cerrado', function (string $estado) {
    $this->actingAs($this->enfermera, 'sanctum');
    $turno = ($this->turno)(['estado' => $estado]);

    $this->postJson("/api/v1/dispensario/agenda/{$turno->id}/triaje", signosDeTriajeNormales())
        ->assertUnprocessable();

    expect(Triaje::count())->toBe(0);
    // Y sobre todo, el turno no resucita a «en sala».
    expect($turno->fresh()->estado)->toBe($estado);
})->with(['cancelada', 'atendido', 'no_presentado', 'en_consulta']);

test('no se toma el triaje de un turno de otro día', function () {
    $this->actingAs($this->enfermera, 'sanctum');
    $turno = ($this->turno)(['fecha' => now()->subDay()->toDateString()]);

    $this->postJson("/api/v1/dispensario/agenda/{$turno->id}/triaje", signosDeTriajeNormales())
        ->assertUnprocessable();
});

test('pendientes de triaje solo trae los turnos de hoy', function () {
    $this->actingAs($this->enfermera, 'sanctum');
    $hoy  = ($this->turno)();
    $ayer = ($this->turno)(['fecha' => now()->subDay()->toDateString()]);

    $ids = collect($this->getJson('/api/v1/dispensario/triaje/pendientes')->assertOk()->json('datos'))
        ->pluck('id');

    expect($ids)->toContain($hoy->id)->not->toContain($ayer->id);
});

test('reactivar respeta el tope de seis horas', function () {
    $this->actingAs($this->medico, 'sanctum');
    $reciente = ($this->turno)(['estado' => 'no_presentado', 'marcado_no_presentado_en' => now()->subHour()]);
    $viejo    = ($this->turno)(['estado' => 'no_presentado', 'marcado_no_presentado_en' => now()->subHours(8)]);

    $this->patchJson("/api/v1/dispensario/agenda/{$reciente->id}/reactivar")->assertOk();
    // Antes pasaba: con Carbon 3 la diferencia salía negativa.
    $this->patchJson("/api/v1/dispensario/agenda/{$viejo->id}/reactivar")->assertUnprocessable();

    expect($reciente->fresh()->estado)->toBe('en_espera');
    expect($viejo->fresh()->estado)->toBe('no_presentado');
});

test('un turno de otro día no se reactiva aunque se haya marcado hace poco', function () {
    $this->actingAs($this->medico, 'sanctum');
    $turno = ($this->turno)([
        'fecha' => now()->subDay()->toDateString(),
        'estado' => 'no_presentado', 'marcado_no_presentado_en' => now()->subMinutes(30),
    ]);

    $this->patchJson("/api/v1/dispensario/agenda/{$turno->id}/reactivar")->assertUnprocessable();
});

test('cancelar y no presentado solo salen de un turno abierto', function () {
    $this->actingAs($this->medico, 'sanctum');
    $cancelado = ($this->turno)(['estado' => 'cancelada']);

    // La cadena que resucitaba un turno cancelado: no presentado → reactivar.
    $this->patchJson("/api/v1/dispensario/agenda/{$cancelado->id}/no-presentado")->assertUnprocessable();
    $this->deleteJson("/api/v1/dispensario/agenda/{$cancelado->id}")->assertUnprocessable();

    $abierto = ($this->turno)();
    $this->deleteJson("/api/v1/dispensario/agenda/{$abierto->id}")->assertOk();
    expect($abierto->fresh()->estado)->toBe('cancelada');
});

test('abrir la ficha pasa el turno a consulta, y solo desde uno abierto', function () {
    $this->actingAs($this->medico, 'sanctum');
    $turno = ($this->turno)(['estado' => 'en_sala']);

    // La ruta existía, pero apuntaba a un método que el controlador no tenía.
    $this->patchJson("/api/v1/dispensario/agenda/{$turno->id}/en-consulta")->assertOk();
    expect($turno->fresh()->estado)->toBe('en_consulta');

    // Volver a abrirla no falla.
    $this->patchJson("/api/v1/dispensario/agenda/{$turno->id}/en-consulta")->assertOk();

    $atendido = ($this->turno)(['estado' => 'atendido']);
    $this->patchJson("/api/v1/dispensario/agenda/{$atendido->id}/en-consulta")->assertUnprocessable();
});

test('el turno ya no se edita por PUT', function () {
    $this->actingAs($this->enfermera, 'sanctum');
    $turno = ($this->turno)(['estado' => 'atendido']);

    $this->putJson("/api/v1/dispensario/agenda/{$turno->id}", ['estado' => 'en_espera'])
        ->assertStatus(405);

    expect($turno->fresh()->estado)->toBe('atendido');
});

test('un paciente no tiene dos turnos abiertos en la misma cola el mismo día', function () {
    $this->actingAs($this->enfermera, 'sanctum');
    $previo = ($this->turno)();

    $this->postJson('/api/v1/dispensario/agenda', [
        'servidor_id' => $this->paciente->id,
        'medico_id' => $this->medico->id,
        'tipo_atencion' => 'medicina_general',
    ])->assertUnprocessable()
      ->assertJsonFragment(['mensaje' => "El paciente ya tiene el turno {$previo->folio} abierto hoy en esta cola."]);
});

test('el turno recién creado vuelve con su paciente', function () {
    $this->actingAs($this->enfermera, 'sanctum');

    $datos = $this->postJson('/api/v1/dispensario/agenda', [
        'servidor_id' => $this->paciente->id,
        'medico_id' => $this->medico->id,
        'tipo_atencion' => 'medicina_general',
    ])->assertCreated()->json('datos');

    // Lo usa la pantalla del triaje inmediato para enseñar el nombre.
    expect($datos['servidor']['nombre'])->toBe('Ana');
});

test('la cola va del que llegó primero al último', function () {
    $this->actingAs($this->enfermera, 'sanctum');
    $primero = ($this->turno)(['registrado_en' => now()->subHour()]);
    $segundo = ($this->turno)(['registrado_en' => now()]);

    $ids = collect($this->getJson('/api/v1/dispensario/agenda')->assertOk()->json('datos.data'))->pluck('id')->all();

    expect($ids)->toBe([$primero->id, $segundo->id]);
});

test('el cierre diario pasa a no presentado los turnos de días anteriores que quedaron esperando', function () {
    $ayer = now()->subDay()->toDateString();
    $enEspera   = ($this->turno)(['fecha' => $ayer, 'estado' => 'en_espera']);
    $enSala     = ($this->turno)(['fecha' => $ayer, 'estado' => 'en_sala']);
    // Puede tener una consulta en borrador: no se toca.
    $enConsulta = ($this->turno)(['fecha' => $ayer, 'estado' => 'en_consulta']);
    $atendido   = ($this->turno)(['fecha' => $ayer, 'estado' => 'atendido']);
    $deHoy      = ($this->turno)(['estado' => 'en_espera']);

    $this->artisan('sgth:dispensario:cerrar-turnos-vencidos')
        ->expectsOutputToContain('2 turno(s)')
        ->assertSuccessful();

    foreach ([$enEspera, $enSala] as $turno) {
        $turno->refresh();
        expect($turno->estado)->toBe('no_presentado');
        expect($turno->marcado_no_presentado_en)->not->toBeNull();
        // Sin usuario: así se distingue del que marca a mano el profesional.
        expect($turno->marcado_no_presentado_por)->toBeNull();
    }

    expect($enConsulta->fresh()->estado)->toBe('en_consulta');
    expect($atendido->fresh()->estado)->toBe('atendido');
    expect($deHoy->fresh()->estado)->toBe('en_espera');

    // Correrlo otra vez no cambia nada.
    $this->artisan('sgth:dispensario:cerrar-turnos-vencidos')
        ->expectsOutputToContain('0 turno(s)')
        ->assertSuccessful();
});

test('el cierre diario está programado', function () {
    $evento = collect(app(Illuminate\Console\Scheduling\Schedule::class)->events())
        ->first(fn ($e) => str_contains((string) $e->command, 'sgth:dispensario:cerrar-turnos-vencidos'));

    expect($evento)->not->toBeNull();
    expect($evento->expression)->toBe('10 0 * * *');
});
