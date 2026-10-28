<?php

/*
| Consultas, exámenes, odontograma, certificados de reposo y CIE-10 del
| Dispensario por permisos (docs/plan-roles-y-accesos.md, PR 17c).
|
| Las rutas pedían roles. Pasan a permisos dados a los mismos roles, así que
| la matriz de quién entra no cambia; lo nuevo es que un permiso suelto basta.
*/

use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolPermisoSeeder::class);

    $this->conRol = function (string $rol): User {
        $usuario = User::factory()->create();
        $usuario->assignRole($rol);

        return $usuario;
    };
});

test('cada rol entra a consultas, exámenes y odontograma como antes', function (string $metodo, string $ruta, array $entran) {
    foreach (['medico', 'odontologo', 'enfermera', 'admin-dispensario', 'auxiliar-farmacia', 'servidor', 'admin-uath'] as $rol) {
        $respuesta = $this->actingAs(($this->conRol)($rol), 'sanctum')->json($metodo, "/api/v1/dispensario/{$ruta}");

        in_array($rol, $entran, true)
            ? expect($respuesta->status())->not->toBe(403, "{$rol} debería entrar a {$metodo} {$ruta}")
            : expect($respuesta->status())->toBe(403, "{$rol} no debería entrar a {$metodo} {$ruta}");
    }
})->with([
    'consultas'              => ['GET', 'consultas', ['medico', 'odontologo']],
    'borrador'               => ['GET', 'consultas/borrador', ['medico', 'odontologo']],
    'CIE-10'                 => ['GET', 'cie10/buscar?q=gripe', ['medico', 'odontologo', 'admin-dispensario']],
    'odontograma'            => ['GET', 'odontograma/historia-clinica/999999', ['medico', 'odontologo', 'admin-dispensario']],
    'procedimiento dental'   => ['POST', 'odontograma/procedimientos', ['odontologo']],
    'certificados de reposo' => ['GET', 'certificados-medicos', ['medico', 'odontologo', 'admin-dispensario']],
    'emitir certificado'     => ['POST', 'certificados-medicos', ['medico', 'odontologo']],
    'resultados'             => ['GET', 'resultados-medicos', ['medico', 'odontologo', 'admin-dispensario']],
    'subir resultado'        => ['POST', 'resultados-medicos', ['medico', 'odontologo']],
    'bandeja'                => ['GET', 'examenes/bandeja', ['medico', 'odontologo', 'admin-dispensario']],
    'órdenes'                => ['GET', 'ordenes-examen', ['medico', 'odontologo', 'admin-dispensario']],
    'emitir orden'           => ['POST', 'ordenes-examen', ['medico', 'odontologo']],
    'perfiles'               => ['GET', 'perfiles-examen', ['medico', 'odontologo', 'admin-dispensario']],
    'catálogo'               => ['GET', 'catalogo-examenes', ['medico', 'odontologo', 'admin-dispensario']],
    'alta en el catálogo'    => ['POST', 'catalogo-examenes', ['admin-dispensario']],
]);

test('un permiso suelto basta, sin el rol', function () {
    $lectura = User::factory()->create();
    $lectura->givePermissionTo('ver-examenes');

    $this->actingAs($lectura, 'sanctum')->getJson('/api/v1/dispensario/examenes/bandeja')->assertOk();
    $this->actingAs($lectura, 'sanctum')->postJson('/api/v1/dispensario/ordenes-examen', [])->assertForbidden();
    $this->actingAs($lectura, 'sanctum')->getJson('/api/v1/dispensario/consultas')->assertForbidden();
});

test('la migracion da los permisos de consultas y examenes a los mismos roles que el seeder', function () {
    $migracion = require database_path('migrations/2026_12_06_000000_dispensario_permisos_de_consultas_y_examenes.php');
    $permisos = ['ver-examenes', 'gestionar-catalogo-examenes', 'registrar-odontograma'];

    $titulares = fn () => collect($permisos)->mapWithKeys(fn (string $p) => [
        $p => Role::where('guard_name', 'sanctum')
            ->whereHas('permissions', fn ($q) => $q->where('name', $p))
            ->orderBy('name')->pluck('name')->all(),
    ])->all();

    $delSeeder = $titulares();
    expect($delSeeder['registrar-odontograma'])->toBe(['odontologo']);

    $migracion->down();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $migracion->up();
    $migracion->up(); // idempotente
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($titulares())->toBe($delSeeder);
});
