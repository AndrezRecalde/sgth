<?php

namespace Tests\Feature\Expediente;

use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Expediente\ContratoServidorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| Fase 1.5 del diseño de Acciones de Personal (8.3):
|  - `estado` significa «tiene un vínculo vigente»;
|  - la antigüedad sale de la historia de vínculos y no la pisa cada ingreso;
|  - el régimen y la fecha de ingreso dejan de editarse en la ficha.
*/
beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    $this->actingAs(User::factory()->create(), 'sanctum');

    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'UATH-ANT', 'nombre' => 'Unidad de Talento Humano', 'nivel' => 1,
    ]);

    $this->puesto = Puesto::create([
        'codigo' => 'P-ANT', 'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 10,
    ]);

    $this->contratos = app(ContratoServidorService::class);

    $this->servidor = fn (array $datos = []) => Servidor::create([
        'cedula'          => '1720000001',
        'nombre'          => 'Servidora',
        'apellido'        => 'Antigua',
        'regimen_laboral' => 'losep',
        ...$datos,
    ]);

    $this->vincular = fn (Servidor $s, string $desde, string $nombramiento = 'nombramiento_permanente') => $this->contratos->crear($s->id, [
        'tipo_nombramiento'        => $nombramiento,
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id'                => $this->puesto->id,
        'fecha_inicio'             => $desde,
        'estado'                   => 'vigente',
    ]);

    $this->cerrar = fn (ContratoServidor $c, string $hasta) => $this->contratos->cerrar($c, [
        'fecha_fin' => $hasta, 'motivo_fin' => 'Renuncia',
    ]);
});

// ── El estado ───────────────────────────────────────────────────

test('cerrar el vínculo deja al servidor inactivo y sin puesto', function () {
    $servidor = ($this->servidor)();
    $contrato = ($this->vincular)($servidor, '2020-01-01');

    expect($servidor->fresh()->estado)->toBeTrue()
        ->and($servidor->fresh()->puesto_id)->toBe($this->puesto->id);

    ($this->cerrar)($contrato, '2026-06-30');

    expect($servidor->fresh()->estado)->toBeFalse()
        ->and($servidor->fresh()->puesto_id)->toBeNull()
        ->and($servidor->fresh()->unidad_administrativa_id)->toBeNull();
});

test('reabrir el vínculo al anular su cesación lo devuelve a funciones', function () {
    $servidor = ($this->servidor)();
    $contrato = ($this->vincular)($servidor, '2020-01-01');
    ($this->cerrar)($contrato, '2026-06-30');

    $this->contratos->reabrirPorAnulacion($contrato->fresh());

    expect($servidor->fresh()->estado)->toBeTrue()
        ->and($servidor->fresh()->puesto_id)->toBe($this->puesto->id);
});

test('quien reingresa vuelve a estar activo', function () {
    $servidor = ($this->servidor)();
    ($this->cerrar)(($this->vincular)($servidor, '2018-01-01'), '2019-12-31');

    ($this->vincular)($servidor, '2024-03-01');

    expect($servidor->fresh()->estado)->toBeTrue();
});

// ── La antigüedad ───────────────────────────────────────────────

test('un vínculo que sigue al día siguiente del anterior conserva la antigüedad', function () {
    $servidor = ($this->servidor)();
    // El «ascenso» de Talento Humano: cesación y nuevo ingreso sin un día fuera.
    ($this->cerrar)(($this->vincular)($servidor, '2015-01-05'), '2020-06-30');

    ($this->vincular)($servidor, '2020-07-01');

    expect($servidor->fresh()->fecha_ingreso_institucion->toDateString())->toBe('2015-01-05');
});

test('también si el nuevo empieza el mismo día en que cerró el anterior', function () {
    $servidor = ($this->servidor)();
    ($this->cerrar)(($this->vincular)($servidor, '2015-01-05'), '2020-06-30');

    ($this->vincular)($servidor, '2020-06-30');

    expect($servidor->fresh()->fecha_ingreso_institucion->toDateString())->toBe('2015-01-05');
});

test('con tiempo fuera, la antigüedad cuenta desde el reingreso', function () {
    $servidor = ($this->servidor)();
    ($this->cerrar)(($this->vincular)($servidor, '2015-01-05'), '2020-06-30');

    ($this->vincular)($servidor, '2020-07-02');

    expect($servidor->fresh()->fecha_ingreso_institucion->toDateString())->toBe('2020-07-02');
});

test('la cadena se corta en el primer hueco, aunque antes hubiera más servicio', function () {
    $servidor = ($this->servidor)();
    ($this->cerrar)(($this->vincular)($servidor, '2010-01-01'), '2012-12-31');
    ($this->cerrar)(($this->vincular)($servidor, '2014-01-01'), '2019-12-31');

    ($this->vincular)($servidor, '2020-01-01');

    expect($servidor->fresh()->fecha_ingreso_institucion->toDateString())->toBe('2014-01-01');
});

test('en el primer vínculo se respeta la fecha declarada si es anterior', function () {
    // Quien ingresó antes de que existiera el sistema y se cargó con su fecha real.
    $servidor = ($this->servidor)(['fecha_ingreso_institucion' => '2009-04-01']);

    ($this->vincular)($servidor, '2020-01-01');

    expect($servidor->fresh()->fecha_ingreso_institucion->toDateString())->toBe('2009-04-01');
});

// ── El comando de conciliación ──────────────────────────────────

test('el comando muestra sin guardar a quién le cambiaría el estado o la antigüedad', function () {
    $cesado = ($this->servidor)();
    ($this->cerrar)(($this->vincular)($cesado, '2015-01-05'), '2020-06-30');
    // Como lo dejaba el código anterior a la fase 1.5: activo y con la fecha
    // pisada por el último ingreso.
    Servidor::whereKey($cesado->id)->update(['estado' => true, 'fecha_ingreso_institucion' => '2019-01-01']);

    $this->artisan('sgth:servidores:conciliar-estado', ['--simular' => true])
        ->expectsOutputToContain('1 servidor(es) cambiarían')
        ->assertSuccessful();

    expect($cesado->fresh()->estado)->toBeTrue();
});

test('aplicarlo pide quién lo autorizó', function () {
    $this->artisan('sgth:servidores:conciliar-estado')->assertFailed();
});

test('aplicado, apaga al cesado, corrige la antigüedad y lo deja en la bitácora', function () {
    $cesado = ($this->servidor)();
    ($this->cerrar)(($this->vincular)($cesado, '2015-01-05'), '2020-06-30');
    Servidor::whereKey($cesado->id)->update([
        'estado' => true, 'fecha_ingreso_institucion' => '2019-01-01', 'puesto_id' => $this->puesto->id,
    ]);

    $this->artisan('sgth:servidores:conciliar-estado', ['--responsable' => 'Dirección de Talento Humano'])
        ->assertSuccessful();

    $cesado->refresh();

    expect($cesado->estado)->toBeFalse()
        ->and($cesado->puesto_id)->toBeNull()
        ->and($cesado->fecha_ingreso_institucion->toDateString())->toBe('2015-01-05')
        ->and(Activity::where('description', 'Estado y antigüedad conciliados con los vínculos')
            ->where('subject_id', $cesado->id)->value('properties')['responsable'] ?? null)
        ->toBe('Dirección de Talento Humano');
});

// ── La ficha ────────────────────────────────────────────────────

test('la fecha de ingreso solo la corrige quien tiene el permiso de corrección', function () {
    $servidor = ($this->servidor)(['fecha_ingreso_institucion' => '2015-01-05']);

    $sinPermiso = User::factory()->create();
    $sinPermiso->assignRole('admin-uath');

    $this->actingAs($sinPermiso, 'sanctum')
        ->putJson("/api/v1/expediente/servidores/{$servidor->id}", ['fecha_ingreso_institucion' => '2014-01-01'])
        ->assertUnprocessable()
        ->assertJsonStructure(['errores' => ['fecha_ingreso_institucion']]);

    Permission::firstOrCreate(['name' => 'corregir-datos-laborales', 'guard_name' => 'sanctum']);
    $conPermiso = User::factory()->create();
    $conPermiso->assignRole('admin-uath');
    $conPermiso->givePermissionTo('corregir-datos-laborales');

    $this->actingAs($conPermiso, 'sanctum')
        ->putJson("/api/v1/expediente/servidores/{$servidor->id}", ['fecha_ingreso_institucion' => '2014-01-01'])
        ->assertOk();

    expect($servidor->fresh()->fecha_ingreso_institucion->toDateString())->toBe('2014-01-01');
});
