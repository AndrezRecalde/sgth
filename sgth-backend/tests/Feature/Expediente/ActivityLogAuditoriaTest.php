<?php

namespace Tests\Feature\Expediente;

use App\Enums\CategoriaEventoVinculo;
use App\Enums\EstadoAccionPersonal;
use App\Models\Estructura\PartidaPresupuestaria;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Expediente\ContratoServidorService;
use App\Services\Expediente\MovimientoPersonalStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);

    $this->user = User::factory()->create();
    $this->user->assignRole('admin-uath');
    $this->actingAs($this->user, 'sanctum');

    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'UATH-01', 'nombre' => 'Unidad de Talento Humano', 'nivel' => 1,
    ]);

    $this->partida = PartidaPresupuestaria::create([
        'codigo' => '510105', 'descripcion' => 'Remuneraciones Unificadas',
        'grupo_gasto' => 'Gastos en Personal', 'activo' => true, 'disponible' => true,
    ]);

    $this->puesto = Puesto::create([
        'codigo' => 'P-01',
        'unidad_administrativa_id' => $this->unidad->id,
        'partida_presupuestaria_id' => $this->partida->id,
        'plazas' => 5,
    ]);

    $this->servidor = Servidor::create([
        'user_id' => User::factory()->create()->id,
        'cedula' => '1111111111', 'nombre' => 'Titular', 'apellido' => 'Test',
        'regimen_laboral' => 'losep',
        'puesto_id' => $this->puesto->id,
        'unidad_administrativa_id' => $this->unidad->id,
    ]);
});

// ── 1. Transición de estado en MovimientoPersonal ────────────────

test('una transición de estado genera una entrada en activity_log con causer, timestamp y valores antes/después', function () {
    // 'comision_servicios': ni modificaVinculo() ni tieneEfectoEconomico() —
    // esta prueba es sobre el log de la transición en sí, no sobre la
    // materialización de vínculo (ver MovimientoPersonalVinculoTest.php).
    $movimiento = MovimientoPersonal::create([
        'servidor_id'     => $this->servidor->id,
        'tipo_movimiento' => 'comision_servicios',
        'categoria'       => CategoriaEventoVinculo::ACCION_DE_PERSONAL,
        'estado'          => EstadoAccionPersonal::SUSCRITA,
        'descripcion'     => 'Movimiento de prueba',
        'fecha_efectiva'  => now()->toDateString(),
        'autorizado_por'  => $this->user->id,
    ]);

    Activity::query()->delete(); // limpiar el log del created() para aislar la transición

    app(MovimientoPersonalStateService::class)->transicionar($movimiento, EstadoAccionPersonal::REGISTRADA);

    $log = Activity::where('subject_type', MovimientoPersonal::class)
        ->where('subject_id', $movimiento->id)
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->causer_id)->toBe($this->user->id);
    expect($log->causer_type)->toBe(User::class);
    expect($log->created_at)->not->toBeNull();

    expect($log->properties['old']['estado'])->toBe('suscrita');
    expect($log->properties['attributes']['estado'])->toBe('registrada');
    expect($log->properties['attributes']['codigo_registro'])->not->toBeNull();
});

test('un guardado que no toca campos auditables no genera fila en activity_log', function () {
    $movimiento = MovimientoPersonal::create([
        'servidor_id'     => $this->servidor->id,
        'tipo_movimiento' => 'traslado',
        'estado'          => EstadoAccionPersonal::REGISTRADA,
        'descripcion'     => 'Movimiento de prueba',
        'fecha_efectiva'  => now()->toDateString(),
        'autorizado_por'  => $this->user->id,
    ]);

    Activity::query()->delete();

    $movimiento->update(['observacion' => 'Nota administrativa, no afecta campos legales.']);

    $count = Activity::where('subject_type', MovimientoPersonal::class)
        ->where('subject_id', $movimiento->id)
        ->count();

    expect($count)->toBe(0);
});

// ── 2. cerrar() en ContratoServidor ───────────────────────────────

test('cerrar() un contrato genera entrada en activity_log con quién lo cerró y el motivo_fin', function () {
    $contrato = ContratoServidor::create([
        'servidor_id'              => $this->servidor->id,
        'tipo_nombramiento'        => 'nombramiento_permanente',
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id'                => $this->puesto->id,
        'fecha_inicio'             => '2020-01-01',
        'estado'                   => 'vigente',
    ]);

    Activity::query()->delete(); // limpiar el log de created()

    app(ContratoServidorService::class)->cerrar($contrato, [
        'motivo_fin' => 'Fin de periodo de prueba.',
    ]);

    $log = Activity::where('subject_type', ContratoServidor::class)
        ->where('subject_id', $contrato->id)
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->causer_id)->toBe($this->user->id);
    expect($log->properties['motivo_fin'])->toBe('Fin de periodo de prueba.');
    expect($log->description)->toBe('Contrato cerrado');
});

test('el cierre automático de un contrato vigente al activar uno nuevo también queda en activity_log', function () {
    $anterior = ContratoServidor::create([
        'servidor_id' => $this->servidor->id,
        'tipo_nombramiento' => 'nombramiento_provisional',
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id' => $this->puesto->id,
        'fecha_inicio' => '2019-01-01',
        'estado' => 'vigente',
    ]);

    Activity::query()->delete();

    app(ContratoServidorService::class)->crear($this->servidor->id, [
        'tipo_nombramiento' => 'nombramiento_permanente',
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id' => $this->puesto->id,
        'fecha_inicio' => '2020-01-01',
        'estado' => 'vigente',
    ]);

    $log = Activity::where('subject_type', ContratoServidor::class)
        ->where('subject_id', $anterior->id)
        ->where('description', 'Contrato cerrado')
        ->first();

    expect($log)->not->toBeNull();
    expect($anterior->fresh()->estado->value)->toBe('terminado');
});

// ── 3. sincronizarRegimenServidor(): MovimientoPersonal + activity_log ──

test('sincronizarRegimenServidor genera el MovimientoPersonal (fase 1) Y la entrada de activity_log (fase 2c) en la misma llamada', function () {
    expect(MovimientoPersonal::where('servidor_id', $this->servidor->id)->count())->toBe(0);
    Activity::query()->delete();

    app(ContratoServidorService::class)->crear($this->servidor->id, [
        'tipo_nombramiento' => 'nombramiento_provisional',
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id' => $this->puesto->id,
        'fecha_inicio' => '2026-07-01',
        'estado' => 'vigente',
    ]);

    // Fase 1: el MovimientoPersonal de sincronización.
    $movimiento = MovimientoPersonal::where('servidor_id', $this->servidor->id)
        ->where('tipo_movimiento', 'novedad_contrato')
        ->first();
    expect($movimiento)->not->toBeNull();

    // Fase 2c: la entrada de activity_log sobre el Servidor mismo.
    $log = Activity::where('subject_type', Servidor::class)
        ->where('subject_id', $this->servidor->id)
        ->where('description', 'Datos laborales del servidor sincronizados')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->causer_id)->toBe($this->user->id);
    expect($log->properties['despues']['tipo_nombramiento'])->toBe('nombramiento_provisional');

    $this->servidor->refresh();
    expect($this->servidor->tipo_nombramiento->value)->toBe('nombramiento_provisional');
});

test('un cambio de servidor que no toca campos legales (ej. contacto) no genera activity_log', function () {
    Activity::query()->delete();

    $this->servidor->update(['correo_personal' => 'nuevo@correo.test']);

    $count = Activity::where('subject_type', Servidor::class)
        ->where('subject_id', $this->servidor->id)
        ->count();

    expect($count)->toBe(0);
});

test('Servidor ya no está observado dos veces: un update legal genera una sola fila de log', function () {
    Activity::query()->delete();

    $this->servidor->update(['tipo_nombramiento' => 'nombramiento_provisional']);

    $count = Activity::where('subject_type', Servidor::class)
        ->where('subject_id', $this->servidor->id)
        ->count();

    expect($count)->toBe(1);
});
