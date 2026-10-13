<?php

use App\Enums\EstadoBien;
use App\Enums\RegimenLaboral;
use App\Models\Expediente\Servidor;
use App\Models\InventarioTi\AsignacionBien;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\Marca;
use App\Models\InventarioTi\SubtipoBien;
use App\Models\InventarioTi\TipoBien;
use App\Models\InventarioTi\Ubicacion;
use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| El modelo de datos del inventario (docs/diseno-subsistema-tic.md, 4.2; fase
| 1.1): lo que trae el Excel de TIC, los estados que salen de la custodia, los
| componentes y el grupo de préstamo. Todo con un técnico de TIC real, no con
| admin-ti.
*/

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Servidor::unguard();
    BienInformatico::unguard();
    AsignacionBien::unguard();

    $this->seed(RolPermisoSeeder::class);

    $this->tecnico = User::factory()->create(['primer_login' => false]);
    $this->tecnico->assignRole('tecnico-dtic');

    $this->computador = TipoBien::create(['nombre' => 'Computador', 'anios_vida_util' => 5]);
    $this->monitor = TipoBien::create(['nombre' => 'Monitor', 'anios_vida_util' => 5]);
    $this->todoEnUno = SubtipoBien::create(['tipo_bien_id' => $this->computador->id, 'nombre' => 'Todo en uno']);
    $this->dell = Marca::create(['nombre' => 'DELL']);

    $this->unidadLegal = unidadDePrueba(['nombre' => 'Asesoría Legal']);
    $this->unidadTic = unidadDePrueba(['nombre' => 'Tecnologías']);
    $this->ana = Servidor::create([
        'cedula' => '0803333333', 'nombre' => 'Ana', 'apellido' => 'Mero',
        'unidad_administrativa_id' => $this->unidadLegal->id,
        'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true,
    ]);

    $this->actingAs($this->tecnico, 'sanctum');
});

function equipoDeModelo(array $atributos = []): BienInformatico
{
    static $n = 4000;
    $n++;

    return BienInformatico::create(array_merge([
        'codigo_qr' => "{$n}-QR", 'codigo_institucional' => (string) $n,
        'tipo_bien_id' => test()->computador->id, 'marca_id' => test()->dell->id,
        'estado_operativo' => 'disponible', 'condicion_fisica' => 'bueno',
    ], $atributos));
}

function asignarDeModelo(BienInformatico $bien, Servidor $servidor)
{
    return test()->postJson('/api/v1/inventario/asignaciones', [
        'bien_informatico_id' => $bien->id,
        'servidor_id'         => $servidor->id,
        'fecha_asignacion'    => now()->toDateString(),
    ]);
}

// ── Lo que trae el Excel ───────────────────────────────────────────────────

test('registra un equipo como el del Excel, con sus datos de red y ubicacion', function () {
    $ubicacion = Ubicacion::create(['sede' => 'Edificio principal', 'piso' => '6']);

    $respuesta = $this->postJson('/api/v1/inventario/bienes', [
        'codigo_institucional' => '1774',
        'codigo_anterior'      => '007-01-010-04047',
        'tipo_bien_id'         => $this->computador->id,
        'subtipo_bien_id'      => $this->todoEnUno->id,
        'marca_id'             => $this->dell->id,
        'modelo'               => 'OPTIPLEX 7480',
        'numero_serie'         => '1KC6NF3',
        'fecha_compra'         => '2021-10-04',
        'ubicacion_id'         => $ubicacion->id,
        'nombre_red'           => 'LEG-GMERO01',
        'usuario_dominio'      => 'gmero',
        'caracteristicas_tecnicas' => ['disco' => 'SSD'],
    ])->assertCreated();

    expect($respuesta->json('datos'))
        ->estado_operativo->toBe('disponible')
        ->codigo_anterior->toBe('007-01-010-04047')
        ->fecha_fin_vida_util->toBe('2026-10-04')
        ->and($respuesta->json('datos.ubicacion.nombre'))->toBe('Edificio principal · 6')
        ->and($respuesta->json('datos.subtipo.nombre'))->toBe('Todo en uno');
});

test('la serie es opcional pero no se repite, sin distinguir mayusculas', function () {
    $base = ['tipo_bien_id' => $this->computador->id, 'marca_id' => $this->dell->id];

    $this->postJson('/api/v1/inventario/bienes', [...$base, 'codigo_institucional' => '9001'])->assertCreated();
    $this->postJson('/api/v1/inventario/bienes', [...$base, 'codigo_institucional' => '9002'])->assertCreated();
    $this->postJson('/api/v1/inventario/bienes', [...$base, 'codigo_institucional' => '9003', 'numero_serie' => 'abc123'])->assertCreated();

    $this->postJson('/api/v1/inventario/bienes', [...$base, 'codigo_institucional' => '9004', 'numero_serie' => 'ABC123'])
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['numero_serie']]);
});

test('el codigo de una ficha borrada por error se puede volver a usar', function () {
    $equivocado = equipoDeModelo(['codigo_institucional' => '7777', 'codigo_qr' => '7777-QR']);
    $this->deleteJson("/api/v1/inventario/bienes/{$equivocado->id}")->assertOk();

    $this->postJson('/api/v1/inventario/bienes', [
        'codigo_institucional' => '7777', 'tipo_bien_id' => $this->computador->id, 'marca_id' => $this->dell->id,
    ])->assertCreated();
});

test('el subtipo tiene que ser del tipo del equipo', function () {
    $this->postJson('/api/v1/inventario/bienes', [
        'codigo_institucional' => '9100', 'tipo_bien_id' => $this->monitor->id,
        'subtipo_bien_id' => $this->todoEnUno->id, 'marca_id' => $this->dell->id,
    ])->assertStatus(422);
});

// ── Componentes ────────────────────────────────────────────────────────────

test('un componente cuelga de un equipo principal y solo un nivel', function () {
    $cpu = equipoDeModelo();
    $monitor = equipoDeModelo(['tipo_bien_id' => $this->monitor->id]);
    $teclado = equipoDeModelo(['tipo_bien_id' => $this->monitor->id]);

    $this->putJson("/api/v1/inventario/bienes/{$monitor->id}", ['bien_padre_id' => $cpu->id])->assertOk();

    // El monitor ya es componente: no puede tener los suyos.
    $this->putJson("/api/v1/inventario/bienes/{$teclado->id}", ['bien_padre_id' => $monitor->id])->assertStatus(422);
    // El CPU tiene componentes: no puede ser componente de otro.
    $this->putJson("/api/v1/inventario/bienes/{$cpu->id}", ['bien_padre_id' => $teclado->id])->assertStatus(422);

    $ficha = $this->getJson("/api/v1/inventario/bienes/{$cpu->id}")->assertOk();
    expect($ficha->json('datos.componentes_count'))->toBe(1)
        ->and($ficha->json('datos.componentes.0.codigo_institucional'))->toBe($monitor->codigo_institucional);

    // Y con componentes, la ficha del CPU no se borra.
    $this->deleteJson("/api/v1/inventario/bienes/{$cpu->id}")->assertStatus(422);
});

// ── Estados ────────────────────────────────────────────────────────────────

test('entregar y devolver mueven el estado entre en uso y disponible', function () {
    $equipo = equipoDeModelo();

    $asignacion = asignarDeModelo($equipo, $this->ana)->assertCreated();
    expect($equipo->fresh()->estado_operativo)->toBe(EstadoBien::EN_USO);

    $this->postJson("/api/v1/inventario/asignaciones/{$asignacion->json('datos.id')}/devolver", [
        'fecha_devolucion' => now()->toDateString(),
    ])->assertOk();
    expect($equipo->fresh()->estado_operativo)->toBe(EstadoBien::DISPONIBLE);
});

test('un equipo no se entrega a dos personas a la vez', function () {
    $equipo = equipoDeModelo();
    asignarDeModelo($equipo, $this->ana)->assertCreated();

    $luis = Servidor::create([
        'cedula' => '0804444444', 'nombre' => 'Luis', 'apellido' => 'Vega',
        'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true,
    ]);

    asignarDeModelo($equipo, $luis)->assertStatus(422);
});

test('en uso y disponible no se eligen: salen de la custodia', function () {
    $equipo = equipoDeModelo();
    asignarDeModelo($equipo, $this->ana)->assertCreated();

    $this->putJson("/api/v1/inventario/bienes/{$equipo->id}", ['estado_operativo' => 'en_mantenimiento'])->assertOk();
    expect($equipo->fresh()->estado_operativo)->toBe(EstadoBien::EN_MANTENIMIENTO);

    // Pedir «disponible» es «vuelve al servicio»: como tiene custodio, en uso.
    $this->putJson("/api/v1/inventario/bienes/{$equipo->id}", ['estado_operativo' => 'disponible'])->assertOk();
    expect($equipo->fresh()->estado_operativo)->toBe(EstadoBien::EN_USO);
});

test('la baja no entra por la edicion y un equipo dado de baja ya no cambia de estado', function () {
    $equipo = equipoDeModelo();

    $this->putJson("/api/v1/inventario/bienes/{$equipo->id}", ['estado_operativo' => 'dado_de_baja'])
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['estado_operativo']]);

    $equipo->update(['estado_operativo' => EstadoBien::DADO_DE_BAJA]);
    $this->putJson("/api/v1/inventario/bienes/{$equipo->id}", ['estado_operativo' => 'en_mantenimiento'])->assertStatus(422);
});

// ── Grupo de préstamo ──────────────────────────────────────────────────────

test('un equipo con custodio no se marca prestable y uno prestable no se entrega', function () {
    $conCustodio = equipoDeModelo();
    asignarDeModelo($conCustodio, $this->ana)->assertCreated();
    $this->putJson("/api/v1/inventario/bienes/{$conCustodio->id}", ['prestable' => true])->assertStatus(422);

    $proyector = equipoDeModelo();
    $this->putJson("/api/v1/inventario/bienes/{$proyector->id}", ['prestable' => true])->assertOk();
    asignarDeModelo($proyector, $this->ana)->assertStatus(422);
});

// ── El listado ─────────────────────────────────────────────────────────────

test('la unidad del equipo es la de su custodio, y la propia solo sin custodio', function () {
    $deAna = equipoDeModelo(['unidad_administrativa_id' => $this->unidadTic->id]);
    asignarDeModelo($deAna, $this->ana)->assertCreated();
    $enTic = equipoDeModelo(['unidad_administrativa_id' => $this->unidadTic->id]);

    $deLegal = $this->getJson("/api/v1/inventario/bienes?unidad_administrativa_id={$this->unidadLegal->id}")->json('datos');
    $deTic = $this->getJson("/api/v1/inventario/bienes?unidad_administrativa_id={$this->unidadTic->id}")->json('datos');

    expect(array_column($deLegal, 'id'))->toBe([$deAna->id])
        ->and(array_column($deTic, 'id'))->toBe([$enTic->id])
        ->and($deLegal[0]['unidad']['nombre'])->toBe('Asesoría Legal')
        ->and($deLegal[0]['custodio']['nombre'])->toBe('Mero Ana');
});

test('el listado busca por el nombre del custodio y filtra los sin custodio', function () {
    $deAna = equipoDeModelo();
    asignarDeModelo($deAna, $this->ana)->assertCreated();
    $libre = equipoDeModelo();

    expect(array_column($this->getJson('/api/v1/inventario/bienes?search=mero')->json('datos'), 'id'))->toBe([$deAna->id])
        ->and(array_column($this->getJson('/api/v1/inventario/bienes?sin_custodio=1')->json('datos'), 'id'))->toBe([$libre->id]);
});

// ── Migración ──────────────────────────────────────────────────────────────

test('la migracion pasa los estados viejos a los nuevos segun la custodia', function () {
    $migracion = require database_path('migrations/2026_10_18_000000_modelo_de_datos_del_inventario_ti.php');
    // La de mantenimientos (1.5) cuelga de `contratos_vigencia`: se deshace antes.
    $mantenimientos = require database_path('migrations/2026_10_21_000000_mantenimientos_de_equipos.php');
    $mantenimientos->down();
    $migracion->down();

    $tipo = DB::table('tipos_bien')->insertGetId(['nombre' => 'Viejo', 'anios_vida_util' => 0]);
    $marca = DB::table('marcas')->insertGetId(['nombre' => 'Vieja']);
    $origen = DB::table('origenes_bien')->insertGetId(['tipo_origen' => 'compra_publica', 'entidad_origen' => 'GADPE', 'fecha_adquisicion' => '2020-01-01']);
    $viejo = fn (string $codigo, string $estado) => DB::table('bienes_informaticos')->insertGetId([
        'codigo_qr' => "{$codigo}-QR", 'codigo_institucional' => $codigo, 'numero_serie' => "S-{$codigo}",
        'tipo_bien_id' => $tipo, 'marca_id' => $marca, 'origen_bien_id' => $origen, 'estado_operativo' => $estado,
    ]);

    $conCustodio = $viejo('V1', 'activo');
    $sinCustodio = $viejo('V2', 'activo');
    $robado = $viejo('V3', 'robado');
    DB::table('asignaciones_bien')->insert([
        'bien_informatico_id' => $conCustodio, 'servidor_id' => $this->ana->id,
        'fecha_asignacion' => '2024-01-01', 'estado' => 'activa',
    ]);

    $migracion->up();
    $mantenimientos->up();

    $estado = fn (int $id) => DB::table('bienes_informaticos')->where('id', $id)->value('estado_operativo');
    expect($estado($conCustodio))->toBe('en_uso')
        ->and($estado($sinCustodio))->toBe('disponible')
        ->and($estado($robado))->toBe('extraviado')
        ->and(Schema::hasColumns('bienes_informaticos', ['codigo_anterior', 'prestable', 'bien_padre_id']))->toBeTrue();
});
