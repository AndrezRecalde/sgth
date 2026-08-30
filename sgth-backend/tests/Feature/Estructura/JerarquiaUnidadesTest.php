<?php

use App\Enums\Permiso;
use App\Models\Estructura\TipoUnidad;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $rol = Role::firstOrCreate(['name' => 'gestor-organigrama', 'guard_name' => 'sanctum']);
    foreach ([Permiso::VER_ESTRUCTURA, Permiso::GESTIONAR_ORGANIGRAMA] as $permiso) {
        $rol->givePermissionTo(
            Permission::firstOrCreate(['name' => $permiso->value, 'guard_name' => 'sanctum'])
        );
    }

    $this->gestor = User::factory()->create(['primer_login' => false]);
    $this->gestor->assignRole($rol);

    $this->raiz = UnidadAdministrativa::create([
        'codigo'          => 'GAD-01',
        'nombre'          => 'GAD Provincial',
        'unidad_padre_id' => null,
        'nivel'           => 1,
        'estado'          => true,
    ]);
});

/**
 * `tipo_unidad_id` va aquí porque es obligatorio al crear por API: el
 * organigrama agrupa las unidades por tipo de proceso y una sin él acababa
 * archivada en «agregadores de valor» sin que nadie lo eligiera.
 *
 * @return array<string, mixed>
 */
function datosUnidad(array $extra = []): array
{
    $tipo = TipoUnidad::firstOrCreate(
        ['id' => '44444444-4444-4444-4444-444444444444'],
        ['acronimo' => 'AV', 'descripcion' => 'AGREGADORES DE VALOR']
    );

    return array_merge([
        'codigo'         => 'UA-'.fake()->unique()->numberBetween(100, 999),
        'nombre'         => 'Unidad de prueba',
        'tipo_unidad_id' => $tipo->id,
        'estado'         => true,
    ], $extra);
}

// ── Cálculo del nivel ────────────────────────────────────────────────────────

test('el nivel se deriva del padre y no se pide en la petición', function () {
    $respuestaNivel2 = $this->actingAs($this->gestor, 'sanctum')
        ->postJson('/api/v1/estructura/unidades-administrativas', datosUnidad([
            'codigo'          => 'DIR-01',
            'nombre'          => 'Gestión de Talento Humano',
            'unidad_padre_id' => $this->raiz->id,
        ]));

    $respuestaNivel2->assertStatus(201)->assertJsonPath('datos.nivel', 2);

    $respuestaNivel3 = $this->actingAs($this->gestor, 'sanctum')
        ->postJson('/api/v1/estructura/unidades-administrativas', datosUnidad([
            'codigo'          => 'SUB-01',
            'nombre'          => 'Subproceso de Nómina',
            'unidad_padre_id' => $respuestaNivel2->json('datos.id'),
        ]));

    $respuestaNivel3->assertStatus(201)->assertJsonPath('datos.nivel', 3);
});

// ── Raíz única ───────────────────────────────────────────────────────────────

test('no se puede crear una segunda unidad raíz', function () {
    // El organigrama de nodos dibuja la primera raíz y el PDF arma su portada
    // con ella: una segunda se guardaba bien y no aparecía en ninguna vista.
    $this->actingAs($this->gestor, 'sanctum')
        ->postJson('/api/v1/estructura/unidades-administrativas', datosUnidad([
            'nombre'          => 'Otra institución',
            'unidad_padre_id' => null,
        ]))
        ->assertStatus(422);

    expect(
        UnidadAdministrativa::whereNull('unidad_padre_id')->count()
    )->toBe(1);
});

test('no se puede dejar huérfana una unidad existente', function () {
    $direccion = UnidadAdministrativa::create(datosUnidad([
        'nombre'          => 'Gestión con padre',
        'unidad_padre_id' => $this->raiz->id,
        'nivel'           => 2,
    ]));

    $this->actingAs($this->gestor, 'sanctum')
        ->putJson("/api/v1/estructura/unidades-administrativas/{$direccion->id}", [
            'unidad_padre_id' => null,
        ])
        ->assertStatus(422);

    expect($direccion->fresh()->unidad_padre_id)->toBe($this->raiz->id);
});

test('la propia raíz se puede editar sin que se le exija un padre', function () {
    $this->actingAs($this->gestor, 'sanctum')
        ->putJson("/api/v1/estructura/unidades-administrativas/{$this->raiz->id}", [
            'nombre'          => 'GAD Provincial de Esmeraldas',
            'unidad_padre_id' => null,
        ])
        ->assertStatus(200)
        ->assertJsonPath('datos.nivel', 1);
});

// ── Tope de profundidad ──────────────────────────────────────────────────────

test('no se puede colgar una unidad de un subproceso', function () {
    $direccion = UnidadAdministrativa::create(datosUnidad([
        'nombre'          => 'Gestión Financiera',
        'unidad_padre_id' => $this->raiz->id,
        'nivel'           => 2,
    ]));

    $subproceso = UnidadAdministrativa::create(datosUnidad([
        'nombre'          => 'Contabilidad',
        'unidad_padre_id' => $direccion->id,
        'nivel'           => 3,
    ]));

    $this->actingAs($this->gestor, 'sanctum')
        ->postJson('/api/v1/estructura/unidades-administrativas', datosUnidad([
            'nombre'          => 'Cuarto nivel',
            'unidad_padre_id' => $subproceso->id,
        ]))
        ->assertStatus(422);

    expect(UnidadAdministrativa::where('nombre', 'Cuarto nivel')->exists())->toBeFalse();
});

test('mover una unidad con subprocesos que excedería el tope se rechaza', function () {
    $direccionA = UnidadAdministrativa::create(datosUnidad([
        'nombre'          => 'Gestión A',
        'unidad_padre_id' => $this->raiz->id,
        'nivel'           => 2,
    ]));

    UnidadAdministrativa::create(datosUnidad([
        'nombre'          => 'Subproceso de A',
        'unidad_padre_id' => $direccionA->id,
        'nivel'           => 3,
    ]));

    $direccionB = UnidadAdministrativa::create(datosUnidad([
        'nombre'          => 'Gestión B',
        'unidad_padre_id' => $this->raiz->id,
        'nivel'           => 2,
    ]));

    // Gestión A lleva un subproceso a cuestas: colgarla de Gestión B dejaría
    // a ese subproceso en un cuarto nivel que la estructura no admite.
    $this->actingAs($this->gestor, 'sanctum')
        ->putJson("/api/v1/estructura/unidades-administrativas/{$direccionA->id}", [
            'unidad_padre_id' => $direccionB->id,
        ])
        ->assertStatus(422);

    expect($direccionA->fresh()->unidad_padre_id)->toBe($this->raiz->id);
});

// ── Integridad del árbol ─────────────────────────────────────────────────────

test('una unidad no puede colgar de su propia descendencia', function () {
    $direccion = UnidadAdministrativa::create(datosUnidad([
        'nombre'          => 'Gestión de Obras',
        'unidad_padre_id' => $this->raiz->id,
        'nivel'           => 2,
    ]));

    $subproceso = UnidadAdministrativa::create(datosUnidad([
        'nombre'          => 'Fiscalización',
        'unidad_padre_id' => $direccion->id,
        'nivel'           => 3,
    ]));

    $this->actingAs($this->gestor, 'sanctum')
        ->putJson("/api/v1/estructura/unidades-administrativas/{$direccion->id}", [
            'unidad_padre_id' => $subproceso->id,
        ])
        ->assertStatus(422);
});

test('mover una unidad recalcula el nivel de toda su rama', function () {
    $direccion = UnidadAdministrativa::create(datosUnidad([
        'nombre'          => 'Gestión suelta',
        'unidad_padre_id' => null,
        'nivel'           => 1,
    ]));

    $subproceso = UnidadAdministrativa::create(datosUnidad([
        'nombre'          => 'Subproceso arrastrado',
        'unidad_padre_id' => $direccion->id,
        'nivel'           => 2,
    ]));

    $this->actingAs($this->gestor, 'sanctum')
        ->putJson("/api/v1/estructura/unidades-administrativas/{$direccion->id}", [
            'unidad_padre_id' => $this->raiz->id,
        ])
        ->assertStatus(200)
        ->assertJsonPath('datos.nivel', 2);

    expect($subproceso->fresh()->nivel)->toBe(3);
});

// ── Anclajes de firma al crear ───────────────────────────────────────────────

test('al crear una unidad marcada como Talento Humano se libera la anterior', function () {
    $anterior = UnidadAdministrativa::create(datosUnidad([
        'nombre'                   => 'UATH anterior',
        'unidad_padre_id'          => $this->raiz->id,
        'nivel'                    => 2,
        'es_unidad_talento_humano' => true,
    ]));

    $this->actingAs($this->gestor, 'sanctum')
        ->postJson('/api/v1/estructura/unidades-administrativas', datosUnidad([
            'nombre'                   => 'UATH nueva',
            'unidad_padre_id'          => $this->raiz->id,
            'es_unidad_talento_humano' => true,
        ]))
        ->assertStatus(201)
        ->assertJsonPath('datos.es_unidad_talento_humano', true);

    expect($anterior->fresh()->es_unidad_talento_humano)->toBeFalse();
});

// ── Tipo de proceso ──────────────────────────────────────────────────────────

test('no se puede crear una unidad sin tipo de proceso', function () {
    $datos = datosUnidad(['unidad_padre_id' => $this->raiz->id]);
    unset($datos['tipo_unidad_id']);

    // Sin tipo, el organigrama la archivaba en «agregadores de valor» —tanto
    // en el gráfico como en el PDF— sin que nadie hubiera elegido esa
    // categoría. Es mejor exigirlo que colocarla en una casilla inventada.
    $this->actingAs($this->gestor, 'sanctum')
        ->postJson('/api/v1/estructura/unidades-administrativas', $datos)
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['tipo_unidad_id']]);
});

// ── Autorización ─────────────────────────────────────────────────────────────

test('sin el permiso de gestionar organigrama no se puede crear una unidad', function () {
    $sinPermiso = User::factory()->create(['primer_login' => false]);

    $this->actingAs($sinPermiso, 'sanctum')
        ->postJson('/api/v1/estructura/unidades-administrativas', datosUnidad([
            'unidad_padre_id' => $this->raiz->id,
        ]))
        ->assertStatus(403);
});
