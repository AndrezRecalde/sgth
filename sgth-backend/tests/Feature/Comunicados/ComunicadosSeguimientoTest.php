<?php

use App\Enums\RegimenLaboral;
use App\Exports\LecturasPendientesExport;
use App\Models\Comunicados\Comunicado;
use App\Models\Comunicados\ComunicadoLectura;
use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

/*
| El seguimiento de lecturas (docs/diseno-noticias-comunicados.md, 6.4).
|
| Todo se cuenta sobre los destinatarios de hoy, con la misma regla con que
| el Portal decide quién ve la publicación: las cifras, la tabla y el Excel
| tienen que decir lo mismo.
*/

uses(Tests\TestCase::class, RefreshDatabase::class);

function lecturaDeSeguimiento(Comunicado $comunicado, User $usuario, bool $confirma = false): void
{
    ComunicadoLectura::create([
        'comunicado_id' => $comunicado->id,
        'user_id'       => $usuario->id,
        'leido_at'      => Carbon::now(),
        'confirmado_at' => $confirma ? Carbon::now() : null,
    ]);
}

function filasDeSeguimiento(object $prueba, User $usuario, Comunicado $comunicado, string $consulta = ''): array
{
    return $prueba->actingAs($usuario, 'sanctum')
        ->getJson("/api/v1/comunicados/{$comunicado->id}/lecturas{$consulta}")
        ->assertOk()
        ->json('datos');
}

beforeEach(function () {
    $this->seed(RolPermisoSeeder::class);
    $this->admin = usuarioDeComunicados('admin-uath');

    $this->financiera = unidadDeComunicados('Gestión Financiera');
    $this->tesoreria = unidadDeComunicados('Tesorería', $this->financiera);
    $this->ambiente = unidadDeComunicados('Gestión Ambiental');

    $this->tesorero = servidorDeComunicados($this->tesoreria);
    $this->contadora = servidorDeComunicados($this->financiera);
    $this->guardaparque = servidorDeComunicados($this->ambiente, RegimenLaboral::CODIGO_TRABAJO);
});

afterEach(function () {
    Carbon::setTestNow();
});

// ── Las cifras ─────────────────────────────────────────────────────────────

test('las cifras cuentan destinatarios, leídos, confirmados y quién falta por confirmar', function () {
    $comunicado = publicadoDeComunicado($this->admin, [
        'audiencia' => 'segmentada',
        'unidades'  => [['id' => $this->financiera->id, 'incluye_dependencias' => true]],
    ]);
    lecturaDeSeguimiento($comunicado, $this->tesorero, confirma: true);
    lecturaDeSeguimiento($comunicado, $this->contadora);

    $this->actingAs($this->admin, 'sanctum')
        ->getJson("/api/v1/comunicados/{$comunicado->id}/lecturas/resumen")
        ->assertOk()
        ->assertJsonPath('datos', ['destinatarios' => 2, 'leidos' => 2, 'confirmados' => 1, 'pendientes' => 1]);
});

test('si no pide confirmación, falta quien no la abrió', function () {
    $comunicado = publicadoDeComunicado($this->admin, ['tipo' => 'noticia', 'requiere_confirmacion' => false]);
    lecturaDeSeguimiento($comunicado, $this->tesorero);

    $cifras = $this->actingAs($this->admin, 'sanctum')
        ->getJson("/api/v1/comunicados/{$comunicado->id}/lecturas/resumen")
        ->assertOk()
        ->json('datos');

    // Los tres servidores, el administrador y nadie más: la audiencia es «todos».
    expect($cifras)->toBe(['destinatarios' => 4, 'leidos' => 1, 'confirmados' => 0, 'pendientes' => 3]);
});

test('cuenta a los destinatarios de hoy: quien se fue de la dirección sale, quien llegó falta', function () {
    $comunicado = publicadoDeComunicado($this->admin, [
        'audiencia' => 'segmentada',
        'unidades'  => [['id' => $this->financiera->id, 'incluye_dependencias' => true]],
    ]);
    lecturaDeSeguimiento($comunicado, $this->tesorero, confirma: true);

    // El tesorero pasa a Ambiental después de confirmar; llega uno nuevo a Tesorería.
    $this->tesorero->servidor->update(['unidad_administrativa_id' => $this->ambiente->id]);
    $nuevo = servidorDeComunicados($this->tesoreria);

    $filas = collect(filasDeSeguimiento($this, $this->admin, $comunicado));

    expect($filas->pluck('user_id')->sort()->values()->all())->toBe(collect([$this->contadora->id, $nuevo->id])->sort()->values()->all())
        ->and($filas->pluck('estado')->unique()->all())->toBe(['sin_leer']);
});

// ── La tabla de destinatarios ──────────────────────────────────────────────

test('cada fila trae el servidor, su unidad y cuándo leyó y confirmó', function () {
    Carbon::setTestNow('2026-10-20 09:30:00');
    $comunicado = publicadoDeComunicado($this->admin, [
        'audiencia' => 'segmentada',
        'unidades'  => [['id' => $this->tesoreria->id, 'incluye_dependencias' => false]],
    ]);
    lecturaDeSeguimiento($comunicado, $this->tesorero, confirma: true);

    $filas = filasDeSeguimiento($this, $this->admin, $comunicado);
    $ficha = $this->tesorero->servidor;

    expect($filas)->toHaveCount(1)
        ->and($filas[0])->toMatchArray([
            'user_id'       => $this->tesorero->id,
            'cedula'        => $ficha->cedula,
            'nombre'        => "Servidor {$ficha->apellido}",
            'unidad'        => 'Tesorería',
            'estado'        => 'confirmado',
            'leido_at'      => Carbon::now()->toIso8601String(),
            'confirmado_at' => Carbon::now()->toIso8601String(),
        ]);
});

test('una dirección filtra con sus dependencias, y las cifras por unidad cuadran', function () {
    $comunicado = publicadoDeComunicado($this->admin);
    lecturaDeSeguimiento($comunicado, $this->tesorero, confirma: true);
    lecturaDeSeguimiento($comunicado, $this->guardaparque);

    $deFinanciera = filasDeSeguimiento($this, $this->admin, $comunicado, "?unidad_administrativa_id={$this->financiera->id}");
    $deAmbiente = filasDeSeguimiento($this, $this->admin, $comunicado, "?unidad_administrativa_id={$this->ambiente->id}");

    expect(collect($deFinanciera)->pluck('user_id')->sort()->values()->all())
        ->toBe(collect([$this->tesorero->id, $this->contadora->id])->sort()->values()->all())
        ->and(collect($deFinanciera)->countBy('estado')->all())->toEqual(['confirmado' => 1, 'sin_leer' => 1])
        ->and(collect($deAmbiente)->pluck('user_id')->all())->toBe([$this->guardaparque->id])
        ->and($deAmbiente[0]['estado'])->toBe('leido');
});

test('el estado filtra sin leer, leído sin confirmar, confirmado y pendiente', function () {
    $comunicado = publicadoDeComunicado($this->admin, [
        'audiencia' => 'segmentada',
        'regimenes' => ['losep'],
    ]);
    lecturaDeSeguimiento($comunicado, $this->tesorero, confirma: true);
    lecturaDeSeguimiento($comunicado, $this->contadora);
    $sinLeer = servidorDeComunicados($this->financiera);

    $ids = fn (string $estado) => collect(filasDeSeguimiento($this, $this->admin, $comunicado, "?estado={$estado}"))
        ->pluck('user_id')->sort()->values()->all();

    expect($ids('sin_leer'))->toBe([$sinLeer->id])
        ->and($ids('leido'))->toBe([$this->contadora->id])
        ->and($ids('confirmado'))->toBe([$this->tesorero->id])
        ->and($ids('pendiente'))->toBe(collect([$this->contadora->id, $sinLeer->id])->sort()->values()->all());
});

test('la búsqueda encuentra por cédula y por apellido, y pagina sin repetir', function () {
    $comunicado = publicadoDeComunicado($this->admin, ['audiencia' => 'segmentada', 'regimenes' => ['losep']]);
    $ficha = $this->contadora->servidor;

    expect(collect(filasDeSeguimiento($this, $this->admin, $comunicado, "?buscar={$ficha->cedula}"))->pluck('user_id')->all())
        ->toBe([$this->contadora->id])
        ->and(collect(filasDeSeguimiento($this, $this->admin, $comunicado, '?buscar='.urlencode($ficha->apellido)))->pluck('user_id')->all())
        ->toBe([$this->contadora->id]);

    $primera = $this->actingAs($this->admin, 'sanctum')
        ->getJson("/api/v1/comunicados/{$comunicado->id}/lecturas?per_page=1")
        ->assertOk();
    $segunda = $this->getJson("/api/v1/comunicados/{$comunicado->id}/lecturas?per_page=1&page=2")->assertOk();

    expect($primera->json('meta.total'))->toBe(2)
        ->and($primera->json('datos.0.user_id'))->not->toBe($segunda->json('datos.0.user_id'));
});

test('un borrador no tiene seguimiento', function () {
    $borrador = borradorDeComunicado($this->admin);

    $this->actingAs($this->admin, 'sanctum')
        ->getJson("/api/v1/comunicados/{$borrador->id}/lecturas/resumen")
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'Un borrador todavía no tiene lecturas: publíquelo primero.');
});

test('un filtro que no existe se rechaza', function () {
    $comunicado = publicadoDeComunicado($this->admin);

    $this->actingAs($this->admin, 'sanctum')
        ->getJson("/api/v1/comunicados/{$comunicado->id}/lecturas?estado=olvidado&unidad_administrativa_id=999999")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['estado', 'unidad_administrativa_id'], 'errores');
});

// ── El Excel de pendientes ─────────────────────────────────────────────────

test('el Excel lleva solo a quien no confirmó, ordenado por unidad', function () {
    Carbon::setTestNow('2026-10-20 10:00:00');
    Excel::fake();
    $comunicado = publicadoDeComunicado($this->admin, ['audiencia' => 'segmentada', 'regimenes' => ['losep']]);
    lecturaDeSeguimiento($comunicado, $this->tesorero, confirma: true);
    lecturaDeSeguimiento($comunicado, $this->contadora);
    $otroDeTesoreria = servidorDeComunicados($this->tesoreria);

    $this->actingAs($this->admin, 'sanctum')
        ->get("/api/v1/comunicados/{$comunicado->id}/lecturas/excel")
        ->assertOk();

    Excel::assertDownloaded(
        "pendientes_comunicado_{$comunicado->id}_20261020_100000.xlsx",
        function (LecturasPendientesExport $libro) use ($otroDeTesoreria) {
            $filas = $libro->collection();

            return $filas->count() === 2
                && $filas[0][2] === 'Gestión Financiera' && $filas[0][3] === 'Leído, sin confirmar' && $filas[0][4] === '20/10/2026 10:00'
                && $filas[1][2] === 'Tesorería' && $filas[1][3] === 'Sin leer' && $filas[1][4] === ''
                && $filas[1][0] === $otroDeTesoreria->servidor->cedula;
        },
    );
});

test('el Excel respeta la dirección elegida en la tabla', function () {
    Carbon::setTestNow('2026-10-20 10:00:00');
    Excel::fake();
    $comunicado = publicadoDeComunicado($this->admin);

    $this->actingAs($this->admin, 'sanctum')
        ->get("/api/v1/comunicados/{$comunicado->id}/lecturas/excel?unidad_administrativa_id={$this->ambiente->id}")
        ->assertOk();

    Excel::assertDownloaded(
        "pendientes_comunicado_{$comunicado->id}_20261020_100000.xlsx",
        fn (LecturasPendientesExport $libro) => $libro->collection()->pluck(0)->all() === [$this->guardaparque->servidor->cedula],
    );
});

// ── Quién lo ve ────────────────────────────────────────────────────────────

test('el seguimiento y el Excel los ven TH y el auditor, y nadie sin ver-lecturas-comunicados', function () {
    Excel::fake();
    $comunicado = publicadoDeComunicado($this->admin);

    foreach (['asistente-uath', 'auditor'] as $rol) {
        $usuario = usuarioDeComunicados($rol);
        $this->actingAs($usuario, 'sanctum')->getJson("/api/v1/comunicados/{$comunicado->id}/lecturas")->assertOk();
        $this->actingAs($usuario, 'sanctum')->get("/api/v1/comunicados/{$comunicado->id}/lecturas/excel")->assertOk();
    }

    // Publica, pero no sigue lecturas: el permiso adicional no las incluye.
    $soloPublica = usuarioDeComunicados('servidor');
    $soloPublica->givePermissionTo('publicar-comunicados');

    foreach ([$soloPublica, $this->tesorero] as $usuario) {
        $this->actingAs($usuario, 'sanctum')->getJson("/api/v1/comunicados/{$comunicado->id}/lecturas")->assertForbidden();
        $this->actingAs($usuario, 'sanctum')->getJson("/api/v1/comunicados/{$comunicado->id}/lecturas/resumen")->assertForbidden();
        $this->actingAs($usuario, 'sanctum')->get("/api/v1/comunicados/{$comunicado->id}/lecturas/excel")->assertForbidden();
    }
});
