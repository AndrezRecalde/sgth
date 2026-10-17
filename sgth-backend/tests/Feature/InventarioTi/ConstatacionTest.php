<?php

use App\Enums\RegimenLaboral;
use App\Models\Estructura\Puesto;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\Servidor;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\Constatacion;
use App\Models\InventarioTi\Marca;
use App\Models\InventarioTi\MovimientoUbicacionBien;
use App\Models\InventarioTi\TipoBien;
use App\Models\InventarioTi\Ubicacion;
use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;

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

// ── Activos Fijos y Bodega (5.2b, P13) ─────────────────────────────────────

/** El jefe de Activos Fijos y Bodega en el organigrama: el titular con contrato vigente sobre el puesto de jefatura. */
function jefeDeActivosFijosDePrueba(Puesto $puesto, string $apellido, string $cedula): ContratoServidor
{
    $s = Servidor::create([
        'cedula' => $cedula, 'nombre' => 'Jefe', 'apellido' => $apellido,
        'unidad_administrativa_id' => $puesto->unidad_administrativa_id, 'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true,
    ]);

    return ContratoServidor::create([
        'servidor_id' => $s->id, 'tipo_nombramiento' => 'nombramiento_permanente', 'unidad_administrativa_id' => $puesto->unidad_administrativa_id,
        'puesto_id' => $puesto->id, 'fecha_inicio' => '2024-01-02', 'estado' => 'vigente',
    ]);
}

/** El acta, en HTML, con los mismos datos con los que la ruta armó el PDF. */
function actaDeConstatacionEnHtml(int $id): string
{
    $datos = null;
    View::composer('pdf.inventario.acta-constatacion', function ($vista) use (&$datos) {
        $datos ??= $vista->getData();
    });
    test()->get("/api/v1/inventario/constataciones/{$id}/acta")->assertOk();

    return view('pdf.inventario.acta-constatacion', $datos)->render();
}

test('se abre con o sin Activos Fijos y Bodega, y la marca solo cambia mientras está en curso', function () {
    $sin = abrirConstatacion();
    $con = abrirConstatacion(['participa_activos_fijos' => true]);

    $this->getJson("/api/v1/inventario/constataciones/{$sin}")->assertOk()
        ->assertJsonPath('datos.participa_activos_fijos', false)->assertJsonPath('datos.firmante_activos_fijos', null);
    $this->getJson("/api/v1/inventario/constataciones/{$con}")->assertOk()
        ->assertJsonPath('datos.participa_activos_fijos', true)->assertJsonPath('datos.firmante_activos_fijos.sellado', false);
    expect(collect($this->getJson('/api/v1/inventario/constataciones')->json('datos'))->pluck('participa_activos_fijos', 'id')->all())
        ->toBe([$con => true, $sin => false]);

    // En curso se corrige; el auditor no.
    $this->patchJson("/api/v1/inventario/constataciones/{$sin}/activos-fijos", ['participa_activos_fijos' => true])->assertOk()
        ->assertJsonPath('datos.participa_activos_fijos', true);
    $this->patchJson("/api/v1/inventario/constataciones/{$sin}/activos-fijos", [])->assertStatus(422)
        ->assertJsonValidationErrors('participa_activos_fijos', 'errores');
    $this->actingAs($this->auditor, 'sanctum')
        ->patchJson("/api/v1/inventario/constataciones/{$sin}/activos-fijos", ['participa_activos_fijos' => false])->assertForbidden();

    // Cerrada, ya no.
    $this->actingAs($this->tecnico, 'sanctum')->postJson("/api/v1/inventario/constataciones/{$con}/cerrar")->assertOk();
    $this->patchJson("/api/v1/inventario/constataciones/{$con}/activos-fijos", ['participa_activos_fijos' => false])->assertStatus(422)
        ->assertJsonPath('mensaje', 'La constatación ya está cerrada: no se puede modificar.');
    expect(Constatacion::find($con)->participa_activos_fijos)->toBeTrue();
});

test('quien firma por Activos Fijos y Bodega queda sellado al cerrar, aunque después cambie el jefe', function () {
    $unidad = unidadDePrueba(['codigo' => 'AFB-01', 'nombre' => 'Activos Fijos y Bodega']);
    config(['inventario.unidad_activos_fijos_codigo' => 'AFB-01']);
    $puesto = puestoJefeDePrueba($unidad, 'Jefe de Activos Fijos y Bodega');
    $saliente = jefeDeActivosFijosDePrueba($puesto, 'Cortez', '0805550001');

    $cerrada = abrirConstatacion(['participa_activos_fijos' => true]);
    $abierta = abrirConstatacion(['participa_activos_fijos' => true]);
    $this->getJson("/api/v1/inventario/constataciones/{$cerrada}")
        ->assertJsonPath('datos.firmante_activos_fijos', ['nombre' => 'Jefe Cortez', 'cargo' => 'Jefe de Activos Fijos y Bodega', 'sellado' => false]);
    $this->postJson("/api/v1/inventario/constataciones/{$cerrada}/cerrar")->assertOk();

    // Cambia el jefe: la cerrada conserva al de su cierre; la que sigue en curso ya muestra al nuevo.
    $saliente->update(['estado' => 'terminado', 'fecha_fin' => now()->toDateString()]);
    jefeDeActivosFijosDePrueba($puesto, 'Valencia', '0805550002');

    $this->getJson("/api/v1/inventario/constataciones/{$cerrada}")
        ->assertJsonPath('datos.firmante_activos_fijos', ['nombre' => 'Jefe Cortez', 'cargo' => 'Jefe de Activos Fijos y Bodega', 'sellado' => true]);
    $this->getJson("/api/v1/inventario/constataciones/{$abierta}")
        ->assertJsonPath('datos.firmante_activos_fijos.nombre', 'Jefe Valencia');
    expect(actaDeConstatacionEnHtml($cerrada))->toContain('Jefe Cortez')->not->toContain('Jefe Valencia');
});

test('el acta lleva dos firmas, o tres cuando participa Activos Fijos y Bodega', function () {
    $sin = abrirConstatacion();
    $this->postJson("/api/v1/inventario/constataciones/{$sin}/cerrar")->assertOk();
    // Sin la unidad en el organigrama (o con el puesto vacante) sale el cargo, y se firma a mano.
    config(['inventario.unidad_activos_fijos_codigo' => 'SIN-UNIDAD']);
    $con = abrirConstatacion(['participa_activos_fijos' => true]);
    $this->postJson("/api/v1/inventario/constataciones/{$con}/cerrar")->assertOk();

    $html = actaDeConstatacionEnHtml($sin);
    expect(substr_count($html, 'class="firma-rotulo"'))->toBe(2)
        ->and($html)->not->toContain('Activos Fijos');

    $html = actaDeConstatacionEnHtml($con);
    expect(substr_count($html, 'class="firma-rotulo"'))->toBe(3)
        ->and($html)->toContain('con la participación de la Unidad de Activos Fijos y Bodega')
        ->toContain('Por Activos Fijos y Bodega')
        ->toContain('Responsable de Activos Fijos y Bodega');
    expect(Constatacion::find($con)->firmante_activos_fijos)
        ->toBe(['nombre' => null, 'cargo' => 'Responsable de Activos Fijos y Bodega']);
});
