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

/** Un sumario listo para resolver: con el informe del instructor presentado. */
function sumarioConInforme(callable $servidorDe, int $n): Sumario
{
    return Sumario::create([
        'servidor_id'    => $servidorDe($n)->id,
        'motivo'         => "Sumario a resolver $n",
        'estado'         => EstadoSumario::CON_INFORME,
        'fecha_apertura' => '2026-02-02',
        'notificado_sn'  => true,
        'fecha_informe'  => '2026-02-20',
    ]);
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

// ── Resolución ──────────────────────────────────────────────────

test('resolver un sumario por HTTP impone la sanción y lo deja resuelto', function () {
    $sumario = Sumario::create([
        'servidor_id'    => ($this->servidor)(90)->id,
        'motivo'         => 'Atraso reiterado',
        'estado'         => EstadoSumario::CON_INFORME,
        'fecha_apertura' => '2026-02-02',
        'notificado_sn'  => true,
        'fecha_informe'  => '2026-02-20',
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson("/api/v1/disciplinario/sumarios/{$sumario->id}/resolver", [
            'tipo_falta'       => 'grave',
            'tipo_sancion'     => 'multa',
            'porcentaje_multa' => 7.5,
            'fecha_efectiva'   => '2026-02-25',
            'observaciones'    => 'Resolución 2026-014 de la autoridad nominadora.',
        ])
        ->assertStatus(200);

    $sumario->refresh()->load('sancion');

    expect($sumario->estado)->toBe(EstadoSumario::RESUELTO)
        ->and($sumario->fecha_resolucion)->not->toBeNull()
        ->and($sumario->sancion)->not->toBeNull()
        ->and($sumario->sancion->tipo_sancion->value)->toBe('multa')
        ->and((float) $sumario->sancion->porcentaje_multa)->toBe(7.5)
        ->and($sumario->sancion->fecha_efectiva->toDateString())->toBe('2026-02-25');
});

test('un sumario ya resuelto no se vuelve a resolver', function () {
    $sumario = Sumario::create([
        'servidor_id'    => ($this->servidor)(91)->id,
        'motivo'         => 'Falta grave',
        'estado'         => EstadoSumario::RESUELTO,
        'fecha_apertura' => '2026-02-02',
        'notificado_sn'  => true,
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson("/api/v1/disciplinario/sumarios/{$sumario->id}/resolver", [
            'tipo_falta'   => 'leve',
            'tipo_sancion' => 'amonestacion_escrita',
        ])
        ->assertStatus(422);
});

test('un sumario apelado no se vuelve a resolver', function () {
    $sumario = Sumario::create([
        'servidor_id'    => ($this->servidor)(92)->id,
        'motivo'         => 'Falta grave',
        'estado'         => EstadoSumario::APELADO,
        'fecha_apertura' => '2026-02-02',
        'notificado_sn'  => true,
    ]);

    // Antes pasaba el guard de estado y moría en el índice único de
    // `sanciones_disciplinarias.sumario_id` con un error de SQL.
    $this->actingAs($this->admin, 'sanctum')
        ->postJson("/api/v1/disciplinario/sumarios/{$sumario->id}/resolver", [
            'tipo_falta'   => 'grave',
            'tipo_sancion' => 'amonestacion_escrita',
        ])
        ->assertStatus(422);

    expect($sumario->fresh()->estado)->toBe(EstadoSumario::APELADO)
        ->and($sumario->fresh()->sancion)->toBeNull();
});

// ── Validación de la sanción ────────────────────────────────────

test('una multa sin porcentaje se rechaza con el error en su campo', function () {
    $sumario = sumarioConInforme($this->servidor, 93);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson("/api/v1/disciplinario/sumarios/{$sumario->id}/resolver", [
            'tipo_falta'   => 'grave',
            'tipo_sancion' => 'multa',
        ])
        ->assertStatus(422)
        ->assertJsonPath('errores.porcentaje_multa.0', 'Una multa necesita su porcentaje de la remuneración.');
});

test('una suspensión sin días se rechaza con el error en su campo', function () {
    $sumario = sumarioConInforme($this->servidor, 94);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson("/api/v1/disciplinario/sumarios/{$sumario->id}/resolver", [
            'tipo_falta'   => 'grave',
            'tipo_sancion' => 'suspension',
        ])
        ->assertStatus(422)
        ->assertJsonPath('errores.dias_suspension.0', 'Una suspensión necesita sus días.');
});

test('no se guardan días de suspensión en una multa', function () {
    $sumario = sumarioConInforme($this->servidor, 95);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson("/api/v1/disciplinario/sumarios/{$sumario->id}/resolver", [
            'tipo_falta'       => 'grave',
            'tipo_sancion'     => 'multa',
            'porcentaje_multa' => 5,
            'dias_suspension'  => 10,
        ])
        ->assertStatus(422)
        // El API nombra el saco de errores `errores`, no `errors`, así que
        // `assertJsonValidationErrors` no lo encuentra.
        ->assertJsonStructure(['errores' => ['dias_suspension']]);
});

test('los topes del Art. 43 de la LOSEP se respetan', function () {
    $sumario = sumarioConInforme($this->servidor, 96);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson("/api/v1/disciplinario/sumarios/{$sumario->id}/resolver", [
            'tipo_falta'       => 'muy_grave',
            'tipo_sancion'     => 'multa',
            'porcentaje_multa' => 15,
        ])
        ->assertStatus(422)
        ->assertJsonPath(
            'errores.porcentaje_multa.0',
            'La multa no puede exceder el 10% de la remuneración según el Art. 43 de la LOSEP.'
        );

    $otro = sumarioConInforme($this->servidor, 97);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson("/api/v1/disciplinario/sumarios/{$otro->id}/resolver", [
            'tipo_falta'      => 'muy_grave',
            'tipo_sancion'    => 'suspension',
            'dias_suspension' => 45,
        ])
        ->assertStatus(422)
        ->assertJsonPath(
            'errores.dias_suspension.0',
            'La suspensión no puede exceder los 30 días según el Art. 43 de la LOSEP.'
        );
});

test('el error de un catálogo nombra el campo en español', function () {
    $sumario = sumarioConInforme($this->servidor, 98);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson("/api/v1/disciplinario/sumarios/{$sumario->id}/resolver", [
            'tipo_falta'   => 'gravisima',
            'tipo_sancion' => 'amonestacion_escrita',
        ])
        ->assertStatus(422)
        ->assertJsonPath(
            'errores.tipo_falta.0',
            'El valor del campo gravedad de la falta no está en el catálogo.'
        );
});

// ── Fechas ──────────────────────────────────────────────────────

test('un sumario no se abre con fecha futura', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/disciplinario/sumarios', [
            'servidor_id'    => ($this->servidor)(99)->id,
            'motivo'         => 'Motivo cualquiera',
            'fecha_apertura' => now()->addWeek()->toDateString(),
        ])
        ->assertStatus(422)
        ->assertJsonPath('errores.fecha_apertura.0', 'El sumario no puede abrirse con una fecha futura.');
});

test('un visto bueno no se solicita con fecha futura', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/disciplinario/vistos-buenos', [
            'servidor_id'     => ($this->servidor)(100)->id,
            'causal'          => 'indisciplina_desobediencia',
            'hechos'          => 'Desobediencia reiterada a los reglamentos internos.',
            'fecha_solicitud' => now()->addWeek()->toDateString(),
        ])
        ->assertStatus(422)
        ->assertJsonPath(
            'errores.fecha_solicitud.0',
            'La solicitud no puede presentarse con una fecha futura.'
        );
});

test('el filtro por año recorta el listado al rango del año', function () {
    Sumario::create([
        'servidor_id'    => ($this->servidor)(101)->id,
        'motivo'         => 'Del año pasado',
        'estado'         => EstadoSumario::CERRADO,
        'fecha_apertura' => '2025-12-31',
        'notificado_sn'  => true,
    ]);
    Sumario::create([
        'servidor_id'    => ($this->servidor)(102)->id,
        'motivo'         => 'De este año, el primer día',
        'estado'         => EstadoSumario::ABIERTO,
        'fecha_apertura' => '2026-01-01',
        'notificado_sn'  => false,
    ]);
    Sumario::create([
        'servidor_id'    => ($this->servidor)(103)->id,
        'motivo'         => 'De este año, el último día',
        'estado'         => EstadoSumario::ABIERTO,
        'fecha_apertura' => '2026-12-31',
        'notificado_sn'  => false,
    ]);

    // Los bordes del año entran: el rango se arma cerrado por los dos lados.
    $pagina = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/disciplinario/sumarios?anio=2026')
        ->assertStatus(200)
        ->json('datos');

    expect($pagina['total'])->toBe(2);
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

// ── Sin detalle por id ─────────────────────────────────────────

test('no hay detalle por id: el listado trae el registro y created_by es el id', function () {
    // Hasta el 2026-10-04 `GET sumarios/{id}` cargaba createdBy, que en el JSON
    // pisaba la columna `created_by` con el usuario entero de quien abrió el
    // trámite: su correo y su ficha, con cédula y teléfono.
    $sumario = Sumario::create([
        'servidor_id'    => ($this->servidor)(1)->id,
        'motivo'         => 'Sumario con autor',
        'estado'         => EstadoSumario::ABIERTO,
        'fecha_apertura' => '2026-03-02',
        'notificado_sn'  => false,
        'created_by'     => $this->admin->id,
    ]);
    $tramite = VistoBueno::create([
        'servidor_id'     => ($this->servidor)(2)->id,
        'causal'          => CausalVistoBueno::INDISCIPLINA_DESOBEDIENCIA->value,
        'estado'          => 'solicitado',
        'hechos'          => 'Trámite con autor',
        'fecha_solicitud' => '2026-03-02',
    ]);

    $this->actingAs($this->admin, 'sanctum');
    $this->getJson("/api/v1/disciplinario/sumarios/{$sumario->id}")->assertNotFound();
    $this->getJson("/api/v1/disciplinario/vistos-buenos/{$tramite->id}")->assertNotFound();

    $fila = $this->getJson('/api/v1/disciplinario/sumarios')->assertOk()->json('datos.data.0');
    expect($fila['created_by'])->toBe($this->admin->id);
});

// ── Cronología de los hitos ────────────────────────────────────

test('cada hito del sumario va después del anterior', function () {
    // Hasta el 2026-10-04 se guardaba cualquier fecha: el informe del
    // instructor podía quedar antes del fin del período de prueba.
    $sumario = Sumario::create([
        'servidor_id'    => ($this->servidor)(1)->id,
        'motivo'         => 'Sumario con hitos',
        'estado'         => EstadoSumario::ABIERTO,
        'fecha_apertura' => '2026-03-02',
        'notificado_sn'  => false,
    ]);
    $url = "/api/v1/disciplinario/sumarios/{$sumario->id}/avanzar";
    $this->actingAs($this->admin, 'sanctum');

    $this->putJson($url, ['estado' => 'en_instruccion', 'fecha_notificacion' => '2026-03-01'])
        ->assertUnprocessable()
        ->assertJsonPath('errores.fecha_notificacion.0', 'La notificación no puede ser anterior a la apertura del sumario (02/03/2026).');

    $this->putJson($url, ['estado' => 'en_instruccion', 'fecha_notificacion' => now()->addDay()->toDateString()])
        ->assertUnprocessable()
        ->assertJsonStructure(['errores' => ['fecha_notificacion']]);

    $this->putJson($url, ['estado' => 'en_instruccion', 'fecha_notificacion' => '2026-03-03'])->assertOk();

    $this->putJson($url, ['estado' => 'en_prueba', 'fecha_termino_prueba' => '2026-03-02'])
        ->assertUnprocessable()
        ->assertJsonStructure(['errores' => ['fecha_termino_prueba']]);

    $this->putJson($url, ['estado' => 'en_prueba', 'fecha_termino_prueba' => '2026-03-10'])->assertOk();

    $this->putJson($url, ['estado' => 'con_informe', 'fecha_informe' => '2026-03-09'])
        ->assertUnprocessable()
        ->assertJsonPath('errores.fecha_informe.0', 'El informe del instructor va después del período de prueba, que termina el 10/03/2026.');

    $this->putJson($url, ['estado' => 'con_informe', 'fecha_informe' => '2026-03-10'])->assertOk();
    expect($sumario->fresh()->estado)->toBe(EstadoSumario::CON_INFORME);
});
