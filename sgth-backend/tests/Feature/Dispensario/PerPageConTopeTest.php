<?php

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

/*
 * Los listados de historias clínicas y de certificados médicos pasaban
 * `per_page` directo a `paginate()`. `limit()` ignora los negativos, así que
 * `?per_page=-1` salía sin LIMIT y devolvía la tabla entera de una vez; y
 * cualquier número grande, lo mismo. El listado de consultas ya tenía techo y
 * suelo; estos dos, no.
 *
 * El de historias clínicas se retiró el 2026-10-29: ninguna pantalla lo pedía
 * (docs/revision-ficha-paciente-dispensario.md, D1).
 */

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Model::unguard();

    $this->admin = User::create([
        'email' => 'per-page-tope@example.com', 'usuario_ti' => 'per-page-tope',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $this->admin->assignRole(Role::firstOrCreate(['name' => 'admin-dispensario', 'guard_name' => 'sanctum']));

    $this->actingAs($this->admin, 'sanctum');
});

dataset('listados con tope', [
    'certificados médicos' => ['/api/v1/dispensario/certificados-medicos'],
]);

it('no deja pedir per_page negativo', function (string $ruta) {
    expect($this->getJson("{$ruta}?per_page=-1")->assertOk()->json('datos.per_page'))
        ->toBe(1);
})->with('listados con tope');

it('acota per_page a su techo', function (string $ruta) {
    expect($this->getJson("{$ruta}?per_page=5000")->assertOk()->json('datos.per_page'))
        ->toBe(100);
})->with('listados con tope');

it('sin per_page sigue trayendo veinte', function (string $ruta) {
    expect($this->getJson($ruta)->assertOk()->json('datos.per_page'))
        ->toBe(20);
})->with('listados con tope');
