<?php

use App\Models\Helpdesk\CategoriaTicket;
use App\Models\Helpdesk\ProblemaFrecuente;
use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
| Los problemas frecuentes de «Pedir ayuda a TIC» (respuestas de TIC del
| 2026-11-05): un catálogo que TIC define, con la categoría, el tipo, la
| prioridad y la descripción que lleva el ticket. Lo mantienen el jefe de TIC
| y los técnicos que él designe con `gestionar-problemas-frecuentes`.
*/

uses(Tests\TestCase::class, RefreshDatabase::class);

const MIGRACION_PROBLEMAS = 'migrations/2026_12_16_000000_problemas_frecuentes_de_la_mesa_de_ayuda.php';

beforeEach(function () {
    $this->seed(RolPermisoSeeder::class);

    $this->jefe = User::factory()->create(['primer_login' => false, 'activo' => true]);
    $this->jefe->assignRole('jefe-tic');
    $this->tecnico = User::factory()->create(['primer_login' => false, 'activo' => true]);
    $this->tecnico->assignRole('tecnico-dtic');
    $this->impresoras = CategoriaTicket::where('nombre', 'Impresoras')->sole();
});

function datosProblema(array $cambios = []): array
{
    return [
        'nombre'       => 'El escáner no escanea',
        'categoria_id' => CategoriaTicket::where('nombre', 'Impresoras')->value('id'),
        'tipo_ticket'  => 'incidente',
        'prioridad'    => 'media',
        'descripcion'  => 'El escáner que uso no escanea.',
        ...$cambios,
    ];
}

test('la migración siembra los 7 problemas que eligió TIC, en su orden', function () {
    $problemas = ProblemaFrecuente::with('categoria')->orderBy('orden')->get();

    expect($problemas->pluck('nombre')->all())->toBe([
        'Ayuda con FirmaEC',
        'El equipo no enciende o está muy lento',
        'La impresora no imprime',
        'Instalar o conectar una impresora',
        'No tengo internet',
        'No puedo entrar a mi correo',
        'Desbloquear mi usuario o cambiar mi contraseña',
    ])->and($problemas->pluck('orden')->all())->toBe([1, 2, 3, 4, 5, 6, 7]);

    $porNombre = $problemas->keyBy('nombre');
    expect($porNombre['Ayuda con FirmaEC']->categoria->nombre)->toBe('Software')
        ->and($porNombre['Ayuda con FirmaEC']->tipo_ticket)->toBe('solicitud')
        // «Alta» solo para el equipo que no enciende y la falta de internet (pregunta 6).
        ->and($problemas->filter(fn ($p) => $p->prioridad->value === 'alta')->pluck('nombre')->all())
        ->toBe(['El equipo no enciende o está muy lento', 'No tengo internet'])
        // «Instalar o conectar una impresora» se pide: es una solicitud.
        ->and($porNombre['Instalar o conectar una impresora']->tipo_ticket)->toBe('solicitud')
        ->and($porNombre['La impresora no imprime']->tipo_ticket)->toBe('incidente');
});

test('volver a correr la migración no duplica nada y da el permiso al jefe de TIC y a admin-ti', function () {
    $migracion = require database_path(MIGRACION_PROBLEMAS);
    Role::findByName('jefe-tic', 'sanctum')->revokePermissionTo('gestionar-problemas-frecuentes');
    Role::findByName('admin-ti', 'sanctum')->revokePermissionTo('gestionar-problemas-frecuentes');

    $migracion->up();
    $migracion->up();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(ProblemaFrecuente::count())->toBe(7)
        ->and(Role::findByName('jefe-tic', 'sanctum')->hasPermissionTo('gestionar-problemas-frecuentes'))->toBeTrue()
        ->and(Role::findByName('admin-ti', 'sanctum')->hasPermissionTo('gestionar-problemas-frecuentes'))->toBeTrue()
        ->and(Role::findByName('tecnico-dtic', 'sanctum')->hasPermissionTo('gestionar-problemas-frecuentes'))->toBeFalse();
});

test('sin la categoría con ese nombre, el problema no se siembra', function () {
    $migracion = require database_path(MIGRACION_PROBLEMAS);
    ProblemaFrecuente::query()->delete();
    CategoriaTicket::where('nombre', 'Software')->update(['nombre' => 'Programas']);

    $migracion->up();

    expect(ProblemaFrecuente::pluck('nombre'))->not->toContain('Ayuda con FirmaEC')
        ->and(ProblemaFrecuente::count())->toBe(6)
        ->and(ProblemaFrecuente::orderBy('orden')->pluck('orden')->all())->toBe([1, 2, 3, 4, 5, 6]);
});

test('el jefe de TIC registra, edita y desactiva; el nuevo va al final', function () {
    $this->actingAs($this->jefe, 'sanctum');

    $id = $this->postJson('/api/v1/helpdesk/problemas-frecuentes', datosProblema())
        ->assertCreated()
        ->assertJsonPath('datos.categoria', 'Impresoras')
        ->assertJsonPath('datos.orden', 8)
        ->assertJsonPath('datos.activo', true)
        ->json('datos.id');

    $this->putJson("/api/v1/helpdesk/problemas-frecuentes/{$id}", ['prioridad' => 'alta', 'tipo_ticket' => 'solicitud'])
        ->assertOk()->assertJsonPath('datos.prioridad', 'alta')->assertJsonPath('datos.tipo_ticket', 'solicitud')
        ->assertJsonPath('datos.nombre', 'El escáner no escanea');

    $this->putJson("/api/v1/helpdesk/problemas-frecuentes/{$id}", ['activo' => false])
        ->assertOk()->assertJsonPath('mensaje', 'Problema frecuente desactivado: ya no se ofrece al pedir ayuda.');

    // Los inactivos van al final del listado.
    $lista = $this->getJson('/api/v1/helpdesk/problemas-frecuentes')->assertOk()->json('datos');
    expect(end($lista)['id'])->toBe($id)->and(end($lista)['activo'])->toBeFalse();
});

test('el nombre no se repite, la categoría tiene que estar activa y el tipo y la prioridad son los del sistema', function () {
    $this->actingAs($this->jefe, 'sanctum');
    $errores = fn (array $cambios) => array_keys($this->postJson('/api/v1/helpdesk/problemas-frecuentes', datosProblema($cambios))
        ->assertStatus(422)->json('errores'));

    expect($errores(['nombre' => 'NO TENGO INTERNET']))->toBe(['nombre'])
        ->and($errores(['tipo_ticket' => 'queja']))->toBe(['tipo_ticket'])
        ->and($errores(['prioridad' => 'altisima']))->toBe(['prioridad'])
        ->and($errores(['descripcion' => 'Corta']))->toBe(['descripcion']);

    $this->impresoras->update(['activa' => false]);
    expect($errores([]))->toBe(['categoria_id']);

    // Editar sin cambiar el nombre no choca consigo mismo.
    $internet = ProblemaFrecuente::where('nombre', 'No tengo internet')->sole();
    $this->putJson("/api/v1/helpdesk/problemas-frecuentes/{$internet->id}", ['nombre' => 'No tengo Internet'])->assertOk();
});

test('con la categoría desactivada, el listado lo avisa', function () {
    $this->impresoras->update(['activa' => false]);

    $lista = collect($this->actingAs($this->jefe, 'sanctum')->getJson('/api/v1/helpdesk/problemas-frecuentes')->json('datos'))->keyBy('nombre');

    expect($lista['La impresora no imprime']['categoria_activa'])->toBeFalse()
        ->and($lista['No tengo internet']['categoria_activa'])->toBeTrue();
});

test('el orden se guarda con la lista completa; una lista desfasada se rechaza', function () {
    $this->actingAs($this->jefe, 'sanctum');
    $ids = ProblemaFrecuente::orderBy('orden')->pluck('id')->all();
    $invertidos = array_reverse($ids);

    $this->putJson('/api/v1/helpdesk/problemas-frecuentes/orden', ['ids' => $invertidos])->assertOk();
    expect(ProblemaFrecuente::orderBy('orden')->pluck('id')->all())->toBe($invertidos);

    $this->putJson('/api/v1/helpdesk/problemas-frecuentes/orden', ['ids' => array_slice($ids, 1)])
        ->assertStatus(422)->assertJsonPath('mensaje', 'La lista cambió mientras la ordenaba: vuelva a cargarla e inténtelo de nuevo.');
    $this->putJson('/api/v1/helpdesk/problemas-frecuentes/orden', ['ids' => [...$ids, 999_999]])->assertStatus(422);
    expect(ProblemaFrecuente::orderBy('orden')->pluck('id')->all())->toBe($invertidos);
});

test('lo mantienen el jefe, admin-ti y el técnico que reciba el permiso', function () {
    $this->actingAs($this->tecnico, 'sanctum')->getJson('/api/v1/helpdesk/problemas-frecuentes')->assertForbidden();
    $this->actingAs($this->tecnico, 'sanctum')->postJson('/api/v1/helpdesk/problemas-frecuentes', datosProblema())->assertForbidden();

    $servidor = User::factory()->create(['primer_login' => false, 'activo' => true]);
    $servidor->assignRole('servidor');
    $this->actingAs($servidor, 'sanctum')->getJson('/api/v1/helpdesk/problemas-frecuentes')->assertForbidden();

    $admin = User::factory()->create(['primer_login' => false, 'activo' => true]);
    $admin->assignRole('admin-ti');
    $this->actingAs($admin, 'sanctum')->getJson('/api/v1/helpdesk/problemas-frecuentes')->assertOk();

    // El designado por el jefe: el permiso se le da desde Usuarios.
    $this->tecnico->givePermissionTo('gestionar-problemas-frecuentes');
    $this->actingAs($this->tecnico->fresh(), 'sanctum')->postJson('/api/v1/helpdesk/problemas-frecuentes', datosProblema())->assertCreated();
});
