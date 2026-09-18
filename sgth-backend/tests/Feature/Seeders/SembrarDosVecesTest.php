<?php

/*
| Volver a sembrar una base en uso no pisa nada (2026-09-18).
|
| Dos seeders lo impedían: UnidadAdministrativaSeeder le ponía a cada unidad
| un código nuevo con sufijo aleatorio (`GESTION-DE-TALE-88`), borrando los
| códigos jerárquicos que son el prefijo de los viáticos; y AdminTiSeeder le
| volvía a poner la contraseña inicial al administrador. Por eso no se podía
| correr `db:seed` sobre una base existente.
*/

use App\Models\Estructura\UnidadAdministrativa;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('las unidades nacen con códigos jerárquicos', function () {
    $this->seed(DatabaseSeeder::class);

    $raiz = UnidadAdministrativa::whereNull('unidad_padre_id')->sole();
    expect($raiz->codigo)->toBe('GADPE')->and($raiz->nivel)->toBe(1);

    // Cada hija cuelga del código de su padre, un nivel más abajo.
    UnidadAdministrativa::with('padre')->whereNotNull('unidad_padre_id')->get()
        ->each(function (UnidadAdministrativa $u) {
            expect($u->codigo)->toMatch('/^'.preg_quote($u->padre->codigo, '/').'-\d{2}$/')
                ->and($u->nivel)->toBe($u->padre->nivel + 1);
        });
});

it('sembrar dos veces no cambia las unidades ni lo editado en ellas', function () {
    $this->seed(DatabaseSeeder::class);

    // Lo que alguien corrige desde Estructura.
    $editada = UnidadAdministrativa::where('nombre', 'Gestión de Comunicación Social')->firstOrFail();
    $editada->update(['codigo' => 'GADPE-COM', 'descripcion' => 'Descripción corregida a mano']);

    $antes = UnidadAdministrativa::orderBy('id')->get(['id', 'nombre', 'codigo', 'nivel', 'unidad_padre_id'])->toArray();

    $this->seed(DatabaseSeeder::class);

    expect(UnidadAdministrativa::orderBy('id')->get(['id', 'nombre', 'codigo', 'nivel', 'unidad_padre_id'])->toArray())
        ->toBe($antes)
        ->and($editada->fresh()->descripcion)->toBe('Descripción corregida a mano');
});

it('sembrar de nuevo no le cambia la contraseña al administrador', function () {
    $this->seed(DatabaseSeeder::class);

    $admin = User::where('email', 'crecalde@gadpe.gob.ec')->sole();
    $admin->update(['password' => Hash::make('una-clave-que-eligio-el')]);

    $this->seed(DatabaseSeeder::class);

    expect(Hash::check('una-clave-que-eligio-el', $admin->fresh()->password))->toBeTrue()
        ->and($admin->fresh()->getRoleNames()->all())->toContain('admin-ti');
});
