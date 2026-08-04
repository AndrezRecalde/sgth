<?php

namespace Tests\Feature\Reporte;

use App\Enums\CategoriaEventoVinculo;
use App\Enums\EstadoAccionPersonal;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\Reporte\ConfiguracionReporteMovimiento;
use App\Models\User;
use App\Services\Reporte\ReporteSiithSutService;
use Database\Seeders\ConfiguracionReporteMovimientoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);

    $this->user = User::factory()->create();
    $this->user->assignRole('admin-uath');
    $this->actingAs($this->user, 'sanctum');

    (new ConfiguracionReporteMovimientoSeeder())->run();

    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'UATH-01', 'nombre' => 'Unidad de Talento Humano', 'nivel' => 1,
    ]);

    $this->puesto = Puesto::create([
        'codigo' => 'P-01', 'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 5,
    ]);

    $this->service = app(ReporteSiithSutService::class);
});

function crearServidorConMovimiento(
    string $tipoNombramiento,
    string $tipoMovimiento,
    string $estadoMovimiento,
    string $fechaEfectiva,
): Servidor {
    $unidad = test()->unidad;
    $puesto = test()->puesto;

    $servidor = Servidor::create([
        'user_id' => User::factory()->create()->id,
        'cedula'  => (string) random_int(1000000000, 1999999999),
        'nombre'  => 'Servidor', 'apellido' => 'Reporte',
        'regimen_laboral' => 'losep',
        'puesto_id' => $puesto->id,
        'unidad_administrativa_id' => $unidad->id,
    ]);

    ContratoServidor::create([
        'servidor_id'              => $servidor->id,
        'tipo_nombramiento'        => $tipoNombramiento,
        'unidad_administrativa_id' => $unidad->id,
        'puesto_id'                => $puesto->id,
        'fecha_inicio'             => '2020-01-01',
        // Servicios Profesionales dura el año calendario y la BD exige el
        // vencimiento; el resto de nombramientos no lleva plazo.
        'fecha_fin'                => $tipoNombramiento === 'servicios_profesionales'
            ? '2020-12-31'
            : null,
        'estado'                   => 'vigente',
    ]);

    MovimientoPersonal::create([
        'servidor_id'     => $servidor->id,
        'tipo_movimiento' => $tipoMovimiento,
        'categoria'       => CategoriaEventoVinculo::ACCION_DE_PERSONAL,
        'estado'          => $estadoMovimiento,
        'descripcion'     => "Movimiento {$tipoMovimiento} de prueba",
        'fecha_efectiva'  => $fechaEfectiva,
        'autorizado_por'  => test()->user->id,
    ]);

    return $servidor;
}

// ── Seeder ─────────────────────────────────────────────────────

test('el seeder crea una fila por cada tipo_movimiento, con los 3 mapeos confirmados y la cesación documentada', function () {
    // Una fila por caso del enum — no un número fijo, para no romperse
    // cada vez que se agregue un tipo_movimiento nuevo (ej. TRASPASO).
    expect(ConfiguracionReporteMovimiento::count())->toBe(count(\App\Enums\TipoMovimientoPersonal::cases()));

    foreach (['ingreso', 'traslado', 'cambio_administrativo'] as $tipo) {
        $config = ConfiguracionReporteMovimiento::where('tipo_movimiento', $tipo)->first();
        expect($config->reportable_siith)->toBeTrue();
        expect($config->reportable_sut)->toBeFalse();
    }

    // Desde la taxonomía de dos niveles, la categoría "cesación de funciones"
    // de la norma mapea a su propio tipo_movimiento y ya no al genérico
    // 'egreso' — pero sigue sin marcarse reportable hasta que la UATH lo
    // confirme contra el formato exacto.
    $cesacion = ConfiguracionReporteMovimiento::where('tipo_movimiento', 'cesacion_funciones')->first();
    expect($cesacion->reportable_siith)->toBeFalse();
    expect($cesacion->descripcion)->toContain('cesación de funciones');

    $egreso = ConfiguracionReporteMovimiento::where('tipo_movimiento', 'egreso')->first();
    expect($egreso->reportable_siith)->toBeFalse();
    expect($egreso->descripcion)->toContain('cesacion_funciones');

    // reportable_sut queda en false para absolutamente todo (sin lista confirmada).
    expect(ConfiguracionReporteMovimiento::where('reportable_sut', true)->count())->toBe(0);
});

// ── movimientosReportables() ──────────────────────────────────

test('un ingreso registrado bajo nombramiento LOSEP aparece en el reporte SIITH', function () {
    $servidor = crearServidorConMovimiento('nombramiento_permanente', 'ingreso', 'registrada', '2026-03-10');

    $resultado = $this->service->movimientosReportables(['portal' => 'siith']);

    expect($resultado)->toHaveCount(1);
    expect($resultado->first()['servidor_id'])->toBe($servidor->id);
    expect($resultado->first()['regimen_juridico'])->toBe('losep');
});

test('un movimiento en borrador no aparece en el reporte aunque su tipo sea reportable', function () {
    crearServidorConMovimiento('nombramiento_permanente', 'ingreso', 'borrador', '2026-03-10');

    $resultado = $this->service->movimientosReportables(['portal' => 'siith']);

    expect($resultado)->toHaveCount(0);
});

test('un tipo_movimiento no marcado como reportable no aparece aunque esté registrado', function () {
    crearServidorConMovimiento('nombramiento_permanente', 'cambio_puesto', 'registrada', '2026-03-10');

    $resultado = $this->service->movimientosReportables(['portal' => 'siith']);

    expect($resultado)->toHaveCount(0);
});

test('un vínculo de servicios profesionales no aparece ni en SIITH ni en SUT', function () {
    crearServidorConMovimiento('servicios_profesionales', 'ingreso', 'registrada', '2026-03-10');

    // 'ingreso' es reportable_siith=true, pero el régimen del vínculo es
    // codigo_civil_losncp — no coincide con 'losep' (siith) ni con
    // 'codigo_trabajo' (sut).
    expect($this->service->movimientosReportables(['portal' => 'siith']))->toHaveCount(0);
    expect($this->service->movimientosReportables(['portal' => 'sut']))->toHaveCount(0);
});

test('el filtro de fechas excluye movimientos fuera del rango', function () {
    crearServidorConMovimiento('nombramiento_permanente', 'ingreso', 'registrada', '2025-01-01');

    $resultado = $this->service->movimientosReportables([
        'portal' => 'siith', 'desde' => '2026-01-01', 'hasta' => '2026-12-31',
    ]);

    expect($resultado)->toHaveCount(0);
});

test('portal desconocido lanza ReglaNegocioException', function () {
    expect(fn () => $this->service->movimientosReportables(['portal' => 'no-existe']))
        ->toThrow(\App\Exceptions\ReglaNegocioException::class);
});

// ── reporteMensual() ───────────────────────────────────────────

test('el reporte mensual agrega por tipo_movimiento y expone el detalle', function () {
    crearServidorConMovimiento('nombramiento_permanente', 'ingreso', 'registrada', '2026-07-05');
    crearServidorConMovimiento('nombramiento_provisional', 'cambio_administrativo', 'notificada', '2026-07-20');
    crearServidorConMovimiento('nombramiento_permanente', 'ingreso', 'registrada', '2026-08-01'); // fuera del mes

    $reporte = $this->service->reporteMensual(2026, 7, 'siith');

    expect($reporte['periodo'])->toBe('2026-07');
    expect($reporte['portal'])->toBe('siith');
    expect($reporte['resumen'])->toBe(['ingreso' => 1, 'cambio_administrativo' => 1]);
    expect($reporte['detalle'])->toHaveCount(2);
});

// ── HTTP ────────────────────────────────────────────────────────

test('GET /reportes/siith-sut/movimientos responde con los datos estructurados', function () {
    crearServidorConMovimiento('nombramiento_permanente', 'ingreso', 'registrada', '2026-03-10');

    $response = $this->getJson('/api/v1/reportes/siith-sut/movimientos?portal=siith');

    $response->assertStatus(200);
    expect($response->json('datos'))->toHaveCount(1);
});

test('GET /reportes/siith-sut/configuracion y PATCH actualizan un flag', function () {
    $response = $this->getJson('/api/v1/reportes/siith-sut/configuracion');
    $response->assertStatus(200);
    expect($response->json('datos'))->toHaveCount(count(\App\Enums\TipoMovimientoPersonal::cases()));

    $config = ConfiguracionReporteMovimiento::where('tipo_movimiento', 'egreso')->first();

    $update = $this->patchJson(
        "/api/v1/reportes/siith-sut/configuracion/{$config->id}",
        ['reportable_siith' => true]
    );

    $update->assertStatus(200);
    expect($config->fresh()->reportable_siith)->toBeTrue();
});
