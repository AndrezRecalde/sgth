<?php

use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\Marca;
use App\Models\InventarioTi\TipoBien;
use App\Models\User;
use Database\Seeders\InventarioTiCatalogoSeeder;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/*
| La ficha de características de cada tipo de equipo (fase 5.3): qué pide el
| formulario para un computador, una impresora, un monitor o un escáner. Va
| en el tipo y no en su nombre, porque TIC puede renombrarlo.
*/

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolPermisoSeeder::class);
    $this->tecnico = User::factory()->create(['primer_login' => false]);
    $this->tecnico->assignRole('tecnico-dtic');
    $this->actingAs($this->tecnico, 'sanctum');
});

test('el catálogo siembra cada tipo con su ficha y el formulario la recibe', function () {
    $this->seed(InventarioTiCatalogoSeeder::class);

    $tipos = collect($this->getJson('/api/v1/inventario/catalogos')->assertOk()->json('datos.tipos'))->keyBy('nombre');

    expect($tipos['Computador']['ficha'])->toBe('computador')
        ->and($tipos['Portátil']['ficha'])->toBe('computador')
        ->and($tipos['Impresora']['ficha'])->toBe('impresora')
        ->and($tipos['Monitor']['ficha'])->toBe('monitor')
        ->and($tipos['Escáner']['ficha'])->toBe('escaner')
        ->and($tipos['Proyector']['ficha'])->toBe('general');
});

test('la migración reconoce los tipos por su nombre, sin distinguir mayúsculas', function () {
    $ids = collect(['IMPRESORA', 'Scanner', 'Laptop', 'Proyector'])
        ->mapWithKeys(fn (string $n) => [$n => DB::table('tipos_bien')->insertGetId(['nombre' => $n, 'anios_vida_util' => 0])]);
    DB::table('tipos_bien')->update(['ficha' => 'general']);

    $migracion = require database_path('migrations/2026_10_29_000000_ficha_de_los_tipos_de_equipo.php');
    $migracion->down();
    $migracion->up();

    expect(DB::table('tipos_bien')->where('id', $ids['IMPRESORA'])->value('ficha'))->toBe('impresora')
        ->and(DB::table('tipos_bien')->where('id', $ids['Scanner'])->value('ficha'))->toBe('escaner')
        ->and(DB::table('tipos_bien')->where('id', $ids['Laptop'])->value('ficha'))->toBe('computador')
        ->and(DB::table('tipos_bien')->where('id', $ids['Proyector'])->value('ficha'))->toBe('general');
});

test('TIC elige la ficha de un tipo nuevo o la cambia', function () {
    $id = $this->postJson('/api/v1/inventario/tipos', ['nombre' => 'Plotter', 'anios_vida_util' => 6, 'ficha' => 'impresora'])
        ->assertCreated()->json('datos.id');
    expect(TipoBien::find($id)->ficha->value)->toBe('impresora');

    $this->putJson("/api/v1/inventario/tipos/{$id}", ['ficha' => 'general'])->assertOk()->assertJsonPath('datos.ficha', 'general');
    $this->postJson('/api/v1/inventario/tipos', ['nombre' => 'Tableta', 'ficha' => 'telefono'])
        ->assertStatus(422)->assertJsonStructure(['errores' => ['ficha']]);
    // Sin decirla, el tipo nuevo es general.
    $sinFicha = $this->postJson('/api/v1/inventario/tipos', ['nombre' => 'Lector de huellas'])->assertCreated()->json('datos.id');
    expect(TipoBien::find($sinFicha)->ficha->value)->toBe('general');
});

test('una impresora guarda su conexión, su consumible y si es a color', function () {
    $impresora = TipoBien::create(['nombre' => 'Impresora', 'ficha' => 'impresora']);

    $id = $this->postJson('/api/v1/inventario/bienes', [
        'codigo_institucional' => '004512', 'tipo_bien_id' => $impresora->id, 'marca_id' => Marca::create(['nombre' => 'HP'])->id,
        'modelo' => 'LaserJet P1102w', 'direccion_ip' => '192.168.10.45',
        'caracteristicas_tecnicas' => ['conexion' => 'red', 'consumible' => 'HP 85A', 'color' => 'monocromatica'],
    ])->assertCreated()->json('datos.id');

    expect(BienInformatico::find($id)->caracteristicas_tecnicas)
        ->toBe(['conexion' => 'red', 'consumible' => 'HP 85A', 'color' => 'monocromatica']);

    // La ficha del equipo dice la de su tipo: la pantalla muestra los campos de esa ficha.
    $this->getJson("/api/v1/inventario/bienes/{$id}")->assertOk()->assertJsonPath('datos.tipo.ficha', 'impresora');
});
