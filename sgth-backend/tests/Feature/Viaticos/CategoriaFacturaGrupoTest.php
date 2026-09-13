<?php

/*
| Qué categorías de comprobante justifican el 70 % del viático.
|
| La columna `grupo` nació con `movilizacion` por defecto y el seeder nunca la
| llenó: ningún comprobante justificaba el 70 %. Decidido con el usuario:
| hospedaje y alimentación son `viatico`, y «Viático diario» se desactiva.
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class, RefreshDatabase::class);

$grupos = fn () => DB::table('categorias_factura')->orderBy('orden')->pluck('grupo', 'codigo')->all();

it('el seeder separa hospedaje y alimentación del resto y desactiva el viático diario', function () use ($grupos) {
    $this->seed(\Database\Seeders\CategoriaFacturaSeeder::class);

    $viatico = collect($grupos())->filter(fn ($g) => $g === 'viatico')->keys()->all();

    expect($viatico)->toBe(['hospedaje', 'alimentacion'])
        ->and(DB::table('categorias_factura')->where('activo', false)->pluck('codigo')->all())->toBe(['viatico_diario']);
});

it('la migración corrige una base sembrada con el grupo por defecto', function () use ($grupos) {
    // Como quedaron las bases existentes: todo movilización y todo activo.
    $this->seed(\Database\Seeders\CategoriaFacturaSeeder::class);
    DB::table('categorias_factura')->update(['grupo' => 'movilizacion', 'activo' => true]);

    $migracion = require database_path('migrations/2026_09_13_110000_corregir_grupo_categorias_factura.php');
    $migracion->up();
    $migracion->up(); // idempotente

    expect($grupos()['hospedaje'])->toBe('viatico')
        ->and($grupos()['alimentacion'])->toBe('viatico')
        ->and($grupos()['transporte_terrestre'])->toBe('movilizacion')
        ->and(DB::table('categorias_factura')->where('codigo', 'viatico_diario')->value('activo'))->toBeFalse();

    $migracion->down();
    expect(collect($grupos())->unique()->values()->all())->toBe(['movilizacion'])
        ->and(DB::table('categorias_factura')->where('activo', false)->count())->toBe(0);
});

it('el catálogo del formulario ya no ofrece el viático diario', function () {
    $this->seed(\Database\Seeders\CategoriaFacturaSeeder::class);
    $this->actingAs(\App\Models\User::factory()->create(), 'sanctum')
        ->getJson('/api/v1/viaticos/catalogos/categorias-factura')
        ->assertOk()
        ->assertJsonMissing(['codigo' => 'viatico_diario'])
        ->assertJsonFragment(['codigo' => 'hospedaje', 'grupo' => 'viatico']);
});
