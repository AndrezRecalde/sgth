<?php

use App\Enums\EstadoBien;
use App\Enums\RegimenLaboral;
use App\Models\Expediente\Servidor;
use App\Models\InventarioTi\AsignacionBien;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\Marca;
use App\Models\InventarioTi\TipoBien;
use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
| La custodia de los equipos (docs/diseno-subsistema-tic.md, 5.3; fase 1.3):
| entregar, traspasar, devolver, el memorando de Quipux, el número de soporte
| del sistema antiguo y «Mis equipos» del Portal.
*/

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Servidor::unguard();
    BienInformatico::unguard();
    AsignacionBien::unguard();
    config(['inventario.corte_soporte_anterior' => now()->addYear()->toDateString()]);

    $this->seed(RolPermisoSeeder::class);

    $this->tecnico = User::factory()->create(['primer_login' => false]);
    $this->tecnico->assignRole('tecnico-dtic');

    $servidor = fn (string $cedula, string $apellido) => Servidor::create([
        'cedula' => $cedula, 'nombre' => 'Ana', 'apellido' => $apellido,
        'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true,
    ]);
    $this->paola = $servidor('0806666661', 'Benavides');
    $this->diego = $servidor('0806666662', 'Luzuriaga');
    $this->omar = $servidor('0806666663', 'Vega');

    $this->equipo = BienInformatico::create([
        'codigo_qr' => '1774-QR', 'codigo_institucional' => '1774',
        'tipo_bien_id' => TipoBien::create(['nombre' => 'Computador'])->id,
        'marca_id' => Marca::create(['nombre' => 'HP'])->id,
        'estado_operativo' => 'disponible', 'condicion_fisica' => 'bueno',
    ]);

    $this->actingAs($this->tecnico, 'sanctum');
});

function entregarDeCustodia(array $datos = []): \Illuminate\Testing\TestResponse
{
    return test()->postJson('/api/v1/inventario/asignaciones', array_merge([
        'bien_informatico_id' => test()->equipo->id,
        'servidor_id'         => test()->paola->id,
        'fecha_asignacion'    => now()->subMonth()->toDateString(),
    ], $datos));
}

test('entregar anota al usuario distinto del custodio, el memorando y el soporte anterior', function () {
    $datos = entregarDeCustodia([
        'usuario_servidor_id' => $this->diego->id,
        'memorando_numero'    => 'Memorando Nº 066 GADPE-HAP-GTIC-2026',
        'soporte_anterior'    => '48618',
    ])->assertCreated()->json('datos');

    expect($datos['custodio']['nombre'])->toBe('Benavides Ana')
        ->and($datos['usuario']['nombre'])->toBe('Luzuriaga Ana')
        ->and($datos['memorando_numero'])->toBe('Memorando Nº 066 GADPE-HAP-GTIC-2026')
        ->and($datos['soporte_anterior'])->toBe('48618')
        ->and($datos['motivo'])->toBe('entrega')
        ->and($this->equipo->fresh()->estado_operativo)->toBe(EstadoBien::EN_USO);

    $this->get("/api/v1/inventario/asignaciones/{$datos['id']}/acta")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

test('si quien usa el equipo es el custodio no se anota usuario', function () {
    $datos = entregarDeCustodia(['usuario_servidor_id' => $this->paola->id])->assertCreated()->json('datos');

    expect($datos['usuario_servidor_id'])->toBeNull();
});

test('el traspaso cierra la custodia vigente y abre la nueva enlazada', function () {
    $primera = entregarDeCustodia()->assertCreated()->json('datos.id');

    $nueva = $this->postJson("/api/v1/inventario/asignaciones/{$primera}/traspasar", [
        'servidor_id'      => $this->omar->id,
        'fecha_asignacion' => now()->toDateString(),
        'memorando_numero' => 'Memorando Nº 067 GADPE-HAP-GTIC-2026',
    ])->assertCreated()->json('datos');

    $anterior = AsignacionBien::find($primera);
    expect($anterior->estado)->toBe('devuelta')
        ->and($anterior->fecha_devolucion->toDateString())->toBe(now()->toDateString())
        ->and($nueva['motivo'])->toBe('traspaso')
        ->and($nueva['custodio_anterior']['nombre'])->toBe('Benavides Ana')
        ->and($this->equipo->fresh()->estado_operativo)->toBe(EstadoBien::EN_USO)
        ->and(AsignacionBien::where('estado', 'activa')->count())->toBe(1);

    // Ya no es la vigente: no se traspasa otra vez.
    $this->postJson("/api/v1/inventario/asignaciones/{$primera}/traspasar", [
        'servidor_id' => $this->diego->id, 'fecha_asignacion' => now()->toDateString(),
    ])->assertStatus(422);
});

test('no se traspasa al mismo custodio ni con fecha anterior a la entrega', function () {
    $id = entregarDeCustodia()->assertCreated()->json('datos.id');

    $this->postJson("/api/v1/inventario/asignaciones/{$id}/traspasar", [
        'servidor_id' => $this->paola->id, 'fecha_asignacion' => now()->toDateString(),
    ])->assertStatus(422);

    $this->postJson("/api/v1/inventario/asignaciones/{$id}/traspasar", [
        'servidor_id' => $this->omar->id, 'fecha_asignacion' => now()->subYear()->toDateString(),
    ])->assertStatus(422);
});

test('entregar un equipo con custodia vigente pide el traspaso', function () {
    entregarDeCustodia()->assertCreated();

    expect(entregarDeCustodia(['servidor_id' => $this->omar->id])->assertStatus(422)->json('mensaje'))
        ->toContain('traspaso');
});

test('la base no admite dos custodias vigentes del mismo equipo', function () {
    entregarDeCustodia()->assertCreated();

    expect(fn () => AsignacionBien::create([
        'bien_informatico_id' => $this->equipo->id, 'servidor_id' => $this->omar->id,
        'fecha_asignacion' => now()->toDateString(), 'estado' => 'activa',
    ]))->toThrow(QueryException::class);
});

test('devolver deja el equipo disponible y no se devuelve dos veces', function () {
    $id = entregarDeCustodia()->assertCreated()->json('datos.id');

    $this->postJson("/api/v1/inventario/asignaciones/{$id}/devolver", ['fecha_devolucion' => now()->toDateString()])->assertOk();
    expect($this->equipo->fresh()->estado_operativo)->toBe(EstadoBien::DISPONIBLE);

    $this->postJson("/api/v1/inventario/asignaciones/{$id}/devolver", ['fecha_devolucion' => now()->toDateString()])->assertStatus(422);
});

test('pasada la fecha de corte el soporte del sistema antiguo ya no se acepta', function () {
    config(['inventario.corte_soporte_anterior' => now()->subDay()->toDateString()]);

    entregarDeCustodia(['soporte_anterior' => '48618'])
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['soporte_anterior']]);

    expect($this->getJson('/api/v1/inventario/catalogos')->json('datos.acepta_soporte_anterior'))->toBeFalse();
});

test('el memorando se adjunta en PDF y se descarga', function () {
    Storage::fake('local');
    $id = entregarDeCustodia()->assertCreated()->json('datos.id');

    $this->postJson("/api/v1/inventario/asignaciones/{$id}/memorando", [
        'archivo' => UploadedFile::fake()->create('memo.docx', 20, 'application/msword'),
    ])->assertStatus(422);

    $this->postJson("/api/v1/inventario/asignaciones/{$id}/memorando", [
        'archivo' => UploadedFile::fake()->create('memo-066.pdf', 20, 'application/pdf'),
    ])->assertOk()->assertJsonPath('datos.tiene_memorando_pdf', true);

    $this->get("/api/v1/inventario/asignaciones/{$id}/memorando")->assertOk()->assertDownload('memo-066.pdf');
});

test('el listado de custodias filtra por equipo y por vigentes', function () {
    $id = entregarDeCustodia()->assertCreated()->json('datos.id');
    $this->postJson("/api/v1/inventario/asignaciones/{$id}/traspasar", [
        'servidor_id' => $this->omar->id, 'fecha_asignacion' => now()->toDateString(),
    ])->assertCreated();

    expect($this->getJson("/api/v1/inventario/asignaciones?bien_informatico_id={$this->equipo->id}")->json('meta.total'))->toBe(2)
        ->and($this->getJson('/api/v1/inventario/asignaciones?vigentes=1')->json('datos.0.custodio.nombre'))->toBe('Vega Ana');

    $historial = $this->getJson("/api/v1/inventario/bienes/{$this->equipo->id}/historial")->json('datos.asignaciones');
    expect(array_column($historial, 'motivo'))->toBe(['traspaso', 'entrega']);
});

test('mis equipos muestra los que el servidor tiene a su cargo o usa, y el acta solo de los suyos', function () {
    $a = entregarDeCustodia(['usuario_servidor_id' => $this->diego->id])->assertCreated()->json('datos.id');

    $deDiego = User::factory()->create(['primer_login' => false, 'servidor_id' => $this->diego->id]);
    $deDiego->assignRole('servidor');
    $deOmar = User::factory()->create(['primer_login' => false, 'servidor_id' => $this->omar->id]);
    $deOmar->assignRole('servidor');
    $deTic = User::factory()->create(['primer_login' => false]);
    $deTic->assignRole('servidor');

    $this->actingAs($deDiego, 'sanctum');
    expect($this->getJson('/api/v1/autoservicio/mis-equipos')->assertOk()->json('datos.0.bien.codigo_institucional'))->toBe('1774');
    // Lo usa, pero el acta la firmó la custodia.
    $this->get("/api/v1/autoservicio/mis-equipos/{$a}/acta")->assertNotFound();

    $this->actingAs($deOmar, 'sanctum');
    expect($this->getJson('/api/v1/autoservicio/mis-equipos')->json('datos'))->toBe([]);

    $this->actingAs($deTic, 'sanctum');
    expect($this->getJson('/api/v1/autoservicio/mis-equipos')->assertOk()->json('datos'))->toBe([]);
});

test('el buscador de equipos encuentra por el numero de soporte del sistema anterior', function () {
    entregarDeCustodia(['soporte_anterior' => '48618'])->assertCreated();

    expect($this->getJson('/api/v1/inventario/bienes?search=48618')->json('datos.0.codigo_institucional'))->toBe('1774')
        ->and($this->getJson('/api/v1/inventario/bienes?search=99999')->json('datos'))->toBe([]);
});
