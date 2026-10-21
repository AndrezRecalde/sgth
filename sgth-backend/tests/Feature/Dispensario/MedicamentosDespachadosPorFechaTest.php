<?php

use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Dispensario\InventarioMedicina;
use App\Models\Dispensario\ItemReceta;
use App\Models\Dispensario\LoteMedicina;
use App\Models\Dispensario\RecetaMedica;
use App\Models\User;
use App\Services\Dispensario\EstadisticasDispensarioService;
use App\Services\Dispensario\RecetaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

/*
 * «Medicamentos más despachados» se leía de `items_receta`, filtrando por
 * cuándo se recetó y sumando todo lo despachado del ítem. Una receta de fin de
 * mes entregada el día 2 contaba en el mes anterior, y un despacho parcial
 * repartido en dos meses caía entero en el primero. Ahora cuenta cada salida
 * del kardex en su fecha.
 */

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Model::unguard();

    // Fechas fijas, para que el mes de cada cosa no dependa del día en que
    // corra la prueba.
    $this->travelTo(Carbon::parse('2026-09-29 08:00'));

    $this->farmacia = User::create([
        'email' => 'kpi-despacho@example.com', 'usuario_ti' => 'kpi-despacho',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);

    $historia = HistoriaClinica::create([
        'numero_historia' => '0912345690', 'cedula_paciente' => '0912345690',
        'tipo_paciente' => 'candidato', 'estado' => true,
    ]);
    $consulta = ConsultaMedica::create([
        'especialidad' => 'medicina_general', 'historia_clinica_id' => $historia->id,
        'medico_id' => $this->farmacia->id, 'fecha_consulta' => '2026-09-29',
        'hora_consulta' => '09:00', 'motivo_consulta' => 'Control',
    ]);
    $medicina = InventarioMedicina::create([
        'codigo' => 'KPI-DES-1', 'nombre' => 'Amoxicilina', 'principio_activo' => 'amoxicilina',
        'presentacion' => 'capsula', 'stock_actual' => 50, 'stock_minimo' => 0, 'estado' => true,
    ]);
    LoteMedicina::create([
        'inventario_medicina_id' => $medicina->id, 'cantidad_ingresada' => 50,
        'stock_actual' => 50, 'fecha_caducidad' => '2027-12-31',
    ]);

    // Recetada el 29 de septiembre.
    $this->travelTo(Carbon::parse('2026-09-29 11:00'));
    $this->receta = RecetaMedica::create([
        'folio' => 'REC-KPI-1', 'consulta_medica_id' => $consulta->id,
        'fecha_emision' => '2026-09-29', 'estado' => 'pendiente',
    ]);
    $this->item = ItemReceta::create([
        'receta_medica_id' => $this->receta->id, 'inventario_medicina_id' => $medicina->id,
        'cantidad_prescrita' => 10, 'cantidad_despachada' => 0, 'estado' => 'pendiente',
        'dosis' => '1 capsula', 'frecuencia' => 'cada 8 horas', 'duracion' => '5 dias',
    ]);
});

function despacharEl(string $cuando, int $recetaId, int $itemId, int $cantidad, int $quien): void
{
    test()->travelTo(Carbon::parse($cuando));
    app(RecetaService::class)->despacharReceta(
        $recetaId, [['item_receta_id' => $itemId, 'cantidad' => $cantidad]], $quien
    );
}

/** @return array<string, int> nombre => unidades despachadas en ese mes */
function despachadoEnElMes(string $mes): array
{
    $inicio = Carbon::parse("{$mes}-01");

    return collect(app(EstadisticasDispensarioService::class)
        ->obtenerKpis($inicio->copy()->startOfMonth(), $inicio->copy()->endOfMonth())
        ['medicamentos_mas_despachados'])
        ->mapWithKeys(fn ($m) => [$m->nombre => (int) $m->total_despachado])
        ->all();
}

test('lo_recetado_un_mes_y_entregado_el_siguiente_cuenta_en_el_mes_de_la_entrega', function () {
    despacharEl('2026-10-02 09:00', $this->receta->id, $this->item->id, 10, $this->farmacia->id);

    expect(despachadoEnElMes('2026-09'))->toBe([])
        ->and(despachadoEnElMes('2026-10'))->toBe(['Amoxicilina' => 10]);
});

test('un_despacho_parcial_repartido_en_dos_meses_cuenta_cada_parte_en_su_mes', function () {
    despacharEl('2026-09-30 16:00', $this->receta->id, $this->item->id, 4, $this->farmacia->id);
    despacharEl('2026-10-03 09:00', $this->receta->id, $this->item->id, 6, $this->farmacia->id);

    expect(despachadoEnElMes('2026-09'))->toBe(['Amoxicilina' => 4])
        ->and(despachadoEnElMes('2026-10'))->toBe(['Amoxicilina' => 6]);
});
