<?php

namespace Tests\Feature\Expediente;

use App\Enums\CategoriaEventoVinculo;
use App\Enums\EstadoAccionPersonal;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Expediente\ContratoServidorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);

    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'UATH-01',
        'nombre' => 'Unidad de Talento Humano',
        'nivel'  => 1,
    ]);

    $this->puesto = Puesto::create([
        'codigo'                   => 'P-01',
        'unidad_administrativa_id' => $this->unidad->id,
        'plazas'                   => 2,
    ]);

    $this->adminUath = User::factory()->create();
    $this->adminUath->assignRole('admin-uath');

    $this->servidor = Servidor::create([
        'user_id'         => User::factory()->create()->id,
        'cedula'          => '1111111111',
        'nombre'          => 'Titular',
        'apellido'        => 'Test',
        'regimen_laboral' => 'losep',
    ]);

    $this->contrato = ContratoServidor::create([
        'servidor_id'              => $this->servidor->id,
        'tipo_nombramiento'        => 'nombramiento_permanente',
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id'                => $this->puesto->id,
        'fecha_inicio'             => '2020-01-01',
        'estado'                   => 'vigente',
    ]);
});

test('un contrato vigente ya no se puede editar in-place vía PUT — la ruta fue removida', function () {
    $response = $this->actingAs($this->adminUath, 'sanctum')
        ->putJson(
            "/api/v1/expediente/servidores/{$this->servidor->id}/contratos/{$this->contrato->id}",
            ['tipo_nombramiento' => 'nombramiento_provisional']
        );

    // El apiResource ya no registra update para esta URI (solo GET/HEAD):
    // Laravel lanza MethodNotAllowedHttpException, que el handler global
    // (bootstrap/app.php) ahora traduce a su código real.
    $response->assertStatus(405);

    expect($this->contrato->fresh()->tipo_nombramiento->value)
        ->toBe('nombramiento_permanente');
});

test('un contrato vigente ya no se puede eliminar vía DELETE — la ruta fue removida', function () {
    $response = $this->actingAs($this->adminUath, 'sanctum')
        ->deleteJson(
            "/api/v1/expediente/servidores/{$this->servidor->id}/contratos/{$this->contrato->id}"
        );

    $response->assertStatus(405);

    expect(ContratoServidor::find($this->contrato->id))->not->toBeNull();
});

test('cerrar un contrato exige motivo_fin y lo deja en estado terminado', function () {
    $response = $this->actingAs($this->adminUath, 'sanctum')
        ->putJson(
            "/api/v1/expediente/servidores/{$this->servidor->id}/contratos/{$this->contrato->id}/cerrar",
            []
        );

    $response->assertStatus(422)
        ->assertJsonStructure(['errores' => ['motivo_fin']]);

    $response2 = $this->actingAs($this->adminUath, 'sanctum')
        ->putJson(
            "/api/v1/expediente/servidores/{$this->servidor->id}/contratos/{$this->contrato->id}/cerrar",
            ['motivo_fin' => 'Fin de periodo de prueba.']
        );

    $response2->assertStatus(200);

    $this->contrato->refresh();
    expect($this->contrato->estado->value)->toBe('terminado');
    expect($this->contrato->motivo_fin)->toBe('Fin de periodo de prueba.');
    expect($this->contrato->fecha_fin)->not->toBeNull();
});

test('sincronizarRegimenServidor genera un MovimientoPersonal en vez de mutar Servidor en silencio', function () {
    $this->actingAs($this->adminUath, 'sanctum');

    $servidor = Servidor::create([
        'user_id'         => User::factory()->create()->id,
        'cedula'          => '2222222222',
        'nombre'          => 'Nuevo',
        'apellido'        => 'Ingreso',
        'regimen_laboral' => 'losep',
    ]);

    expect(MovimientoPersonal::where('servidor_id', $servidor->id)->count())->toBe(0);

    /** @var ContratoServidorService $service */
    $service = app(ContratoServidorService::class);

    $service->crear($servidor->id, [
        'tipo_nombramiento'        => 'nombramiento_provisional',
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id'                => $this->puesto->id,
        'fecha_inicio'             => '2026-07-01',
        'estado'                   => 'vigente',
    ]);

    $movimiento = MovimientoPersonal::where('servidor_id', $servidor->id)
        ->where('tipo_movimiento', 'novedad_contrato')
        ->first();

    expect($movimiento)->not->toBeNull();
    // Registrada desde 2026-08-04: la bitácora documenta un hecho consumado
    // —el contrato ya existe—, no una solicitud pendiente de aprobación. En
    // borrador aparecía en la bandeja pidiendo aprobar algo ya ocurrido.
    expect($movimiento->estado)->toBe(EstadoAccionPersonal::REGISTRADA);
    expect($movimiento->categoria)->toBe(CategoriaEventoVinculo::ACCION_DE_PERSONAL);
    expect($movimiento->puesto_destino_id)->toBe($this->puesto->id);

    $servidor->refresh();
    expect($servidor->tipo_nombramiento->value)->toBe('nombramiento_provisional');
    expect($servidor->puesto_id)->toBe($this->puesto->id);
});
