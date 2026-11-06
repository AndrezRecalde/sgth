<?php

use App\Enums\RegimenLaboral;
use App\Models\Expediente\Servidor;
use App\Models\Helpdesk\CategoriaTicket;
use App\Models\Helpdesk\Ticket;
use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
| La mesa de ayuda centralizada (respuestas de TIC del 2026-11-06): el
| coordinador recibe todos los pedidos, los reparte, registra a nombre de
| otros y resuelve lo simple; se encarga del inventario. El técnico atiende
| solo lo que le asignan, no registra a nombre de otros y escala de vuelta al
| coordinador. Para su propio equipo, pide ayuda desde el Portal.
*/

uses(Tests\TestCase::class, RefreshDatabase::class);

const MIGRACION_COORDINADOR = 'migrations/2026_12_19_000000_coordinador_de_la_mesa_de_ayuda.php';

beforeEach(function () {
    Mail::fake();
    Servidor::unguard();
    $this->seed(RolPermisoSeeder::class);

    $servidor = fn (string $cedula, string $nombre) => Servidor::create([
        'cedula' => $cedula, 'nombre' => $nombre, 'apellido' => 'Quiñónez', 'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true,
    ]);
    $usuario = function (string $rol, ?Servidor $s = null) {
        $u = User::factory()->create(['primer_login' => false, 'activo' => true, 'servidor_id' => $s?->id]);
        $u->assignRole($rol);

        return $u;
    };

    $this->paola = $servidor('0810101001', 'Paola');
    $this->usuarioPaola = $usuario('servidor', $this->paola);
    $this->coordinador = $usuario('coordinador-mesa-ayuda', $servidor('0810101002', 'Karina'));
    $this->tecnico = $usuario('tecnico-dtic', $servidor('0810101003', 'Jorge'));
    $this->otroTecnico = $usuario('tecnico-dtic');
    $this->red = CategoriaTicket::where('nombre', 'Red e internet')->sole();
});

function pedirAyudaComo(User $quien, array $datos = []): Illuminate\Testing\TestResponse
{
    return test()->actingAs($quien, 'sanctum')->postJson('/api/v1/helpdesk/tickets', [
        'categoria_id' => test()->red->id, 'asunto' => 'No tengo internet', 'descripcion' => 'Desde la mañana la computadora no navega.',
        ...$datos,
    ]);
}

function pasoComo(User $quien, int $id, string $paso, array $datos = []): Illuminate\Testing\TestResponse
{
    return test()->actingAs($quien, 'sanctum')->postJson("/api/v1/helpdesk/tickets/{$id}/{$paso}", $datos);
}

test('el coordinador recibe lo nuevo, lo reparte y resuelve lo simple', function () {
    $simple = pedirAyudaComo($this->usuarioPaola)->assertCreated()->json('datos.id');
    $paraTecnico = pedirAyudaComo($this->usuarioPaola, ['asunto' => 'La impresora no imprime'])->assertCreated()->json('datos.id');

    $sinAsignar = collect($this->actingAs($this->coordinador, 'sanctum')->getJson('/api/v1/helpdesk/tickets?vista=sin_asignar')
        ->assertOk()->json('datos'))->pluck('id')->sort()->values()->all();
    expect($sinAsignar)->toBe([$simple, $paraTecnico]);

    pasoComo($this->coordinador, $paraTecnico, 'asignar', ['tecnico_id' => $this->tecnico->id])->assertOk()->assertJsonPath('datos.estado', 'asignado');
    pasoComo($this->coordinador, $simple, 'tomar')->assertOk();
    pasoComo($this->coordinador, $simple, 'resolver', ['solucion' => 'Se reinició el router del piso.'])->assertOk();

    expect(Ticket::find($simple)->tecnico_id)->toBe($this->coordinador->id)
        ->and(Ticket::find($paraTecnico)->tecnico_id)->toBe($this->tecnico->id);
});

test('registrar a nombre de otro es del coordinador y del jefe, no del técnico', function () {
    pedirAyudaComo($this->coordinador, ['servidor_id' => $this->paola->id])->assertCreated()->assertJsonPath('datos.canal', 'tic');
    pedirAyudaComo($this->tecnico, ['servidor_id' => $this->paola->id])->assertForbidden();

    // Su propio pedido, desde el Portal, sí: llega al coordinador como todos.
    $propio = pedirAyudaComo($this->tecnico)->assertCreated()->json('datos');
    expect($propio['canal'])->toBe('portal')->and($propio['tecnico'])->toBeNull();
    $suyos = collect($this->actingAs($this->tecnico, 'sanctum')->getJson('/api/v1/helpdesk/tickets?propios=1')->json('datos'))->pluck('id');
    expect($suyos)->toContain($propio['id']);
});

test('el técnico ve y toma solo lo que le asignaron', function () {
    $sinAsignar = pedirAyudaComo($this->usuarioPaola)->json('datos.id');
    $suyo = pedirAyudaComo($this->usuarioPaola, ['asunto' => 'El correo no abre'])->json('datos.id');
    $deOtro = pedirAyudaComo($this->usuarioPaola, ['asunto' => 'El monitor parpadea'])->json('datos.id');
    pasoComo($this->coordinador, $suyo, 'asignar', ['tecnico_id' => $this->tecnico->id])->assertOk();
    pasoComo($this->coordinador, $deOtro, 'asignar', ['tecnico_id' => $this->otroTecnico->id])->assertOk();

    $visibles = collect($this->actingAs($this->tecnico, 'sanctum')->getJson('/api/v1/helpdesk/tickets')->json('datos'))->pluck('id')->all();
    expect($visibles)->toBe([$suyo]);

    $this->getJson("/api/v1/helpdesk/tickets/{$sinAsignar}")->assertForbidden();
    pasoComo($this->tecnico, $sinAsignar, 'tomar')->assertForbidden();
    pasoComo($this->tecnico, $deOtro, 'tomar')->assertForbidden();
    pasoComo($this->tecnico, $suyo, 'tomar')->assertOk()->assertJsonPath('datos.estado', 'en_proceso');
});

test('el técnico escala de vuelta al coordinador, sin elegir a otro técnico', function () {
    $id = pedirAyudaComo($this->usuarioPaola)->json('datos.id');
    pasoComo($this->coordinador, $id, 'asignar', ['tecnico_id' => $this->tecnico->id])->assertOk();
    pasoComo($this->tecnico, $id, 'tomar')->assertOk();

    pasoComo($this->tecnico, $id, 'escalar', ['nivel' => 2, 'motivo' => 'Es del switch del piso.', 'tecnico_id' => $this->otroTecnico->id])
        ->assertStatus(422)->assertJsonPath('errores.tecnico_id.0', 'El ticket vuelve al coordinador de la mesa de ayuda: él decide quién sigue.');

    pasoComo($this->tecnico, $id, 'escalar', ['nivel' => 2, 'motivo' => 'Es del switch del piso.'])
        ->assertOk()->assertJsonPath('datos.estado', 'abierto')->assertJsonPath('datos.tecnico', null);

    // Vuelve a «Sin asignar» del coordinador, que lo pasa al especialista.
    $sinAsignar = collect($this->actingAs($this->coordinador, 'sanctum')->getJson('/api/v1/helpdesk/tickets?vista=sin_asignar')->json('datos'))->pluck('id');
    expect($sinAsignar)->toContain($id);
    pasoComo($this->coordinador, $id, 'asignar', ['tecnico_id' => $this->otroTecnico->id])->assertOk();
});

test('el rol se encarga de la mesa de ayuda y del inventario; lo demás, con permisos adicionales', function () {
    $rol = Role::findByName('coordinador-mesa-ayuda', 'sanctum');

    expect($rol->hasAllPermissions([
        'gestionar-tickets', 'ver-tickets-todos', 'asignar-ticket', 'registrar-ticket-a-nombre', 'gestionar-problemas-frecuentes',
        'ver-inventario-ti', 'gestionar-inventario-ti', 'registrar-mantenimiento', 'buscar-servidores',
        // Los del Portal, como todo servidor.
        'crear-ticket', 'ver-ticket-propio', 'acceso-autoservicio',
    ]))->toBeTrue();

    // Aprobar bajas, los plazos y los técnicos son del jefe; Usuarios, de admin-ti.
    foreach (['dar-baja-bien', 'configurar-sla', 'gestionar-tecnicos', 'gestionar-prestamos', 'gestionar-directorio', 'gestionar-usuarios'] as $permiso) {
        expect($rol->hasPermissionTo($permiso))->toBeFalse("El coordinador no debería tener {$permiso}");
    }

    expect(Role::findByName('tecnico-dtic', 'sanctum')->hasPermissionTo('registrar-ticket-a-nombre'))->toBeFalse()
        ->and(Role::findByName('jefe-tic', 'sanctum')->hasPermissionTo('registrar-ticket-a-nombre'))->toBeTrue()
        ->and(Role::findByName('admin-ti', 'sanctum')->hasPermissionTo('registrar-ticket-a-nombre'))->toBeTrue();
});

test('la migración crea el rol y da el permiso sin duplicar, y no lo retira si alguien lo tiene', function () {
    $migracion = require database_path(MIGRACION_COORDINADOR);
    Role::findByName('jefe-tic', 'sanctum')->revokePermissionTo('registrar-ticket-a-nombre');
    Role::findByName('coordinador-mesa-ayuda', 'sanctum')->revokePermissionTo('asignar-ticket');

    $migracion->up();
    $migracion->up();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(Role::findByName('jefe-tic', 'sanctum')->hasPermissionTo('registrar-ticket-a-nombre'))->toBeTrue()
        ->and(Role::findByName('coordinador-mesa-ayuda', 'sanctum')->hasPermissionTo('asignar-ticket'))->toBeTrue()
        ->and(Role::where('name', 'coordinador-mesa-ayuda')->count())->toBe(1);

    expect(fn () => $migracion->down())->toThrow(RuntimeException::class, 'No se retira el rol coordinador-mesa-ayuda');
});
