<?php

use App\Enums\EstadoBien;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\ContratoVigencia;
use App\Models\InventarioTi\Marca;
use App\Models\InventarioTi\MantenimientoBien;
use App\Models\InventarioTi\TipoBien;
use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
| Los mantenimientos de los equipos (docs/diseno-subsistema-tic.md, fase 1.5).
| El controlador respondía «Mantenimiento programado» sin guardar nada.
*/

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    BienInformatico::unguard();
    config(['inventario.corte_soporte_anterior' => now()->addYear()->toDateString()]);
    $this->seed(RolPermisoSeeder::class);

    $this->tecnico = User::factory()->create(['primer_login' => false, 'activo' => true]);
    $this->tecnico->assignRole('tecnico-dtic');

    $this->contrato = ContratoVigencia::create(['proceso' => 'SIE-GADPE-018-2021', 'proveedor' => 'Proveedor S.A.']);
    $this->equipo = BienInformatico::create([
        'codigo_qr' => '1774-QR', 'codigo_institucional' => '001774',
        'tipo_bien_id' => TipoBien::create(['nombre' => 'Computador'])->id,
        'marca_id' => Marca::create(['nombre' => 'DELL'])->id,
        'contrato_vigencia_id' => $this->contrato->id,
        'estado_operativo' => 'en_mantenimiento', 'condicion_fisica' => 'regular',
    ]);

    $this->actingAs($this->tecnico, 'sanctum');
});

function mantenimientoDePrueba(array $datos = []): \Illuminate\Testing\TestResponse
{
    return test()->postJson('/api/v1/inventario/mantenimientos', array_merge([
        'bien_informatico_id' => test()->equipo->id,
        'tipo_mantenimiento'  => 'correctivo',
        'realizado_por'       => 'tic',
        'fecha_mantenimiento' => now()->toDateString(),
        'descripcion'         => 'Cambio de la fuente de poder.',
    ], $datos));
}

test('el mantenimiento de TIC queda con su tecnico y puede devolver el equipo al servicio', function () {
    $datos = mantenimientoDePrueba(['estado_equipo' => 'disponible', 'costo' => 35.5, 'soporte_anterior' => '48700'])
        ->assertCreated()->json('datos');

    expect($datos['tipo_etiqueta'])->toBe('Correctivo')
        ->and($datos['tecnico']['id'])->toBe($this->tecnico->id)
        ->and($datos['contrato_vigencia'])->toBeNull()
        ->and($datos['costo'])->toBe(35.5)
        ->and($datos['soporte_anterior'])->toBe('48700')
        // Sin custodio, «vuelve al servicio» es «disponible».
        ->and($this->equipo->fresh()->estado_operativo)->toBe(EstadoBien::DISPONIBLE);
});

test('el del proveedor va ligado al contrato del equipo y sin tecnico de TIC', function () {
    $datos = mantenimientoDePrueba(['realizado_por' => 'proveedor', 'tipo_mantenimiento' => 'preventivo', 'tecnico_id' => $this->tecnico->id, 'estado_equipo' => 'en_garantia'])
        ->assertCreated()->json('datos');

    expect($datos['contrato_vigencia']['proceso'])->toBe('SIE-GADPE-018-2021')
        ->and($datos['tecnico'])->toBeNull()
        ->and($this->equipo->fresh()->estado_operativo)->toBe(EstadoBien::EN_GARANTIA);
});

test('la fecha es obligatoria y no futura, y un equipo de baja no recibe mantenimientos', function () {
    mantenimientoDePrueba(['fecha_mantenimiento' => null])->assertStatus(422)->assertJsonStructure(['errores' => ['fecha_mantenimiento']]);
    mantenimientoDePrueba(['fecha_mantenimiento' => now()->addDay()->toDateString()])->assertStatus(422);
    mantenimientoDePrueba(['estado_equipo' => 'dado_de_baja'])->assertStatus(422)->assertJsonStructure(['errores' => ['estado_equipo']]);

    $this->equipo->update(['estado_operativo' => EstadoBien::DADO_DE_BAJA]);
    mantenimientoDePrueba()->assertStatus(422);
});

test('pasada la fecha de corte el soporte anterior ya no se acepta', function () {
    config(['inventario.corte_soporte_anterior' => now()->subDay()->toDateString()]);

    mantenimientoDePrueba(['soporte_anterior' => '48700'])->assertStatus(422)->assertJsonStructure(['errores' => ['soporte_anterior']]);
});

test('anular deja el mantenimiento en el historial pero fuera del listado', function () {
    $id = mantenimientoDePrueba()->assertCreated()->json('datos.id');

    $this->postJson("/api/v1/inventario/mantenimientos/{$id}/anular", ['motivo' => 'Se registró en el equipo equivocado.'])
        ->assertOk()->assertJsonPath('datos.anulado', true);
    $this->postJson("/api/v1/inventario/mantenimientos/{$id}/anular", ['motivo' => 'Otra vez la misma anulación.'])->assertStatus(422);

    expect($this->getJson('/api/v1/inventario/mantenimientos')->json('meta.total'))->toBe(0)
        ->and($this->getJson('/api/v1/inventario/mantenimientos?incluir_anulados=1')->json('meta.total'))->toBe(1)
        ->and($this->getJson("/api/v1/inventario/bienes/{$this->equipo->id}/historial")->json('datos.mantenimientos.0.motivo_anulacion'))
        ->toBe('Se registró en el equipo equivocado.')
        ->and(MantenimientoBien::withTrashed()->count())->toBe(1);
});

test('el listado filtra por tipo y por quien lo hizo y deja los sin fecha al final', function () {
    mantenimientoDePrueba(['tipo_mantenimiento' => 'preventivo', 'fecha_mantenimiento' => now()->subMonth()->toDateString()])->assertCreated();
    mantenimientoDePrueba(['realizado_por' => 'proveedor'])->assertCreated();
    MantenimientoBien::create([
        'bien_informatico_id' => $this->equipo->id, 'tipo_mantenimiento' => 'repotenciacion',
        'fecha_mantenimiento' => null, 'descripcion' => 'Cambio del disco a SSD. Del Excel de TIC.', 'soporte_anterior' => '45496',
    ]);

    expect(array_column($this->getJson('/api/v1/inventario/mantenimientos')->json('datos'), 'tipo_mantenimiento'))
        ->toBe(['correctivo', 'preventivo', 'repotenciacion'])
        ->and($this->getJson('/api/v1/inventario/mantenimientos?realizado_por=proveedor')->json('meta.total'))->toBe(1)
        ->and($this->getJson('/api/v1/inventario/mantenimientos?search=45496')->json('meta.total'))->toBe(1);
});

test('los tecnicos son quienes registran mantenimientos y el auditor solo consulta', function () {
    $servidor = User::factory()->create(['primer_login' => false, 'activo' => true]);
    $servidor->assignRole('servidor');

    $ids = array_column($this->getJson('/api/v1/inventario/mantenimientos/tecnicos')->assertOk()->json('datos'), 'id');
    expect($ids)->toContain($this->tecnico->id)->not->toContain($servidor->id);

    $auditor = User::factory()->create(['primer_login' => false]);
    $auditor->assignRole('auditor');
    $this->actingAs($auditor, 'sanctum');

    $this->getJson('/api/v1/inventario/mantenimientos')->assertOk();
    mantenimientoDePrueba()->assertForbidden();
});
