<?php

use App\Contracts\Comunicados\ArchivosComunicadoServiceInterface;
use App\Contracts\Comunicados\ComunicadoServiceInterface;
use App\Contracts\Comunicados\DestinatariosComunicadoServiceInterface;
use App\Enums\RegimenLaboral;
use App\Models\Comunicados\Comunicado;
use App\Models\Comunicados\ComunicadoLectura;
use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/*
| Las noticias y comunicados en el Portal del servidor
| (docs/diseno-noticias-comunicados.md, 3.3, 3.4 y 5).
|
| Lo que no le corresponde a quien pregunta no existe para él: 404, no 403.
| La regla de quién ve qué es la misma con que Talento Humano cuenta a quién
| llega, y aquí se comprueba que las dos digan lo mismo.
*/

uses(Tests\TestCase::class, RefreshDatabase::class);

function idsEnMiPortal(object $prueba, User $usuario, string $consulta = ''): array
{
    return collect($prueba->actingAs($usuario, 'sanctum')
        ->getJson('/api/v1/autoservicio/comunicados'.$consulta)
        ->assertOk()
        ->json('datos'))->pluck('id')->all();
}

beforeEach(function () {
    $this->seed(RolPermisoSeeder::class);
    $this->admin = usuarioDeComunicados('admin-uath');

    $this->financiera = unidadDeComunicados('Gestión Financiera');
    $this->tesoreria = unidadDeComunicados('Tesorería', $this->financiera);
    $this->ambiente = unidadDeComunicados('Gestión Ambiental');

    $this->enTesoreria = servidorDeComunicados($this->tesoreria);
    $this->enAmbiente = servidorDeComunicados($this->ambiente, RegimenLaboral::CODIGO_TRABAJO);
});

afterEach(function () {
    Carbon::setTestNow();
});

// ── Quién ve qué ───────────────────────────────────────────────────────────

test('lo dirigido a todos lo ve cualquiera, también una cuenta sin servidor', function () {
    $publicado = publicadoDeComunicado($this->admin);
    $sinServidor = usuarioDeComunicados('servidor');

    expect(idsEnMiPortal($this, $this->enTesoreria))->toBe([$publicado->id])
        ->and(idsEnMiPortal($this, $sinServidor))->toBe([$publicado->id]);
});

test('por dirección lo ve quien trabaja en ella o en una dependencia, y nadie más', function () {
    $publicado = publicadoDeComunicado($this->admin, [
        'audiencia' => 'segmentada',
        'unidades'  => [['id' => $this->financiera->id, 'incluye_dependencias' => true]],
    ]);

    expect(idsEnMiPortal($this, $this->enTesoreria))->toBe([$publicado->id])
        ->and(idsEnMiPortal($this, $this->enAmbiente))->toBe([]);

    $this->actingAs($this->enAmbiente, 'sanctum')
        ->getJson("/api/v1/autoservicio/comunicados/{$publicado->id}")
        ->assertNotFound();
});

test('por régimen lo ve solo quien tiene ese régimen', function () {
    $publicado = publicadoDeComunicado($this->admin, ['audiencia' => 'segmentada', 'regimenes' => ['codigo_trabajo']]);

    expect(idsEnMiPortal($this, $this->enAmbiente))->toBe([$publicado->id])
        ->and(idsEnMiPortal($this, $this->enTesoreria))->toBe([]);
});

test('una ficha inactiva o una cuenta sin servidor no ven lo segmentado', function () {
    $publicado = publicadoDeComunicado($this->admin, [
        'audiencia' => 'segmentada',
        'unidades'  => [['id' => $this->financiera->id, 'incluye_dependencias' => true]],
    ]);

    $this->enTesoreria->servidor->update(['estado' => false]);

    expect(idsEnMiPortal($this, $this->enTesoreria->fresh()))->toBe([])
        ->and(idsEnMiPortal($this, usuarioDeComunicados('servidor')))->toBe([]);
});

test('el Portal y el conteo de Talento Humano dicen lo mismo', function () {
    servidorDeComunicados($this->financiera);
    servidorDeComunicados($this->financiera, RegimenLaboral::CODIGO_TRABAJO);
    servidorDeComunicados($this->ambiente);
    usuarioDeComunicados('servidor');

    $publicado = publicadoDeComunicado($this->admin, [
        'audiencia' => 'segmentada',
        'unidades'  => [['id' => $this->financiera->id, 'incluye_dependencias' => true]],
        'regimenes' => ['losep'],
    ]);

    $destinatarios = app(DestinatariosComunicadoServiceInterface::class);
    $segunTalentoHumano = $destinatarios
        ->usuarios($publicado->audiencia, $publicado->unidades()->pluck('unidades_administrativas.id')->all(), $publicado->regimenes)
        ->pluck('id')->sort()->values()->all();

    $segunElPortal = User::where('activo', true)->get()
        ->filter(fn (User $u) => $destinatarios->visiblesPara($u)->whereKey($publicado->id)->exists())
        ->pluck('id')->sort()->values()->all();

    expect($segunElPortal)->toBe($segunTalentoHumano)
        ->and($segunElPortal)->toHaveCount(2);
});

test('el borrador, lo programado y lo retirado no se ven; lo vencido, solo en el archivo', function () {
    Carbon::setTestNow('2026-10-17 10:00:00');

    $vigente = publicadoDeComunicado($this->admin, ['titulo' => 'Vigente']);
    $vence = publicadoDeComunicado($this->admin, ['titulo' => 'Vence', 'publicado_hasta' => '2026-10-18']);
    publicadoDeComunicado($this->admin, ['titulo' => 'Programado', 'publicado_desde' => '2026-10-30']);
    borradorDeComunicado($this->admin, ['titulo' => 'Borrador']);
    $retirado = publicadoDeComunicado($this->admin, ['titulo' => 'Retirado']);
    app(ComunicadoServiceInterface::class)->retirar($retirado, 'Estaba mal.', $this->admin);

    Carbon::setTestNow('2026-10-20 09:00:00');

    expect(idsEnMiPortal($this, $this->enTesoreria))->toBe([$vigente->id])
        ->and(idsEnMiPortal($this, $this->enTesoreria, '?archivo=1'))->toBe([$vence->id]);

    $this->actingAs($this->enTesoreria, 'sanctum')
        ->getJson("/api/v1/autoservicio/comunicados/{$retirado->id}")
        ->assertNotFound();
});

test('sin acceso al Portal no se entra', function () {
    $sinRol = User::factory()->create();

    $this->actingAs($sinRol, 'sanctum')->getJson('/api/v1/autoservicio/comunicados')->assertForbidden();
    $this->actingAs($sinRol, 'sanctum')->getJson('/api/v1/autoservicio/comunicados/resumen')->assertForbidden();
});

// ── Lectura y confirmación ─────────────────────────────────────────────────

test('abrirla la deja leída, una sola vez, y no enseña nada de la gestión', function () {
    Carbon::setTestNow('2026-10-17 10:00:00');
    $publicado = publicadoDeComunicado($this->admin);

    $this->actingAs($this->enTesoreria, 'sanctum')
        ->getJson("/api/v1/autoservicio/comunicados/{$publicado->id}")
        ->assertOk()
        ->assertJsonPath('datos.cuerpo', '<p>A todos los servidores:</p><ul><li>Descanso el lunes 2.</li></ul>')
        ->assertJsonPath('datos.leido_at', '2026-10-17T10:00:00-05:00')
        ->assertJsonPath('datos.por_confirmar', true)
        ->assertJsonMissingPath('datos.audiencia')
        ->assertJsonMissingPath('datos.creado_por')
        ->assertJsonMissingPath('datos.total_destinatarios');

    Carbon::setTestNow('2026-10-17 15:00:00');
    $this->actingAs($this->enTesoreria, 'sanctum')
        ->getJson("/api/v1/autoservicio/comunicados/{$publicado->id}")
        ->assertJsonPath('datos.leido_at', '2026-10-17T10:00:00-05:00');

    expect(ComunicadoLectura::where('comunicado_id', $publicado->id)->count())->toBe(1);
});

test('confirmar la lectura deja la fecha y repetirlo no la cambia', function () {
    Carbon::setTestNow('2026-10-17 10:42:00');
    $publicado = publicadoDeComunicado($this->admin);

    // Se puede confirmar sin haberla abierto antes: también queda leída.
    $this->actingAs($this->enTesoreria, 'sanctum')
        ->postJson("/api/v1/autoservicio/comunicados/{$publicado->id}/confirmacion")
        ->assertOk()
        ->assertJsonPath('mensaje', 'Confirmó la lectura.')
        ->assertJsonPath('datos.confirmado_at', '2026-10-17T10:42:00-05:00')
        ->assertJsonPath('datos.leido_at', '2026-10-17T10:42:00-05:00')
        ->assertJsonPath('datos.por_confirmar', false);

    Carbon::setTestNow('2026-10-18 08:00:00');
    $this->actingAs($this->enTesoreria, 'sanctum')
        ->postJson("/api/v1/autoservicio/comunicados/{$publicado->id}/confirmacion")
        ->assertOk()
        ->assertJsonPath('datos.confirmado_at', '2026-10-17T10:42:00-05:00');
});

test('no se confirma lo que no pide confirmación ni lo que ya venció', function () {
    Carbon::setTestNow('2026-10-17 10:00:00');
    $noPide = publicadoDeComunicado($this->admin, ['requiere_confirmacion' => false]);
    $vence = publicadoDeComunicado($this->admin, ['publicado_hasta' => '2026-10-18']);

    $this->actingAs($this->enTesoreria, 'sanctum')
        ->postJson("/api/v1/autoservicio/comunicados/{$noPide->id}/confirmacion")
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'Este comunicado no pide confirmación de lectura.');

    Carbon::setTestNow('2026-10-19 09:00:00');
    $this->actingAs($this->enTesoreria, 'sanctum')
        ->postJson("/api/v1/autoservicio/comunicados/{$vence->id}/confirmacion")
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'Este comunicado ya venció: la confirmación de lectura se cerró.');

    // En el archivo ya no figura como pendiente.
    $this->actingAs($this->enTesoreria, 'sanctum')
        ->getJson('/api/v1/autoservicio/comunicados?archivo=1')
        ->assertJsonPath('datos.0.por_confirmar', false)
        ->assertJsonPath('datos.0.vencido', true);
});

test('quien no es destinatario no la abre, no la confirma ni descarga sus documentos', function () {
    $publicado = publicadoDeComunicado($this->admin, ['audiencia' => 'segmentada', 'regimenes' => ['codigo_trabajo']]);

    $this->actingAs($this->enTesoreria, 'sanctum')
        ->getJson("/api/v1/autoservicio/comunicados/{$publicado->id}")->assertNotFound();
    $this->actingAs($this->enTesoreria, 'sanctum')
        ->postJson("/api/v1/autoservicio/comunicados/{$publicado->id}/confirmacion")->assertNotFound();
    $this->actingAs($this->enTesoreria, 'sanctum')
        ->get("/api/v1/autoservicio/comunicados/{$publicado->id}/adjuntos/1")->assertNotFound();

    expect(ComunicadoLectura::count())->toBe(0);
});

test('el destinatario descarga los documentos con su nombre', function () {
    Storage::fake('local');
    $borrador = borradorDeComunicado($this->admin);
    $adjunto = app(ArchivosComunicadoServiceInterface::class)->agregarAdjunto(
        $borrador,
        UploadedFile::fake()->createWithContent('Circular-045.pdf', "%PDF-1.4\n%%EOF\n"),
    );
    app(ComunicadoServiceInterface::class)->publicar($borrador, $this->admin);

    $respuesta = $this->actingAs($this->enTesoreria, 'sanctum')
        ->get("/api/v1/autoservicio/comunicados/{$borrador->id}/adjuntos/{$adjunto->id}")
        ->assertOk();

    expect($respuesta->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($respuesta->headers->get('Content-Disposition'))->toContain('Circular-045.pdf');
});

// ── Inicio, campana y listado ──────────────────────────────────────────────

test('el resumen trae las fijadas primero, cinco como máximo, y las cifras', function () {
    Carbon::setTestNow('2026-10-10 08:00:00');
    $fijada = publicadoDeComunicado($this->admin, ['titulo' => 'Fijada y antigua', 'fijado' => true]);

    foreach (range(1, 5) as $i) {
        Carbon::setTestNow("2026-10-1{$i} 08:00:00");
        publicadoDeComunicado($this->admin, ['titulo' => "Nueva {$i}", 'requiere_confirmacion' => $i <= 2]);
    }
    Carbon::setTestNow('2026-10-17 08:00:00');

    $this->actingAs($this->enTesoreria, 'sanctum')
        ->getJson("/api/v1/autoservicio/comunicados/{$fijada->id}");

    $datos = $this->actingAs($this->enTesoreria, 'sanctum')
        ->getJson('/api/v1/autoservicio/comunicados/resumen')
        ->assertOk()
        ->json('datos');

    expect(collect($datos['recientes'])->pluck('titulo')->all())
        ->toBe(['Fijada y antigua', 'Nueva 5', 'Nueva 4', 'Nueva 3', 'Nueva 2'])
        ->and($datos['recientes'][0]['leido_at'])->not->toBeNull()
        ->and($datos['recientes'][1]['leido_at'])->toBeNull()
        ->and($datos['sin_leer'])->toBe(5)
        // La fijada pide confirmación (la ayuda la pide por defecto) y las dos primeras nuevas también.
        ->and($datos['por_confirmar'])->toBe(3)
        ->and(collect($datos['no_leidos'])->pluck('titulo')->all())->toBe(['Nueva 5', 'Nueva 4', 'Nueva 3', 'Nueva 2', 'Nueva 1']);
});

test('el listado filtra por tipo, por lo que falta leer o confirmar y por búsqueda', function () {
    $noticia = publicadoDeComunicado($this->admin, ['tipo' => 'noticia', 'titulo' => 'Jornada deportiva']);
    $circular = publicadoDeComunicado($this->admin, ['titulo' => 'Cuadro de turnos', 'referencia' => 'Circular GADPE-DATH-2026-045']);
    $leida = publicadoDeComunicado($this->admin, [
        'titulo' => 'Ya leída', 'referencia' => 'Circular GADPE-DATH-2026-040', 'requiere_confirmacion' => false,
    ]);

    $this->actingAs($this->enTesoreria, 'sanctum')->getJson("/api/v1/autoservicio/comunicados/{$leida->id}");

    expect(idsEnMiPortal($this, $this->enTesoreria, '?tipo=noticia'))->toBe([$noticia->id])
        ->and(idsEnMiPortal($this, $this->enTesoreria, '?lectura=sin_leer'))->toBe([$circular->id, $noticia->id])
        ->and(idsEnMiPortal($this, $this->enTesoreria, '?lectura=por_confirmar'))->toBe([$circular->id])
        ->and(idsEnMiPortal($this, $this->enTesoreria, '?buscar=2026-045'))->toBe([$circular->id]);
});
