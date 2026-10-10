<?php

namespace Tests\Feature\Expediente;

use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'servidor', 'guard_name' => 'sanctum']);

    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'UATH-01', 'nombre' => 'Unidad de Talento Humano', 'nivel' => 1,
    ]);

    $this->puesto = Puesto::create([
        'codigo' => 'P-01', 'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 5,
    ]);

    $this->servidorPropio = Servidor::create([
        'cedula' => '1111111111', 'nombre' => 'Titular', 'apellido' => 'Propio',
        'regimen_laboral' => 'losep',
        'puesto_id' => $this->puesto->id,
        'unidad_administrativa_id' => $this->unidad->id,
    ]);

    $this->servidorAjeno = Servidor::create([
        'cedula' => '2222222222', 'nombre' => 'Titular', 'apellido' => 'Ajeno',
        'regimen_laboral' => 'losep',
        'puesto_id' => $this->puesto->id,
        'unidad_administrativa_id' => $this->unidad->id,
    ]);

    // El vínculo se hace desde users.servidor_id (servidores.user_id no
    // existe desde 2026_05_27_161227_reestructurar_relacion_users_servidores.php).
    $this->usuario = User::factory()->create(['servidor_id' => $this->servidorPropio->id]);
    $this->usuario->assignRole('servidor');
});

test('un servidor vinculado a su propio expediente puede verlo y actualizarlo', function () {
    $this->actingAs($this->usuario, 'sanctum');

    $this->getJson("/api/v1/expediente/servidores/{$this->servidorPropio->id}")
        ->assertStatus(200);

    $this->putJson("/api/v1/expediente/servidores/{$this->servidorPropio->id}", [
        'telefono_celular' => '0999999999',
    ])->assertStatus(200);

    expect($this->servidorPropio->fresh()->telefono_celular)->toBe('0999999999');
});

test('un servidor vinculado a otro expediente no puede verlo ni actualizarlo', function () {
    $this->actingAs($this->usuario, 'sanctum');

    $this->getJson("/api/v1/expediente/servidores/{$this->servidorAjeno->id}")
        ->assertStatus(403);

    $this->putJson("/api/v1/expediente/servidores/{$this->servidorAjeno->id}", [
        'telefono_celular' => '0999999999',
    ])->assertStatus(403);

    expect($this->servidorAjeno->fresh()->telefono_celular)->toBeNull();
});

test('asistente-uath puede listar servidores, para registrar permisos a nombre de otros', function () {
    // Tiene `registrar-permisos-servidores`, pero la policy solo dejaba listar
    // a admin-uath: el selector de servidores de Asistencia › Permisos le
    // respondía 403 y no podía elegir a nadie.
    Role::firstOrCreate(['name' => 'asistente-uath', 'guard_name' => 'sanctum']);
    $asistente = User::factory()->create();
    $asistente->assignRole('asistente-uath');

    $this->actingAs($asistente, 'sanctum')
        ->getJson("/api/v1/expediente/servidores?unidad_administrativa_id={$this->unidad->id}")
        ->assertOk();
});

test('un servidor no puede listar los servidores de la institución', function () {
    $this->actingAs($this->usuario, 'sanctum')
        ->getJson('/api/v1/expediente/servidores')
        ->assertStatus(403);
});

/*
| Las rutas de Acciones de Personal conceden 'asistente-uath' —registrar,
| transicionar y la bandeja—, pero los controladores autorizan contra
| ServidorPolicy, que hasta el 2026-09-27 solo aceptaba admin-uath, super-admin
| o el titular. El asistente veía la bandeja (200, sin policy) y recibía 403 en
| todo lo demás: la ruta y el policy se contradecían, y como el cajón de detalle
| tampoco pintaba el error, lo que veía era un esqueleto que no terminaba nunca.
*/
test('asistente-uath puede trabajar las acciones de personal que sus rutas le conceden', function () {
    Role::firstOrCreate(['name' => 'asistente-uath', 'guard_name' => 'sanctum']);
    permisosDeAccionesPersonal();
    $asistente = User::factory()->create();
    $asistente->assignRole('asistente-uath');
    $this->actingAs($asistente, 'sanctum');

    $movimiento = \App\Models\Expediente\MovimientoPersonal::create([
        'servidor_id'     => $this->servidorAjeno->id,
        'tipo_movimiento' => \App\Enums\TipoMovimientoPersonal::LICENCIA_SIN_REMUNERACION->value,
        'estado'          => \App\Enums\EstadoAccionPersonal::REGISTRADA,
        'codigo_registro' => 'AP-2026-0500',
        'fecha_registro'  => now(),
        'descripcion'     => 'Licencia registrada',
        'fecha_efectiva'  => '2026-08-01',
    ]);

    $this->getJson('/api/v1/expediente/movimientos')->assertOk();
    $this->getJson("/api/v1/expediente/movimientos/{$movimiento->id}")->assertOk();
    $this->getJson("/api/v1/expediente/movimientos/{$movimiento->id}/accion-personal-pdf")->assertOk();
    $this->getJson("/api/v1/expediente/servidores/{$this->servidorAjeno->id}/movimientos")->assertOk();

    $this->putJson("/api/v1/expediente/movimientos/{$movimiento->id}/transicionar", [
        'estado' => 'notificada',
    ])->assertOk();

    // Desde la fase 1.1 la API crea por clase y solo las del formulario: la
    // «novedad de contrato» que usaba esta prueba es bitácora y ya no entra.
    $this->postJson("/api/v1/expediente/servidores/{$this->servidorAjeno->id}/movimientos", [
        'clase'                       => \App\Enums\ClaseAccionPersonal::INGRESO->value,
        'tipo_nombramiento_propuesto' => \App\Enums\TipoNombramiento::SERVICIOS_OCASIONALES->value,
        'requiere_dictamen_medico'    => false,
        'descripcion'                 => 'Ingreso registrado por el asistente',
        'fecha_efectiva'              => '2026-09-01',
    ])->assertCreated();
});

/*
| El límite del cambio de arriba. 'asistente-uath' NO entra en crear(), y de ahí
| sale la restricción de campos: UpdateServidorRequest da la ficha entera solo a
| quien pasa `can('crear', Servidor::class)` y marca el resto como `prohibited`.
*/
test('asistente-uath edita el contacto de una ficha, no la cédula ni el régimen', function () {
    Role::firstOrCreate(['name' => 'asistente-uath', 'guard_name' => 'sanctum']);
    $asistente = User::factory()->create();
    $asistente->assignRole('asistente-uath');
    $this->actingAs($asistente, 'sanctum');

    $this->putJson("/api/v1/expediente/servidores/{$this->servidorAjeno->id}", [
        'telefono_celular' => '0988888888',
    ])->assertOk();

    $this->putJson("/api/v1/expediente/servidores/{$this->servidorAjeno->id}", [
        'cedula' => '3333333333',
    ])->assertStatus(422);

    expect($this->servidorAjeno->fresh())
        ->telefono_celular->toBe('0988888888')
        ->cedula->toBe('2222222222');
});

test('un asistente-uath no puede crear fichas', function () {
    Role::firstOrCreate(['name' => 'asistente-uath', 'guard_name' => 'sanctum']);
    $asistente = User::factory()->create();
    $asistente->assignRole('asistente-uath');

    expect($asistente->can('crear', Servidor::class))->toBeFalse();
});

/*
| El límite de la ampliación, por el lado de los documentos.
|
| Subir y borrar papeles del expediente no tenía middleware de rol y se
| autorizaba solo con ServidorPolicy::actualizar, que también deja pasar al
| propio titular. Al abrir ese policy a 'asistente-uath' para que pudiera
| trabajar las acciones de personal, eso se habría extendido al expediente de
| cualquiera, así que la escritura se ancla en la ruta: quien archiva un
| documento es Talento Humano. Leer y descargar siguen por policy.
|
| Desde el 2026-10-03 el asistente también SUBE (decisión de Talento Humano);
| borrar sigue siendo solo de admin-uath. Ver AsistenteSubeDocumentosTest.
*/
test('el asistente sube pero no borra, y el titular no escribe en su ficha', function () {
    Role::firstOrCreate(['name' => 'asistente-uath', 'guard_name' => 'sanctum']);
    $asistente = User::factory()->create();
    $asistente->assignRole('asistente-uath');

    $base = "/api/v1/expediente/servidores/{$this->servidorAjeno->id}/documentos";

    // El asistente lee y sube —pasa la ruta y solo le frena la validación—,
    // pero no borra.
    $this->actingAs($asistente, 'sanctum');
    $this->getJson($base)->assertOk();
    $this->postJson($base, [])->assertUnprocessable();
    $this->deleteJson("{$base}/1")->assertForbidden();

    // Y el titular tampoco mete papeles en su propia ficha.
    $this->actingAs($this->usuario, 'sanctum');
    $propio = "/api/v1/expediente/servidores/{$this->servidorPropio->id}/documentos";
    $this->postJson($propio, [])->assertForbidden();
    $this->deleteJson("{$propio}/1")->assertForbidden();
});
