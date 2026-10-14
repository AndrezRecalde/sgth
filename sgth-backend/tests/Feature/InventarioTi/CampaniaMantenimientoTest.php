<?php

use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\MantenimientoBien;
use App\Models\InventarioTi\Marca;
use App\Models\InventarioTi\TipoBien;
use App\Models\InventarioTi\Ubicacion;
use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
| El plan de mantenimiento preventivo por campaña (docs/diseno-subsistema-tic.md,
| fase 5.1): un lugar, un técnico, un período y los equipos, con su avance. Cada
| equipo hecho es un mantenimiento preventivo en su ficha; si se anula desde la
| ficha, vuelve a pendiente.
*/

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    BienInformatico::unguard();
    $this->seed(RolPermisoSeeder::class);
    $this->travelTo(now()->setDate(2026, 10, 14)->setTime(9, 0));

    $this->tecnico = User::factory()->create(['primer_login' => false]);
    $this->tecnico->assignRole('tecnico-dtic');
    $this->otroTecnico = User::factory()->create(['primer_login' => false]);
    $this->otroTecnico->assignRole('tecnico-dtic');
    $this->auditor = User::factory()->create(['primer_login' => false]);
    $this->auditor->assignRole('auditor');

    $this->piso3 = Ubicacion::where('sede', 'Edificio Central')->where('piso', 'Piso 3')->sole();
    $this->sanMateo = Ubicacion::where('sede', 'San Mateo')->sole();
    $this->tipoPreventivo = TipoBien::create(['nombre' => 'Computador']);
    $this->marcaPreventivo = Marca::create(['nombre' => 'Dell']);
    $this->actingAs($this->tecnico, 'sanctum');
});

function equipoDePreventivo(array $atributos = []): BienInformatico
{
    static $n = 9700;
    $n++;

    return BienInformatico::create(array_merge([
        'codigo_qr' => "{$n}-QR", 'codigo_institucional' => (string) $n,
        'tipo_bien_id' => test()->tipoPreventivo->id, 'marca_id' => test()->marcaPreventivo->id,
        'estado_operativo' => 'disponible', 'condicion_fisica' => 'bueno', 'ubicacion_id' => test()->piso3->id,
    ], $atributos));
}

function crearCampania(array $datos = []): int
{
    return test()->postJson('/api/v1/inventario/campanias', [
        'nombre' => 'Preventivo piso 3, octubre', 'descripcion' => 'Limpieza interna y actualización del sistema',
        'ubicacion_id' => test()->piso3->id, 'tecnico_id' => test()->tecnico->id,
        'fecha_inicio' => '2026-10-12', 'fecha_fin' => '2026-10-23', ...$datos,
    ])->assertCreated()->json('datos.id');
}

function campania(int $id): array
{
    return test()->getJson("/api/v1/inventario/campanias/{$id}")->assertOk()->json('datos');
}

function itemDe(array $campania, BienInformatico $bien): int
{
    return collect($campania['filas'])->firstWhere('bien_id', $bien->id)['item_id'];
}

test('la planifica quien gestiona el inventario; el auditor la consulta', function () {
    $id = crearCampania();

    $this->actingAs($this->auditor, 'sanctum');
    $this->getJson('/api/v1/inventario/campanias')->assertOk()->assertJsonPath('datos.0.id', $id);
    $this->getJson("/api/v1/inventario/campanias/{$id}")->assertOk();
    $this->postJson('/api/v1/inventario/campanias', [])->assertForbidden();
    $this->postJson("/api/v1/inventario/campanias/{$id}/realizar", ['items' => [1], 'fecha' => '2026-10-14'])->assertForbidden();
    $this->postJson("/api/v1/inventario/campanias/{$id}/cerrar")->assertForbidden();
});

test('el técnico es alguien que registra mantenimientos, y el fin no va antes del inicio', function () {
    $this->postJson('/api/v1/inventario/campanias', [
        'nombre' => 'Mal armada', 'descripcion' => 'Limpieza interna', 'ubicacion_id' => $this->piso3->id,
        'tecnico_id' => $this->auditor->id, 'fecha_inicio' => '2026-10-20', 'fecha_fin' => '2026-10-19',
    ])->assertStatus(422)->assertJsonValidationErrors(['tecnico_id', 'fecha_fin'], 'errores');
});

test('al crearla entran los equipos principales en servicio del lugar', function () {
    $pc = equipoDePreventivo();
    $impresora = equipoDePreventivo();
    equipoDePreventivo(['bien_padre_id' => $pc->id]); // monitor: va con su equipo
    equipoDePreventivo(['estado_operativo' => 'dado_de_baja']);
    equipoDePreventivo(['ubicacion_id' => $this->sanMateo->id]);

    $c = campania(crearCampania());

    expect(collect($c['filas'])->pluck('bien_id')->sort()->values()->all())->toBe([$pc->id, $impresora->id])
        ->and($c['resumen'])->toMatchArray(['total' => 2, 'pendientes' => 2, 'hechos' => 0])
        ->and($c['situacion'])->toBe('en_curso')
        ->and($c['tecnico'])->toBe($this->tecnico->nombre_completo);
});

test('marcar como hecho registra el preventivo en la ficha; anulado desde la ficha, vuelve a pendiente', function () {
    $pc = equipoDePreventivo();
    $impresora = equipoDePreventivo();
    $id = crearCampania();
    $c = campania($id);

    $this->postJson("/api/v1/inventario/campanias/{$id}/realizar", [
        'items' => [itemDe($c, $pc), itemDe($c, $impresora)], 'fecha' => '2026-10-13',
        'tecnico_id' => $this->otroTecnico->id, 'observacion' => 'Se cambió la pasta térmica',
    ])->assertOk()->assertJsonPath('datos.hechos', 2);

    $m = MantenimientoBien::where('bien_informatico_id', $pc->id)->sole();
    expect($m->tipo_mantenimiento->value)->toBe('preventivo')
        ->and($m->realizado_por)->toBe(MantenimientoBien::POR_TIC)
        ->and($m->tecnico_id)->toBe($this->otroTecnico->id)
        ->and($m->fecha_mantenimiento->toDateString())->toBe('2026-10-13')
        ->and($m->descripcion)->toBe('Campaña «Preventivo piso 3, octubre»: Limpieza interna y actualización del sistema. Se cambió la pasta térmica');
    $this->getJson('/api/v1/inventario/campanias')->assertJsonPath('datos.0.hechos', 2)->assertJsonPath('datos.0.total', 2);

    // Otra vez, no: ya tiene su mantenimiento.
    $this->postJson("/api/v1/inventario/campanias/{$id}/realizar", ['items' => [itemDe($c, $pc)], 'fecha' => '2026-10-14'])->assertStatus(422);

    $this->postJson("/api/v1/inventario/mantenimientos/{$m->id}/anular", ['motivo' => 'Se registró en el equipo equivocado'])->assertOk();
    $fila = collect(campania($id)['filas'])->firstWhere('bien_id', $pc->id);
    expect($fila)->toMatchArray(['estado' => 'pendiente', 'anulado' => true, 'mantenimiento_id' => null]);
    $this->getJson('/api/v1/inventario/campanias')->assertJsonPath('datos.0.hechos', 1);

    // Y se puede volver a marcar, sin técnico: el de la campaña.
    $this->postJson("/api/v1/inventario/campanias/{$id}/realizar", ['items' => [itemDe($c, $pc)], 'fecha' => '2026-10-14'])->assertOk();
    expect(MantenimientoBien::where('bien_informatico_id', $pc->id)->whereNull('anulado_en')->sole()->tecnico_id)->toBe($this->tecnico->id);
});

test('la fecha va desde el inicio de la campaña y hasta hoy', function () {
    equipoDePreventivo();
    $id = crearCampania();
    $item = campania($id)['filas'][0]['item_id'];

    $this->postJson("/api/v1/inventario/campanias/{$id}/realizar", ['items' => [$item], 'fecha' => '2026-10-11'])->assertStatus(422);
    $this->postJson("/api/v1/inventario/campanias/{$id}/realizar", ['items' => [$item], 'fecha' => '2026-10-15'])
        ->assertStatus(422)->assertJsonValidationErrors('fecha', 'errores');
});

test('omitir con motivo, agregar de otro lugar y quitar lo no atendido', function () {
    $pc = equipoDePreventivo();
    $prestado = equipoDePreventivo();
    $id = crearCampania();
    $c = campania($id);

    $this->postJson("/api/v1/inventario/campanias/{$id}/omitir", ['items' => [itemDe($c, $prestado)], 'motivo' => 'El servidor estaba de vacaciones'])->assertOk();
    expect(collect(campania($id)['filas'])->firstWhere('bien_id', $prestado->id))
        ->toMatchArray(['estado' => 'omitido', 'motivo_omision' => 'El servidor estaba de vacaciones']);
    // El omitido se puede hacer después.
    $this->postJson("/api/v1/inventario/campanias/{$id}/realizar", ['items' => [itemDe($c, $prestado)], 'fecha' => '2026-10-14'])->assertOk();

    $deSanMateo = equipoDePreventivo(['ubicacion_id' => $this->sanMateo->id]);
    $monitor = equipoDePreventivo(['bien_padre_id' => $pc->id]);
    $this->postJson("/api/v1/inventario/campanias/{$id}/equipos", ['bienes' => [$deSanMateo->id]])->assertOk()->assertJsonPath('datos.agregados', 1);
    $this->postJson("/api/v1/inventario/campanias/{$id}/equipos", ['bienes' => [$deSanMateo->id]])->assertJsonPath('datos.agregados', 0);
    $this->postJson("/api/v1/inventario/campanias/{$id}/equipos", ['bienes' => [$monitor->id]])->assertStatus(422);

    $this->deleteJson("/api/v1/inventario/campanias/{$id}/equipos/" . itemDe($c, $prestado))->assertStatus(422);
    $this->deleteJson("/api/v1/inventario/campanias/{$id}/equipos/" . itemDe($c, $pc))->assertOk();
    expect(campania($id)['resumen'])->toMatchArray(['total' => 2, 'hechos' => 1, 'pendientes' => 1]);
});

test('programada, atrasada y cerrada; cerrada, lo pendiente queda como no realizado y ya no cambia', function () {
    $pc = equipoDePreventivo();
    $programada = crearCampania(['fecha_inicio' => '2026-11-02', 'fecha_fin' => '2026-11-06']);
    $atrasada = crearCampania(['fecha_inicio' => '2026-10-01', 'fecha_fin' => '2026-10-09']);
    expect(campania($programada)['situacion'])->toBe('programada')
        ->and(campania($atrasada)['situacion'])->toBe('atrasada');

    $this->postJson("/api/v1/inventario/campanias/{$atrasada}/cerrar")->assertOk()->assertJsonPath('datos.situacion', 'cerrada');
    $c = campania($atrasada);
    expect($c['filas'][0]['estado'])->toBe('no_realizado')
        ->and($c['resumen'])->toMatchArray(['no_realizados' => 1, 'pendientes' => 0]);

    $this->postJson("/api/v1/inventario/campanias/{$atrasada}/realizar", ['items' => [itemDe($c, $pc)], 'fecha' => '2026-10-14'])->assertStatus(422);
    $this->putJson("/api/v1/inventario/campanias/{$atrasada}", [
        'nombre' => 'Otra', 'descripcion' => 'Limpieza interna', 'tecnico_id' => $this->tecnico->id,
        'fecha_inicio' => '2026-10-01', 'fecha_fin' => '2026-10-30',
    ])->assertStatus(422);
});
