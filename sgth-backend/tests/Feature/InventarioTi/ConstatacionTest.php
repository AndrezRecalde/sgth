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

/*
| La constatación física por QR (docs/diseno-subsistema-tic.md, fase 5.2): TIC
| recorre una ubicación escaneando las etiquetas; lo que está en su lugar, lo
| registrado en otra parte (que se mueve aquí), lo dado de alta en sitio y,
| al cerrar, lo que no apareció. Reemplaza `auditoria/*`, que era simulada.
*/

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Servidor::unguard();
    BienInformatico::unguard();
    $this->seed(RolPermisoSeeder::class);
    $this->travelTo(now()->setDate(2026, 10, 14)->setTime(9, 0));
    config(['app.frontend_url' => 'https://sgth.gadpe.gob.ec']);

    $this->tecnico = User::factory()->create(['primer_login' => false]);
    $this->tecnico->assignRole('tecnico-dtic');
    $this->auditor = User::factory()->create(['primer_login' => false]);
    $this->auditor->assignRole('auditor');

    $this->piso2 = Ubicacion::where('sede', 'Edificio Central')->where('piso', 'Piso 2')->sole();
    $this->sanMateo = Ubicacion::where('sede', 'San Mateo')->sole();
    $this->tic = unidadDePrueba(['nombre' => 'Gestión de TIC']);
    $this->obras = unidadDePrueba(['nombre' => 'Dirección de Obras']);
    $this->computador = TipoBien::create(['nombre' => 'Computador']);
    $this->hp = Marca::create(['nombre' => 'HP']);
    $this->actingAs($this->tecnico, 'sanctum');
});

function equipoConstatado(array $atributos = []): BienInformatico
{
    static $n = 9500;
    $n++;

    return BienInformatico::create(array_merge([
        'codigo_qr' => "{$n}-QR", 'codigo_institucional' => (string) $n,
        'tipo_bien_id' => test()->computador->id, 'marca_id' => test()->hp->id,
        'estado_operativo' => 'disponible', 'condicion_fisica' => 'bueno', 'ubicacion_id' => test()->piso2->id,
    ], $atributos));
}

function abrirConstatacion(array $datos = []): int
{
    return test()->postJson('/api/v1/inventario/constataciones', [
        'nombre' => 'Piso 2, octubre de 2026', 'ubicacion_id' => test()->piso2->id, ...$datos,
    ])->assertCreated()->json('datos.id');
}

function escanearEn(int $id, string $codigo): \Illuminate\Testing\TestResponse
{
    return test()->postJson("/api/v1/inventario/constataciones/{$id}/escanear", ['codigo' => $codigo]);
}

test('la hace solo TIC; el auditor la consulta', function () {
    $id = abrirConstatacion();

    $this->actingAs($this->auditor, 'sanctum');
    $this->getJson('/api/v1/inventario/constataciones')->assertOk()->assertJsonPath('datos.0.id', $id);
    $this->getJson("/api/v1/inventario/constataciones/{$id}")->assertOk();
    $this->postJson('/api/v1/inventario/constataciones', ['nombre' => 'Otra', 'ubicacion_id' => $this->piso2->id])->assertForbidden();
    escanearEn($id, '9999')->assertForbidden();
    // Las rutas simuladas ya no existen.
    $this->actingAs($this->tecnico, 'sanctum')->postJson('/api/v1/inventario/auditoria/escanear', ['codigo_qr' => 'x'])->assertNotFound();
});

test('el recorrido: en su lugar, de otra ubicación movida aquí, de baja, repetido y desconocido; al cerrar, lo que faltó', function () {
    $enSuLugar = equipoConstatado();
    $faltante = equipoConstatado();
    $deSanMateo = equipoConstatado(['ubicacion_id' => $this->sanMateo->id]);
    $monitor = equipoConstatado(['bien_padre_id' => $deSanMateo->id, 'ubicacion_id' => $this->sanMateo->id]);
    $deBaja = equipoConstatado(['estado_operativo' => 'dado_de_baja']);
    $id = abrirConstatacion();

    // Con la cámara llega la URL de la etiqueta; a mano, el código.
    escanearEn($id, "https://sgth.gadpe.gob.ec/tic/inventario/equipos/{$enSuLugar->id}")->assertOk()
        ->assertJsonPath('datos.fila.resultado', 'en_su_lugar');
    $otra = escanearEn($id, " {$deSanMateo->codigo_institucional} ")->assertOk()->json('datos');
    expect($otra['fila'])->toMatchArray(['resultado' => 'otra_ubicacion', 'ubicacion_registrada' => 'San Mateo']);
    escanearEn($id, $deBaja->codigo_institucional)->assertJsonPath('datos.fila.resultado', 'de_baja');
    escanearEn($id, $enSuLugar->codigo_institucional)->assertJsonPath('datos.repetido', true);
    escanearEn($id, '000777')->assertOk()->assertJsonPath('datos.encontrado', false)->assertJsonPath('datos.codigo', '000777');

    $resumen = $this->getJson("/api/v1/inventario/constataciones/{$id}")->assertOk()->json('datos.resumen');
    expect($resumen)->toMatchArray(['esperados' => 2, 'pendientes' => 1, 'en_su_lugar' => 1, 'otra_ubicacion' => 1, 'de_baja' => 1]);

    // Lo encontrado aquí que estaba registrado en San Mateo pasa a esta ubicación, con su monitor.
    $this->postJson("/api/v1/inventario/constataciones/{$id}/items/{$otra['fila']['item_id']}/mover")->assertOk();
    expect($deSanMateo->fresh()->ubicacion_id)->toBe($this->piso2->id)
        ->and($monitor->fresh()->ubicacion_id)->toBe($this->piso2->id)
        ->and(MovimientoUbicacionBien::where('bien_informatico_id', $deSanMateo->id)->sole()->motivo)->toBe('constatacion');
    $this->postJson("/api/v1/inventario/constataciones/{$id}/items/{$otra['fila']['item_id']}/mover")->assertStatus(422);

    $this->postJson("/api/v1/inventario/constataciones/{$id}/cerrar", ['observaciones' => 'Faltó revisar la sala de reuniones.'])->assertOk()
        ->assertJsonPath('datos.estado', 'cerrada');
    $cerrada = $this->getJson("/api/v1/inventario/constataciones/{$id}")->json('datos');
    $noEncontrados = collect($cerrada['filas'])->where('resultado', 'no_encontrado')->pluck('codigo_institucional')->all();
    expect($noEncontrados)->toBe([$faltante->codigo_institucional])
        ->and($cerrada['resumen']['pendientes'])->toBe(0);

    // Cerrada, ya no cambia.
    escanearEn($id, $faltante->codigo_institucional)->assertStatus(422);

    $acta = $this->get("/api/v1/inventario/constataciones/{$id}/acta")->assertOk();
    expect($acta->headers->get('content-type'))->toBe('application/pdf')->and(substr($acta->getContent(), 0, 4))->toBe('%PDF');
});

test('lo registrado en sitio durante la constatación cuenta como nuevo', function () {
    $id = abrirConstatacion();
    $this->travel(10)->minutes();
    $nuevo = equipoConstatado();

    escanearEn($id, $nuevo->codigo_institucional)->assertJsonPath('datos.fila.resultado', 'nuevo');
});

test('con unidad, solo se espera lo de esa unidad: la de quien lo tiene o, sin custodio, la del equipo', function () {
    $paola = Servidor::create(['cedula' => '0804444441', 'nombre' => 'Paola', 'apellido' => 'Benavides', 'unidad_administrativa_id' => $this->tic->id, 'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true]);
    $dePaola = equipoConstatado(['unidad_administrativa_id' => $this->obras->id]);
    $this->postJson('/api/v1/inventario/asignaciones', ['bien_informatico_id' => $dePaola->id, 'servidor_id' => $paola->id, 'fecha_asignacion' => now()->toDateString()])->assertCreated();
    $bodegaTic = equipoConstatado(['unidad_administrativa_id' => $this->tic->id]);
    equipoConstatado(['unidad_administrativa_id' => $this->obras->id]);

    $id = abrirConstatacion(['unidad_administrativa_id' => $this->tic->id]);
    $filas = collect($this->getJson("/api/v1/inventario/constataciones/{$id}")->json('datos.filas'));

    expect($filas->pluck('codigo_institucional')->sort()->values()->all())
        ->toBe(collect([$dePaola->codigo_institucional, $bodegaTic->codigo_institucional])->sort()->values()->all());
});
