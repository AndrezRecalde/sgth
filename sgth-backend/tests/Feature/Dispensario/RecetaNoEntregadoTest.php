<?php

/*
| Un medicamento del inventario que el mostrador no va a entregar (decisión
| del Dispensario del 2026-10-24, opción A): se marca «no entregado» con su
| motivo, quién y cuándo; la receta se cierra cuando todo está entregado o
| marcado, y no se anula. No se avisa al médico.
*/

use App\Enums\RegimenLaboral;
use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Dispensario\InventarioMedicina;
use App\Models\Dispensario\ItemReceta;
use App\Models\Dispensario\LoteMedicina;
use App\Models\Dispensario\MovimientoInventarioMed;
use App\Models\Dispensario\RecetaMedica;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Model::unguard();

    $usuario = function (string $alias, string $rol) {
        $user = User::create([
            'email' => "{$alias}@example.com", 'usuario_ti' => $alias,
            'password' => bcrypt('123456'), 'primer_login' => false,
        ]);
        $user->assignRole(Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum']));

        return $user;
    };
    $this->medico = $usuario('medico-no-entregado', 'medico');
    $this->enfermera = $usuario('enfermera-no-entregado', 'enfermera');

    $unidad = unidadDePrueba(['nombre' => 'Direccion de Recetas No Entregadas']);
    $paciente = Servidor::create([
        'cedula' => '0812530001', 'nombre' => 'Ana', 'apellido' => 'Mostrador',
        'puesto_id' => puestoDePrueba($unidad, 'Analista de Recetas No Entregadas')->id,
        'unidad_administrativa_id' => $unidad->id, 'regimen_laboral' => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(3), 'estado' => true,
    ]);
    $historia = HistoriaClinica::create(['servidor_id' => $paciente->id, 'cedula_paciente' => '0812530001']);
    $this->consulta = ConsultaMedica::create([
        'especialidad' => 'medicina_general', 'historia_clinica_id' => $historia->id,
        'medico_id' => $this->medico->id, 'fecha_consulta' => now(), 'hora_consulta' => now()->format('H:i:s'),
        'motivo_consulta' => 'Control',
    ]);

    $this->paracetamol = medicinaNoEntregado('MED-NE-1', 'Paracetamol', 50);
    $this->amoxicilina = medicinaNoEntregado('MED-NE-2', 'Amoxicilina', 50);
});

function medicinaNoEntregado(string $codigo, string $nombre, int $stock): InventarioMedicina
{
    $medicina = InventarioMedicina::create([
        'codigo' => $codigo, 'nombre' => $nombre, 'principio_activo' => strtolower($nombre),
        'presentacion' => 'tableta', 'stock_actual' => $stock, 'stock_minimo' => 0, 'estado' => true,
    ]);
    LoteMedicina::create([
        'inventario_medicina_id' => $medicina->id, 'cantidad_ingresada' => $stock,
        'stock_actual' => $stock, 'fecha_caducidad' => now()->addYear(),
    ]);

    return $medicina;
}

/** Una receta del médico con esas medicinas, diez de cada una. */
function recetaNoEntregado(array $medicinas, array $externos = []): RecetaMedica
{
    $items = [
        ...array_map(fn (InventarioMedicina $m) => ['inventario_medicina_id' => $m->id], $medicinas),
        ...array_map(fn (string $nombre) => ['medicamento_externo' => $nombre], $externos),
    ];

    $id = test()->actingAs(test()->medico, 'sanctum')->postJson('/api/v1/dispensario/recetas', [
        'consulta_medica_id' => test()->consulta->id,
        'fecha_emision'      => now()->toDateString(),
        'items'              => array_map(fn ($i) => [
            ...$i, 'cantidad_prescrita' => 10, 'dosis' => '1 tableta', 'frecuencia' => 'cada 8 horas', 'duracion' => '3 dias',
        ], $items),
    ])->assertCreated()->json('datos.receta.id');

    return RecetaMedica::with('items')->findOrFail($id);
}

function itemNoEntregado(RecetaMedica $receta, InventarioMedicina $medicina): ItemReceta
{
    return $receta->items->firstWhere('inventario_medicina_id', $medicina->id);
}

function marcarNoEntregado(RecetaMedica $receta, ItemReceta $item, array $datos = ['motivo' => 'agotado']): TestResponse
{
    return test()->actingAs(test()->enfermera, 'sanctum')
        ->patchJson("/api/v1/dispensario/recetas/{$receta->id}/items/{$item->id}/no-entregado", $datos);
}

function despacharNoEntregado(RecetaMedica $receta, ItemReceta $item, int $cantidad): TestResponse
{
    return test()->actingAs(test()->enfermera, 'sanctum')
        ->postJson("/api/v1/dispensario/recetas/{$receta->id}/despachar", [
            'items' => [['item_receta_id' => $item->id, 'cantidad' => $cantidad]],
        ]);
}

test('lo entregado más lo marcado cierra la receta, sin anularla ni tocar el kardex', function () {
    $receta = recetaNoEntregado([$this->paracetamol, $this->amoxicilina]);
    despacharNoEntregado($receta, itemNoEntregado($receta, $this->paracetamol), 10)->assertOk();
    $movimientos = MovimientoInventarioMed::count();

    marcarNoEntregado($receta, itemNoEntregado($receta, $this->amoxicilina))
        ->assertOk()
        ->assertJsonPath('datos.estado', 'cerrada_con_faltantes');

    $item = itemNoEntregado($receta, $this->amoxicilina)->fresh();
    expect($item->estado)->toBe('no_entregado')
        ->and($item->motivo_no_entregado)->toBe('agotado')
        ->and($item->no_entregado_por)->toBe($this->enfermera->id)
        ->and($item->no_entregado_en)->not->toBeNull()
        ->and($receta->fresh()->anulado_en)->toBeNull()
        ->and(MovimientoInventarioMed::count())->toBe($movimientos)
        ->and($this->amoxicilina->fresh()->stock_actual)->toBe(50);
});

test('marcar uno antes de entregar el otro deja la receta abierta hasta despacharlo', function () {
    $receta = recetaNoEntregado([$this->paracetamol, $this->amoxicilina]);

    marcarNoEntregado($receta, itemNoEntregado($receta, $this->amoxicilina), ['motivo' => 'vencido'])
        ->assertOk()
        ->assertJsonPath('datos.estado', 'despachada_parcial');

    despacharNoEntregado($receta, itemNoEntregado($receta, $this->paracetamol), 10)
        ->assertOk()
        ->assertJsonPath('datos.estado', 'cerrada_con_faltantes');
});

test('lo que falta de uno entregado a medias también se marca, y lo entregado se queda', function () {
    $receta = recetaNoEntregado([$this->amoxicilina]);
    despacharNoEntregado($receta, itemNoEntregado($receta, $this->amoxicilina), 4)->assertOk();

    marcarNoEntregado($receta, itemNoEntregado($receta, $this->amoxicilina))->assertOk()
        ->assertJsonPath('datos.estado', 'cerrada_con_faltantes');

    $item = itemNoEntregado($receta, $this->amoxicilina)->fresh();
    expect($item->estado)->toBe('no_entregado')->and($item->cantidad_despachada)->toBe(4);
});

test('«otro motivo» pide el detalle, y el motivo tiene que ser uno de la lista', function () {
    $receta = recetaNoEntregado([$this->amoxicilina]);
    $item = itemNoEntregado($receta, $this->amoxicilina);

    marcarNoEntregado($receta, $item, ['motivo' => 'otro'])
        ->assertStatus(422)->assertJsonValidationErrors(['detalle'], 'errores');
    marcarNoEntregado($receta, $item, ['motivo' => 'se acabó'])
        ->assertStatus(422)->assertJsonValidationErrors(['motivo'], 'errores');

    marcarNoEntregado($receta, $item, ['motivo' => 'otro', 'detalle' => 'Retirado por el fabricante'])->assertOk();
    expect($item->fresh()->detalle_no_entregado)->toBe('Retirado por el fabricante');
});

test('no se marca un externo, uno ya entregado, ni nada de una receta cerrada', function () {
    $receta = recetaNoEntregado([$this->paracetamol, $this->amoxicilina], ['Rosuvastatina 20 mg']);
    $externo = $receta->items->firstWhere('medicamento_externo', 'Rosuvastatina 20 mg');

    marcarNoEntregado($receta, $externo)
        ->assertStatus(422)
        ->assertJsonPath('mensaje', '«Rosuvastatina 20 mg» no lo maneja la farmacia: ya se adquiere fuera.');

    despacharNoEntregado($receta, itemNoEntregado($receta, $this->paracetamol), 10)->assertOk();
    marcarNoEntregado($receta, itemNoEntregado($receta, $this->paracetamol))
        ->assertStatus(422)
        ->assertJsonPath('mensaje', '«Paracetamol» ya se entregó completo.');

    marcarNoEntregado($receta, itemNoEntregado($receta, $this->amoxicilina))->assertOk();
    // Cerrada: ni se vuelve a marcar, ni se despacha, ni se anula.
    marcarNoEntregado($receta, itemNoEntregado($receta, $this->amoxicilina))->assertStatus(422);
    despacharNoEntregado($receta, itemNoEntregado($receta, $this->amoxicilina), 1)->assertStatus(422);
    $this->actingAs($this->medico, 'sanctum')
        ->postJson("/api/v1/dispensario/recetas/{$receta->id}/anular", ['motivo_anulacion' => 'Cambio de tratamiento'])
        ->assertStatus(422);
});

test('lo marca el mostrador, no el médico', function () {
    $receta = recetaNoEntregado([$this->amoxicilina]);

    $this->actingAs($this->medico, 'sanctum')
        ->patchJson("/api/v1/dispensario/recetas/{$receta->id}/items/" . itemNoEntregado($receta, $this->amoxicilina)->id . '/no-entregado', ['motivo' => 'agotado'])
        ->assertForbidden();
});

test('el listado y el resumen del mostrador cuentan las cerradas con faltantes', function () {
    $receta = recetaNoEntregado([$this->amoxicilina]);
    marcarNoEntregado($receta, itemNoEntregado($receta, $this->amoxicilina))->assertOk();

    $this->actingAs($this->enfermera, 'sanctum')
        ->getJson('/api/v1/dispensario/recetas?estado=cerrada_con_faltantes')
        ->assertOk()
        ->assertJsonPath('meta.resumen.cerrada_con_faltantes', 1);
});

test('el impreso dice qué adquirir fuera, con lo que ya se entregó', function () {
    $receta = recetaNoEntregado([$this->paracetamol, $this->amoxicilina]);
    despacharNoEntregado($receta, itemNoEntregado($receta, $this->paracetamol), 10)->assertOk();
    despacharNoEntregado($receta, itemNoEntregado($receta, $this->amoxicilina), 4)->assertOk();
    marcarNoEntregado($receta, itemNoEntregado($receta, $this->amoxicilina))->assertOk();

    $html = view('pdf.dispensario.receta-medica',
        app(App\Services\Dispensario\PdfRecetaService::class)->datosDelImpreso($receta->id))->render();

    expect($html)->toContain('No se entregó en el Dispensario (agotado)')
        ->and($html)->toContain('se entregaron 4; adquiera 6 unid. fuera.')
        ->and($html)->toContain('adquiéralo en una')
        // El asterisco del amoxicilina y el de la nota; el paracetamol, entregado, no lleva.
        ->and(substr_count($html, '<span class="marca-externo">*</span>'))->toBe(2);
});
