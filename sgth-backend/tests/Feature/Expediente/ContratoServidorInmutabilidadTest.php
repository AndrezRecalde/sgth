<?php

namespace Tests\Feature\Expediente;

use App\Enums\CategoriaEventoVinculo;
use App\Enums\TipoEventoVinculo;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\EventoVinculo;
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

    // Ninguna ruta responde en esta URI. Era 405 mientras existía `GET
    // contratos/{id}`, que nadie usaba y se retiró el 2026-10-04.
    $response->assertNotFound();

    expect($this->contrato->fresh()->tipo_nombramiento->value)
        ->toBe('nombramiento_permanente');
});

test('un contrato vigente ya no se puede eliminar vía DELETE — la ruta fue removida', function () {
    $response = $this->actingAs($this->adminUath, 'sanctum')
        ->deleteJson(
            "/api/v1/expediente/servidores/{$this->servidor->id}/contratos/{$this->contrato->id}"
        );

    $response->assertNotFound();

    expect(ContratoServidor::find($this->contrato->id))->not->toBeNull();
});

test('un vínculo no se cierra por API sin acción de personal — la ruta fue removida', function () {
    // `PUT contratos/{id}/cerrar` se retiró el 2026-10-03. Ninguna pantalla
    // lo usaba y cerraba el vínculo sin acción de personal que lo respaldara,
    // sin `movimiento_cierre_id` y sin devolver el puesto: el servidor quedaba
    // asignado al puesto de un vínculo terminado. Un vínculo se cierra con la
    // acción de cesación, que llama a ContratoServidorService::cerrar().
    $response = $this->actingAs($this->adminUath, 'sanctum')
        ->putJson(
            "/api/v1/expediente/servidores/{$this->servidor->id}/contratos/{$this->contrato->id}/cerrar",
            ['motivo_fin' => 'Fin de periodo de prueba.']
        );

    $response->assertNotFound();

    $this->contrato->refresh();
    expect($this->contrato->estado->value)->toBe('vigente')
        ->and($this->contrato->fecha_fin)->toBeNull();
});

test('sincronizarRegimenServidor anota el contrato en la bitácora del vínculo en vez de mutar Servidor en silencio', function () {
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

    // Hasta la fase 1.2 era un MovimientoPersonal 'novedad_contrato' en
    // REGISTRADA: la bitácora documenta un hecho consumado —el contrato ya
    // existe—, no una acción que alguien deba aprobar, y ya no comparte tabla
    // con ellas.
    expect(MovimientoPersonal::where('servidor_id', $servidor->id)->count())->toBe(0);

    $evento = EventoVinculo::where('servidor_id', $servidor->id)->sole();

    expect($evento->tipo)->toBe(TipoEventoVinculo::CONTRATO_REGISTRADO);
    expect($evento->contrato_servidor_id)->toBe($servidor->contratoVigente->id);
    expect($evento->datos['categoria'])->toBe(CategoriaEventoVinculo::ACCION_DE_PERSONAL->value);
    expect($evento->datos['puesto_destino_id'])->toBe($this->puesto->id);

    $servidor->refresh();
    expect($servidor->tipo_nombramiento->value)->toBe('nombramiento_provisional');
    expect($servidor->puesto_id)->toBe($this->puesto->id);
});
