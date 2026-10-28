<?php

/*
| El rol `auxiliar-farmacia` (docs/plan-roles-y-accesos.md, PR 13).
|
| Atiende el mostrador: consulta el inventario y su kárdex, da de baja lo
| vencido o roto, y despacha las recetas de cualquier médico. No toca el
| catálogo ni ajusta el stock, que son de la administración, y no entra a
| historias clínicas, consultas, agenda ni búsqueda de pacientes. El JSON de
| las recetas ya no lleva las notas de la consulta (PR 5).
*/

use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Dispensario\InventarioMedicina;
use App\Models\Dispensario\LoteMedicina;
use App\Models\Dispensario\RecetaMedica;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolPermisoSeeder::class);

    $this->conRol = function (string $rol): User {
        $usuario = User::factory()->create();
        $usuario->assignRole($rol);

        return $usuario;
    };

    $this->auxiliar = ($this->conRol)('auxiliar-farmacia');
    $this->medico = ($this->conRol)('medico');
    $this->colega = ($this->conRol)('medico');

    $this->consultaDe = function (User $medico, string $cedula): ConsultaMedica {
        $servidor = Servidor::forceCreate(['cedula' => $cedula, 'nombre' => 'Paciente', 'apellido' => $cedula]);
        $historia = HistoriaClinica::forceCreate(['servidor_id' => $servidor->id]);

        return ConsultaMedica::forceCreate([
            'especialidad'       => 'medicina_general',
            'historia_clinica_id' => $historia->id,
            'medico_id'          => $medico->id,
            'fecha_consulta'     => now()->toDateString(),
            'hora_consulta'      => '09:00:00',
            'motivo_consulta'    => 'Dolor de garganta',
            'notas_medico'       => 'Nota privada del médico',
        ]);
    };

    $this->paracetamol = InventarioMedicina::forceCreate([
        'codigo' => 'MED-AUX-1', 'nombre' => 'Paracetamol', 'principio_activo' => 'paracetamol',
        'presentacion' => 'tableta', 'stock_actual' => 50, 'stock_minimo' => 0, 'estado' => true,
    ]);
    LoteMedicina::forceCreate([
        'inventario_medicina_id' => $this->paracetamol->id, 'cantidad_ingresada' => 50,
        'stock_actual' => 50, 'fecha_caducidad' => now()->addYear(),
    ]);

    // La receta la emite el médico por el API, como en el consultorio.
    $this->recetaDe = function (ConsultaMedica $consulta): RecetaMedica {
        $id = $this->actingAs(User::find($consulta->medico_id), 'sanctum')
            ->postJson('/api/v1/dispensario/recetas', [
                'consulta_medica_id' => $consulta->id,
                'fecha_emision'      => now()->toDateString(),
                'items'              => [[
                    'inventario_medicina_id' => $this->paracetamol->id, 'cantidad_prescrita' => 10,
                    'dosis' => '1 tableta', 'frecuencia' => 'cada 8 horas', 'duracion' => '3 dias',
                ]],
            ])->assertCreated()->json('datos.receta.id');

        return RecetaMedica::with('items')->findOrFail($id);
    };
});

// ── El rol ────────────────────────────────────────────────────────────────

test('el auxiliar despacha y no tiene permisos clinicos', function () {
    expect($this->auxiliar->can('despachar-medicamento'))->toBeTrue();

    foreach (['ver-historia-clinica', 'ver-agenda-dispensario', 'crear-consulta', 'emitir-receta',
        'gestionar-fichas-sso-med', 'evaluar-personal-medico'] as $permiso) {
        expect($this->auxiliar->can($permiso))->toBeFalse("no debería tener {$permiso}");
    }
});

// ── Inventario ────────────────────────────────────────────────────────────

test('el auxiliar consulta el inventario y su kardex', function () {
    $this->actingAs($this->auxiliar, 'sanctum');

    $this->getJson('/api/v1/dispensario/inventario/medicinas')->assertOk();
    $this->getJson("/api/v1/dispensario/inventario/medicinas/{$this->paracetamol->id}")->assertOk();
    $this->getJson('/api/v1/dispensario/inventario/medicinas/stock-bajo')->assertOk();
    $this->getJson('/api/v1/dispensario/inventario/medicinas/buscar?q=para')->assertOk();
    $this->getJson("/api/v1/dispensario/inventario/medicinas/{$this->paracetamol->id}/kardex")->assertOk();
});

test('el auxiliar da de baja existencias', function () {
    $this->actingAs($this->auxiliar, 'sanctum')
        ->postJson("/api/v1/dispensario/inventario/medicinas/{$this->paracetamol->id}/baja", [
            'cantidad' => 5, 'motivo' => 'Rotura',
        ])
        ->assertOk();

    expect($this->paracetamol->fresh()->stock_actual)->toBe(45);
});

test('el catalogo, el ajuste y las adquisiciones siguen siendo de la administracion', function () {
    $this->actingAs($this->auxiliar, 'sanctum');

    $this->postJson('/api/v1/dispensario/inventario/medicinas', [])->assertForbidden();
    $this->putJson("/api/v1/dispensario/inventario/medicinas/{$this->paracetamol->id}", [])->assertForbidden();
    $this->postJson("/api/v1/dispensario/inventario/medicinas/{$this->paracetamol->id}/ajustar-inventario", [])
        ->assertForbidden();
    $this->getJson('/api/v1/dispensario/adquisiciones')->assertForbidden();
});

// ── Despacho ──────────────────────────────────────────────────────────────

test('el auxiliar ve las recetas de todos los medicos, sin las notas de la consulta', function () {
    ($this->recetaDe)(($this->consultaDe)($this->medico, '0809292901'));
    ($this->recetaDe)(($this->consultaDe)($this->colega, '0809292902'));

    $recetas = $this->actingAs($this->auxiliar, 'sanctum')
        ->getJson('/api/v1/dispensario/recetas')
        ->assertOk()
        ->json('datos.data');

    expect($recetas)->toHaveCount(2)
        ->and($recetas[0]['consulta_medica'])->not->toHaveKeys(['motivo_consulta', 'notas_medico']);
});

test('el auxiliar despacha una receta y marca lo que no entrega', function () {
    $receta = ($this->recetaDe)(($this->consultaDe)($this->medico, '0809292901'));
    $item = $receta->items->first();

    $this->actingAs($this->auxiliar, 'sanctum');

    $this->getJson("/api/v1/dispensario/recetas/{$receta->id}")->assertOk();
    $this->get("/api/v1/dispensario/recetas/{$receta->id}/pdf")->assertOk();

    $this->postJson("/api/v1/dispensario/recetas/{$receta->id}/despachar", [
        'items' => [['item_receta_id' => $item->id, 'cantidad' => 4]],
    ])->assertOk();

    $this->patchJson("/api/v1/dispensario/recetas/{$receta->id}/items/{$item->id}/no-entregado", [
        'motivo' => 'agotado',
    ])->assertOk()->assertJsonPath('datos.estado', 'cerrada_con_faltantes');

    expect($this->paracetamol->fresh()->stock_actual)->toBe(46);
});

test('el auxiliar no emite ni anula recetas', function () {
    $consulta = ($this->consultaDe)($this->medico, '0809292901');
    $receta = ($this->recetaDe)($consulta);

    $this->actingAs($this->auxiliar, 'sanctum');

    $this->postJson('/api/v1/dispensario/recetas', ['consulta_medica_id' => $consulta->id])->assertForbidden();
    $this->postJson("/api/v1/dispensario/recetas/{$receta->id}/anular", ['motivo_anulacion' => 'Prueba'])
        ->assertForbidden();
});

test('el auxiliar lista al personal medico para filtrar el despacho', function () {
    $this->actingAs($this->auxiliar, 'sanctum')
        ->getJson('/api/v1/dispensario/personal-medico')
        ->assertOk();
});

// ── Lo clínico ────────────────────────────────────────────────────────────

test('el auxiliar no entra a historias, consultas, agenda ni pacientes', function () {
    $this->actingAs($this->auxiliar, 'sanctum');

    foreach ([
        'historias-clinicas', 'consultas', 'agenda/turnos-del-dia',
        'pacientes/buscar?cedula=0809292901', 'pacientes/buscar-por-nombre?nombre=Paciente',
        'atenciones-enfermeria', 'reportes',
    ] as $ruta) {
        $this->getJson("/api/v1/dispensario/{$ruta}")->assertForbidden();
    }
});

// ── Migración ─────────────────────────────────────────────────────────────

test('la migracion crea el rol como el seeder', function () {
    $permisos = fn () => Role::findByName('auxiliar-farmacia', 'sanctum')
        ->permissions()->pluck('name')->sort()->values()->all();

    $delSeeder = $permisos();

    $this->auxiliar->syncRoles([]);

    $migracion = require database_path('migrations/2026_12_01_000000_crear_rol_auxiliar_farmacia.php');

    $migracion->down();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    expect(Role::where('name', 'auxiliar-farmacia')->exists())->toBeFalse();

    $migracion->up();
    $migracion->up(); // idempotente
    // Las lecturas de farmacia se las da la migración del PR 17a.
    (require database_path('migrations/2026_12_04_000000_dispensario_permisos_de_farmacia.php'))->up();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($permisos())->toBe($delSeeder);
});

test('la migracion no retira el rol mientras alguien lo tenga', function () {
    $migracion = require database_path('migrations/2026_12_01_000000_crear_rol_auxiliar_farmacia.php');

    expect(fn () => $migracion->down())->toThrow(RuntimeException::class, $this->auxiliar->usuario_ti);
    expect(Role::where('name', 'auxiliar-farmacia')->exists())->toBeTrue();
});
