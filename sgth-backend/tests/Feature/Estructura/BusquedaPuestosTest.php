<?php

use App\Enums\Permiso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/**
 * `search` en el listado de puestos.
 *
 * No existía, y no era un filtro de menos: `BuscarPuestoSelect` lo mandaba
 * desde cinco pantallas —convocatoria nueva, inscribir postulante, asignar EPP
 * por puesto, reporte de EPP y riesgo laboral— y el servicio lo descartaba sin
 * decir nada, así que el desplegable devolvía siempre los diez primeros puestos
 * de la institución por orden alfabético, escribiera uno lo que escribiera.
 * Parecía funcionar, y con ocho puestos de prueba nadie lo notaba.
 */
beforeEach(function () {
    $rol = Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    $rol->givePermissionTo(Permission::firstOrCreate([
        'name' => Permiso::VER_ESTRUCTURA->value, 'guard_name' => 'sanctum',
    ]));

    $usuario = User::factory()->create();
    $usuario->assignRole('admin-uath');
    $this->actingAs($usuario, 'sanctum');

    $this->tic  = unidadDePrueba(['nombre' => 'Gestión de Tecnologías']);
    $this->obra = unidadDePrueba(['nombre' => 'Gestión de Obras Públicas']);

    puestoDePrueba($this->tic, 'Analista de Tecnologías de la Información');
    puestoDePrueba($this->tic, 'Técnico de Soporte');
    puestoDePrueba($this->obra, 'Analista de Presupuesto');
    puestoDePrueba($this->obra, 'Fiscalizador');

    /** Devuelve los nombres de cargo que trae la búsqueda, en el orden servido. */
    $this->cargos = function (array $params) {
        $respuesta = $this
            ->getJson('/api/v1/estructura/puestos?'.http_build_query($params))
            ->assertOk();

        return [
            'cargos' => array_map(fn ($p) => $p['cargo']['nombre'], $respuesta->json('datos')),
            'total'  => $respuesta->json('meta.total'),
        ];
    };
});

test('search filtra por el nombre del cargo', function () {
    expect(($this->cargos)(['search' => 'fiscalizador'])['cargos'])
        ->toBe(['Fiscalizador']);
});

test('search no distingue mayúsculas de minúsculas', function () {
    expect(($this->cargos)(['search' => 'TÉCNICO'])['cargos'])
        ->toBe(['Técnico de Soporte']);
});

/*
| El desplegable enseña el cargo y debajo la unidad, y la búsqueda abarca la
| institución entera: con solo el cargo, «analista» devuelve a los analistas de
| todas las direcciones y hay que reconocerlos de vista. Por eso la unidad
| también se busca.
*/
test('search encuentra por el nombre de la unidad', function () {
    expect(($this->cargos)(['search' => 'obras'])['cargos'])
        ->toBe(['Analista de Presupuesto', 'Fiscalizador']);
});

/*
| Cada palabra tiene que aparecer en alguna de las columnas, no la frase entera
| en una sola: si no, escribir el puesto como uno lo tiene en la cabeza —el
| cargo y la dirección, en cualquier orden— no devuelve nada. Es la misma regla
| que ExpedienteService::filtrarServidores() usa para buscar personas.
*/
test('cada palabra estrecha la búsqueda en vez de exigir la frase literal', function () {
    expect(($this->cargos)(['search' => 'analista obras'])['cargos'])
        ->toBe(['Analista de Presupuesto']);
});

test('una palabra que no está en ninguna columna deja la búsqueda sin resultados', function () {
    $r = ($this->cargos)(['search' => 'analista veterinaria']);

    expect($r['cargos'])->toBe([])
        ->and($r['total'])->toBe(0);
});

test('search convive con el filtro de unidad', function () {
    // Hay dos analistas, pero solo uno en Tecnologías.
    expect(($this->cargos)([
        'search' => 'analista',
        'unidad_administrativa_id' => $this->tic->id,
    ])['cargos'])->toBe(['Analista de Tecnologías de la Información']);
});

test('sin search el listado sigue devolviéndolo todo', function () {
    expect(($this->cargos)([])['total'])->toBe(4);
});

/*
| El `meta.total` es lo que el selector usa para avisar «se muestran N de M».
| Si siguiera contando el catálogo entero, el aviso saldría sobre una búsqueda
| que ya cabe y quien lo lea creería que le falta por ver.
*/
test('el total refleja lo encontrado, no el catálogo entero', function () {
    expect(($this->cargos)(['search' => 'analista'])['total'])->toBe(2);
});

test('una búsqueda de solo espacios se ignora en vez de no devolver nada', function () {
    expect(($this->cargos)(['search' => '   '])['total'])->toBe(4);
});

/*
| El join con `unidades_administrativas` se añadió para poder buscar por unidad.
| Un join mal hecho en un «muchos a uno» no rompe nada visible: solo repite
| filas, y el listado empieza a enseñar el mismo puesto dos veces.
*/
test('el listado no repite puestos al haber dos joins', function () {
    $r = ($this->cargos)([]);

    expect($r['cargos'])->toHaveCount(4)
        ->and(array_unique($r['cargos']))->toHaveCount(4);
});
