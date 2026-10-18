<?php

use App\Enums\EstadoComunicado;
use App\Models\Comunicados\Comunicado;
use App\Models\Comunicados\ComunicadoLectura;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

/*
| El borrador: crearlo, editarlo, eliminarlo y el listado de Talento Humano
| (docs/diseno-noticias-comunicados.md, 4 y 6). Lo publicado no se edita ni
| se elimina (reglas 1 y 3), y el texto se limpia al guardar (regla 5).
*/

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolPermisoSeeder::class);
    $this->autor = usuarioDeComunicados('asistente-uath');
    $this->admin = usuarioDeComunicados('admin-uath');
    servidorDeComunicados(unidadDeComunicados('Dirección de Talento Humano'));
});

afterEach(function () {
    Carbon::setTestNow();
});

test('un borrador se guarda con solo el tipo, el título y los destinatarios', function () {
    $this->actingAs($this->autor, 'sanctum')
        ->postJson('/api/v1/comunicados', [
            'tipo' => 'noticia', 'titulo' => 'Jornada deportiva', 'audiencia' => 'todos',
        ])
        ->assertCreated()
        ->assertJsonPath('datos.estado', 'borrador')
        ->assertJsonPath('datos.vigencia', 'borrador')
        ->assertJsonPath('datos.vigencia_etiqueta', 'Borrador')
        ->assertJsonPath('datos.creado_por.id', $this->autor->id)
        ->assertJsonPath('datos.total_destinatarios', 3);
});

test('sin título, tipo o destinatarios no se guarda, y los errores van en errores', function () {
    $this->actingAs($this->autor, 'sanctum')
        ->postJson('/api/v1/comunicados', ['resumen' => 'algo'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['titulo', 'tipo', 'audiencia'], 'errores');
});

test('una noticia descarta el número de documento y la confirmación de lectura', function () {
    $id = $this->actingAs($this->autor, 'sanctum')
        ->postJson('/api/v1/comunicados', datosDeComunicado(['tipo' => 'noticia']))
        ->assertCreated()
        ->json('datos.id');

    $comunicado = Comunicado::find($id);
    expect($comunicado->referencia)->toBeNull()
        ->and($comunicado->requiere_confirmacion)->toBeFalse();
});

test('el texto se limpia al guardar: sin scripts, imágenes ni enlaces a javascript', function () {
    $sucio = '<h1>Título pegado</h1><p onclick="robar()">Hola <strong>equipo</strong></p>'
        .'<img src="x" onerror="alert(1)"><script>alert(2)</script>'
        .'<p><a href="javascript:alert(3)">malo</a> <a href="https://gadpe.gob.ec" target="_blank">bueno</a></p>'
        .'<div><span>texto pegado de Word</span></div>';

    $id = $this->actingAs($this->autor, 'sanctum')
        ->postJson('/api/v1/comunicados', datosDeComunicado(['cuerpo' => $sucio]))
        ->assertCreated()
        ->json('datos.id');

    $cuerpo = Comunicado::find($id)->cuerpo;

    expect($cuerpo)->not->toContain('<script')
        ->not->toContain('onerror')
        ->not->toContain('onclick')
        ->not->toContain('<img')
        ->not->toContain('javascript:')
        ->not->toContain('target=')
        ->not->toContain('<h1')
        // De lo que no se permite como etiqueta, el texto se queda.
        ->toContain('Título pegado')
        ->toContain('texto pegado de Word')
        ->toContain('<strong>equipo</strong>')
        ->toContain('<a href="https://gadpe.gob.ec" rel="noopener noreferrer">bueno</a>');
});

test('enviado a todos, se olvidan las unidades y los regímenes elegidos', function () {
    $unidad = unidadDeComunicados('Dirección Financiera');

    $id = $this->actingAs($this->autor, 'sanctum')
        ->postJson('/api/v1/comunicados', datosDeComunicado([
            'audiencia' => 'todos',
            'unidades'  => [['id' => $unidad->id, 'incluye_dependencias' => true]],
            'regimenes' => ['losep'],
        ]))
        ->assertCreated()
        ->json('datos.id');

    $comunicado = Comunicado::find($id);
    expect($comunicado->regimenes)->toBeNull()
        ->and($comunicado->unidades()->count())->toBe(0);
});

test('las fechas no pueden quedar en el pasado ni la de fin antes de la de inicio', function () {
    $this->actingAs($this->autor, 'sanctum')
        ->postJson('/api/v1/comunicados', datosDeComunicado([
            'publicado_desde' => now()->subDay()->toDateString(),
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['publicado_desde'], 'errores');

    $this->actingAs($this->autor, 'sanctum')
        ->postJson('/api/v1/comunicados', datosDeComunicado([
            'publicado_desde' => now()->addDays(5)->toDateString(),
            'publicado_hasta' => now()->addDays(2)->toDateString(),
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['publicado_hasta'], 'errores');
});

test('la fecha de fin vale hasta el final de ese día', function () {
    $id = $this->actingAs($this->autor, 'sanctum')
        ->postJson('/api/v1/comunicados', datosDeComunicado([
            'publicado_hasta' => '2099-11-04',
        ]))
        ->assertCreated()
        ->json('datos.id');

    expect(Comunicado::find($id)->publicado_hasta->format('Y-m-d H:i:s'))->toBe('2099-11-04 23:59:59');
});

test('el borrador se edita y se elimina', function () {
    $borrador = borradorDeComunicado($this->autor);

    $this->actingAs($this->autor, 'sanctum')
        ->putJson("/api/v1/comunicados/{$borrador->id}", datosDeComunicado(['titulo' => 'Título corregido']))
        ->assertOk()
        ->assertJsonPath('datos.titulo', 'Título corregido');

    $this->actingAs($this->autor, 'sanctum')
        ->deleteJson("/api/v1/comunicados/{$borrador->id}")
        ->assertOk();

    expect(Comunicado::find($borrador->id))->toBeNull();
});

test('lo publicado no se edita ni se elimina, ni siquiera admin-ti', function (string $rol) {
    $publicado = publicadoDeComunicado($this->admin);
    $usuario = usuarioDeComunicados($rol);

    $this->actingAs($usuario, 'sanctum')
        ->putJson("/api/v1/comunicados/{$publicado->id}", datosDeComunicado(['titulo' => 'Cambiado']))
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'Solo se edita un borrador. Para corregir lo publicado, retírelo y duplíquelo.');

    $this->actingAs($usuario, 'sanctum')
        ->deleteJson("/api/v1/comunicados/{$publicado->id}")
        ->assertStatus(422);

    $publicado->refresh();
    expect($publicado->titulo)->toBe('Descanso obligatorio del 2 y 3 de noviembre')
        ->and($publicado->estado)->toBe(EstadoComunicado::PUBLICADO);
})->with(['admin-uath', 'admin-ti']);

test('el listado deduce programado y vencido de las fechas, y filtra por ellos', function () {
    Carbon::setTestNow('2026-10-17 10:00:00');

    $vigente = publicadoDeComunicado($this->admin, ['titulo' => 'Vigente']);
    $programado = publicadoDeComunicado($this->admin, ['titulo' => 'Programado', 'publicado_desde' => '2026-10-25']);
    $vencido = publicadoDeComunicado($this->admin, ['titulo' => 'Vencido', 'publicado_hasta' => '2026-10-20']);
    borradorDeComunicado($this->admin, ['titulo' => 'Borrador']);

    Carbon::setTestNow('2026-10-22 10:00:00');

    $vigencias = collect($this->actingAs($this->autor, 'sanctum')
        ->getJson('/api/v1/comunicados')
        ->assertOk()
        ->json('datos'))->pluck('vigencia', 'titulo');

    expect($vigencias->all())->toBe([
        'Borrador'   => 'borrador',
        'Vencido'    => 'vencido',
        'Programado' => 'programado',
        'Vigente'    => 'publicado',
    ]);

    foreach (['publicado' => $vigente, 'programado' => $programado, 'vencido' => $vencido] as $vigencia => $esperado) {
        $ids = collect($this->actingAs($this->autor, 'sanctum')
            ->getJson("/api/v1/comunicados?vigencia={$vigencia}")
            ->assertOk()
            ->json('datos'))->pluck('id')->all();

        expect($ids)->toBe([$esperado->id]);
    }
});

test('el listado busca por título o número de documento, y no trae el texto', function () {
    publicadoDeComunicado($this->admin, ['titulo' => 'Uso de la credencial', 'referencia' => 'Circular GADPE-DATH-2026-041']);
    publicadoDeComunicado($this->admin, ['titulo' => 'Vacaciones 2027', 'referencia' => 'Circular GADPE-DATH-2026-043']);

    $respuesta = $this->actingAs($this->autor, 'sanctum')
        ->getJson('/api/v1/comunicados?buscar=2026-041')
        ->assertOk();

    expect(collect($respuesta->json('datos'))->pluck('titulo')->all())->toBe(['Uso de la credencial'])
        ->and($respuesta->json('datos.0'))->not->toHaveKey('cuerpo')
        ->and($respuesta->json('meta.total'))->toBe(1);
});

test('el listado cuenta lecturas, confirmaciones y destinatarios de cada publicación', function () {
    $publicado = publicadoDeComunicado($this->admin);
    $lectores = [usuarioDeComunicados('servidor'), usuarioDeComunicados('servidor')];

    ComunicadoLectura::create(['comunicado_id' => $publicado->id, 'user_id' => $lectores[0]->id, 'leido_at' => now(), 'confirmado_at' => now()]);
    ComunicadoLectura::create(['comunicado_id' => $publicado->id, 'user_id' => $lectores[1]->id, 'leido_at' => now()]);

    $this->actingAs($this->autor, 'sanctum')
        ->getJson('/api/v1/comunicados')
        ->assertOk()
        ->assertJsonPath('datos.0.leidos', 2)
        ->assertJsonPath('datos.0.confirmados', 1)
        // Las tres cuentas de beforeEach, más los dos lectores.
        ->assertJsonPath('datos.0.total_destinatarios', 5);
});

test('el detalle trae el texto, las unidades elegidas y quién lo hizo', function () {
    $unidad = unidadDeComunicados('Dirección Financiera');
    servidorDeComunicados($unidad);
    $borrador = borradorDeComunicado($this->autor, [
        'audiencia' => 'segmentada',
        'unidades'  => [['id' => $unidad->id, 'incluye_dependencias' => true]],
    ]);

    $this->actingAs($this->autor, 'sanctum')
        ->getJson("/api/v1/comunicados/{$borrador->id}")
        ->assertOk()
        ->assertJsonPath('datos.cuerpo', '<p>A todos los servidores:</p><ul><li>Descanso el lunes 2.</li></ul>')
        ->assertJsonPath('datos.unidades.0.nombre', 'Dirección Financiera')
        ->assertJsonPath('datos.unidades.0.incluye_dependencias', true)
        ->assertJsonPath('datos.total_destinatarios', 1)
        ->assertJsonPath('datos.creado_por.id', $this->autor->id);
});

test('el campo se llama asunto en un comunicado y título en una noticia', function () {
    $this->actingAs($this->autor, 'sanctum')
        ->postJson('/api/v1/comunicados', ['tipo' => 'comunicado', 'audiencia' => 'todos'])
        ->assertStatus(422)
        ->assertJsonPath('errores.titulo.0', 'El campo asunto es obligatorio.');

    $this->actingAs($this->autor, 'sanctum')
        ->postJson('/api/v1/comunicados', ['tipo' => 'noticia', 'audiencia' => 'todos'])
        ->assertStatus(422)
        ->assertJsonPath('errores.titulo.0', 'El campo título es obligatorio.');
});

test('la tarjeta usa el resumen, y sin él el comienzo del texto', function () {
    $conResumen = borradorDeComunicado($this->autor, ['resumen' => 'Lo esencial, en una frase.']);

    $saludo = '<p>A las servidoras, servidores, trabajadoras y trabajadores de la Prefectura:</p>'
        .'<p>Con base en el calendario de feriados nacionales de 2026, la Dirección de Talento Humano comunica '
        .'que el lunes 2 y el martes 3 de noviembre son de <strong>descanso obligatorio</strong> para todo el personal.</p>';
    $sinResumen = borradorDeComunicado($this->autor, ['resumen' => null, 'cuerpo' => $saludo]);

    expect($conResumen->textoDeTarjeta())->toBe('Lo esencial, en una frase.');

    $extracto = $sinResumen->textoDeTarjeta();
    expect($extracto)
        ->toStartWith('A las servidoras, servidores, trabajadoras y trabajadores de la Prefectura: Con base en')
        ->toEndWith('…')
        ->not->toContain('<')
        ->and(mb_strlen($extracto))->toBeLessThanOrEqual(201);

    // El listado no trae el texto, pero sí lo de la tarjeta.
    $fila = collect($this->actingAs($this->autor, 'sanctum')->getJson('/api/v1/comunicados')->assertOk()->json('datos'))
        ->firstWhere('id', $sinResumen->id);
    expect($fila)->not->toHaveKey('cuerpo')
        ->and($fila['extracto'])->toBe($extracto);
});
