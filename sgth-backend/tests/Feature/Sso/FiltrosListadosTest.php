<?php

/*
| Los filtros de los siete listados del módulo SSO.
|
| Los `index` pasaban `$request->all()` al servicio y el servicio lo usaba
| crudo en un `where`. Dos consecuencias, las dos comprobadas aquí:
|
| - Un valor que no es booleano sobre una columna `boolean` llegaba a
|   PostgreSQL sin tocar, y respondía `22P02` — un 500 que no dice nada.
|   Ahora es un 422 que nombra el campo.
| - `por_pagina` no tenía techo: una sola petición podía pedir la tabla
|   entera. Ahora está acotado a `ListadoSsoRequest::MAX_POR_PAGINA`.
|
| Y el tercer punto, que es el que de verdad se arregló: el filtro llega al
| servicio TIPADO. Antes `estado=false` llegaba como la cadena `'false'`, que
| en PostgreSQL funciona de casualidad y en PHP es verdadera.
*/

use App\Http\Requests\Sso\ListadoSsoRequest;
use App\Models\Sso\AccidenteTrabajo;
use App\Models\Sso\CapacitacionSso;
use App\Models\Sso\EquipoProteccion;
use App\Models\Sso\FactorRiesgoCatalogo;
use App\Models\Sso\RiesgoLaboral;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Servidor::unguard();

    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $this->unidad = unidadDePrueba();
    $this->puesto = puestoDePrueba($this->unidad, 'Operador');

    $this->gestor = User::create([
        'email' => 'sso@gadpe.gob.ec',
        'usuario_ti' => 'sso_gestor',
        'password' => bcrypt('secreto'),
        'primer_login' => false,
        'activo' => true,
    ]);
    $this->gestor->assignRole('admin-uath');

    $this->servidor = Servidor::create([
        'cedula' => '0800000077',
        'nombre' => 'Ana',
        'apellido' => 'Quiñónez',
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id' => $this->puesto->id,
        'estado' => true,
    ]);

    $this->factor = FactorRiesgoCatalogo::create([
        'nombre' => 'Ruido continuo',
        'categoria' => 'fisico',
        'activo' => true,
    ]);
});

function listar(string $ruta, array $params = []): \Illuminate\Testing\TestResponse
{
    return test()->actingAs(test()->gestor, 'sanctum')
        ->getJson('/api/v1/sso/' . $ruta . '?' . http_build_query($params));
}

/** Las siete rutas de listado del módulo, con el filtro booleano que acepta cada una. */
dataset('listados', [
    'riesgos'             => ['riesgos', 'estado'],
    'accidentes'          => ['accidentes', 'estado'],
    'equipos de protección' => ['equipos-proteccion', 'estado'],
    'inspecciones'        => ['inspecciones', 'estado'],
    'capacitaciones'      => ['capacitaciones', 'estado'],
]);

// ── Lo que antes era un 500 ───────────────────────────────────────────

test('un estado que no es booleano se rechaza con 422 y no con 500', function (string $ruta, string $campo) {
    listar($ruta, [$campo => 'abc'])
        ->assertStatus(422)
        ->assertJsonPath("errores.{$campo}.0", fn ($mensaje) => is_string($mensaje));
})->with('listados');

test('un filtro numérico que no es número se rechaza con 422', function () {
    listar('riesgos', ['puesto_id' => 'x'])->assertStatus(422);
    listar('accidentes', ['servidor_id' => 'x'])->assertStatus(422);
    listar('inspecciones', ['unidad_administrativa_id' => 'x'])->assertStatus(422);
    listar('epp-entregas', ['servidor_id' => 'x'])->assertStatus(422);
});

test('un filtro que apunta a algo que no existe se rechaza con 422', function () {
    // `exists` y no solo `integer`: un id inventado devolvía una lista vacía,
    // que se lee como «este puesto no tiene riesgos» y no como «ese puesto no
    // existe».
    listar('riesgos', ['puesto_id' => 999999])->assertStatus(422);
    listar('accidentes', ['servidor_id' => 999999])->assertStatus(422);
});

// ── El techo de la paginación ─────────────────────────────────────────

test('por_pagina por encima del tope se rechaza', function (string $ruta) {
    listar($ruta, ['por_pagina' => 1000000])
        ->assertStatus(422)
        ->assertJsonPath('errores.por_pagina.0', fn ($mensaje) => is_string($mensaje));
})->with('listados');

test('por_pagina dentro del tope se respeta', function () {
    foreach (range(1, 20) as $i) {
        EquipoProteccion::create([
            'codigo' => "EPP-{$i}",
            'nombre' => "Equipo {$i}",
            'tipo' => 'manos',
            'estado' => true,
        ]);
    }

    listar('equipos-proteccion', ['por_pagina' => ListadoSsoRequest::MAX_POR_PAGINA])
        ->assertOk()
        ->assertJsonPath('meta.por_pagina', ListadoSsoRequest::MAX_POR_PAGINA)
        ->assertJsonCount(20, 'datos');
});

test('sin por_pagina el listado sigue paginando de quince en quince', function () {
    foreach (range(1, 18) as $i) {
        EquipoProteccion::create([
            'codigo' => "EPP-{$i}",
            'nombre' => "Equipo {$i}",
            'tipo' => 'manos',
            'estado' => true,
        ]);
    }

    listar('equipos-proteccion')
        ->assertOk()
        ->assertJsonPath('meta.por_pagina', 15)
        ->assertJsonCount(15, 'datos');
});

test('por_pagina cero o negativo se rechaza', function () {
    listar('riesgos', ['por_pagina' => 0])->assertStatus(422);
    listar('riesgos', ['por_pagina' => -5])->assertStatus(422);
});

// ── El filtro llega tipado ────────────────────────────────────────────

test('estado=false trae los inactivos y no los activos', function () {
    // Este es el que no se podía escribir antes: `'false'` como cadena es
    // verdadera en PHP, así que el filtro dependía de que PostgreSQL la
    // interpretara.
    $riesgo = fn(bool $estado) => RiesgoLaboral::create([
        'puesto_id' => $this->puesto->id,
        'factor_riesgo_id' => $this->factor->id,
        'descripcion' => 'Riesgo de prueba',
        'nivel_deficiencia' => 'mejorable',
        'nivel_exposicion' => 'ocasional',
        'nivel_consecuencias' => 'leve',
        'estado' => $estado,
    ]);

    $riesgo(true);
    $riesgo(true);
    $riesgo(false);

    listar('riesgos', ['estado' => 'false'])->assertOk()->assertJsonCount(1, 'datos');
    listar('riesgos', ['estado' => 'true'])->assertOk()->assertJsonCount(2, 'datos');
    listar('riesgos', ['estado' => '0'])->assertOk()->assertJsonCount(1, 'datos');
    listar('riesgos', ['estado' => '1'])->assertOk()->assertJsonCount(2, 'datos');
    // Sin el filtro, los tres.
    listar('riesgos')->assertOk()->assertJsonCount(3, 'datos');
});

test('el estado llega como la cadena que manda axios y no como 422', function () {
    // La regla `boolean` de Laravel NO acepta 'true' ni 'false', y eso es
    // exactamente lo que serializa axios con `{ estado: true }`. Los tres
    // formularios de EPP piden así el catálogo activo, así que validar sin
    // normalizar convertía una consulta que funciona en un 422. Esta prueba
    // es la que queda vigilando ese borde.
    EquipoProteccion::create([
        'codigo' => 'EPP-101',
        'nombre' => 'Casco',
        'tipo' => 'craneal',
        'estado' => true,
    ]);

    listar('equipos-proteccion', ['estado' => 'true'])->assertOk()->assertJsonCount(1, 'datos');
    // Y lo que no se reconoce sigue siendo un 422, que es el motivo del cambio.
    listar('equipos-proteccion', ['estado' => 'sí'])->assertStatus(422);
});

test('un estado vacío no se interpreta como inactivo', function () {
    // `?estado=` lo convierte el middleware en null. Con `has()` en vez de
    // `filled()` habría pasado por un `estado=false` y la pantalla habría
    // mostrado solo los inactivos sin que nadie lo pidiera.
    RiesgoLaboral::create([
        'puesto_id' => $this->puesto->id,
        'factor_riesgo_id' => $this->factor->id,
        'descripcion' => 'Riesgo activo',
        'nivel_deficiencia' => 'mejorable',
        'nivel_exposicion' => 'ocasional',
        'nivel_consecuencias' => 'leve',
        'estado' => true,
    ]);

    listar('riesgos', ['estado' => ''])->assertOk()->assertJsonCount(1, 'datos');
});

test('los filtros de los otros listados siguen filtrando', function () {
    AccidenteTrabajo::create([
        'servidor_id' => $this->servidor->id,
        'tipo_evento' => 'accidente',
        'fecha_accidente' => '2026-03-10',
        'hora_accidente' => '09:00',
        'lugar_accidente' => 'Patio',
        'descripcion_hechos' => 'Prueba',
        'gravedad' => 'leve',
        'requirio_atencion_medica' => true,
        'dias_reposo_medico' => 2,
        'estado' => true,
    ]);

    CapacitacionSso::create([
        'tema' => 'Uso de EPP',
        'fecha' => '2026-03-01',
        'duracion_horas' => 2,
        'instructor' => 'Instructor',
        'estado' => true,
    ]);

    EquipoProteccion::create([
        'codigo' => 'EPP-900',
        'nombre' => 'Casco',
        'tipo' => 'craneal',
        'estado' => true,
    ]);

    listar('accidentes', ['servidor_id' => $this->servidor->id])->assertOk()->assertJsonCount(1, 'datos');
    listar('capacitaciones', ['estado' => 'true'])->assertOk()->assertJsonCount(1, 'datos');
    listar('equipos-proteccion', ['tipo' => 'craneal'])->assertOk()->assertJsonCount(1, 'datos');
    listar('equipos-proteccion', ['tipo' => 'manos'])->assertOk()->assertJsonCount(0, 'datos');
});

test('el rango de fechas de las entregas se valida', function () {
    listar('epp-entregas', ['fecha_inicio' => 'ayer'])->assertStatus(422);
    listar('epp-entregas', [
        'fecha_inicio' => '2026-03-10',
        'fecha_fin' => '2026-03-01',
    ])->assertStatus(422);
    listar('epp-entregas', [
        'fecha_inicio' => '2026-03-01',
        'fecha_fin' => '2026-03-10',
    ])->assertOk();
});

test('el periodo de las horas trabajadas se valida con el mismo patrón del módulo', function () {
    listar('horas-trabajadas', ['periodo' => '2026-13'])->assertStatus(422);
    listar('horas-trabajadas', ['periodo' => 'el año pasado'])->assertStatus(422);
    listar('horas-trabajadas', ['periodo' => '2026'])->assertOk();
    listar('horas-trabajadas', ['periodo' => '2026-07'])->assertOk();
    listar('horas-trabajadas')->assertOk();
});

// ── El 403 sigue llegando antes que el 422 ────────────────────────────

test('a quien no puede ver el módulo se le responde 403, no 422', function () {
    // `authorize()` corre antes de `rules()`, así que un filtro inválido no
    // delata la existencia del endpoint a quien no puede consultarlo.
    $raso = User::create([
        'email' => 'raso@gadpe.gob.ec',
        'usuario_ti' => 'raso_u',
        'password' => bcrypt('secreto'),
        'primer_login' => false,
        'activo' => true,
    ]);
    $raso->assignRole('auditor');
    $raso->revokePermissionTo('ver-reportes-sso');
    $raso->roles()->detach();
    $raso->assignRole('servidor');

    $this->actingAs($raso, 'sanctum')
        ->getJson('/api/v1/sso/riesgos?estado=abc')
        ->assertStatus(403);
});
