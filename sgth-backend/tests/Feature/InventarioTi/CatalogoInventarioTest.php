<?php

use App\Models\Expediente\Servidor;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\ContratoVigencia;
use App\Models\InventarioTi\Marca;
use App\Models\InventarioTi\TipoBien;
use App\Models\User;
use App\Enums\RegimenLaboral;
use Database\Seeders\InventarioTiCatalogoSeeder;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
| Los catálogos del inventario (fase 1.1). Hasta aquí había tablas de tipos y
| marcas, pero ninguna ruta las llenaba: registrar un equipo pedía ids que
| nadie podía dar de alta.
*/

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    BienInformatico::unguard();
    Servidor::unguard();

    $this->seed(RolPermisoSeeder::class);

    $this->tecnico = User::factory()->create(['primer_login' => false]);
    $this->tecnico->assignRole('tecnico-dtic');
    $this->actingAs($this->tecnico, 'sanctum');
});

function equipoDeCatalogo(array $atributos): BienInformatico
{
    static $n = 6000;
    $n++;

    return BienInformatico::create(array_merge([
        'codigo_qr' => "{$n}-QR", 'codigo_institucional' => (string) $n,
        'estado_operativo' => 'disponible', 'condicion_fisica' => 'bueno',
    ], $atributos));
}

test('la marca no se repite sin distinguir mayusculas ni espacios', function () {
    $this->postJson('/api/v1/inventario/marcas', ['nombre' => 'DELL'])->assertCreated();

    $this->postJson('/api/v1/inventario/marcas', ['nombre' => '  dell '])
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['nombre']]);
});

test('una marca en uso no se borra, y una borrada se puede volver a crear', function () {
    $tipo = TipoBien::create(['nombre' => 'Computador']);
    $hp = Marca::create(['nombre' => 'HP']);
    $lenovo = Marca::create(['nombre' => 'LENOVO']);
    equipoDeCatalogo(['tipo_bien_id' => $tipo->id, 'marca_id' => $hp->id]);

    $this->deleteJson("/api/v1/inventario/marcas/{$hp->id}")->assertStatus(422);

    $this->deleteJson("/api/v1/inventario/marcas/{$lenovo->id}")->assertOk();
    $this->postJson('/api/v1/inventario/marcas', ['nombre' => 'Lenovo'])->assertCreated();

    $marcas = $this->getJson('/api/v1/inventario/marcas')->assertOk()->json('datos');
    expect(collect($marcas)->firstWhere('nombre', 'HP')['bienes_count'])->toBe(1);
});

test('el subtipo no se repite dentro de su tipo, pero si en otro', function () {
    $computador = TipoBien::create(['nombre' => 'Computador']);
    $impresora = TipoBien::create(['nombre' => 'Impresora']);

    $this->postJson('/api/v1/inventario/subtipos', ['tipo_bien_id' => $computador->id, 'nombre' => 'Completo'])->assertCreated();
    $this->postJson('/api/v1/inventario/subtipos', ['tipo_bien_id' => $computador->id, 'nombre' => 'COMPLETO'])->assertStatus(422);
    $this->postJson('/api/v1/inventario/subtipos', ['tipo_bien_id' => $impresora->id, 'nombre' => 'Completo'])->assertCreated();
});

test('sede y piso juntos no se repiten', function () {
    $this->postJson('/api/v1/inventario/ubicaciones', ['sede' => 'Edificio principal', 'piso' => '3'])->assertCreated();
    $this->postJson('/api/v1/inventario/ubicaciones', ['sede' => 'Edificio principal', 'piso' => '4'])->assertCreated();
    $this->postJson('/api/v1/inventario/ubicaciones', ['sede' => 'edificio principal', 'piso' => '3'])
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['sede']]);

    $this->postJson('/api/v1/inventario/ubicaciones', ['sede' => 'San Mateo'])->assertCreated();
    $this->postJson('/api/v1/inventario/ubicaciones', ['sede' => 'San Mateo'])->assertStatus(422);
});

test('el contrato de vigencia guarda su cronograma y lo reemplaza al editar', function () {
    $administrador = Servidor::create([
        'cedula' => '0805555555', 'nombre' => 'Carlos', 'apellido' => 'Batioja',
        'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true,
    ]);

    $creado = $this->postJson('/api/v1/inventario/contratos-vigencia', [
        'proceso'          => 'SIE-GADPE-018-2021',
        'administrador_id' => $administrador->id,
        'fecha_inicio'     => '2021-10-04',
        'fecha_fin'        => '2024-10-30',
        'cronograma'       => [
            ['fecha_desde' => '2022-10-15', 'fecha_hasta' => '2022-10-30'],
            ['fecha_desde' => '2023-10-15', 'fecha_hasta' => '2023-10-30'],
        ],
    ])->assertCreated();

    expect($creado->json('datos.cronograma'))->toHaveCount(2)
        ->and($creado->json('datos.administrador.nombre'))->toBe('Batioja Carlos')
        ->and($creado->json('datos.vigente'))->toBeFalse();

    $id = $creado->json('datos.id');
    $this->putJson("/api/v1/inventario/contratos-vigencia/{$id}", [
        'cronograma' => [['fecha_desde' => '2024-10-15', 'fecha_hasta' => '2024-10-30', 'descripcion' => 'Tercer año']],
    ])->assertOk();

    expect(ContratoVigencia::find($id)->cronograma()->pluck('descripcion')->all())->toBe(['Tercer año']);

    $this->postJson('/api/v1/inventario/contratos-vigencia', ['proceso' => 'sie-gadpe-018-2021'])->assertStatus(422);
    $this->postJson('/api/v1/inventario/contratos-vigencia', [
        'proceso' => 'OTRO-2025', 'fecha_inicio' => '2025-01-01', 'fecha_fin' => '2024-01-01',
    ])->assertStatus(422);
});

test('un contrato que cubre equipos no se borra', function () {
    $contrato = ContratoVigencia::create(['proceso' => 'SIE-2023-01']);
    equipoDeCatalogo([
        'tipo_bien_id' => TipoBien::create(['nombre' => 'Computador'])->id,
        'marca_id' => Marca::create(['nombre' => 'HP'])->id,
        'contrato_vigencia_id' => $contrato->id,
    ]);

    $this->deleteJson("/api/v1/inventario/contratos-vigencia/{$contrato->id}")->assertStatus(422);
});

test('el formulario recibe los catalogos y los estados en una consulta', function () {
    $this->seed(InventarioTiCatalogoSeeder::class);

    $datos = $this->getJson('/api/v1/inventario/catalogos')->assertOk()->json('datos');

    $computador = collect($datos['tipos'])->firstWhere('nombre', 'Computador');
    expect(collect($computador['subtipos'])->pluck('nombre')->all())->toContain('Todo en uno', 'CPU')
        ->and(collect($datos['estados'])->pluck('valor')->all())->toContain('en_uso', 'disponible', 'dado_de_baja')
        ->and(collect($datos['ubicaciones'])->where('sede', 'Edificio principal'))->toHaveCount(7);
});

test('el auditor consulta los catalogos pero no los cambia', function () {
    $auditor = User::factory()->create(['primer_login' => false]);
    $auditor->assignRole('auditor');
    $this->actingAs($auditor, 'sanctum');

    $this->getJson('/api/v1/inventario/catalogos')->assertOk();
    $this->getJson('/api/v1/inventario/contratos-vigencia')->assertOk();
    $this->postJson('/api/v1/inventario/marcas', ['nombre' => 'ACER'])->assertForbidden();
    $this->postJson('/api/v1/inventario/contratos-vigencia', ['proceso' => 'X'])->assertForbidden();
});
