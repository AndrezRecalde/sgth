<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/**
 * Rutas que un comodín se estaba tragando.
 *
 * `GET nomina/conceptos` encajaba en `nomina/{id}` y acababa en
 * `NominaController::show()` con la palabra «conceptos» por id; lo mismo le
 * pasaba a `handoffs`, a `descuentos-recurrentes` y a `actividades/por-unidad`
 * con el `{actividad}` de su `apiResource`. Las cuatro devolvían un 500 desde
 * que se escribieron, y no se había notado porque ninguna pantalla las pide.
 * Las de actividades se retiraron el 2026-10-12: viven en el portal, en
 * `autoservicio/mis-actividades`, con su propio literal (`pdf`) que cuidar.
 * Las de nómina, el 2026-10-20, con la nómina: de ella solo quedan las
 * novedades para Financiero.
 *
 * Lo que se comprueba aquí no es que devuelvan datos, sino que llegan a su
 * controlador: que la ruta exista es la mitad del asunto, la otra mitad es que
 * el enrutador no se la dé a otro.
 */
beforeEach(function () {
    User::unguard();

    $this->usuario = User::create([
        'email' => 'rutas@example.com', 'usuario_ti' => 'rutas',
        'password' => bcrypt('123456'), 'primer_login' => false,
        'activo' => true,
    ]);

    foreach (['admin-uath', 'asistente-uath', 'jefe-unidad', 'director'] as $rol) {
        $this->usuario->assignRole(Role::firstOrCreate(
            ['name' => $rol, 'guard_name' => 'sanctum']
        ));
    }
});

/** El controlador y el método a los que llega de verdad una URL. */
function destinoDe(string $metodo, string $uri): string
{
    $ruta = Route::getRoutes()->match(
        Illuminate\Http\Request::create($uri, $metodo)
    );

    return class_basename($ruta->getController()) . '@' . $ruta->getActionMethod();
}

test('de_la_nomina_solo_quedan_las_novedades', function () {
    // El SGTH no calcula la nómina desde el 2026-10-20: es informativo.
    expect(destinoDe('GET', '/api/v1/nomina/novedades'))
        ->toBe('NovedadNominaController@index');

    $this->usuario->givePermissionTo(\Spatie\Permission\Models\Permission::firstOrCreate(
        ['name' => 'ver-novedades-nomina', 'guard_name' => 'sanctum']
    ));

    foreach ([
        ['GET', '/api/v1/nomina'],
        ['POST', '/api/v1/nomina'],
        ['GET', '/api/v1/nomina/7'],
        ['GET', '/api/v1/nomina/conceptos'],
        ['GET', '/api/v1/nomina/handoffs'],
        ['GET', '/api/v1/nomina/descuentos-recurrentes'],
        ['GET', '/api/v1/nomina/7/rol-pago/3'],
        ['GET', '/api/v1/autoservicio/mis-roles-pago'],
    ] as [$metodo, $uri]) {
        $this->actingAs($this->usuario, 'sanctum')
            ->json($metodo, $uri)
            ->assertNotFound();
    }
});

test('el_pdf_de_mis_actividades_no_lo_traga_el_comodin', function () {
    expect(destinoDe('GET', '/api/v1/autoservicio/mis-actividades/pdf'))
        ->toBe('MisActividadesController@pdf');

    expect(destinoDe('PUT', '/api/v1/autoservicio/mis-actividades/7'))
        ->toBe('MisActividadesController@update');
});
