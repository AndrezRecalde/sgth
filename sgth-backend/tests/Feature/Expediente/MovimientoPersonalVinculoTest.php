<?php

namespace Tests\Feature\Expediente;

use App\Enums\CategoriaEventoVinculo;
use App\Enums\EstadoAccionPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Expediente\MovimientoPersonalStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    $this->puestoA = Puesto::create([
        'codigo' => 'P-A', 'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 5,
    ]);

    $this->puestoB = Puesto::create([
        'codigo' => 'P-B', 'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 5,
    ]);

    $this->stateService = app(MovimientoPersonalStateService::class);
});

// ── Exclusión mutua ────────────────────────────────────────────

test('ningún tipo_movimiento es creaVinculo() y modificaVinculo() a la vez', function () {
    foreach (TipoMovimientoPersonal::cases() as $tipo) {
        expect($tipo->creaVinculo() && $tipo->modificaVinculo())
            ->toBeFalse("El tipo '{$tipo->value}' es creaVinculo() y modificaVinculo() a la vez.");
    }
});

// ── Completitud de datos propuestos — INGRESO (creaVinculo) ──────

test('un ingreso sin tipo_nombramiento_propuesto no puede registrarse', function () {
    $servidor = Servidor::create([
        'user_id' => User::factory()->create()->id,
        'cedula' => '1111111111', 'nombre' => 'Nuevo', 'apellido' => 'Ingreso',
        'regimen_laboral' => 'losep',
    ]);

    $movimiento = MovimientoPersonal::create([
        'servidor_id'       => $servidor->id,
        'tipo_movimiento'   => 'ingreso',
        'categoria'         => CategoriaEventoVinculo::ACCION_DE_PERSONAL,
        'estado'            => EstadoAccionPersonal::SUSCRITA,
        'descripcion'       => 'Ingreso propuesto sin tipo de nombramiento',
        'fecha_efectiva'    => '2026-08-01',
        'puesto_destino_id' => $this->puestoA->id,
        'unidad_destino_id' => $this->unidad->id,
        'autorizado_por'    => $this->user->id,
    ]);

    expect(fn () => $this->stateService->transicionar($movimiento, EstadoAccionPersonal::REGISTRADA))
        ->toThrow(ReglaNegocioException::class);

    expect(ContratoServidor::where('servidor_id', $servidor->id)->count())->toBe(0);
});

test('un ingreso sin puesto o unidad propuestos no puede registrarse', function () {
    $servidor = Servidor::create([
        'user_id' => User::factory()->create()->id,
        'cedula' => '2222222222', 'nombre' => 'Nuevo', 'apellido' => 'SinPuesto',
        'regimen_laboral' => 'losep',
    ]);

    $movimiento = MovimientoPersonal::create([
        'servidor_id'                 => $servidor->id,
        'tipo_movimiento'             => 'ingreso',
        'categoria'                   => CategoriaEventoVinculo::ACCION_DE_PERSONAL,
        'estado'                      => EstadoAccionPersonal::SUSCRITA,
        'tipo_nombramiento_propuesto' => 'nombramiento_provisional',
        'descripcion'                 => 'Ingreso propuesto sin puesto',
        'fecha_efectiva'              => '2026-08-01',
        'autorizado_por'              => $this->user->id,
    ]);

    expect(fn () => $this->stateService->transicionar($movimiento, EstadoAccionPersonal::REGISTRADA))
        ->toThrow(ReglaNegocioException::class);
});

// ── Completitud de datos propuestos — modificaVinculo() ──────────

test('un traslado sin puesto_destino_id no puede registrarse', function () {
    $servidor = Servidor::create([
        'user_id' => User::factory()->create()->id,
        'cedula' => '3333333333', 'nombre' => 'Titular', 'apellido' => 'Test',
        'regimen_laboral' => 'losep',
        'puesto_id' => $this->puestoA->id,
        'unidad_administrativa_id' => $this->unidad->id,
    ]);

    ContratoServidor::create([
        'servidor_id' => $servidor->id,
        'tipo_nombramiento' => 'nombramiento_permanente',
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id' => $this->puestoA->id,
        'fecha_inicio' => '2020-01-01',
        'estado' => 'vigente',
    ]);

    $movimiento = MovimientoPersonal::create([
        'servidor_id'     => $servidor->id,
        'tipo_movimiento' => 'traslado',
        'categoria'       => CategoriaEventoVinculo::ACCION_DE_PERSONAL,
        'estado'          => EstadoAccionPersonal::SUSCRITA,
        'descripcion'     => 'Traslado sin puesto de destino',
        'fecha_efectiva'  => '2026-08-01',
        'autorizado_por'  => $this->user->id,
    ]);

    expect(fn () => $this->stateService->transicionar($movimiento, EstadoAccionPersonal::REGISTRADA))
        ->toThrow(ReglaNegocioException::class);
});

// ── End-to-end: INGRESO ──────────────────────────────────────────

test('un ingreso completo, al registrarse, crea el ContratoServidor y sincroniza Servidor', function () {
    $servidor = Servidor::create([
        'user_id' => User::factory()->create()->id,
        'cedula' => '4444444444', 'nombre' => 'Completo', 'apellido' => 'Ingreso',
        'regimen_laboral' => 'losep',
    ]);

    expect($servidor->contratoVigente)->toBeNull();

    $movimiento = MovimientoPersonal::create([
        'servidor_id'                 => $servidor->id,
        'tipo_movimiento'             => 'ingreso',
        'categoria'                   => CategoriaEventoVinculo::ACCION_DE_PERSONAL,
        'estado'                      => EstadoAccionPersonal::BORRADOR,
        'descripcion'                 => 'Ingreso incompleto inicialmente',
        'fecha_efectiva'              => '2026-08-01',
        'autorizado_por'              => $this->user->id,
    ]);

    // Incompleto: falla y no crea nada.
    $this->stateService->transicionar($movimiento, EstadoAccionPersonal::SUSCRITA);
    expect(fn () => $this->stateService->transicionar($movimiento, EstadoAccionPersonal::REGISTRADA))
        ->toThrow(ReglaNegocioException::class);
    expect(ContratoServidor::where('servidor_id', $servidor->id)->count())->toBe(0);

    // Se completan los datos propuestos (sigue en 'suscrita', editable
    // porque el guard de inmutabilidad solo bloquea desde REGISTRADA/NOTIFICADA).
    $movimiento->update([
        'tipo_nombramiento_propuesto' => 'nombramiento_provisional',
        'puesto_destino_id'           => $this->puestoA->id,
        'unidad_destino_id'           => $this->unidad->id,
        'remuneracion_propuesta'      => 850.00,
        'numero_contrato'             => 'CT-2026-0001',
    ]);

    $registrado = $this->stateService->transicionar($movimiento->fresh(), EstadoAccionPersonal::REGISTRADA);

    expect($registrado->estado)->toBe(EstadoAccionPersonal::REGISTRADA);

    $contrato = ContratoServidor::where('servidor_id', $servidor->id)->first();
    expect($contrato)->not->toBeNull();
    expect($contrato->estado->value)->toBe('vigente');
    expect($contrato->puesto_id)->toBe($this->puestoA->id);
    expect($contrato->tipo_nombramiento->value)->toBe('nombramiento_provisional');

    $servidor->refresh();
    expect($servidor->puesto_id)
        ->toBe($contrato->puesto_id)
        ->toBe($this->puestoA->id);
    expect($servidor->unidad_administrativa_id)->toBe($contrato->unidad_administrativa_id);
});

// ── End-to-end: traslado (reubica dentro del mismo vínculo) ──────

test('un traslado reubica al servidor conservando el mismo contrato', function () {
    $servidor = Servidor::create([
        'user_id' => User::factory()->create()->id,
        'cedula' => '5555555555', 'nombre' => 'Titular', 'apellido' => 'Traslado',
        'regimen_laboral' => 'losep',
        'puesto_id' => $this->puestoA->id,
        'unidad_administrativa_id' => $this->unidad->id,
    ]);

    $contratoOriginal = ContratoServidor::create([
        'servidor_id' => $servidor->id,
        'tipo_nombramiento' => 'nombramiento_permanente',
        'numero_contrato' => 'CT-2020-0001',
        'resolucion_numero' => 'RES-2020-0007',
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id' => $this->puestoA->id,
        'fecha_inicio' => '2020-01-01',
        'estado' => 'vigente',
    ]);

    $movimiento = MovimientoPersonal::create([
        'servidor_id'       => $servidor->id,
        'tipo_movimiento'   => 'traslado',
        'categoria'         => CategoriaEventoVinculo::ACCION_DE_PERSONAL,
        'estado'            => EstadoAccionPersonal::SUSCRITA,
        'descripcion'       => 'Traslado al puesto B',
        'fecha_efectiva'    => '2026-08-01',
        'puesto_destino_id' => $this->puestoB->id,
        'autorizado_por'    => $this->user->id,
    ]);

    $registrado = $this->stateService->transicionar($movimiento, EstadoAccionPersonal::REGISTRADA);

    expect($registrado->estado)->toBe(EstadoAccionPersonal::REGISTRADA);

    // El traslado no interrumpe la relación laboral: sigue habiendo UN solo
    // contrato, el mismo, con su número y resolución originales — no existe
    // ningún documento nuevo que justificara crear otro.
    expect(ContratoServidor::where('servidor_id', $servidor->id)->count())->toBe(1);

    $contratoOriginal->refresh();
    expect($contratoOriginal->estado->value)->toBe('vigente')
        ->and($contratoOriginal->numero_contrato)->toBe('CT-2020-0001')
        ->and($contratoOriginal->resolucion_numero)->toBe('RES-2020-0007')
        ->and($contratoOriginal->fecha_inicio->toDateString())->toBe('2020-01-01')
        ->and($contratoOriginal->puesto_id)->toBe($this->puestoB->id)
        ->and($contratoOriginal->tipo_nombramiento->value)->toBe('nombramiento_permanente');

    // Integridad en una sola cadena: Servidor === contrato vigente === puesto B.
    $servidor->refresh();
    expect($servidor->puesto_id)
        ->toBe($servidor->contratoVigente->puesto_id)
        ->toBe($this->puestoB->id);

    // Sin duplicado de 'novedad_contrato': solo existe el traslado mismo.
    $movimientosDelServidor = MovimientoPersonal::where('servidor_id', $servidor->id)->get();
    expect($movimientosDelServidor)->toHaveCount(1);
    expect($movimientosDelServidor->first()->tipo_movimiento->value)->toBe('traslado');
});

// ── PUT /expediente/servidores/{id} rechaza puesto/unidad/tipo_nombramiento ──

test('PUT servidores/{id} con puesto_id en el body responde 422', function () {
    $servidor = Servidor::create([
        'user_id' => User::factory()->create()->id,
        'cedula' => '6666666666', 'nombre' => 'Titular', 'apellido' => 'Prohibited',
        'regimen_laboral' => 'losep',
        'puesto_id' => $this->puestoA->id,
        'unidad_administrativa_id' => $this->unidad->id,
    ]);

    $response = $this->putJson("/api/v1/expediente/servidores/{$servidor->id}", [
        'puesto_id' => $this->puestoB->id,
    ]);

    $response->assertStatus(422)->assertJsonStructure(['errores' => ['puesto_id']]);
    expect($servidor->fresh()->puesto_id)->toBe($this->puestoA->id);
});

test('PUT servidores/{id} con unidad_administrativa_id en el body responde 422', function () {
    $otraUnidad = UnidadAdministrativa::create([
        'codigo' => 'UATH-02', 'nombre' => 'Otra Unidad', 'nivel' => 1,
    ]);

    $servidor = Servidor::create([
        'user_id' => User::factory()->create()->id,
        'cedula' => '7777777777', 'nombre' => 'Titular', 'apellido' => 'Prohibited',
        'regimen_laboral' => 'losep',
        'puesto_id' => $this->puestoA->id,
        'unidad_administrativa_id' => $this->unidad->id,
    ]);

    $response = $this->putJson("/api/v1/expediente/servidores/{$servidor->id}", [
        'unidad_administrativa_id' => $otraUnidad->id,
    ]);

    $response->assertStatus(422)->assertJsonStructure(['errores' => ['unidad_administrativa_id']]);
    expect($servidor->fresh()->unidad_administrativa_id)->toBe($this->unidad->id);
});

test('PUT servidores/{id} con tipo_nombramiento en el body responde 422', function () {
    $servidor = Servidor::create([
        'user_id' => User::factory()->create()->id,
        'cedula' => '8888888888', 'nombre' => 'Titular', 'apellido' => 'Prohibited',
        'regimen_laboral' => 'losep',
        'puesto_id' => $this->puestoA->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'tipo_nombramiento' => 'nombramiento_provisional',
    ]);

    $response = $this->putJson("/api/v1/expediente/servidores/{$servidor->id}", [
        'tipo_nombramiento' => 'nombramiento_permanente',
    ]);

    $response->assertStatus(422)->assertJsonStructure(['errores' => ['tipo_nombramiento']]);
    expect($servidor->fresh()->tipo_nombramiento->value)->toBe('nombramiento_provisional');
});

// ── Comisión de servicios: ausencia temporal, no reubicación ─────

test('una comisión de servicios no mueve al servidor de puesto', function () {
    $servidor = Servidor::create([
        'cedula' => '6666666666', 'nombre' => 'En', 'apellido' => 'Comision',
        'regimen_laboral' => 'losep',
        'puesto_id' => $this->puestoA->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'fecha_ingreso_institucion' => '2018-01-01',
    ]);

    $contrato = ContratoServidor::create([
        'servidor_id' => $servidor->id,
        'tipo_nombramiento' => 'nombramiento_permanente',
        'numero_contrato' => 'CT-2018-0009',
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id' => $this->puestoA->id,
        'fecha_inicio' => '2018-01-01',
        'estado' => 'vigente',
    ]);

    $movimiento = app(\App\Services\Expediente\MovimientoPersonalService::class)
        ->registrar($servidor->id, [
            'tipo_movimiento'    => \App\Enums\TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO->value,
            'subtipo_movimiento' => \App\Enums\SubtipoMovimientoPersonal::COMISION_SIN_REMUNERACION->value,
            'descripcion'        => 'Comisión de servicios en otra institución',
            'fecha_efectiva'     => '2026-01-01',
            'fecha_inicio'       => '2026-01-01',
            'fecha_fin'          => '2028-01-01',
        ]);

    // Comparte el tipo paraguas con el traspaso, pero no exige puesto destino
    // ni reubica: el servidor conserva su puesto y vuelve al terminar.
    $movimiento = $this->stateService->transicionar($movimiento, EstadoAccionPersonal::SUSCRITA);
    $this->stateService->transicionar($movimiento->fresh(), EstadoAccionPersonal::REGISTRADA);

    $contrato->refresh();

    expect($contrato->estado->value)->toBe('vigente')
        ->and($contrato->puesto_id)->toBe($this->puestoA->id)
        ->and(ContratoServidor::where('servidor_id', $servidor->id)->count())->toBe(1);
});

// ── Actividad laboral: acciones anidadas bajo su vínculo ─────────

test('la actividad laboral agrupa las acciones bajo el contrato al que pertenecen', function () {
    $servidor = Servidor::create([
        'cedula' => '7777777777', 'nombre' => 'Con', 'apellido' => 'Historial',
        'regimen_laboral' => 'losep',
        'puesto_id' => $this->puestoA->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'fecha_ingreso_institucion' => '2018-01-01',
    ]);

    ContratoServidor::create([
        'servidor_id' => $servidor->id,
        'tipo_nombramiento' => 'nombramiento_permanente',
        'numero_contrato' => 'CT-2018-0100',
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id' => $this->puestoA->id,
        'fecha_inicio' => '2018-01-01',
        'estado' => 'vigente',
    ]);

    $servicio = app(\App\Services\Expediente\MovimientoPersonalService::class);

    $traspaso = $servicio->registrar($servidor->id, [
        'tipo_movimiento'    => \App\Enums\TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO->value,
        'subtipo_movimiento' => \App\Enums\SubtipoMovimientoPersonal::TRASPASO->value,
        'descripcion'        => 'Traspaso',
        'fecha_efectiva'     => '2026-03-15',
        'puesto_destino_id'  => $this->puestoB->id,
        'unidad_destino_id'  => $this->unidad->id,
    ]);
    $traspaso = $this->stateService->transicionar($traspaso, EstadoAccionPersonal::SUSCRITA);
    $this->stateService->transicionar($traspaso->fresh(), EstadoAccionPersonal::REGISTRADA);

    $comision = $servicio->registrar($servidor->id, [
        'tipo_movimiento'    => \App\Enums\TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO->value,
        'subtipo_movimiento' => \App\Enums\SubtipoMovimientoPersonal::COMISION_SIN_REMUNERACION->value,
        'descripcion'        => 'Comisión',
        'fecha_efectiva'     => now()->subMonth()->toDateString(),
        'fecha_inicio'       => now()->subMonth()->toDateString(),
        'fecha_fin'          => now()->addYear()->toDateString(),
    ]);
    $comision = $this->stateService->transicionar($comision, EstadoAccionPersonal::SUSCRITA);
    $this->stateService->transicionar($comision->fresh(), EstadoAccionPersonal::REGISTRADA);

    $actividad = app(\App\Services\Expediente\ContratoServidorService::class)
        ->actividadLaboral($servidor->id);

    expect($actividad)->toHaveCount(1);

    $vinculo = $actividad[0];

    // Un solo contrato, con sus dos acciones colgando y la situación derivada
    // de la comisión vigente hoy.
    expect($vinculo['contrato']->numero_contrato)->toBe('CT-2018-0100')
        ->and($vinculo['acciones'])->toHaveCount(2)
        ->and($vinculo['acciones'][0]['etiqueta'])->toBe('Traspaso')
        ->and($vinculo['situacion'])->not->toBeNull()
        ->and($vinculo['situacion']['etiqueta'])->toBe('Comisión de Servicios sin Remuneración');
});

// ── Situación actual congelada al crear la acción ────────────────

test('la acción congela dónde estaba el servidor al momento de registrarla', function () {
    $servidor = Servidor::create([
        'cedula' => '8888888888', 'nombre' => 'Origen', 'apellido' => 'Capturado',
        'regimen_laboral' => 'losep',
        'puesto_id' => $this->puestoA->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'fecha_ingreso_institucion' => '2018-01-01',
    ]);

    ContratoServidor::create([
        'servidor_id' => $servidor->id,
        'tipo_nombramiento' => 'nombramiento_permanente',
        'numero_contrato' => 'CT-2018-0200',
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id' => $this->puestoA->id,
        'fecha_inicio' => '2018-01-01',
        'estado' => 'vigente',
    ]);

    $traspaso = app(\App\Services\Expediente\MovimientoPersonalService::class)
        ->registrar($servidor->id, [
            'tipo_movimiento'    => \App\Enums\TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO->value,
            'subtipo_movimiento' => \App\Enums\SubtipoMovimientoPersonal::TRASPASO->value,
            'descripcion'        => 'Traspaso al puesto B',
            'fecha_efectiva'     => '2026-05-01',
            'puesto_destino_id'  => $this->puestoB->id,
            'unidad_destino_id'  => $this->unidad->id,
        ]);

    // Es la columna "situación actual" del PDF y, desde que el traspaso
    // actualiza el contrato en vez de duplicarlo, el único registro de dónde
    // venía la persona.
    expect($traspaso->puesto_origen_id)->toBe($this->puestoA->id)
        ->and($traspaso->unidad_origen_id)->toBe($this->unidad->id);

    $traspaso = $this->stateService->transicionar($traspaso, EstadoAccionPersonal::SUSCRITA);
    $this->stateService->transicionar($traspaso->fresh(), EstadoAccionPersonal::REGISTRADA);

    // El contrato ya apunta al puesto B, pero la acción conserva el A.
    expect($servidor->fresh()->contratoVigente->puesto_id)->toBe($this->puestoB->id)
        ->and($traspaso->fresh()->puesto_origen_id)->toBe($this->puestoA->id);
});

test('un ingreso no tiene situación actual que congelar', function () {
    $servidor = Servidor::create([
        'cedula' => '9999999999', 'nombre' => 'Primer', 'apellido' => 'Ingreso',
        'regimen_laboral' => 'losep',
    ]);

    $ingreso = app(\App\Services\Expediente\MovimientoPersonalService::class)
        ->registrar($servidor->id, [
            'tipo_movimiento'             => 'ingreso',
            'tipo_nombramiento_propuesto' => 'nombramiento_permanente',
            'puesto_destino_id'           => $this->puestoA->id,
            'unidad_destino_id'           => $this->unidad->id,
            'descripcion'                 => 'Primer ingreso',
            'fecha_efectiva'              => '2026-08-01',
        ]);

    expect($ingreso->puesto_origen_id)->toBeNull()
        ->and($ingreso->unidad_origen_id)->toBeNull();
});
