<?php

namespace Tests\Feature\Expediente;

use App\Enums\CategoriaEventoVinculo;
use App\Enums\EstadoAccionPersonal;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Expediente\ExpedienteService;
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

    $this->puesto = Puesto::create([
        'codigo' => 'P-01', 'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 5,
    ]);
});

test('un servidor recién creado vía crearServidorBasico() está pendiente_vinculacion=true', function () {
    $servidor = app(ExpedienteService::class)->crearServidorBasico([
        'cedula' => '1111111111', 'nombre' => 'Nuevo', 'apellido' => 'Ingreso',
        'fecha_nacimiento' => '1990-01-01', 'genero' => 'masculino', 'estado_civil' => 'soltero',
        'es_extranjero' => false, 'tiene_discapacidad' => false, 'tiene_enfermedad_catastrofica' => false,
    ]);

    expect($servidor->contratoVigente)->toBeNull();

    $response = $this->getJson("/api/v1/expediente/servidores/{$servidor->id}");

    $response->assertStatus(200);
    expect($response->json('datos.pendiente_vinculacion'))->toBeTrue();
});

test('deja de estar pendiente_vinculacion después de que un ingreso llega a registrada', function () {
    $servidor = app(ExpedienteService::class)->crearServidorBasico([
        'cedula' => '2222222222', 'nombre' => 'Nuevo', 'apellido' => 'Vinculado',
        'fecha_nacimiento' => '1990-01-01', 'genero' => 'masculino', 'estado_civil' => 'soltero',
        'es_extranjero' => false, 'tiene_discapacidad' => false, 'tiene_enfermedad_catastrofica' => false,
    ]);

    $antes = $this->getJson("/api/v1/expediente/servidores/{$servidor->id}");
    expect($antes->json('datos.pendiente_vinculacion'))->toBeTrue();

    $movimiento = MovimientoPersonal::create([
        'servidor_id'                 => $servidor->id,
        'tipo_movimiento'             => 'ingreso',
        'categoria'                   => CategoriaEventoVinculo::ACCION_DE_PERSONAL,
        'estado'                      => EstadoAccionPersonal::SUSCRITA,
        'tipo_nombramiento_propuesto' => 'nombramiento_provisional',
        'descripcion'                 => 'Ingreso formal',
        'fecha_efectiva'              => now()->toDateString(),
        'puesto_destino_id'           => $this->puesto->id,
        'unidad_destino_id'           => $this->unidad->id,
        'numero_contrato'             => 'CT-2026-0002',
        // Obligatoria para registrar el ingreso: en Código del Trabajo y
        // Servicios Profesionales se pacta en el contrato, así que se exige
        // al aprobar y no se deriva del puesto.
        'remuneracion_propuesta'      => 900.00,
        'autorizado_por'              => $this->user->id,
    ]);

    app(MovimientoPersonalStateService::class)->transicionar($movimiento, EstadoAccionPersonal::REGISTRADA);

    $despues = $this->getJson("/api/v1/expediente/servidores/{$servidor->id}");

    $despues->assertStatus(200);
    expect($despues->json('datos.pendiente_vinculacion'))->toBeFalse();
    expect($despues->json('datos.contrato_vigente.puesto_id'))->toBe($this->puesto->id);
});
