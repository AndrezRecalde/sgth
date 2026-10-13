<?php

use App\Enums\EstadoBien;
use App\Enums\RegimenLaboral;
use App\Models\Expediente\Servidor;
use App\Models\InventarioTi\AsignacionBien;
use App\Models\InventarioTi\BajaBien;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\Marca;
use App\Models\InventarioTi\TipoBien;
use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
| La baja de los equipos y las etiquetas con QR (docs/diseno-subsistema-tic.md,
| fase 1.6). Antes «dar de baja» cambiaba el estado y respondía que el acta
| quedaba pendiente: no se guardaba ni el motivo.
*/

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Servidor::unguard();
    BienInformatico::unguard();
    config(['inventario.corte_soporte_anterior' => now()->addYear()->toDateString()]);
    $this->seed(RolPermisoSeeder::class);

    $this->tecnico = User::factory()->create(['primer_login' => false, 'activo' => true]);
    $this->tecnico->assignRole('tecnico-dtic');
    $this->jefe = User::factory()->create(['primer_login' => false, 'activo' => true]);
    $this->jefe->assignRole('jefe-tic');

    $this->servidor = Servidor::create([
        'cedula' => '0807777771', 'nombre' => 'Paola', 'apellido' => 'Benavides',
        'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true,
    ]);

    $tipo = TipoBien::create(['nombre' => 'Computador']);
    $marca = Marca::create(['nombre' => 'DELL']);
    $this->equipo = BienInformatico::create([
        'codigo_qr' => '1774-QR', 'codigo_institucional' => '001774', 'numero_serie' => 'SN-1774',
        'tipo_bien_id' => $tipo->id, 'marca_id' => $marca->id, 'modelo' => 'OptiPlex 3020',
        'estado_operativo' => 'disponible', 'condicion_fisica' => 'malo',
    ]);
    $componente = fn (string $codigo, string $nombre) => BienInformatico::create([
        'codigo_qr' => "{$codigo}-QR", 'codigo_institucional' => $codigo,
        'tipo_bien_id' => TipoBien::firstOrCreate(['nombre' => $nombre])->id, 'marca_id' => $marca->id,
        'estado_operativo' => 'disponible', 'condicion_fisica' => 'bueno', 'bien_padre_id' => $this->equipo->id,
    ]);
    $this->monitor = $componente('001775', 'Monitor');
    $this->teclado = $componente('001776', 'Teclado');

    $this->actingAs($this->tecnico, 'sanctum');
});

function proponerBaja(array $datos = []): \Illuminate\Testing\TestResponse
{
    return test()->postJson('/api/v1/inventario/bajas', array_merge([
        'bien_informatico_id' => test()->equipo->id,
        'motivo'              => 'dano_irreparable',
        'diagnostico'         => 'La placa madre no enciende; el repuesto ya no se fabrica.',
        'recomendacion'       => 'Dar de baja y reponer el equipo.',
        'fecha_informe'       => now()->subDays(3)->toDateString(),
    ], $datos));
}

function aprobarBaja(int $id, array $datos = []): \Illuminate\Testing\TestResponse
{
    return test()->actingAs(test()->jefe, 'sanctum')->postJson("/api/v1/inventario/bajas/{$id}/aprobar", array_merge([
        'fecha_baja'       => now()->subDay()->toDateString(),
        'memorando_numero' => 'Memorando Nº 120 GADPE-GTIC-2026',
    ], $datos));
}

test('el tecnico propone la baja con su informe y el equipo sigue en servicio', function () {
    $datos = proponerBaja(['soporte_anterior' => '48990'])->assertCreated()->json('datos');

    expect($datos['numero'])->toBe('IB-' . str_pad((string) $datos['id'], 6, '0', STR_PAD_LEFT))
        ->and($datos['estado'])->toBe('propuesta')
        ->and($datos['motivo_etiqueta'])->toBe('Daño irreparable')
        ->and($datos['informado_por'])->toBe($this->tecnico->nombre_completo)
        ->and($datos['soporte_anterior'])->toBe('48990')
        ->and($this->equipo->fresh()->estado_operativo)->toBe(EstadoBien::DISPONIBLE);

    // La ficha avisa de la baja en curso.
    expect($this->getJson("/api/v1/inventario/bienes/{$this->equipo->id}")->json('datos.baja.estado'))->toBe('propuesta');
});

test('el informe tecnico se valida y no hay dos bajas en curso del mismo equipo', function () {
    proponerBaja(['diagnostico' => 'No sirve.'])->assertStatus(422)->assertJsonStructure(['errores' => ['diagnostico']]);
    proponerBaja(['motivo' => 'capricho'])->assertStatus(422)->assertJsonStructure(['errores' => ['motivo']]);
    proponerBaja(['fecha_informe' => now()->addDay()->toDateString()])->assertStatus(422);

    proponerBaja()->assertCreated();
    proponerBaja()->assertStatus(422);
});

test('el indice no deja dos bajas vigentes aunque se salte el servicio', function () {
    $fila = [
        'bien_informatico_id' => $this->equipo->id, 'estado' => 'propuesta', 'motivo' => 'obsolescencia',
        'diagnostico' => 'x', 'fecha_informe' => now()->toDateString(),
    ];
    BajaBien::create($fila);
    BajaBien::create([...$fila, 'estado' => 'rechazada']); // Una rechazada no cuenta.

    expect(fn () => BajaBien::create([...$fila, 'estado' => 'aprobada']))->toThrow(QueryException::class);
});

test('aprobar devuelve la custodia, saca al equipo del servicio y decide sobre sus componentes', function () {
    $this->equipo->update(['prestable' => false]);
    $this->postJson('/api/v1/inventario/asignaciones', [
        'bien_informatico_id' => $this->equipo->id, 'servidor_id' => $this->servidor->id,
        'fecha_asignacion'    => now()->subMonths(2)->toDateString(),
    ])->assertCreated();

    $id = proponerBaja()->json('datos.id');
    $datos = aprobarBaja($id, ['componentes_baja' => [$this->monitor->id]])->assertOk()->json('datos');

    expect($datos['estado'])->toBe('aprobada')
        ->and($datos['aprobado_por'])->toBe($this->jefe->nombre_completo)
        ->and($datos['memorando_numero'])->toBe('Memorando Nº 120 GADPE-GTIC-2026')
        ->and($datos['componentes'])->toHaveCount(1)
        ->and($datos['componentes'][0]['codigo_institucional'])->toBe('001775');

    $equipo = $this->equipo->fresh();
    expect($equipo->estado_operativo)->toBe(EstadoBien::DADO_DE_BAJA)
        ->and($equipo->asignacionVigente)->toBeNull();

    $custodia = AsignacionBien::where('bien_informatico_id', $equipo->id)->latest('id')->first();
    expect($custodia->estado)->toBe('devuelta')
        ->and($custodia->fecha_devolucion->toDateString())->toBe(now()->subDay()->toDateString());

    // El monitor sale con él; el teclado se suelta y sigue en servicio.
    expect($this->monitor->fresh()->estado_operativo)->toBe(EstadoBien::DADO_DE_BAJA)
        ->and($this->monitor->fresh()->bien_padre_id)->toBeNull()
        ->and($this->teclado->fresh()->estado_operativo)->toBe(EstadoBien::DISPONIBLE)
        ->and($this->teclado->fresh()->bien_padre_id)->toBeNull();

    // El historial del equipo lleva la baja.
    expect($this->getJson("/api/v1/inventario/bienes/{$equipo->id}/historial")->json('datos.bajas.0.estado'))->toBe('aprobada');
});

test('la aprobacion pide memorando, una fecha no anterior al informe y componentes del equipo', function () {
    $id = proponerBaja()->json('datos.id');
    $ajeno = BienInformatico::create([
        'codigo_qr' => '9999-QR', 'codigo_institucional' => '009999',
        'tipo_bien_id' => $this->equipo->tipo_bien_id, 'marca_id' => $this->equipo->marca_id,
        'estado_operativo' => 'disponible', 'condicion_fisica' => 'bueno',
    ]);

    aprobarBaja($id, ['memorando_numero' => ''])->assertStatus(422)->assertJsonStructure(['errores' => ['memorando_numero']]);
    aprobarBaja($id, ['fecha_baja' => now()->subDays(10)->toDateString()])->assertStatus(422)->assertJsonStructure(['errores' => ['fecha_baja']]);
    aprobarBaja($id, ['componentes_baja' => [$ajeno->id]])->assertStatus(422)->assertJsonStructure(['errores' => ['componentes_baja.0']]);

    expect(BajaBien::find($id)->estado->value)->toBe('propuesta');
});

test('el tecnico no aprueba ni rechaza', function () {
    $id = proponerBaja()->json('datos.id');

    $this->postJson("/api/v1/inventario/bajas/{$id}/aprobar", ['fecha_baja' => now()->toDateString(), 'memorando_numero' => 'M-1'])
        ->assertForbidden();
    $this->postJson("/api/v1/inventario/bajas/{$id}/rechazar", ['motivo' => 'No procede'])->assertForbidden();
});

test('rechazar deja el equipo como estaba y permite volver a proponer', function () {
    $id = proponerBaja()->json('datos.id');

    $this->actingAs($this->jefe, 'sanctum')->postJson("/api/v1/inventario/bajas/{$id}/rechazar", ['motivo' => ''])->assertStatus(422);
    $datos = $this->postJson("/api/v1/inventario/bajas/{$id}/rechazar", ['motivo' => 'Se puede reparar con garantía.'])
        ->assertOk()->json('datos');

    expect($datos['estado'])->toBe('rechazada')
        ->and($datos['rechazado_por'])->toBe($this->jefe->nombre_completo)
        ->and($this->equipo->fresh()->estado_operativo)->toBe(EstadoBien::DISPONIBLE);

    // Ya resuelta, no se vuelve a resolver.
    aprobarBaja($id)->assertStatus(422);
    $this->actingAs($this->tecnico, 'sanctum');
    proponerBaja()->assertCreated();
});

test('un equipo dado de baja no recibe otra propuesta', function () {
    aprobarBaja(proponerBaja()->json('datos.id'))->assertOk();
    $this->actingAs($this->tecnico, 'sanctum');

    proponerBaja()->assertStatus(422);
});

test('el memorando y la entrega a activos fijos van sobre la baja aprobada', function () {
    Storage::fake('local');
    $id = proponerBaja()->json('datos.id');
    $pdf = UploadedFile::fake()->create('memorando.pdf', 50, 'application/pdf');

    $this->actingAs($this->jefe, 'sanctum');
    $this->post("/api/v1/inventario/bajas/{$id}/memorando", ['archivo' => $pdf], ['Accept' => 'application/json'])->assertStatus(422);
    $this->postJson("/api/v1/inventario/bajas/{$id}/entrega-activos", ['fecha' => now()->toDateString()])->assertStatus(422);

    aprobarBaja($id)->assertOk();
    $datos = $this->post("/api/v1/inventario/bajas/{$id}/memorando", ['archivo' => $pdf], ['Accept' => 'application/json'])
        ->assertOk()->json('datos');
    expect($datos['tiene_memorando'])->toBeTrue();
    $this->get("/api/v1/inventario/bajas/{$id}/memorando")->assertOk();

    $this->postJson("/api/v1/inventario/bajas/{$id}/entrega-activos", ['fecha' => now()->subDays(5)->toDateString()])->assertStatus(422);
    $datos = $this->postJson("/api/v1/inventario/bajas/{$id}/entrega-activos", ['fecha' => now()->toDateString()])->assertOk()->json('datos');
    expect($datos['entregado_activos_en'])->toBe(now()->toDateString());

    $pendientes = $this->getJson('/api/v1/inventario/bajas?pendientes_de_entrega=1')->assertOk()->json('datos');
    expect($pendientes)->toBeEmpty();
});

test('el informe tecnico sale en pdf en cada estado', function () {
    $id = proponerBaja()->json('datos.id');

    $this->get("/api/v1/inventario/bajas/{$id}/informe")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename="informe-baja-IB-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT) . '.pdf"');

    aprobarBaja($id, ['componentes_baja' => [$this->monitor->id]])->assertOk();
    $this->get("/api/v1/inventario/bajas/{$id}/informe")->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

test('el listado filtra por estado y por codigo', function () {
    proponerBaja()->assertCreated();

    expect($this->getJson('/api/v1/inventario/bajas?estado=propuesta')->json('datos'))->toHaveCount(1)
        ->and($this->getJson('/api/v1/inventario/bajas?estado=aprobada')->json('datos'))->toHaveCount(0)
        ->and($this->getJson('/api/v1/inventario/bajas?search=1774')->json('datos.0.bien.codigo_institucional'))->toBe('001774');
});

test('un equipo con una baja en su historia no se borra', function () {
    $id = proponerBaja()->json('datos.id');
    $this->actingAs($this->jefe, 'sanctum')->postJson("/api/v1/inventario/bajas/{$id}/rechazar", ['motivo' => 'Se repara.'])->assertOk();
    $this->monitor->update(['bien_padre_id' => null]);
    $this->teclado->update(['bien_padre_id' => null]);

    $this->actingAs($this->tecnico, 'sanctum')->deleteJson("/api/v1/inventario/bienes/{$this->equipo->id}")->assertStatus(422);
});

// ── Etiquetas con QR ───────────────────────────────────────────────────────

test('la etiqueta de un equipo sale en pdf con su codigo', function () {
    $this->get("/api/v1/inventario/bienes/{$this->equipo->id}/etiqueta")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename="etiqueta-001774.pdf"');
});

test('el qr lleva la direccion de la ficha del equipo', function () {
    config(['app.frontend_url' => 'https://sgth.gadpe.gob.ec/']);

    expect(app(\App\Services\InventarioTi\EtiquetaQrService::class)->urlFicha($this->equipo))
        ->toBe("https://sgth.gadpe.gob.ec/tic/inventario/equipos/{$this->equipo->id}");
});

test('las etiquetas en lote siguen el filtro del listado y tienen tope', function () {
    $this->get('/api/v1/inventario/etiquetas?search=00177')
        ->assertOk()
        ->assertHeader('Content-Disposition', 'inline; filename="etiquetas-equipos.pdf"');
    $this->get("/api/v1/inventario/etiquetas?ids={$this->monitor->id},{$this->teclado->id}")->assertOk();

    $this->getJson('/api/v1/inventario/etiquetas?search=no-existe')->assertStatus(422);

    $muchos = collect(range(1, \App\Services\InventarioTi\EtiquetaQrService::MAXIMO))->map(fn ($i) => [
        'codigo_qr' => "L{$i}-QR", 'codigo_institucional' => 'L' . str_pad((string) $i, 5, '0', STR_PAD_LEFT),
        'tipo_bien_id' => $this->equipo->tipo_bien_id, 'marca_id' => $this->equipo->marca_id,
        'estado_operativo' => 'disponible', 'condicion_fisica' => 'bueno',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    BienInformatico::insert($muchos->all());
    $this->getJson('/api/v1/inventario/etiquetas')->assertStatus(422);
});

test('quien no ve el inventario no imprime etiquetas', function () {
    $this->actingAs(User::factory()->create(['primer_login' => false]), 'sanctum');

    $this->getJson("/api/v1/inventario/bienes/{$this->equipo->id}/etiqueta")->assertForbidden();
    $this->getJson('/api/v1/inventario/etiquetas')->assertForbidden();
});
