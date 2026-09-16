<?php

/*
| Los catálogos de viáticos se siembran con la instalación.
|
| `DatabaseSeeder` no llamaba a las tarifas, los transportes ni las categorías
| de comprobante: en una instalación nueva no se podía solicitar un viático
| (sin tarifa no hay monto), armar el itinerario ni registrar comprobantes.
*/

use Database\Seeders\CatalogoTransporteSeeder;
use Database\Seeders\CategoriaFacturaSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TarifaViaticoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('DatabaseSeeder llama a los tres seeders de viáticos', function () {
    // Correr DatabaseSeeder entero sembraría provincias, estructura y usuarios:
    // basta con comprobar que los tres están en su lista.
    $fuente = file_get_contents((new ReflectionClass(DatabaseSeeder::class))->getFileName());

    foreach ([TarifaViaticoSeeder::class, CatalogoTransporteSeeder::class, CategoriaFacturaSeeder::class] as $seeder) {
        expect($fuente)->toContain(class_basename($seeder).'::class');
    }
});

it('los tres se pueden volver a correr sin duplicar ni pisar lo ajustado', function () {
    $sembrar = function () {
        $this->seed(TarifaViaticoSeeder::class);
        $this->seed(CatalogoTransporteSeeder::class);
        $this->seed(CategoriaFacturaSeeder::class);
    };

    $sembrar();
    $conteos = [
        DB::table('tarifas_viatico')->count(),
        DB::table('catalogo_transportes')->count(),
        DB::table('empresas_transporte')->count(),
        DB::table('categorias_factura')->count(),
    ];

    // Financiero ajusta una tarifa; volver a sembrar no la devuelve al valor del seeder.
    DB::table('tarifas_viatico')
        ->where(['zona' => 'dentro_provincia', 'nivel' => 'servidor', 'tipo_tarifa' => 'con_pernocte'])
        ->update(['valor_diario' => 95]);

    $sembrar();

    expect($conteos)->toBe([6, 9, 35, 13])
        ->and([
            DB::table('tarifas_viatico')->count(),
            DB::table('catalogo_transportes')->count(),
            DB::table('empresas_transporte')->count(),
            DB::table('categorias_factura')->count(),
        ])->toBe($conteos)
        ->and((float) DB::table('tarifas_viatico')
            ->where(['zona' => 'dentro_provincia', 'nivel' => 'servidor', 'tipo_tarifa' => 'con_pernocte'])
            ->value('valor_diario'))->toBe(95.0);
});
