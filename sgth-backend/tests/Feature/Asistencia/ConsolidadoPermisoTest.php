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
