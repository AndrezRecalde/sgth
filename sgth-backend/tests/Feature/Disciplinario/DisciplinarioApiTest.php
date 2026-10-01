<?php

namespace Tests\Feature\Disciplinario;

use App\Enums\CausalVistoBueno;
use App\Enums\EstadoSumario;
use App\Models\Disciplinario\Sumario;
use App\Models\Disciplinario\VistoBueno;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * El listado del módulo por HTTP: quién entra, cuántas filas trae y si pagina
 * sin repetirse. Las reglas de negocio viven en VistoBuenoTest.
 */
beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    Role::firstOrCreate(['name' => 'asistente-uath', 'guard_name' => 'sanctum']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin-uath');

    $this->asistente = User::factory()->create();
    $this->asistente->assignRole('asistente-uath');

    $unidad = UnidadAdministrativa::create([
        'codigo' => 'UATH-API', 'nombre' => 'Unidad de Talento Humano', 'nivel' => 1,
    ]);

    $puesto = Puesto::create([
        'codigo' => 'P-API', 'unidad_administrativa_id' => $unidad->id, 'plazas' => 40,
    ]);

    $this->servidor = function (int $n) use ($unidad, $puesto): Servidor {
        return Servidor::create([
            'user_id'                   => User::factory()->create()->id,
            'cedula'                    => str_pad((string) (6100000000 + $n), 10, '0', STR_PAD_LEFT),
            'nombre'                    => 'Servidor',
            'apellido'                  => 'Listado'.$n,
            'regimen_laboral'           => 'losep',
            'puesto_id'                 => $puesto->id,
            'unidad_administrativa_id'  => $unidad->id,
            'fecha_ingreso_institucion' => '2018-01-01',
        ]);
    };
});

// ── Quién entra ─────────────────────────────────────────────────

test('el módulo disciplinario exige el rol admin-uath', function () {
    $this->actingAs($this->asistente, 'sanctum')
        ->getJson('/api/v1/disciplinario/sumarios')
        ->assertStatus(403);

    $this->actingAs($this->asistente, 'sanctum')
        ->getJson('/api/v1/disciplinario/vistos-buenos')
        ->assertStatus(403);
});

test('sin autenticar el módulo responde 401', function () {
    $this->getJson('/api/v1/disciplinario/sumarios')->assertStatus(401);
});

// ── Paginación ──────────────────────────────────────────────────

/**
 * Todos el MISMO día a propósito: `fecha_apertura` es una fecha sin hora, así
 * que es el caso en el que un `ORDER BY` sin desempate deja el orden en manos
 * del motor y una fila se repite entre páginas o no sale en ninguna.
 */
function abrirSumarios(callable $servidorDe, int $cuantos): void
{
    for ($i = 1; $i <= $cuantos; $i++) {
        Sumario::create([
            'servidor_id'    => $servidorDe($i)->id,
            'motivo'         => "Sumario de prueba $i",
            'estado'         => EstadoSumario::ABIERTO,
            'fecha_apertura' => '2026-03-02',
            'notificado_sn'  => false,
        ]);
    }
}

test('el listado de sumarios pagina de 15 en 15 por defecto', function () {
    abrirSumarios($this->servidor, 20);

    $pagina = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/disciplinario/sumarios')
        ->assertStatus(200)
        ->json('datos');

    expect($pagina['per_page'])->toBe(15)
        ->and($pagina['total'])->toBe(20)
        ->and($pagina['data'])->toHaveCount(15);
});

test('las dos páginas de sumarios no comparten ninguna fila', function () {
    abrirSumarios($this->servidor, 20);

    $primera = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/disciplinario/sumarios?per_page=10&page=1')
        ->json('datos.data');

    $segunda = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/disciplinario/sumarios?per_page=10&page=2')
        ->json('datos.data');

    $idsPrimera = array_column($primera, 'id');
    $idsSegunda = array_column($segunda, 'id');

    expect($idsPrimera)->toHaveCount(10)
        ->and($idsSegunda)->toHaveCount(10)
        ->and(array_intersect($idsPrimera, $idsSegunda))->toBeEmpty()
        ->and(array_unique(array_merge($idsPrimera, $idsSegunda)))->toHaveCount(20);
});

test('el listado de vistos buenos pagina de 15 en 15 por defecto', function () {
    for ($i = 1; $i <= 18; $i++) {
        VistoBueno::create([
            'servidor_id'     => ($this->servidor)($i)->id,
            'causal'          => CausalVistoBueno::INDISCIPLINA_DESOBEDIENCIA->value,
            'estado'          => 'solicitado',
            'hechos'          => "Trámite de prueba $i",
            'fecha_solicitud' => '2026-03-02',
        ]);
    }

    $pagina = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/disciplinario/vistos-buenos')
        ->assertStatus(200)
        ->json('datos');

    expect($pagina['per_page'])->toBe(15)
        ->and($pagina['total'])->toBe(18)
        ->and($pagina['data'])->toHaveCount(15);
});

test('el filtro por estado recorta el listado y el total', function () {
    abrirSumarios($this->servidor, 4);

    Sumario::query()->limit(1)->update(['estado' => EstadoSumario::CERRADO->value]);

    $pagina = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/disciplinario/sumarios?estado=cerrado')
        ->assertStatus(200)
        ->json('datos');

    expect($pagina['total'])->toBe(1)
        ->and($pagina['data'][0]['estado'])->toBe('cerrado');
});
