<?php

use App\Enums\RegimenLaboral;
use App\Models\Expediente\Servidor;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\Marca;
use App\Models\InventarioTi\MovimientoUbicacionBien;
use App\Models\InventarioTi\TipoBien;
use App\Models\InventarioTi\Ubicacion;
use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/*
| Las ubicaciones de los equipos (decidido con TIC el 2026-10-13): son del
| equipo, no de la unidad. El catálogo de sedes, la ubicación habitual de cada
| unidad, la mudanza en lote y los movimientos en el historial, con los
| componentes acompañando a su equipo.
*/

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Servidor::unguard();
    BienInformatico::unguard();
    config(['inventario.corte_soporte_anterior' => now()->addYear()->toDateString()]);
    $this->seed(RolPermisoSeeder::class);

    $this->tecnico = User::factory()->create(['primer_login' => false]);
    $this->tecnico->assignRole('tecnico-dtic');
    $this->tic = unidadDePrueba(['nombre' => 'Gestión de TIC']);
    $this->obras = unidadDePrueba(['nombre' => 'Dirección de Obras']);
    $servidor = fn (string $cedula, string $apellido, $unidad) => Servidor::create([
        'cedula' => $cedula, 'nombre' => 'Ana', 'apellido' => $apellido, 'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true,
    ]);
    $this->paola = $servidor('0805555551', 'Benavides', $this->tic);
    $this->diego = $servidor('0805555552', 'Luzuriaga', $this->obras);

    $lugar = fn (string $sede, ?string $piso = null) => Ubicacion::where('sede', $sede)->where('piso', $piso)->sole();
    $this->piso2 = $lugar('Edificio Central', 'Piso 2');
    $this->piso3 = $lugar('Edificio Central', 'Piso 3');
    $this->sanMateo = $lugar('San Mateo');

    $this->computador = TipoBien::create(['nombre' => 'Computador']);
    $this->hp = Marca::create(['nombre' => 'HP']);
    $this->actingAs($this->tecnico, 'sanctum');
});

function equipoUbicado(array $atributos = []): BienInformatico
{
    static $n = 9100;
    $n++;

    return BienInformatico::create(array_merge([
        'codigo_qr' => "{$n}-QR", 'codigo_institucional' => (string) $n,
        'tipo_bien_id' => test()->computador->id, 'marca_id' => test()->hp->id,
        'estado_operativo' => 'disponible', 'condicion_fisica' => 'bueno',
    ], $atributos));
}

function entregarUbicado(BienInformatico $bien, Servidor $servidor, array $extra = []): void
{
    test()->postJson('/api/v1/inventario/asignaciones', [
        'bien_informatico_id' => $bien->id, 'servidor_id' => $servidor->id, 'fecha_asignacion' => now()->toDateString(), ...$extra,
    ])->assertCreated();
}

test('el catálogo trae las sedes de TIC, en orden', function () {
    expect(Ubicacion::ordenadas()->get()->map(fn (Ubicacion $u) => $u->nombre)->all())->toBe([
        'Dispensario Médico', 'Edificio Central', 'Edificio Central · Planta baja',
        'Edificio Central · Piso 1', 'Edificio Central · Piso 2', 'Edificio Central · Piso 3',
        'Edificio Central · Piso 4', 'Edificio Central · Piso 5', 'Edificio Central · Piso 6', 'San Mateo',
    ]);
});

test('la migración renombra lo que dejó la carga y retira UNAMY si está vacía', function () {
    $viejo = DB::table('ubicaciones')->insertGetId(['sede' => 'Edificio principal', 'piso' => '2', 'created_at' => now(), 'updated_at' => now()]);
    $pb = DB::table('ubicaciones')->insertGetId(['sede' => 'Edificio principal', 'piso' => 'PB', 'created_at' => now(), 'updated_at' => now()]);
    $unamy = DB::table('ubicaciones')->insertGetId(['sede' => 'UNAMYDESC', 'created_at' => now(), 'updated_at' => now()]);
    // «Edificio principal · 2» ya existe como «Piso 2»: su equipo pasa a esa.
    $equipo = equipoUbicado(['ubicacion_id' => $viejo]);
    DB::table('ubicaciones')->where('id', $pb)->update(['sede' => 'Edificio principal']);
    DB::table('ubicaciones')->where('sede', 'Edificio Central')->where('piso', 'Planta baja')->delete();

    $migracion = require database_path('migrations/2026_10_28_000000_ubicaciones_de_tic.php');
    (fn () => $this->normalizarCatalogo())->call($migracion);

    expect($equipo->fresh()->ubicacion_id)->toBe($this->piso2->id)
        ->and(Ubicacion::find($viejo))->toBeNull()
        ->and(Ubicacion::find($pb)->nombre)->toBe('Edificio Central · Planta baja')
        ->and(Ubicacion::find($unamy))->toBeNull();
});

test('cambiar la ubicación lleva a los componentes que estaban con el equipo y queda en el historial', function () {
    $cpu = equipoUbicado(['ubicacion_id' => $this->piso2->id]);
    $monitor = equipoUbicado(['bien_padre_id' => $cpu->id, 'ubicacion_id' => $this->piso2->id]);
    // Este componente está en otra parte (lo llevaron a reparar): no se mueve.
    $teclado = equipoUbicado(['bien_padre_id' => $cpu->id, 'ubicacion_id' => $this->sanMateo->id]);

    $this->putJson("/api/v1/inventario/bienes/{$cpu->id}", ['ubicacion_id' => $this->piso3->id])->assertOk();
    // Guardar sin cambiar la ubicación no deja otro movimiento.
    $this->putJson("/api/v1/inventario/bienes/{$cpu->id}", ['modelo' => 'ProDesk 400'])->assertOk();

    expect($monitor->fresh()->ubicacion_id)->toBe($this->piso3->id)
        ->and($teclado->fresh()->ubicacion_id)->toBe($this->sanMateo->id)
        ->and(MovimientoUbicacionBien::where('bien_informatico_id', $cpu->id)->count())->toBe(1);

    $historial = $this->getJson("/api/v1/inventario/bienes/{$cpu->id}/historial")->assertOk()->json('datos.ubicaciones');
    expect($historial[0])->toMatchArray([
        'anterior' => 'Edificio Central · Piso 2', 'nueva' => 'Edificio Central · Piso 3', 'motivo' => 'Edición de la ficha',
    ]);
});

test('la ubicación habitual de la unidad se propone al entregar, y la entrega puede mover el equipo', function () {
    $this->putJson("/api/v1/inventario/ubicaciones-por-unidad/{$this->tic->id}", ['ubicacion_id' => $this->piso2->id])->assertOk();

    $this->getJson("/api/v1/inventario/ubicaciones-por-unidad/sugerida?servidor_id={$this->paola->id}")->assertOk()
        ->assertJsonPath('datos.nombre', 'Edificio Central · Piso 2');
    $this->getJson("/api/v1/inventario/ubicaciones-por-unidad/sugerida?unidad_administrativa_id={$this->obras->id}")->assertOk()
        ->assertJsonPath('datos', null);

    $equipo = equipoUbicado(['ubicacion_id' => $this->sanMateo->id]);
    entregarUbicado($equipo, $this->paola, ['ubicacion_id' => $this->piso2->id]);
    $otro = equipoUbicado(['ubicacion_id' => $this->sanMateo->id]);
    // Sin ubicación en la entrega, el equipo sigue donde estaba.
    entregarUbicado($otro, $this->diego);

    expect($equipo->fresh()->ubicacion_id)->toBe($this->piso2->id)
        ->and(MovimientoUbicacionBien::where('bien_informatico_id', $equipo->id)->sole()->motivo)->toBe('entrega')
        ->and($otro->fresh()->ubicacion_id)->toBe($this->sanMateo->id);

    $fila = collect($this->getJson('/api/v1/inventario/ubicaciones-por-unidad')->assertOk()->json('datos'))->firstWhere('unidad_id', $this->tic->id);
    expect($fila['ubicacion'])->toBe('Edificio Central · Piso 2');
});

test('la mudanza mueve los equipos de la unidad que estaban en el piso de antes', function () {
    $dePaola = equipoUbicado(['ubicacion_id' => $this->piso2->id, 'unidad_administrativa_id' => $this->obras->id]);
    entregarUbicado($dePaola, $this->paola);
    $monitor = equipoUbicado(['bien_padre_id' => $dePaola->id, 'ubicacion_id' => $this->piso2->id]);
    // De TIC, sin custodio, en la bodega del piso 2.
    $bodega = equipoUbicado(['ubicacion_id' => $this->piso2->id, 'unidad_administrativa_id' => $this->tic->id]);
    // De TIC pero prestado o en San Mateo: no se toca.
    $fuera = equipoUbicado(['ubicacion_id' => $this->sanMateo->id, 'unidad_administrativa_id' => $this->tic->id]);
    // De otra unidad en el mismo piso: tampoco.
    $deDiego = equipoUbicado(['ubicacion_id' => $this->piso2->id]);
    entregarUbicado($deDiego, $this->diego);

    $mudanza = ['unidad_administrativa_id' => $this->tic->id, 'desde_ubicacion_id' => $this->piso2->id, 'hacia_ubicacion_id' => $this->piso3->id];
    $vista = $this->postJson('/api/v1/inventario/ubicaciones-por-unidad/mudanza', $mudanza)->assertOk()->json('datos');
    expect($vista['total'])->toBe(2)
        ->and($vista['aplicada'])->toBeFalse()
        ->and($dePaola->fresh()->ubicacion_id)->toBe($this->piso2->id);

    $this->postJson('/api/v1/inventario/ubicaciones-por-unidad/mudanza', [...$mudanza, 'aplicar' => true])->assertOk()
        ->assertJsonPath('datos.total', 2);

    expect($dePaola->fresh()->ubicacion_id)->toBe($this->piso3->id)
        ->and($monitor->fresh()->ubicacion_id)->toBe($this->piso3->id)
        ->and($bodega->fresh()->ubicacion_id)->toBe($this->piso3->id)
        ->and($fuera->fresh()->ubicacion_id)->toBe($this->sanMateo->id)
        ->and($deDiego->fresh()->ubicacion_id)->toBe($this->piso2->id)
        ->and(MovimientoUbicacionBien::where('bien_informatico_id', $dePaola->id)->sole()->detalle)
        ->toBe('Mudanza de Gestión de TIC: de Edificio Central · Piso 2 a Edificio Central · Piso 3.');

    // La unidad queda con su nueva ubicación habitual.
    $this->getJson("/api/v1/inventario/ubicaciones-por-unidad/sugerida?unidad_administrativa_id={$this->tic->id}")
        ->assertJsonPath('datos.nombre', 'Edificio Central · Piso 3');

    // Repetirla ya no encuentra nada que mover.
    $this->postJson('/api/v1/inventario/ubicaciones-por-unidad/mudanza', [...$mudanza, 'aplicar' => true])->assertStatus(422);
});

test('el auditor consulta las ubicaciones habituales pero no muda ni las cambia', function () {
    $auditor = User::factory()->create(['primer_login' => false]);
    $auditor->assignRole('auditor');
    $this->actingAs($auditor, 'sanctum');

    $this->getJson('/api/v1/inventario/ubicaciones-por-unidad')->assertOk();
    $this->putJson("/api/v1/inventario/ubicaciones-por-unidad/{$this->tic->id}", ['ubicacion_id' => $this->piso2->id])->assertForbidden();
    $this->postJson('/api/v1/inventario/ubicaciones-por-unidad/mudanza', [
        'unidad_administrativa_id' => $this->tic->id, 'desde_ubicacion_id' => null, 'hacia_ubicacion_id' => $this->piso3->id, 'aplicar' => true,
    ])->assertForbidden();
});
