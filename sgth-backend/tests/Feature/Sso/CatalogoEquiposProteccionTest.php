<?php

/*
| El catálogo de equipos de protección que alimenta los desplegables.
|
| Los tres formularios que eligen un equipo —asignar EPP a un puesto,
| registrar un movimiento y entregar el kit— pedían `/sso/equipos-proteccion`,
| que PAGINA de 15 en 15, y leían `datos`. Con dieciséis equipos activos, el
| decimosexto no se podía asignar ni entregar, y el desplegable respondía «Sin
| equipos en el catálogo»: la pantalla afirmaba que no había lo que sí había.
|
| Subir el tope de `por_pagina` habría sido el mismo defecto aplazado a los
| 101 equipos. De ahí un endpoint de catálogo, como el que ya tenía
| `factores-riesgo`.
*/

use App\Models\Sso\EquipoProteccion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $this->gestor = User::create([
        'email' => 'catalogo@gadpe.gob.ec',
        'usuario_ti' => 'catalogo_epp',
        'password' => bcrypt('secreto'),
        'primer_login' => false,
        'activo' => true,
    ]);
    $this->gestor->assignRole('admin-uath');
});

function equipo(int $n, bool $activo = true): EquipoProteccion
{
    return EquipoProteccion::create([
        'codigo' => sprintf('EPP-%03d', $n),
        'nombre' => sprintf('Equipo %03d', $n),
        'tipo' => 'manos',
        'vida_util_meses' => 12,
        'estado' => $activo,
    ]);
}

function catalogo(): \Illuminate\Testing\TestResponse
{
    return test()->actingAs(test()->gestor, 'sanctum')
        ->getJson('/api/v1/sso/catalogos/equipos-proteccion');
}

// ── El tope que ya no existe ──────────────────────────────────────────

test('el catálogo devuelve más de una página de equipos', function () {
    // 40: más del doble de los 15 que cabían en la primera página, y más del
    // límite por defecto de cualquier paginación.
    foreach (range(1, 40) as $n) {
        equipo($n);
    }

    catalogo()->assertOk()->assertJsonCount(40, 'datos');
});

test('el listado sigue paginando y el catálogo no: son dos cosas distintas', function () {
    // La tabla del catálogo SÍ debe paginar —son 40 filas en pantalla— y el
    // desplegable NO. Esta prueba fija la diferencia para que nadie unifique
    // los dos endpoints «simplificando»: eso es exactamente lo que causaba el
    // defecto.
    foreach (range(1, 40) as $n) {
        equipo($n);
    }

    $this->actingAs($this->gestor, 'sanctum')
        ->getJson('/api/v1/sso/equipos-proteccion?estado=true')
        ->assertOk()
        ->assertJsonCount(15, 'datos')
        ->assertJsonPath('meta.total', 40);

    catalogo()->assertOk()->assertJsonCount(40, 'datos');
});

test('el equipo número dieciséis está en el catálogo', function () {
    // El caso concreto que no se podía asignar ni entregar.
    foreach (range(1, 16) as $n) {
        equipo($n);
    }

    $codigos = collect(catalogo()->assertOk()->json('datos'))->pluck('codigo');

    expect($codigos)->toContain('EPP-016');
    expect($codigos)->toHaveCount(16);
});

// ── Qué entra y qué no ────────────────────────────────────────────────

test('el catálogo trae solo los equipos activos', function () {
    equipo(1);
    equipo(2, activo: false);

    $codigos = collect(catalogo()->assertOk()->json('datos'))->pluck('codigo');

    expect($codigos->all())->toBe(['EPP-001']);
});

test('el catálogo viene ordenado por nombre', function () {
    equipo(3);
    equipo(1);
    equipo(2);

    $nombres = collect(catalogo()->assertOk()->json('datos'))->pluck('nombre');

    expect($nombres->all())->toBe(['Equipo 001', 'Equipo 002', 'Equipo 003']);
});

test('el catálogo trae las cuatro columnas que necesita un desplegable', function () {
    // Y no la fila entera: lo que se pinta es «EPP-014 — Respirador».
    equipo(1);

    $fila = catalogo()->assertOk()->json('datos.0');

    expect(array_keys($fila))->toEqualCanonicalizing(['id', 'codigo', 'nombre', 'tipo']);
});

test('un catálogo vacío es una lista vacía, no un error', function () {
    // Es lo que hace honesto el mensaje «Sin equipos en el catálogo» del
    // desplegable: antes lo decía con quince equipos y un decimosexto fuera.
    catalogo()->assertOk()->assertJsonCount(0, 'datos');
});

// ── Quién puede leerlo ────────────────────────────────────────────────

test('quien solo ve reportes también puede leer el catálogo', function () {
    // El auditor abre las nueve pantallas en lectura; el desplegable aparece
    // deshabilitado, pero la consulta no debe darle 403.
    equipo(1);

    $auditor = User::create([
        'email' => 'auditor@gadpe.gob.ec',
        'usuario_ti' => 'auditor_epp',
        'password' => bcrypt('secreto'),
        'primer_login' => false,
        'activo' => true,
    ]);
    $auditor->assignRole('auditor');

    $this->actingAs($auditor, 'sanctum')
        ->getJson('/api/v1/sso/catalogos/equipos-proteccion')
        ->assertOk();
});

test('quien no tiene acceso al módulo no lee el catálogo', function () {
    $raso = User::create([
        'email' => 'raso@gadpe.gob.ec',
        'usuario_ti' => 'raso_epp',
        'password' => bcrypt('secreto'),
        'primer_login' => false,
        'activo' => true,
    ]);
    $raso->assignRole('servidor');

    $this->actingAs($raso, 'sanctum')
        ->getJson('/api/v1/sso/catalogos/equipos-proteccion')
        ->assertForbidden();
});

// ── Que no se coma la ruta del recurso ────────────────────────────────

test('la ruta del catálogo no choca con el show del recurso', function () {
    // `catalogos/equipos-proteccion` y no `equipos-proteccion/catalogo`: el
    // `apiResource` ya registró `GET equipos-proteccion/{id}` y Laravel
    // resuelve por orden de registro, así que el segundo nombre habría
    // quedado capturado como un id.
    $uno = equipo(1);

    $this->actingAs($this->gestor, 'sanctum')
        ->getJson("/api/v1/sso/equipos-proteccion/{$uno->id}")
        ->assertOk()
        ->assertJsonPath('datos.codigo', 'EPP-001');

    catalogo()->assertOk()->assertJsonCount(1, 'datos');
});
