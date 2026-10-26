<?php

/*
| admin-ti, solo técnico (docs/plan-roles-y-accesos.md, PR 3; decisión del
| 2026-10-25).
|
| Hasta el 2026-10-26 un Gate::before le aprobaba cualquier permiso y cualquier
| policy, salvo sobre un Servidor. Por el API marcaba asistencia sin
| `marcar-en-linea`, leía todos los permisos de ausencia con sus motivos,
| aprobaba licencias y abría la bandeja de viáticos, aunque el menú, que mira
| los permisos reales, no le enseñaba nada de eso. Ahora tiene lo suyo y nada
| más: Usuarios, TIC, Comunicación, comunicados y la vinculación inicial.
*/

use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolPermisoSeeder::class);

    $this->ti = User::factory()->create();
    $this->ti->assignRole('admin-ti');

    $this->actingAs($this->ti, 'sanctum');
});

it('no marca asistencia en línea sin el permiso de la persona', function () {
    $this->postJson('/api/v1/asistencia/marcaciones/online', [
        'checktype' => 'I', 'latitud' => 0.96, 'longitud' => -79.65,
    ])->assertForbidden();
});

it('no entra a lo de Talento Humano', function (string $metodo, string $ruta) {
    $this->json($metodo, $ruta)->assertForbidden();
})->with([
    'el consolidado de permisos'   => ['GET', '/api/v1/asistencia/consolidado-permisos'],
    'las licencias'                => ['GET', '/api/v1/asistencia/licencias'],
    'la bandeja de viáticos'       => ['GET', '/api/v1/viaticos/bandeja/resumen'],
    'las novedades del mes'        => ['GET', '/api/v1/nomina/novedades'],
    'las convocatorias'            => ['GET', '/api/v1/seleccion/convocatorias'],
    'las subrogaciones'            => ['GET', '/api/v1/expediente/subrogaciones/vigentes'],
    'crear una partida'            => ['POST', '/api/v1/estructura/partidas-presupuestarias'],
    'crear una entidad financiera' => ['POST', '/api/v1/catalogos/entidades-financieras'],
]);

it('conserva lo técnico, que tiene en su rol', function (string $ruta) {
    $this->getJson($ruta)->assertOk();
})->with([
    'Usuarios'               => '/api/v1/admin/usuarios',
    'la mesa de ayuda'       => '/api/v1/helpdesk/tickets/resumen',
    'el inventario'          => '/api/v1/inventario/bienes',
    'las credenciales'       => '/api/v1/comunicacion/credenciales',
    'los comunicados'        => '/api/v1/comunicados',
    'el buscador de personas' => '/api/v1/expediente/servidores/buscar?search=pe',
]);
