<?php

use App\Models\Dispensario\DiagnosticoCie10;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Un puñado del catálogo real, con sus tildes y sus mayúsculas.
    collect([
        ['G430', 'MIGRAÑA SIN AURA'],
        ['G431', 'MIGRAÑA CON AURA'],
        ['I10X', 'HIPERTENSIÓN ESENCIAL (PRIMARIA)'],
        ['E112', 'DIABETES MELLITUS TIPO 2 CON COMPLICACIONES RENALES'],
        ['J00X', 'FARINGITIS AGUDA, NO ESPECIFICADA'],
        ['A000', 'CÓLERA DEBIDO A VIBRIO CHOLERAE O1'],
    ])->each(fn ($d) => DiagnosticoCie10::create([
        'codigo' => $d[0], 'descripcion' => $d[1],
        'categoria' => substr($d[0], 0, 3), 'activo' => true,
    ]));

    DiagnosticoCie10::create([
        'codigo' => 'Z999', 'descripcion' => 'MIGRAÑA RETIRADA DEL CATÁLOGO',
        'categoria' => 'Z99', 'activo' => false,
    ]);
});

/** @return array<int, string> los códigos que devuelve la búsqueda, en orden */
function codigosBuscando(string $termino): array
{
    return collect(
        test()->getJson('/api/v1/dispensario/cie10/buscar?q=' . urlencode($termino))
            ->assertOk()
            ->json('datos')
    )->pluck('codigo')->all();
}

test('el_cie10_se_encuentra_sin_teclear_las_tildes', function () {
    // 2325 de las 8918 descripciones del catálogo llevan tilde o eñe, y con un
    // paciente delante nadie las teclea. Antes esto devolvía cero y el
    // desplegable decía «Sin resultados», que se lee como «no existe».
    expect(codigosBuscando('migrana'))->toBe(['G430', 'G431']);
    expect(codigosBuscando('hipertension'))->toBe(['I10X']);
    expect(codigosBuscando('colera'))->toBe(['A000']);
});

test('las_palabras_se_pueden_escribir_en_cualquier_orden', function () {
    // El término tenía que aparecer seguido y en ese orden: «diabetes 2» no
    // encontraba «DIABETES MELLITUS TIPO 2».
    expect(codigosBuscando('diabetes 2'))->toBe(['E112']);
    expect(codigosBuscando('aguda faringitis'))->toBe(['J00X']);
    expect(codigosBuscando('renales diabetes'))->toBe(['E112']);
});

test('el_codigo_exacto_sale_primero', function () {
    DiagnosticoCie10::create([
        'codigo' => 'J001', 'descripcion' => 'OTRA COSA QUE EMPIEZA POR J00',
        'categoria' => 'J00', 'activo' => true,
    ]);

    expect(codigosBuscando('J00X')[0])->toBe('J00X');
    expect(codigosBuscando('J00'))->toBe(['J001', 'J00X']);
});

test('la_busqueda_dice_cuantos_hay_cuando_recorta_la_lista', function () {
    foreach (range(1, 25) as $i) {
        DiagnosticoCie10::create([
            'codigo'      => 'X' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
            'descripcion' => 'INFECCIÓN DE PRUEBA NÚMERO ' . $i,
            'categoria'   => 'X00',
            'activo'      => true,
        ]);
    }

    $respuesta = $this->getJson('/api/v1/dispensario/cie10/buscar?q=infeccion')
        ->assertOk();

    // Se devuelven 20, pero la respuesta dice que hay 25: el recorte se hacía
    // en silencio y quien buscaba algo general creía que veía todo.
    expect($respuesta->json('datos'))->toHaveCount(20);
    expect($respuesta->json('meta.total'))->toBe(25);
    expect($respuesta->json('meta.hay_mas'))->toBeTrue();
});

test('cuando_caben_todos_no_dice_que_haya_mas', function () {
    $respuesta = $this->getJson('/api/v1/dispensario/cie10/buscar?q=migrana')
        ->assertOk();

    expect($respuesta->json('meta.total'))->toBe(2);
    expect($respuesta->json('meta.hay_mas'))->toBeFalse();
});

test('un_codigo_retirado_no_se_ofrece_para_diagnosticar', function () {
    expect(codigosBuscando('migrana'))->not->toContain('Z999');
});

test('con_menos_de_dos_caracteres_no_se_busca', function () {
    $respuesta = $this->getJson('/api/v1/dispensario/cie10/buscar?q=m')
        ->assertOk();

    expect($respuesta->json('datos'))->toBe([]);
});
