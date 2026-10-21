<?php

use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Dispensario\InventarioMedicina;
use App\Models\Dispensario\ItemReceta;
use App\Models\Dispensario\LoteMedicina;
use App\Models\Dispensario\RecetaMedica;
use App\Models\User;
use App\Services\Dispensario\RecetaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/*
 * Dos despachos a la vez de la misma receta sacaban el doble del estante: los
 * dos leían «0 entregadas» antes de que el otro escribiera. La carrera no se
 * puede provocar dentro de una prueba (corre en una sola conexión), así que se
 * comprueba lo que la evita: que la receta se bloquea antes de leer sus ítems.
 * Se reprodujo aparte con tres procesos contra una base real: con el código
 * anterior salían 20 unidades para 10 recetadas; con este, el segundo despacho
 * se rechaza.
 */

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Model::unguard();

    $this->medico = User::create([
        'email' => 'medico-bloqueo@example.com', 'usuario_ti' => 'medbloq',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $this->medico->assignRole(Role::firstOrCreate(['name' => 'medico', 'guard_name' => 'sanctum']));

    $historia = HistoriaClinica::create([
        'numero_historia' => '0912345670', 'cedula_paciente' => '0912345670',
        'tipo_paciente' => 'candidato', 'estado' => true,
    ]);
    $consulta = ConsultaMedica::create([
        'especialidad' => 'medicina_general', 'historia_clinica_id' => $historia->id,
        'medico_id' => $this->medico->id, 'fecha_consulta' => now(),
        'hora_consulta' => '09:00', 'motivo_consulta' => 'Control',
    ]);
    $medicina = InventarioMedicina::create([
        'codigo' => 'BLQ-001', 'nombre' => 'Paracetamol', 'principio_activo' => 'paracetamol',
        'presentacion' => 'tableta', 'stock_actual' => 50, 'stock_minimo' => 0, 'estado' => true,
    ]);
    LoteMedicina::create([
        'inventario_medicina_id' => $medicina->id, 'cantidad_ingresada' => 50,
        'stock_actual' => 50, 'fecha_caducidad' => now()->addYear(),
    ]);
    $this->receta = RecetaMedica::create([
        'folio' => 'REC-BLQ-1', 'consulta_medica_id' => $consulta->id,
        'fecha_emision' => now(), 'estado' => 'pendiente',
    ]);
    $this->item = ItemReceta::create([
        'receta_medica_id' => $this->receta->id, 'inventario_medicina_id' => $medicina->id,
        'cantidad_prescrita' => 10, 'cantidad_despachada' => 0, 'estado' => 'pendiente',
        'dosis' => '1 tableta', 'frecuencia' => 'cada 8 horas', 'duracion' => '3 dias',
    ]);
});

/**
 * Las consultas a recetas e ítems que hace $accion, en orden.
 *
 * @return list<string>
 */
function consultasDeRecetaDurante(callable $accion): array
{
    $vistas = [];
    DB::listen(function ($q) use (&$vistas) {
        if (preg_match('/from "(recetas_medicas|items_receta)"/', $q->sql)) {
            $vistas[] = $q->sql;
        }
    });

    $accion();

    return $vistas;
}

test('el_despacho_bloquea_la_receta_antes_de_leer_sus_items', function () {
    $consultas = consultasDeRecetaDurante(fn () => app(RecetaService::class)->despacharReceta(
        $this->receta->id,
        [['item_receta_id' => $this->item->id, 'cantidad' => 10]],
        $this->medico->id,
    ));

    expect($consultas[0])->toContain('from "recetas_medicas"')->toContain('for update')
        ->and(collect($consultas)->search(fn ($sql) => str_contains($sql, 'from "items_receta"')))
        ->toBeGreaterThan(0);
});

test('editar_un_item_bloquea_la_receta_como_el_despacho', function () {
    $this->actingAs($this->medico, 'sanctum');

    $consultas = consultasDeRecetaDurante(fn () => $this->patchJson(
        "/api/v1/dispensario/recetas/{$this->receta->id}/items/{$this->item->id}",
        ['cantidad_prescrita' => 5, 'dosis' => '1 tableta', 'frecuencia' => 'cada 8 horas', 'duracion' => '3 dias'],
    )->assertOk());

    expect(collect($consultas)->contains(
        fn ($sql) => str_contains($sql, 'from "recetas_medicas"') && str_contains($sql, 'for update')
    ))->toBeTrue()
        ->and($this->item->fresh()->cantidad_prescrita)->toBe(5);
});
