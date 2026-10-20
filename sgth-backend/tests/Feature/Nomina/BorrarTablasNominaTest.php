<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
| Las tablas de la nómina del SGTH se borran (2026-10-20): no calcula la nómina,
| y su código se fue en el #491. Borrar no se deshace, así que con datos en las
| tablas de cálculo la migración se niega hasta que se confirma con
| SGTH_BORRAR_NOMINA=1, después de respaldarlas.
*/

beforeEach(function () {
    $this->migracion = require database_path('migrations/2026_11_14_000000_borrar_tablas_de_nomina.php');
    $this->tablas = ['nominas', 'detalle_nomina', 'roles_pago', 'conceptos_nomina', 'descuentos_recurrentes', 'handoffs_erp'];
});

afterEach(fn () => putenv('SGTH_BORRAR_NOMINA'));

test('las tablas de la nómina ya no están, ni el permiso del rol de pago propio', function () {
    foreach ($this->tablas as $tabla) {
        expect(Schema::hasTable($tabla))->toBeFalse("{$tabla} sigue");
    }

    expect(Permission::where('name', 'ver-rol-pago-propio')->exists())->toBeFalse();
});

test('el down las vuelve a crear vacías, y con solo el catálogo se borran sin preguntar', function () {
    $this->migracion->down();

    expect(Schema::hasTable('roles_pago'))->toBeTrue()
        // Con su forma final: la de la fase 7.3c.
        ->and(Schema::hasColumn('nominas', 'referencial'))->toBeTrue()
        ->and(Schema::hasColumn('detalle_nomina', 'descuento_recurrente_id'))->toBeTrue()
        ->and(DB::table('roles_pago')->count())->toBe(0)
        ->and(DB::table('conceptos_nomina')->count())->toBeGreaterThan(0)
        ->and(Permission::where('name', 'ver-rol-pago-propio')->exists())->toBeTrue();

    $this->migracion->up();

    expect(Schema::hasTable('conceptos_nomina'))->toBeFalse()
        ->and(Permission::where('name', 'ver-rol-pago-propio')->exists())->toBeFalse();
});

test('con nóminas guardadas se niega y no borra nada, hasta que se confirma', function () {
    $this->migracion->down();
    DB::table('nominas')->insert([
        'periodo' => '2026-08', 'fecha_inicio' => '2026-08-01', 'fecha_fin' => '2026-08-31', 'estado' => 'cerrada',
        'total_ingresos' => 1000, 'total_descuentos' => 100, 'total_neto' => 900,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(fn () => $this->migracion->up())->toThrow(RuntimeException::class, 'nominas: 1');

    foreach ($this->tablas as $tabla) {
        expect(Schema::hasTable($tabla))->toBeTrue("{$tabla} se borró sin confirmar");
    }

    putenv('SGTH_BORRAR_NOMINA=1');
    $this->migracion->up();

    expect(Schema::hasTable('nominas'))->toBeFalse();
});
