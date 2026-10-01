<?php

/*
| Los dos cuestionarios públicos y la ventana de su campaña.
|
| Son rutas sin autenticación, accesibles solo por el código de la campaña, y
| la guarda que decide si admiten una respuesta comprobaba dos de los tres
| datos de la ventana:
|
| - `fecha_apertura` no se miraba. Una campaña creada con apertura el mes que
|   viene se respondía hoy con su enlace.
| - El cierre se comparaba con `isPast()` sobre una columna casteada a `date`,
|   así que a las 00:00 del día de cierre ya era pasado: el último día de toda
|   campaña era inservible.
|
| Se prueban las dos rutas de cada campaña —`cuestionario` y `respuestas`—,
| porque las dos pasan por la misma guarda y una respuesta aceptada fuera de
| la ventana es un dato que ya no se puede distinguir de los buenos.
*/

use App\Models\Sso\EvaluacionAssist;
use App\Models\Sso\EvaluacionPsicosocial;
use App\Models\Sso\RespuestaAssist;
use App\Models\Sso\RespuestaPsicosocial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->usuario = User::create([
        'email' => 'campanias@gadpe.gob.ec',
        'usuario_ti' => 'campanias',
        'password' => bcrypt('secreto'),
        'primer_login' => false,
        'activo' => true,
    ]);
});

function campaniaPsicosocial(array $atributos = []): EvaluacionPsicosocial
{
    return EvaluacionPsicosocial::create(array_merge([
        'periodo' => '2026',
        'codigo_acceso' => strtoupper(Str::random(8)),
        'fecha_apertura' => now()->subMonth()->toDateString(),
        'activa' => true,
        'creado_por' => test()->usuario->id,
    ], $atributos));
}

function campaniaAssist(array $atributos = []): EvaluacionAssist
{
    return EvaluacionAssist::create(array_merge([
        'periodo' => '2026',
        'codigo_acceso' => strtoupper(Str::random(8)),
        'fecha_apertura' => now()->subMonth()->toDateString(),
        'activa' => true,
        'creado_por' => test()->usuario->id,
    ], $atributos));
}

/** Las 58 respuestas válidas del psicosocial. */
function respuestasPsicosocialValidas(): array
{
    return ['respuestas' => array_fill_keys(range(1, 58), 3)];
}

// ── La apertura, que no se miraba ─────────────────────────────────────

test('una campaña psicosocial con apertura futura no entrega el cuestionario', function () {
    $campania = campaniaPsicosocial(['fecha_apertura' => now()->addMonth()->toDateString()]);

    $this->getJson("/api/v1/sso/psicosocial/{$campania->codigo_acceso}/cuestionario")
        ->assertStatus(422)
        ->assertJsonPath('errores.codigo_acceso.0', fn ($m) => str_contains($m, 'abre el'));
});

test('una campaña psicosocial con apertura futura no acepta respuestas', function () {
    // Lo que de verdad importa: no es que no se vea el formulario, es que no
    // entre el dato.
    $campania = campaniaPsicosocial(['fecha_apertura' => now()->addMonth()->toDateString()]);

    $this->postJson(
        "/api/v1/sso/psicosocial/{$campania->codigo_acceso}/respuestas",
        respuestasPsicosocialValidas(),
    )->assertStatus(422);

    expect(RespuestaPsicosocial::count())->toBe(0);
});

test('un tamizaje ASSIST con apertura futura no acepta respuestas', function () {
    $campania = campaniaAssist(['fecha_apertura' => now()->addDay()->toDateString()]);

    $this->getJson("/api/v1/sso/assist/{$campania->codigo_acceso}/cuestionario")
        ->assertStatus(422);

    $this->postJson(
        "/api/v1/sso/assist/{$campania->codigo_acceso}/respuestas",
        ['sustancias' => []],
    )->assertStatus(422);

    expect(RespuestaAssist::count())->toBe(0);
});

test('el mensaje de una campaña programada dice cuándo abre y no que se cerró', function () {
    // Decirle «ya fue cerrada» a quien abre el enlace de una campaña que
    // todavía no empieza lo manda a reportar un error que no existe.
    $campania = campaniaPsicosocial(['fecha_apertura' => '2026-12-01']);

    $respuesta = $this->getJson("/api/v1/sso/psicosocial/{$campania->codigo_acceso}/cuestionario")
        ->assertStatus(422);

    $mensaje = $respuesta->json('errores.codigo_acceso.0');

    expect($mensaje)->toContain('01/12/2026');
    expect($mensaje)->not->toContain('cerrada');
});

// ── El día de cierre, que se perdía entero ────────────────────────────

test('el día de cierre todavía acepta respuestas', function () {
    // El defecto: con `fecha_cierre->isPast()` y la columna casteada a `date`,
    // una campaña «abierta hasta hoy» rechazaba desde las 00:00 de hoy.
    $campania = campaniaPsicosocial(['fecha_cierre' => now()->toDateString()]);

    $this->getJson("/api/v1/sso/psicosocial/{$campania->codigo_acceso}/cuestionario")
        ->assertOk();

    $this->postJson(
        "/api/v1/sso/psicosocial/{$campania->codigo_acceso}/respuestas",
        respuestasPsicosocialValidas(),
    )->assertCreated();

    expect(RespuestaPsicosocial::count())->toBe(1);
});

test('el día de cierre del ASSIST también cuenta', function () {
    $campania = campaniaAssist(['fecha_cierre' => now()->toDateString()]);

    $this->postJson(
        "/api/v1/sso/assist/{$campania->codigo_acceso}/respuestas",
        ['sustancias' => []],
    )->assertCreated();

    expect(RespuestaAssist::count())->toBe(1);
});

test('el día siguiente al cierre ya no acepta respuestas', function () {
    $campania = campaniaPsicosocial(['fecha_cierre' => now()->subDay()->toDateString()]);

    $this->postJson(
        "/api/v1/sso/psicosocial/{$campania->codigo_acceso}/respuestas",
        respuestasPsicosocialValidas(),
    )->assertStatus(422);

    expect(RespuestaPsicosocial::count())->toBe(0);
});

// ── Lo que ya funcionaba y tiene que seguir ───────────────────────────

test('una campaña cerrada a mano no acepta respuestas', function () {
    $campania = campaniaPsicosocial(['activa' => false]);

    $this->postJson(
        "/api/v1/sso/psicosocial/{$campania->codigo_acceso}/respuestas",
        respuestasPsicosocialValidas(),
    )->assertStatus(422)
        ->assertJsonPath('errores.codigo_acceso.0', fn ($m) => str_contains($m, 'cerrada'));

    expect(RespuestaPsicosocial::count())->toBe(0);
});

test('una campaña abierta y dentro de la ventana acepta su respuesta', function () {
    $campania = campaniaPsicosocial([
        'fecha_apertura' => now()->subDay()->toDateString(),
        'fecha_cierre' => now()->addMonth()->toDateString(),
    ]);

    $this->postJson(
        "/api/v1/sso/psicosocial/{$campania->codigo_acceso}/respuestas",
        respuestasPsicosocialValidas(),
    )->assertCreated();

    expect(RespuestaPsicosocial::count())->toBe(1);
});

test('un código que no existe lo dice, en vez de hablar de cierres', function () {
    $this->getJson('/api/v1/sso/psicosocial/NOEXISTE/cuestionario')
        ->assertStatus(422)
        ->assertJsonPath('errores.codigo_acceso.0', fn ($m) => str_contains($m, 'enlace'));
});

// ── El estado que viaja a la pantalla ─────────────────────────────────

test('la campaña publica su estado real y no solo activa', function () {
    // `estado_campania` va en `$appends`: un accesor que no está ahí no viaja,
    // y la pantalla se quedaría pintando `activa`, que es lo que hacía.
    $programada = campaniaPsicosocial(['fecha_apertura' => now()->addMonth()->toDateString()]);
    $abierta    = campaniaPsicosocial();
    $vencida    = campaniaPsicosocial(['fecha_cierre' => now()->subDay()->toDateString()]);
    $cerrada    = campaniaPsicosocial(['activa' => false]);

    expect($programada->estado_campania)->toBe('programada');
    expect($abierta->estado_campania)->toBe('abierta');
    // Las dos que la pantalla pintaba «Abierta» mirando solo `activa`:
    expect($vencida->activa)->toBeTrue();
    expect($vencida->estado_campania)->toBe('cerrada');
    expect($cerrada->estado_campania)->toBe('cerrada');
});

test('el listado de campañas lleva el estado a la pantalla', function () {
    // El tramo completo: el accesor tiene que llegar por el API, que es lo que
    // lee la columna Estado. Con el accesor fuera de `$appends` esto pasaría
    // igual en el modelo y fallaría aquí.
    $this->seed(\Database\Seeders\RolPermisoSeeder::class);
    $this->usuario->assignRole('admin-uath');

    campaniaPsicosocial(['fecha_apertura' => now()->addMonth()->toDateString()]);

    $this->actingAs($this->usuario, 'sanctum')
        ->getJson('/api/v1/sso/psicosocial/campanias')
        ->assertOk()
        ->assertJsonPath('datos.0.estado_campania', 'programada');
});
