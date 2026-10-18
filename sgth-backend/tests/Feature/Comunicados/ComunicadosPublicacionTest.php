<?php

use App\Enums\EstadoComunicado;
use App\Enums\RegimenLaboral;
use App\Models\Comunicados\Comunicado;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

/*
| Publicar, retirar, duplicar y lo único que cambia en lo publicado
| (docs/diseno-noticias-comunicados.md, 3 y 4).
|
| Los destinatarios se calculan al leer, pero las dependencias de una unidad
| elegida se congelan al publicar: un cambio del orgánico no cambia en
| silencio a quién iba dirigido.
*/

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolPermisoSeeder::class);
    $this->admin = usuarioDeComunicados('admin-uath');

    // Una dirección con una jefatura, y otra dirección aparte.
    $this->financiera = unidadDeComunicados('Dirección Financiera');
    $this->tesoreria = unidadDeComunicados('Tesorería', $this->financiera);
    $this->ambiente = unidadDeComunicados('Dirección de Gestión Ambiental');

    $this->enFinanciera = servidorDeComunicados($this->financiera);
    $this->enTesoreria = servidorDeComunicados($this->tesoreria, RegimenLaboral::CODIGO_TRABAJO);
    $this->enAmbiente = servidorDeComunicados($this->ambiente);
});

afterEach(function () {
    Carbon::setTestNow();
});

function publicarComunicadoPorApi(object $prueba, Comunicado $comunicado)
{
    return $prueba->actingAs($prueba->admin, 'sanctum')
        ->postJson("/api/v1/comunicados/{$comunicado->id}/publicar");
}

// ── Publicar ───────────────────────────────────────────────────────────────

test('publicado hoy sale ya, con quién y cuándo', function () {
    Carbon::setTestNow('2026-10-17 10:42:00');
    $borrador = borradorDeComunicado($this->admin, ['publicado_desde' => '2026-10-17']);

    publicarComunicadoPorApi($this, $borrador)
        ->assertOk()
        ->assertJsonPath('mensaje', 'Publicado: ya se ve en el Portal.')
        ->assertJsonPath('datos.vigencia', 'publicado')
        ->assertJsonPath('datos.publicado_por.id', $this->admin->id);

    $borrador->refresh();
    expect($borrador->estado)->toBe(EstadoComunicado::PUBLICADO)
        ->and($borrador->publicado_desde->format('Y-m-d H:i'))->toBe('2026-10-17 10:42')
        ->and($borrador->publicado_at->format('Y-m-d H:i'))->toBe('2026-10-17 10:42');
});

test('con fecha de inicio futura queda programado hasta ese día', function () {
    Carbon::setTestNow('2026-10-17 10:00:00');
    $borrador = borradorDeComunicado($this->admin, ['publicado_desde' => '2026-10-25']);

    publicarComunicadoPorApi($this, $borrador)
        ->assertOk()
        ->assertJsonPath('mensaje', 'Publicación programada: el Portal la mostrará el 25/10/2026.')
        ->assertJsonPath('datos.vigencia', 'programado');

    Carbon::setTestNow('2026-10-25 00:00:01');
    expect($borrador->fresh()->vigencia()->value)->toBe('publicado');
});

test('sin texto no se publica; sin resumen, sí', function () {
    $vacio = borradorDeComunicado($this->admin, ['resumen' => null, 'cuerpo' => '<p>&nbsp;</p>']);

    publicarComunicadoPorApi($this, $vacio)
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'Para publicar falta el texto.');

    expect($vacio->fresh()->estado)->toBe(EstadoComunicado::BORRADOR);

    // El resumen es opcional: la tarjeta usa el comienzo del texto.
    $sinResumen = borradorDeComunicado($this->admin, ['resumen' => null]);

    publicarComunicadoPorApi($this, $sinResumen)
        ->assertOk()
        ->assertJsonPath('datos.resumen', null)
        ->assertJsonPath('datos.extracto', 'A todos los servidores: Descanso el lunes 2.');
});

test('un borrador cuya fecha de inicio ya pasó no se publica hasta corregirla', function () {
    Carbon::setTestNow('2026-10-17 10:00:00');
    $borrador = borradorDeComunicado($this->admin, ['publicado_desde' => '2026-10-18']);

    Carbon::setTestNow('2026-10-20 09:00:00');

    publicarComunicadoPorApi($this, $borrador)
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'La fecha de inicio ya pasó: cámbiela antes de publicar.');
});

test('lo ya publicado no se vuelve a publicar', function () {
    $publicado = publicadoDeComunicado($this->admin);

    publicarComunicadoPorApi($this, $publicado)
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'Solo se publica un borrador.');
});

// ── Destinatarios ──────────────────────────────────────────────────────────

test('segmentado sin unidades ni regímenes no se publica', function () {
    $borrador = borradorDeComunicado($this->admin, ['audiencia' => 'segmentada']);

    publicarComunicadoPorApi($this, $borrador)
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'Elija al menos una dirección o un régimen, o envíelo a todos.');
});

test('si nadie lo recibiría, no se publica', function () {
    $vacia = unidadDeComunicados('Unidad sin personal');
    $borrador = borradorDeComunicado($this->admin, [
        'audiencia' => 'segmentada',
        'unidades'  => [['id' => $vacia->id]],
    ]);

    publicarComunicadoPorApi($this, $borrador)
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'Nadie recibiría esta publicación: ningún servidor activo cumple los destinatarios elegidos.');
});

test('el conteo del formulario sigue unidades, dependencias y régimen', function (array $eleccion, int $esperado) {
    $unidades = collect($eleccion['unidades'] ?? [])->map(fn ($u) => [
        'id' => $this->{$u[0]}->id, 'incluye_dependencias' => $u[1],
    ])->all();

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/comunicados/destinatarios/conteo', [
            'audiencia' => $eleccion['audiencia'],
            'unidades'  => $unidades,
            'regimenes' => $eleccion['regimenes'] ?? [],
        ])
        ->assertOk()
        ->assertJsonPath('datos.total', $esperado);
})->with([
    // Las cuatro cuentas activas: el admin, sin ficha, también recibe lo de todos.
    'a todos'                         => [['audiencia' => 'todos'], 4],
    'la dirección sola'               => [['audiencia' => 'segmentada', 'unidades' => [['financiera', false]]], 1],
    'la dirección con dependencias'   => [['audiencia' => 'segmentada', 'unidades' => [['financiera', true]]], 2],
    'solo el código del trabajo'      => [['audiencia' => 'segmentada', 'regimenes' => ['codigo_trabajo']], 1],
    'dirección y régimen a la vez'    => [['audiencia' => 'segmentada', 'unidades' => [['financiera', true]], 'regimenes' => ['losep']], 1],
    'segmentado sin elegir nada'      => [['audiencia' => 'segmentada'], 0],
]);

test('una ficha inactiva o una cuenta desactivada no reciben nada', function () {
    servidorDeComunicados($this->ambiente, activo: false);
    $desactivado = servidorDeComunicados($this->ambiente);
    $desactivado->update(['activo' => false]);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/comunicados/destinatarios/conteo', [
            'audiencia' => 'segmentada',
            'unidades'  => [['id' => $this->ambiente->id]],
        ])
        ->assertOk()
        ->assertJsonPath('datos.total', 1);
});

test('al publicar se congelan las dependencias: una jefatura creada después no entra', function () {
    $borrador = borradorDeComunicado($this->admin, [
        'audiencia' => 'segmentada',
        'unidades'  => [['id' => $this->financiera->id, 'incluye_dependencias' => true]],
    ]);

    publicarComunicadoPorApi($this, $borrador)->assertOk();

    $nueva = unidadDeComunicados('Presupuesto', $this->financiera);
    servidorDeComunicados($nueva);

    $comunicado = $borrador->fresh();
    $unidades = $comunicado->unidades()->get()->mapWithKeys(fn ($u) => [$u->id => (bool) $u->pivot->seleccionada]);

    expect($unidades->all())->toEqualCanonicalizing([
        $this->financiera->id => true,
        $this->tesoreria->id  => false,
    ])->and(app(App\Contracts\Comunicados\DestinatariosComunicadoServiceInterface::class)->contar($comunicado))->toBe(2);

    // Lo que se edita y se duplica sigue siendo solo lo elegido.
    $this->actingAs($this->admin, 'sanctum')
        ->getJson("/api/v1/comunicados/{$comunicado->id}")
        ->assertJsonCount(1, 'datos.unidades')
        ->assertJsonPath('datos.unidades.0.id', $this->financiera->id);
});

// ── Fijados ────────────────────────────────────────────────────────────────

test('hay tres fijados a la vez como máximo; uno vencido ya no ocupa lugar', function () {
    Carbon::setTestNow('2026-10-17 10:00:00');

    publicadoDeComunicado($this->admin, ['fijado' => true]);
    publicadoDeComunicado($this->admin, ['fijado' => true]);
    $vence = publicadoDeComunicado($this->admin, ['fijado' => true, 'publicado_hasta' => '2026-10-18']);
    $cuarto = borradorDeComunicado($this->admin, ['fijado' => true]);

    publicarComunicadoPorApi($this, $cuarto)
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'Ya hay 3 publicaciones fijadas: desfije una antes de fijar otra.');

    $sinFijar = publicadoDeComunicado($this->admin);
    $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/comunicados/{$sinFijar->id}/publicacion", ['fijado' => true])
        ->assertStatus(422);

    Carbon::setTestNow('2026-10-19 08:00:00');
    expect($vence->fresh()->vigencia()->value)->toBe('vencido');

    publicarComunicadoPorApi($this, $cuarto)->assertOk()->assertJsonPath('datos.fijado', true);
});

// ── Lo que cambia en lo publicado ─────────────────────────────────────────

test('en lo publicado solo cambian el fijado y la fecha de fin', function () {
    Carbon::setTestNow('2026-10-17 10:00:00');
    $publicado = publicadoDeComunicado($this->admin);

    $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/comunicados/{$publicado->id}/publicacion", [
            'fijado' => true, 'publicado_hasta' => '2026-11-04', 'titulo' => 'ignorado',
        ])
        ->assertOk()
        ->assertJsonPath('datos.fijado', true);

    $publicado->refresh();
    expect($publicado->publicado_hasta->format('Y-m-d H:i:s'))->toBe('2026-11-04 23:59:59')
        ->and($publicado->titulo)->toBe('Descanso obligatorio del 2 y 3 de noviembre');

    $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/comunicados/{$publicado->id}/publicacion", ['publicado_hasta' => '2026-10-16'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['publicado_hasta'], 'errores');

    $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/comunicados/{$publicado->id}/publicacion", ['publicado_hasta' => null])
        ->assertOk();
    expect($publicado->fresh()->publicado_hasta)->toBeNull();
});

test('un borrador no cambia por la ruta de la publicación', function () {
    $borrador = borradorDeComunicado($this->admin);

    $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/comunicados/{$borrador->id}/publicacion", ['fijado' => true])
        ->assertStatus(422);
});

// ── Retirar ────────────────────────────────────────────────────────────────

test('se retira con motivo y queda en el historial', function () {
    $publicado = publicadoDeComunicado($this->admin);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson("/api/v1/comunicados/{$publicado->id}/retirar", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['motivo'], 'errores');

    $this->actingAs($this->admin, 'sanctum')
        ->postJson("/api/v1/comunicados/{$publicado->id}/retirar", ['motivo' => 'La fecha del feriado estaba mal.'])
        ->assertOk()
        ->assertJsonPath('datos.vigencia', 'retirado')
        ->assertJsonPath('datos.motivo_retiro', 'La fecha del feriado estaba mal.')
        ->assertJsonPath('datos.retirado_por.id', $this->admin->id);

    expect(Comunicado::find($publicado->id))->not->toBeNull();
});

test('un borrador no se retira: se elimina', function () {
    $borrador = borradorDeComunicado($this->admin);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson("/api/v1/comunicados/{$borrador->id}/retirar", ['motivo' => 'No va.'])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'Solo se retira lo publicado. Un borrador se elimina.');
});

// ── Duplicar ───────────────────────────────────────────────────────────────

test('duplicar deja un borrador con lo elegido, sin fechas ni fijado', function () {
    $asistente = usuarioDeComunicados('asistente-uath');
    $publicado = publicadoDeComunicado($this->admin, [
        'audiencia'       => 'segmentada',
        'unidades'        => [['id' => $this->financiera->id, 'incluye_dependencias' => true]],
        'regimenes'       => ['losep'],
        'fijado'          => true,
        'publicado_hasta' => now()->addDays(10)->toDateString(),
    ]);

    $id = $this->actingAs($asistente, 'sanctum')
        ->postJson("/api/v1/comunicados/{$publicado->id}/duplicar")
        ->assertCreated()
        ->assertJsonPath('mensaje', 'Copia creada como borrador.')
        ->assertJsonPath('datos.vigencia', 'borrador')
        ->assertJsonPath('datos.duplicado_de_id', $publicado->id)
        ->assertJsonPath('datos.creado_por.id', $asistente->id)
        ->json('datos.id');

    $copia = Comunicado::find($id);
    expect($copia->titulo)->toBe($publicado->titulo)
        ->and($copia->referencia)->toBe('Circular GADPE-DATH-2026-045')
        ->and($copia->regimenes)->toBe(['losep'])
        ->and($copia->fijado)->toBeFalse()
        ->and($copia->publicado_desde)->toBeNull()
        ->and($copia->publicado_hasta)->toBeNull()
        // Solo la dirección elegida; Tesorería se recalcula al publicar la copia.
        ->and($copia->unidades()->pluck('unidades_administrativas.id')->all())->toBe([$this->financiera->id])
        ->and((bool) $copia->unidades()->first()->pivot->incluye_dependencias)->toBeTrue();
});
