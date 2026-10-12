<?php

namespace Tests\Feature\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Exceptions\ReglaNegocioException;
use App\Models\Asistencia\PeriodoVacacion;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\Desvinculacion;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\SalarioBasico;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Expediente\DesvinculacionService;
use App\Services\Expediente\MovimientoPersonalStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| Fase 7.4b del diseño de Acciones de Personal (9.9): lo que paga la salida.
| La liquidación de vacaciones, que calcula el sistema y confirma el Director;
| la indemnización, dentro del tope del Mandato 2; las dos, en las novedades
| del mes de la salida; y la prohibición de reingreso de quien cobró una y no
| la devolvió.
|
| Hoy es el lunes 19 de octubre de 2026, el día 292 del año.
*/
beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    permisosDeAccionesPersonal();
    (require database_path('migrations/2026_10_13_000100_crear_permisos_novedades_nomina.php'))->up();
    $this->travelTo(Carbon::parse('2026-10-19 10:00'));

    $this->director = User::factory()->create();
    $this->director->assignRole('admin-uath');
    $this->actingAs($this->director, 'sanctum');

    $unidad = unidadDePrueba(['nombre' => 'Dirección de Obras']);
    $this->puesto = puestoDePrueba($unidad, 'Analista', ['plazas' => 10]);

    $this->servidor = Servidor::create([
        'cedula' => '7700000001', 'nombre' => 'Servidor', 'apellido' => 'Liquidado', 'regimen_laboral' => 'losep',
        'fecha_ingreso_institucion' => '2018-01-02', 'puesto_id' => $this->puesto->id, 'unidad_administrativa_id' => $unidad->id,
    ]);
    ContratoServidor::create([
        'servidor_id' => $this->servidor->id, 'tipo_nombramiento' => 'nombramiento_permanente',
        'unidad_administrativa_id' => $unidad->id, 'puesto_id' => $this->puesto->id,
        'fecha_inicio' => '2018-01-02', 'estado' => 'vigente', 'remuneracion' => 1000,
    ]);

    $this->periodo = fn (int $anio, float $generados, float $utilizados) => PeriodoVacacion::create([
        'servidor_id' => $this->servidor->id, 'anio' => $anio, 'regimen' => 'losep', 'anios_antiguedad' => 5,
        'fecha_inicio_periodo' => Carbon::create($anio, 1, 1), 'fecha_fin_periodo' => Carbon::create($anio, 12, 31),
        'dias_generados' => $generados, 'dias_utilizados' => $utilizados, 'dias_saldo' => $generados - $utilizados,
        'saldo_acumulado' => $generados - $utilizados, 'estado' => 'abierto',
    ]);

    $this->salir = function (): Desvinculacion {
        $cesacion = MovimientoPersonal::create([
            'servidor_id' => $this->servidor->id, 'tipo_movimiento' => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
            'subtipo_movimiento' => SubtipoMovimientoPersonal::RETIRO_VOLUNTARIO->value, 'estado' => EstadoAccionPersonal::SUSCRITA,
            'descripcion' => 'Retiro voluntario.', 'fecha_efectiva' => now()->toDateString(), 'requiere_dictamen_medico' => false,
        ]);
        app(MovimientoPersonalStateService::class)->transicionar($cesacion, EstadoAccionPersonal::REGISTRADA);

        return Desvinculacion::where('movimiento_cierre_id', $cesacion->id)->sole();
    };

    $this->paso = fn (Desvinculacion $d, string $paso) => collect($this->getJson("/api/v1/expediente/desvinculaciones/{$d->id}")
        ->assertOk()->json('datos.pasos'))->firstWhere('paso', $paso);

    $this->novedad = fn (string $concepto) => collect($this->getJson('/api/v1/nomina/novedades?periodo=2026-10')
        ->assertOk()->json('datos.novedades'))->firstWhere('concepto', $concepto);
});

// ── Las vacaciones ──────────────────────────────────────────────

test('la liquidación paga lo ganado del año de la salida, no el período entero', function () {
    ($this->periodo)(2025, 30, 20);
    ($this->periodo)(2026, 30, 5);
    $desvinculacion = ($this->salir)();

    $liquidacion = ($this->paso)($desvinculacion, 'vacaciones')['liquidacion'];

    // 10 de 2025, y de 2026, 30 × 292 / 365 = 24 ganados, menos 5 gozados.
    expect($liquidacion['periodos'])->toBe([['anio' => 2025, 'dias' => 10]])
        ->and($liquidacion['proporcional']['ganados'])->toEqual(24)
        ->and($liquidacion['proporcional']['dias'])->toEqual(19)
        ->and($liquidacion['dias'])->toEqual(29)
        ->and($liquidacion['monto'])->toEqual(966.67);

    $this->postJson("/api/v1/expediente/desvinculaciones/{$desvinculacion->id}/vacaciones/confirmar")
        ->assertOk()
        ->assertJsonPath('datos.pasos.0.estado', 'hecho');

    expect((float) $desvinculacion->fresh()->vacaciones_monto)->toBe(966.67)
        ->and(($this->novedad)('vacaciones_no_gozadas')['monto'])->toEqual(966.67);
});

test('en la LOSEP no pasa de 60 días', function () {
    ($this->periodo)(2024, 40, 0);
    ($this->periodo)(2025, 30, 0);
    $desvinculacion = ($this->salir)();

    $liquidacion = ($this->paso)($desvinculacion, 'vacaciones')['liquidacion'];

    expect($liquidacion['total'])->toBeGreaterThan(60)
        ->and($liquidacion['tope'])->toEqual(60)
        ->and($liquidacion['dias'])->toEqual(60)
        ->and($liquidacion['monto'])->toEqual(2000);
});

// ── La indemnización y el reingreso ─────────────────────────────

test('la indemnización no pasa el tope del Mandato 2, y sin el salario básico no hay tope', function () {
    $desvinculacion = ($this->salir)();
    $registrar = fn (float $monto) => $this->postJson("/api/v1/expediente/desvinculaciones/{$desvinculacion->id}/indemnizacion", [
        'concepto' => 'retiro_voluntario', 'monto' => $monto, 'resolucion' => 'Resolución GADPE-2026-210',
    ]);

    $registrar(10000)->assertStatus(422)->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'salario básico'));

    SalarioBasico::create(['anio' => 2026, 'monto' => 482]);
    $tope = ($this->paso)($desvinculacion, 'indemnizacion')['indemnizacion']['tope'];
    expect($tope)->toBeGreaterThan(29000)->toBeLessThan(30000);

    $registrar(50000)->assertStatus(422)->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'Pasa el tope'));
    $registrar(20000)->assertOk()->assertJsonPath('datos.pasos.1.estado', 'hecho');

    expect(($this->novedad)('indemnizacion')['monto'])->toEqual(20000);
});

test('quien cobró una indemnización no reingresa hasta devolverla', function () {
    SalarioBasico::create(['anio' => 2026, 'monto' => 482]);
    $desvinculacion = ($this->salir)();
    $this->postJson("/api/v1/expediente/desvinculaciones/{$desvinculacion->id}/indemnizacion", [
        'concepto' => 'retiro_voluntario', 'monto' => 15000, 'resolucion' => 'Resolución GADPE-2026-210',
    ])->assertOk();

    $reingreso = ($this->paso)($desvinculacion, 'reingreso');
    expect($reingreso['estado'])->toBe('hecho')
        ->and($reingreso['reingreso']['prohibido'])->toBeTrue();

    $servicio = app(DesvinculacionService::class);
    expect(fn () => $servicio->impedirReingresoProhibido($this->servidor->id))
        ->toThrow(ReglaNegocioException::class, 'Mandato Constituyente 2');

    // Por el registro de un ingreso, también.
    $this->postJson("/api/v1/expediente/servidores/{$this->servidor->id}/movimientos", [
        'clase' => 'ingreso', 'descripcion' => 'Reingreso.', 'fecha_efectiva' => '2026-11-01',
        'tipo_nombramiento_propuesto' => 'nombramiento_provisional', 'puesto_destino_id' => $this->puesto->id,
    ])->assertStatus(422)->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'no puede reingresar'));

    $this->postJson("/api/v1/expediente/desvinculaciones/{$desvinculacion->id}/indemnizacion/devolucion", [
        'fecha' => '2026-10-19', 'documento' => 'Depósito 55123 a la cuenta del GAD',
    ])->assertOk();

    $servicio->impedirReingresoProhibido($this->servidor->id);
    expect(($this->paso)($desvinculacion, 'reingreso')['reingreso']['prohibido'])->toBeFalse();
});
