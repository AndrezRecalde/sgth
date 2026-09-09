<?php

use App\Enums\RegimenLaboral;
use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Dispensario\InventarioMedicina;
use App\Models\Dispensario\ItemReceta;
use App\Models\Dispensario\LoteMedicina;
use App\Models\Dispensario\RecetaMedica;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();
    ConsultaMedica::unguard();
    HistoriaClinica::unguard();
    RecetaMedica::unguard();
    ItemReceta::unguard();
    InventarioMedicina::unguard();
    LoteMedicina::unguard();

    $this->medico = User::create([
        'email'        => 'medico-externo@example.com',
        'usuario_ti'   => 'medext',
        'password'     => bcrypt('123456'),
        'primer_login' => false,
    ]);
    $this->medico->assignRole(Role::firstOrCreate(
        ['name' => 'medico', 'guard_name' => 'sanctum']
    ));

    $this->farmacia = User::create([
        'email'        => 'farmacia-externo@example.com',
        'usuario_ti'   => 'farmext',
        'password'     => bcrypt('123456'),
        'primer_login' => false,
    ]);
    $this->farmacia->assignRole(Role::firstOrCreate(
        ['name' => 'admin-dispensario', 'guard_name' => 'sanctum']
    ));

    $unidad = unidadDePrueba(['nombre' => 'Direccion de Recetas Externas']);
    $puesto = puestoDePrueba($unidad, 'Analista de Recetas Externas');

    $paciente = Servidor::create([
        'cedula'                    => '0802345673',
        'nombre'                    => 'Ana',
        'apellido'                  => 'Lopez',
        'puesto_id'                 => $puesto->id,
        'unidad_administrativa_id'  => $unidad->id,
        'regimen_laboral'           => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(3),
        'estado'                    => true,
    ]);

    $historia = HistoriaClinica::create([
        'servidor_id'     => $paciente->id,
        'grupo_sanguineo' => 'O+',
    ]);

    $this->consulta = ConsultaMedica::create([
        'especialidad'        => 'medicina_general',
        'historia_clinica_id' => $historia->id,
        'medico_id'           => $this->medico->id,
        'fecha_consulta'      => now(),
        'hora_consulta'       => now()->format('H:i:s'),
        'motivo_consulta'     => 'Control',
    ]);
});

/** Una medicina del catálogo con existencias vigentes. */
function medicinaConStock(string $codigo, string $nombre, int $stock): InventarioMedicina
{
    $medicina = InventarioMedicina::create([
        'codigo'           => $codigo,
        'nombre'           => $nombre,
        'principio_activo' => strtolower($nombre),
        'presentacion'     => 'tableta',
        'stock_actual'     => $stock,
        'stock_minimo'     => 0,
        'estado'           => true,
    ]);

    LoteMedicina::create([
        'inventario_medicina_id' => $medicina->id,
        'cantidad_ingresada'     => $stock,
        'stock_actual'           => $stock,
        'fecha_caducidad'        => now()->addYear(),
    ]);

    return $medicina;
}

/** @param array<int, array<string, mixed>> $items */
function emitir(array $items, ConsultaMedica $consulta): array
{
    return [
        'consulta_medica_id' => $consulta->id,
        'fecha_emision'      => now()->toDateString(),
        'items'              => $items,
    ];
}

function itemBase(array $extra): array
{
    return array_merge([
        'cantidad_prescrita' => 10,
        'dosis'              => '1 tableta',
        'frecuencia'         => 'cada 8 horas',
        'duracion'           => '3 dias',
    ], $extra);
}

it('receta un medicamento que la farmacia no maneja', function () {
    $respuesta = $this->actingAs($this->medico, 'sanctum')
        ->postJson('/api/v1/dispensario/recetas', emitir([
            itemBase(['medicamento_externo' => 'Rosuvastatina 20 mg']),
        ], $this->consulta))
        ->assertCreated();

    $recetaId = $respuesta->json('datos.receta.id');
    $item     = ItemReceta::where('receta_medica_id', $recetaId)->sole();

    expect($item->inventario_medicina_id)->toBeNull()
        ->and($item->medicamento_externo)->toBe('Rosuvastatina 20 mg')
        ->and($item->estado)->toBe(ItemReceta::NO_DISPONIBLE);

    // Sin nada que entregar, la receta no se queda en la cola del mostrador.
    expect(RecetaMedica::find($recetaId)->estado)->toBe('externa');
});

it('receta un medicamento del catalogo que hoy esta agotado', function () {
    $medicina = InventarioMedicina::create([
        'codigo'           => 'AGOT-001',
        'nombre'           => 'Ibuprofeno',
        'principio_activo' => 'ibuprofeno',
        'presentacion'     => 'tableta',
        'stock_actual'     => 0,
        'stock_minimo'     => 0,
        'estado'           => true,
    ]);

    $this->actingAs($this->medico, 'sanctum')
        ->postJson('/api/v1/dispensario/recetas', emitir([
            itemBase(['inventario_medicina_id' => $medicina->id]),
        ], $this->consulta))
        ->assertCreated();
});

it('rechaza un item que no dice que medicamento es', function () {
    $this->actingAs($this->medico, 'sanctum')
        ->postJson('/api/v1/dispensario/recetas', emitir([
            itemBase([]),
        ], $this->consulta))
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['items.0.inventario_medicina_id']]);
});

it('rechaza un item que dice ser del catalogo y externo a la vez', function () {
    $medicina = medicinaConStock('DUAL-001', 'Amoxicilina', 50);

    $this->actingAs($this->medico, 'sanctum')
        ->postJson('/api/v1/dispensario/recetas', emitir([
            itemBase([
                'inventario_medicina_id' => $medicina->id,
                'medicamento_externo'    => 'Amoxicilina',
            ]),
        ], $this->consulta))
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['items.0.inventario_medicina_id']]);
});

it('despacha lo que hay y deja constancia de lo que no maneja la farmacia', function () {
    $medicina = medicinaConStock('MIX-001', 'Paracetamol', 40);

    $respuesta = $this->actingAs($this->medico, 'sanctum')
        ->postJson('/api/v1/dispensario/recetas', emitir([
            itemBase(['inventario_medicina_id' => $medicina->id]),
            itemBase(['medicamento_externo' => 'Rosuvastatina 20 mg']),
        ], $this->consulta))
        ->assertCreated();

    $recetaId = $respuesta->json('datos.receta.id');

    // Mezclada: hay algo que entregar, así que sigue siendo trabajo del
    // mostrador y no nace cerrada.
    expect(RecetaMedica::find($recetaId)->estado)->toBe('pendiente');

    $delCatalogo = ItemReceta::where('receta_medica_id', $recetaId)
        ->whereNotNull('inventario_medicina_id')->sole();

    $this->actingAs($this->farmacia, 'sanctum')
        ->postJson("/api/v1/dispensario/recetas/{$recetaId}/despachar", [
            'items' => [
                ['item_receta_id' => $delCatalogo->id, 'cantidad' => 10],
            ],
        ])
        ->assertOk();

    // El externo no impide que la receta se cierre: entregado todo lo que la
    // farmacia maneja, no queda nada pendiente en el mostrador.
    expect(RecetaMedica::find($recetaId)->estado)->toBe('despachada_completa')
        ->and($medicina->fresh()->stock_actual)->toBe(30);
});

it('no deja despachar un medicamento externo', function () {
    $medicina = medicinaConStock('BLOQ-001', 'Loratadina', 20);

    $respuesta = $this->actingAs($this->medico, 'sanctum')
        ->postJson('/api/v1/dispensario/recetas', emitir([
            itemBase(['inventario_medicina_id' => $medicina->id]),
            itemBase(['medicamento_externo' => 'Insulina glargina']),
        ], $this->consulta))
        ->assertCreated();

    $recetaId = $respuesta->json('datos.receta.id');
    $externo  = ItemReceta::where('receta_medica_id', $recetaId)
        ->whereNull('inventario_medicina_id')->sole();

    $this->actingAs($this->farmacia, 'sanctum')
        ->postJson("/api/v1/dispensario/recetas/{$recetaId}/despachar", [
            'items' => [
                ['item_receta_id' => $externo->id, 'cantidad' => 1],
            ],
        ])
        ->assertStatus(422);

    // Y nada se movió: el rechazo deshace la transacción entera.
    expect($medicina->fresh()->stock_actual)->toBe(20);
});

it('avisa de la alergia tambien cuando el medicamento es externo', function () {
    $this->consulta->historiaClinica->alergias()->create([
        'tipo'        => 'medicamento',
        'descripcion' => 'Penicilina',
        'severidad'   => 'grave',
    ]);

    $respuesta = $this->actingAs($this->medico, 'sanctum')
        ->postJson('/api/v1/dispensario/recetas', emitir([
            itemBase(['medicamento_externo' => 'Penicilina G benzatinica']),
        ], $this->consulta))
        ->assertCreated();

    expect($respuesta->json('datos.alertas_alergias'))->not->toBeEmpty();
});

it('permite editar un item de una receta pendiente', function () {
    $medicina = medicinaConStock('EDIT-001', 'Omeprazol', 30);

    $respuesta = $this->actingAs($this->medico, 'sanctum')
        ->postJson('/api/v1/dispensario/recetas', emitir([
            itemBase(['inventario_medicina_id' => $medicina->id]),
        ], $this->consulta))
        ->assertCreated();

    $recetaId = $respuesta->json('datos.receta.id');
    $item     = ItemReceta::where('receta_medica_id', $recetaId)->sole();

    $this->actingAs($this->medico, 'sanctum')
        ->patchJson("/api/v1/dispensario/recetas/{$recetaId}/items/{$item->id}", [
            'cantidad_prescrita' => 12,
            'dosis'              => '2 tabletas',
            'frecuencia'         => 'cada 12 horas',
            'duracion'           => '5 dias',
        ])
        ->assertOk();

    expect($item->fresh()->cantidad_prescrita)->toBe(12)
        ->and($item->fresh()->dosis)->toBe('2 tabletas');
});

it('no deja que otro medico cambie los medicamentos de una receta ajena', function () {
    $medicina = medicinaConStock('AJEN-001', 'Naproxeno', 40);

    $respuesta = $this->actingAs($this->medico, 'sanctum')
        ->postJson('/api/v1/dispensario/recetas', emitir([
            itemBase(['inventario_medicina_id' => $medicina->id]),
            itemBase(['medicamento_externo' => 'Colchicina 0.5 mg']),
        ], $this->consulta))
        ->assertCreated();

    $recetaId = $respuesta->json('datos.receta.id');
    $item     = ItemReceta::where('receta_medica_id', $recetaId)
        ->whereNotNull('inventario_medicina_id')->sole();

    $otroMedico = User::create([
        'email'        => 'otro-medico@example.com',
        'usuario_ti'   => 'otromed',
        'password'     => bcrypt('123456'),
        'primer_login' => false,
    ]);
    $otroMedico->assignRole(Role::firstOrCreate(
        ['name' => 'medico', 'guard_name' => 'sanctum']
    ));

    // La prescripción la firma quien la emitió: cambiarle la dosis o quitarle
    // un medicamento es decidir sobre esa firma.
    $this->actingAs($otroMedico, 'sanctum')
        ->patchJson("/api/v1/dispensario/recetas/{$recetaId}/items/{$item->id}", [
            'cantidad_prescrita' => 99,
            'dosis'              => '9 tabletas',
            'frecuencia'         => 'cada hora',
            'duracion'           => '90 dias',
        ])
        ->assertStatus(403);

    $this->actingAs($otroMedico, 'sanctum')
        ->deleteJson("/api/v1/dispensario/recetas/{$recetaId}/items/{$item->id}")
        ->assertStatus(403);

    expect($item->fresh()->cantidad_prescrita)->toBe(10)
        ->and($item->fresh()->deleted_at)->toBeNull();
});

it('cierra la receta si se quita el ultimo medicamento del catalogo', function () {
    $medicina = medicinaConStock('QUIT-001', 'Metformina', 60);

    $respuesta = $this->actingAs($this->medico, 'sanctum')
        ->postJson('/api/v1/dispensario/recetas', emitir([
            itemBase(['inventario_medicina_id' => $medicina->id]),
            itemBase(['medicamento_externo' => 'Empagliflozina 10 mg']),
        ], $this->consulta))
        ->assertCreated();

    $recetaId = $respuesta->json('datos.receta.id');
    $item     = ItemReceta::where('receta_medica_id', $recetaId)
        ->whereNotNull('inventario_medicina_id')->sole();

    $this->actingAs($this->medico, 'sanctum')
        ->deleteJson("/api/v1/dispensario/recetas/{$recetaId}/items/{$item->id}")
        ->assertOk();

    expect(RecetaMedica::find($recetaId)->estado)->toBe('externa');
});
