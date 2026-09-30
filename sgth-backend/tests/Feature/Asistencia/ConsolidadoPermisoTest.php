<?php

/*
| El consolidado, ahora agregado por PostgreSQL.
|
| La suma se hacía en PHP recorriendo todos los permisos del rango; pasó a un
| GROUP BY. Estos tests fijan los números para que el cambio de motor no los
| mueva, y cubren el filtro de estados, que estaba mal en las tres copias que
| tenía el controlador.
*/

use App\Enums\EstadoPermiso;
use App\Enums\RegimenLaboral;
use App\Enums\TipoPermiso;
use App\Models\Asistencia\PermisoServidor;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();
    PermisoServidor::unguard();

    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $this->unidad = unidadDePrueba(['nombre' => 'Dirección Financiera']);

    $this->ana = Servidor::create([
        'cedula' => '0808888881', 'nombre' => 'Ana', 'segundo_nombre' => 'María',
        'apellido' => 'Alfa', 'segundo_apellido' => 'Beta',
        'puesto_id' => puestoDePrueba($this->unidad)->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true,
    ]);

    $this->beto = Servidor::create([
        'cedula' => '0808888882', 'nombre' => 'Beto', 'apellido' => 'Zeta',
        'puesto_id' => puestoDePrueba($this->unidad)->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true,
    ]);

    $this->uath = User::create([
        'email' => 'uath@example.com', 'usuario_ti' => 'uath_u',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $this->uath->assignRole('admin-uath');
});

function permisoConcedido(
    Servidor $servidor,
    string $folio,
    string $horaInicio,
    string $horaFin,
    EstadoPermiso $estado = EstadoPermiso::ACTIVO,
    TipoPermiso $tipo = TipoPermiso::PERSONAL,
): PermisoServidor {
    return PermisoServidor::create([
        'servidor_id' => $servidor->id,
        'tipo'        => $tipo->value,
        'fecha'       => Carbon::today()->toDateString(),
        'hora_inicio' => $horaInicio,
        'hora_fin'    => $horaFin,
        'estado'      => $estado->value,
        'vence_en'    => now()->addDays(3),
        'folio'       => $folio,
    ]);
}

function consultarConsolidado(array $extra = []): \Illuminate\Testing\TestResponse
{
    $params = array_merge([
        'fecha_inicio' => Carbon::today()->subDays(5)->toDateString(),
        'fecha_fin'    => Carbon::today()->addDays(5)->toDateString(),
    ], $extra);

    return test()->actingAs(test()->uath, 'sanctum')
        ->getJson('/api/v1/asistencia/consolidado-permisos?' . http_build_query($params));
}

test('varios permisos de un servidor se suman en una sola fila', function () {
    permisoConcedido($this->ana, 'PER-2026-C0001', '08:00', '10:00'); // 120 min
    permisoConcedido($this->ana, 'PER-2026-C0002', '14:00', '15:30'); //  90 min

    $filas = consultarConsolidado()->assertStatus(200)->json('datos.consolidado');

    expect($filas)->toHaveCount(1);

    $fila = $filas[0];

    expect($fila['total_permisos'])->toBe(2)
        ->and($fila['total_minutos'])->toBe(210)
        ->and($fila['tiempo_total'])->toBe('03:30')
        ->and($fila['total_dias'])->toBe(0.44)   // 210 / 480
        ->and($fila['cedula'])->toBe('0808888881')
        ->and($fila['unidad'])->toBe('Dirección Financiera');
});

test('el nombre sale completo y en mayúsculas, con los dos apellidos primero', function () {
    permisoConcedido($this->ana, 'PER-2026-C0003', '08:00', '09:00');

    $filas = consultarConsolidado()->assertStatus(200)->json('datos.consolidado');

    expect($filas[0]['servidor_nombre'])->toBe('ALFA BETA ANA MARÍA');
});

test('cada servidor es una fila y los totales generales cuadran', function () {
    permisoConcedido($this->ana,  'PER-2026-C0004', '08:00', '12:00'); // 240
    permisoConcedido($this->beto, 'PER-2026-C0005', '08:00', '10:00'); // 120
    permisoConcedido($this->beto, 'PER-2026-C0006', '15:00', '16:00'); //  60

    $respuesta = consultarConsolidado()->assertStatus(200);

    $filas   = collect($respuesta->json('datos.consolidado'));
    $totales = $respuesta->json('datos.totales');

    expect($filas)->toHaveCount(2)
        ->and($totales['total_permisos'])->toBe(3)
        ->and($totales['total_minutos'])->toBe(420)
        // 0.50 + 0.38: el total de días es la suma de los días ya redondeados
        // de cada fila, así que puede separarse un céntimo de dividir el total
        // de minutos. Es como se ha calculado siempre y así cuadra con lo que
        // muestra cada línea del informe.
        ->and($totales['total_dias'])->toBe(0.88);

    // Ordenado por apellido: Alfa antes que Zeta.
    expect($filas->pluck('cedula')->all())->toBe(['0808888881', '0808888882']);
});

test('solo cuentan los permisos concedidos', function () {
    permisoConcedido($this->ana, 'PER-2026-C0010', '08:00', '09:00'); // activo, sí
    permisoConcedido($this->ana, 'PER-2026-C0011', '09:00', '10:00',
        EstadoPermiso::VALIDADO_TRABAJO_SOCIAL); // sí

    foreach ([
        ['PER-2026-C0012', EstadoPermiso::PENDIENTE],
        ['PER-2026-C0013', EstadoPermiso::ANULADO],
        ['PER-2026-C0014', EstadoPermiso::RECHAZADO],
        ['PER-2026-C0015', EstadoPermiso::FALTA_INJUSTIFICADA],
    ] as [$folio, $estado]) {
        permisoConcedido($this->ana, $folio, '11:00', '13:00', $estado);
    }

    $filas = consultarConsolidado()->assertStatus(200)->json('datos.consolidado');

    expect($filas[0]['total_permisos'])->toBe(2)
        ->and($filas[0]['total_minutos'])->toBe(120);
});

test('el consolidado es por tipo, y no mezcla', function () {
    permisoConcedido($this->ana, 'PER-2026-C0020', '08:00', '10:00');
    permisoConcedido($this->ana, 'PER-2026-C0021', '14:00', '17:00',
        EstadoPermiso::ACTIVO, TipoPermiso::OFICIAL);

    expect(consultarConsolidado(['tipo' => 'personal'])->json('datos.totales.total_minutos'))
        ->toBe(120);

    expect(consultarConsolidado(['tipo' => 'oficial'])->json('datos.totales.total_minutos'))
        ->toBe(180);
});

test('un permiso fuera del rango no entra', function () {
    $viejo = permisoConcedido($this->ana, 'PER-2026-C0030', '08:00', '10:00');
    $viejo->update(['fecha' => Carbon::today()->subMonths(2)->toDateString()]);

    expect(consultarConsolidado()->json('datos.consolidado'))->toBe([]);
});

test('un rango sin permisos devuelve totales en cero, no un error', function () {
    $respuesta = consultarConsolidado()->assertStatus(200);

    expect($respuesta->json('datos.consolidado'))->toBe([])
        ->and($respuesta->json('datos.totales.total_permisos'))->toBe(0)
        ->and($respuesta->json('datos.totales.total_minutos'))->toBe(0);
});

test('un tipo inventado se rechaza en vez de devolver un informe vacío', function () {
    // Antes cualquier cadena pasaba y el informe salía en blanco: un error de
    // escritura en el filtro parecía «este mes nadie pidió permiso».
    consultarConsolidado(['tipo' => 'personl'])
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['tipo']]);
});

test('la exportación a CSV trae encabezados y una fila por servidor', function () {
    permisoConcedido($this->ana,  'PER-2026-C0040', '08:00', '10:00');
    permisoConcedido($this->beto, 'PER-2026-C0041', '08:00', '09:00');

    $params = http_build_query([
        'fecha_inicio' => Carbon::today()->subDays(5)->toDateString(),
        'fecha_fin'    => Carbon::today()->addDays(5)->toDateString(),
    ]);

    $respuesta = $this->actingAs($this->uath, 'sanctum')
        ->get("/api/v1/asistencia/consolidado-permisos/exportar-excel?{$params}")
        ->assertStatus(200);

    $csv = $respuesta->streamedContent();

    expect($csv)->toContain('Cedula;Servidor;Unidad')
        ->and($csv)->toContain('0808888881')
        ->and($csv)->toContain('ALFA BETA ANA MARÍA')
        ->and($csv)->toContain('0808888882');
});

test('la exportación a PDF se genera', function () {
    permisoConcedido($this->ana, 'PER-2026-C0050', '08:00', '10:00');

    $params = http_build_query([
        'fecha_inicio' => Carbon::today()->subDays(5)->toDateString(),
        'fecha_fin'    => Carbon::today()->addDays(5)->toDateString(),
    ]);

    $respuesta = $this->actingAs($this->uath, 'sanctum')
        ->get("/api/v1/asistencia/consolidado-permisos/exportar-pdf?{$params}")
        ->assertStatus(200);

    expect($respuesta->headers->get('content-type'))->toContain('application/pdf');
});

// ── Quién puede pedirlo ──────────────────────────────────────────

/*
| El informe dice, de toda la institución, quién se ausenta y cuánto. Estuvo
| abierto a cualquier usuario autenticado —un servidor raso podía descargarlo
| en Excel— hasta que se cerró con `ver-permisos-todos`.
|
| Aquel arreglo no dejó ninguna prueba detrás, así que hoy quitar el middleware
| no rompe nada visible. Esto no destapa un fallo: lo sostiene.
*/
test('sin el permiso no se consulta ni se exporta', function () {
    $raso = User::create([
        'email' => 'raso@example.com', 'usuario_ti' => 'raso_u',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $raso->assignRole('servidor');

    $params = http_build_query([
        'fecha_inicio' => Carbon::today()->subDays(5)->toDateString(),
        'fecha_fin'    => Carbon::today()->addDays(5)->toDateString(),
    ]);

    foreach ([
        '/api/v1/asistencia/consolidado-permisos',
        '/api/v1/asistencia/consolidado-permisos/exportar-excel',
        '/api/v1/asistencia/consolidado-permisos/exportar-pdf',
    ] as $ruta) {
        $this->actingAs($raso, 'sanctum')
            ->getJson("{$ruta}?{$params}")
            ->assertStatus(403);
    }
});

test('con el permiso sí', function () {
    permisoConcedido($this->ana, 'PER-2026-C0060', '08:00', '10:00');

    consultarConsolidado()->assertStatus(200);
});

// ── El orden y el rango ──────────────────────────────────────────

/*
| Dos servidores que se llaman igual quedaban en un orden que Postgres no
| promete: `ORDER BY apellido, nombre` sin desempate. Dos exportaciones del
| mismo período podían traer las filas cambiadas de sitio en un informe que se
| firma. El id las ordena.
|
| Es una prueba de guarda, no una reproducción: con dos filas el motor devuelve
| el mismo orden con desempate y sin él, y no se puede forzar de forma fiable a
| que no lo haga. Lo que fija es la intención —el orden lo decide el id— para
| que quitar ese `orderBy` se note aquí y no en un informe firmado.
*/
test('dos servidores homónimos salen siempre en el mismo orden', function () {
    $unidad = $this->unidad;

    $gemelos = collect(range(1, 2))->map(fn ($i) => Servidor::create([
        'cedula' => '08077777'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
        'nombre' => 'Juan', 'apellido' => 'Perez',
        'puesto_id' => puestoDePrueba($unidad)->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true,
    ]));

    foreach ($gemelos as $i => $servidor) {
        permisoConcedido($servidor, 'PER-2026-C007'.$i, '08:00', '09:00');
    }

    $esperado = $gemelos->sortBy('id')->pluck('cedula')->values()->all();

    foreach (range(1, 3) as $_) {
        $filas = collect(consultarConsolidado()->json('datos.consolidado'))
            ->whereIn('cedula', $esperado)
            ->pluck('cedula')
            ->values()
            ->all();

        expect($filas)->toBe($esperado);
    }
});

test('un rango de más de cinco años se rechaza', function () {
    consultarConsolidado([
        'fecha_inicio' => '1900-01-01',
        'fecha_fin'    => '2100-12-31',
    ])
        ->assertStatus(422)
        ->assertJsonPath('errores.fecha_fin.0', 'El consolidado abarca como máximo 5 años; el rango pedido es mayor.');
});

test('un rango largo pero razonable pasa', function () {
    permisoConcedido($this->ana, 'PER-2026-C0080', '08:00', '10:00');

    consultarConsolidado([
        'fecha_inicio' => Carbon::today()->subYears(2)->toDateString(),
        'fecha_fin'    => Carbon::today()->addDays(5)->toDateString(),
    ])->assertStatus(200);
});

// ── El filtro por servidor ───────────────────────────────────────

/*
| Sin `servidor_id` el informe es de toda la institución; con él, de una sola
| persona: la tabla, los totales y las dos exportaciones. Que el archivo diga
| lo mismo que la pantalla es el punto del filtro.
*/
test('con servidor_id el informe es de esa persona y de nadie más', function () {
    permisoConcedido($this->ana,  'PER-2026-C0090', '08:00', '12:00'); // 240
    permisoConcedido($this->beto, 'PER-2026-C0091', '08:00', '10:00'); // 120

    $respuesta = consultarConsolidado(['servidor_id' => $this->ana->id])->assertStatus(200);

    $filas   = $respuesta->json('datos.consolidado');
    $totales = $respuesta->json('datos.totales');

    expect($filas)->toHaveCount(1)
        ->and($filas[0]['cedula'])->toBe('0808888881')
        ->and($totales['total_minutos'])->toBe(240)
        ->and($totales['total_permisos'])->toBe(1)
        ->and($totales['tiempo_total'])->toBe('04:00');
});

test('sin servidor_id siguen saliendo todos', function () {
    permisoConcedido($this->ana,  'PER-2026-C0092', '08:00', '12:00');
    permisoConcedido($this->beto, 'PER-2026-C0093', '08:00', '10:00');

    expect(consultarConsolidado()->json('datos.consolidado'))->toHaveCount(2);
});

test('un servidor sin permisos en el rango devuelve vacío, no un error', function () {
    permisoConcedido($this->beto, 'PER-2026-C0094', '08:00', '10:00');

    $respuesta = consultarConsolidado(['servidor_id' => $this->ana->id])->assertStatus(200);

    expect($respuesta->json('datos.consolidado'))->toBe([])
        ->and($respuesta->json('datos.totales.total_minutos'))->toBe(0);
});

test('un servidor que no existe se rechaza', function () {
    consultarConsolidado(['servidor_id' => 999999])
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['servidor_id']]);
});

test('la exportación respeta el filtro, para que el archivo diga lo que la pantalla', function () {
    permisoConcedido($this->ana,  'PER-2026-C0095', '08:00', '12:00');
    permisoConcedido($this->beto, 'PER-2026-C0096', '08:00', '10:00');

    $params = http_build_query([
        'fecha_inicio' => Carbon::today()->subDays(5)->toDateString(),
        'fecha_fin'    => Carbon::today()->addDays(5)->toDateString(),
        'servidor_id'  => $this->ana->id,
    ]);

    $csv = $this->actingAs($this->uath, 'sanctum')
        ->get("/api/v1/asistencia/consolidado-permisos/exportar-excel?{$params}")
        ->assertStatus(200)
        ->streamedContent();

    expect($csv)->toContain('0808888881')
        ->and($csv)->not->toContain('0808888882');
});

test('el PDF filtrado se genera', function () {
    permisoConcedido($this->ana, 'PER-2026-C0097', '08:00', '10:00');

    $params = http_build_query([
        'fecha_inicio' => Carbon::today()->subDays(5)->toDateString(),
        'fecha_fin'    => Carbon::today()->addDays(5)->toDateString(),
        'servidor_id'  => $this->ana->id,
    ]);

    $respuesta = $this->actingAs($this->uath, 'sanctum')
        ->get("/api/v1/asistencia/consolidado-permisos/exportar-pdf?{$params}")
        ->assertStatus(200);

    expect($respuesta->headers->get('content-type'))->toContain('application/pdf');
});
