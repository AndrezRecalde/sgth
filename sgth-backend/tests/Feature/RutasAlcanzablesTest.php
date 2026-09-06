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

test('las_rutas_literales_de_nomina_no_las_traga_el_comodin', function () {
    expect(destinoDe('GET', '/api/v1/nomina/conceptos'))
        ->toBe('ConceptoNominaController@index');

    expect(destinoDe('GET', '/api/v1/nomina/handoffs'))
        ->toBe('HandoffErpController@index');

    expect(destinoDe('GET', '/api/v1/nomina/descuentos-recurrentes'))
        ->toBe('DescuentoRecurrenteController@index');

    // Y el comodín sigue funcionando para lo suyo.
    expect(destinoDe('GET', '/api/v1/nomina/7'))
        ->toBe('NominaController@show');
});

test('por_unidad_no_la_traga_el_recurso_de_actividades', function () {
    expect(destinoDe('GET', '/api/v1/actividades/por-unidad'))
        ->toBe('ActividadLaboralController@porUnidad');

    expect(destinoDe('GET', '/api/v1/actividades/7'))
        ->toBe('ActividadLaboralController@show');
});

test('el_comodin_de_nomina_solo_acepta_numeros', function () {
    // Sin la restricción, el orden de las líneas era lo único que sostenía
    // esto: bastaba añadir una ruta literal más abajo para romperla otra vez.
    $this->actingAs($this->usuario, 'sanctum')
        ->getJson('/api/v1/nomina/loquesea')
        ->assertNotFound();
});

test('las_rutas_de_nomina_que_estaban_rotas_ya_responden', function () {
    foreach ([
        '/api/v1/nomina/conceptos',
        '/api/v1/nomina/handoffs',
        '/api/v1/nomina/descuentos-recurrentes',
    ] as $uri) {
        $this->actingAs($this->usuario, 'sanctum')
            ->getJson($uri)
            ->assertOk();
    }
});

test('actividades_por_unidad_ya_responde', function () {
    $this->actingAs($this->usuario, 'sanctum')
        ->getJson('/api/v1/actividades/por-unidad?unidad_administrativa_id=1')
        ->assertOk()
        ->assertJsonPath('datos', []);
});
