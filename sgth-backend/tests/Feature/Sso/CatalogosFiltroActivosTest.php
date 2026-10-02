<?php

/*
| Que los tres catálogos del módulo se puedan abrir.
|
| Los modales piden el catálogo con `?solo_activos=true`, porque axios
| serializa así un `true` de JavaScript. La regla `boolean` de Laravel acepta
| `true`, `false`, `1`, `0`, `'1'` y `'0'` — y NO las cadenas `'true'` y
| `'false'`. Con la regla a secas, los tres modales recibían un 422 y se abrían
| enteros en estado de error: no había forma de dar de alta un factor de riesgo
| ni de corregir una normativa desde la pantalla. Comprobado en el navegador
| antes de escribir esto.
|
| El canario de este archivo es el caso de la cadena `'true'`. El del valor
| inválido está para que el arreglo no se haga tragándose la validación: si
| `?solo_activos=abc` deja de ser 422, es que se quitó la regla en vez de
| ajustarla.
*/

use App\Models\Sso\FactorRiesgoCatalogo;
use App\Models\Sso\NormativaLegalSso;
use App\Models\Sso\ProgramaDrogaActividad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $this->usuario = User::create([
        'email'        => 'sso-catalogos@example.com',
        'usuario_ti'   => 'ssocatalogos',
        'password'     => bcrypt('123456'),
        'primer_login' => false,
    ]);
    $this->usuario->assignRole('admin-uath');

    $this->actingAs($this->usuario, 'sanctum');

    // Uno activo y uno retirado en cada catálogo: con los dos se distingue
    // «solo los activos» de «todos», que es lo que el filtro decide.
    FactorRiesgoCatalogo::create([
        'nombre'    => 'Manejo manual de cargas',
        'categoria' => 'ergonomico',
        'activo'    => true,
    ]);
    FactorRiesgoCatalogo::create([
        'nombre'    => 'Factor retirado del catálogo',
        'categoria' => 'ergonomico',
        'activo'    => false,
    ]);

    NormativaLegalSso::create([
        'nombre' => 'Decreto Ejecutivo 2393',
        'tipo'   => 'reglamento',
        'activo' => true,
    ]);
    NormativaLegalSso::create([
        'nombre' => 'Norma derogada',
        'tipo'   => 'reglamento',
        'activo' => false,
    ]);

    ProgramaDrogaActividad::create([
        'nombre' => 'Socializar el programa a la población trabajadora',
        'fase'   => 'fase_3_socializacion',
        'activo' => true,
    ]);
    ProgramaDrogaActividad::create([
        'nombre' => 'Actividad retirada de la matriz',
        'fase'   => 'fase_3_socializacion',
        'activo' => false,
    ]);
});

dataset('catálogos', [
    'factores de riesgo' => ['/api/v1/sso/factores-riesgo', 'solo_activos'],
    'normativa legal'    => ['/api/v1/sso/normativa-legal', 'solo_activas'],
    'actividades'        => ['/api/v1/sso/programa-drogas/actividades', 'solo_activas'],
]);

test('el catálogo se abre con la cadena que manda el navegador', function (string $url, string $campo) {
    $this->getJson("{$url}?{$campo}=true")
        ->assertOk()
        ->assertJsonCount(1, 'datos');
})->with('catálogos');

test('la cadena false levanta el filtro y devuelve también los retirados', function (string $url, string $campo) {
    // `false` no es «solo los inactivos», es «no te limites a los activos»:
    // así es como lo usa el interruptor «Ver inactivos» de los tres modales.
    // Se fija aquí para que el arreglo de la validación no cambie de paso el
    // significado del filtro.
    $this->getJson("{$url}?{$campo}=false")
        ->assertOk()
        ->assertJsonCount(2, 'datos');
})->with('catálogos');

test('las formas numéricas siguen valiendo', function (string $url, string $campo) {
    $this->getJson("{$url}?{$campo}=1")->assertOk()->assertJsonCount(1, 'datos');
    $this->getJson("{$url}?{$campo}=0")->assertOk()->assertJsonCount(2, 'datos');
})->with('catálogos');

test('sin el filtro, el catálogo responde los activos', function (string $url) {
    $this->getJson($url)
        ->assertOk()
        ->assertJsonCount(1, 'datos');
})->with('catálogos');

test('un valor que no es booleano sigue siendo 422', function (string $url, string $campo) {
    $this->getJson("{$url}?{$campo}=abc")
        ->assertStatus(422)
        ->assertJsonPath("errores.{$campo}.0", fn (string $mensaje) => $mensaje !== '');
})->with('catálogos');
