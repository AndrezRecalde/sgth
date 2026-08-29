<?php

use App\Models\Asistencia\PeriodoVacacion;
use App\Models\Estructura\Cargo;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Services\Asistencia\PeriodoVacacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

/**
 * `generarPeriodo()` sirve para crear y para recalcular, y el botón «Generar
 * todos» lo dispara sobre toda la plantilla. Ponía `dias_utilizados` en cero,
 * así que una regeneración de rutina habría borrado el consumo de vacaciones de
 * todo el personal.
 */
beforeEach(function () {
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();

    $unidad = UnidadAdministrativa::create([
        'codigo' => 'U1', 'nombre' => 'Unidad', 'nivel' => 1,
    ]);
    $cargo = Cargo::create([
        'nombre' => 'Analista',
    ]);
    $puesto = Puesto::create([
        'unidad_administrativa_id' => $unidad->id, 'cargo_id' => $cargo->id, 'plazas' => 1,
    ]);

    $this->servidor = Servidor::create([
        'cedula' => '0801234567', 'nombre' => 'Marlon', 'apellido' => 'Vera',
        'puesto_id' => $puesto->id,
        'regimen_laboral' => 'losep',
        'fecha_ingreso_sector_publico' => '2020-01-01',
        'fecha_ingreso_institucion' => '2020-01-01',
    ]);

    $this->servicio = app(PeriodoVacacionService::class);
});

test('regenerar conserva los días ya gozados', function () {
    $this->servicio->generarPeriodo($this->servidor, 2026);
    $this->servicio->descontarDias($this->servidor->id, 6, 2026);

    $antes = PeriodoVacacion::where('servidor_id', $this->servidor->id)->firstOrFail();
    expect((float) $antes->dias_utilizados)->toBe(6.0);

    $this->servicio->generarPeriodo($this->servidor, 2026);

    $despues = $antes->fresh();

    expect((float) $despues->dias_utilizados)->toBe(6.0)
        ->and((float) $despues->dias_saldo)
        ->toBe((float) $despues->dias_generados - 6.0);
});

test('regenerar sí recalcula lo generado cuando cambia el régimen', function () {
    $this->servicio->generarPeriodo($this->servidor, 2026);
    $this->servicio->descontarDias($this->servidor->id, 5, 2026);

    // Un contrato civil no genera vacaciones. Lo gozado sigue siendo un hecho.
    $this->servidor->update(['regimen_laboral' => 'servicios_profesionales']);
    $this->servicio->generarPeriodo($this->servidor, 2026);

    $periodo = PeriodoVacacion::where('servidor_id', $this->servidor->id)->firstOrFail();

    expect((float) $periodo->dias_generados)->toBe(0.0)
        ->and((float) $periodo->dias_utilizados)->toBe(5.0)
        // Se acota a cero, como hace descontarDias(), en vez de quedar en -5.
        ->and((float) $periodo->dias_saldo)->toBe(0.0);
});

test('un período recién creado arranca sin días usados', function () {
    $periodo = $this->servicio->generarPeriodo($this->servidor, 2026);

    expect((float) $periodo->dias_utilizados)->toBe(0.0)
        ->and((float) $periodo->dias_saldo)->toBe((float) $periodo->dias_generados);
});

test('regenerar no reabre un período cerrado', function () {
    $this->servicio->generarPeriodo($this->servidor, 2025);

    PeriodoVacacion::where('servidor_id', $this->servidor->id)
        ->where('anio', 2025)
        ->update(['estado' => 'cerrado']);

    $this->servicio->generarPeriodo($this->servidor, 2025);

    expect(PeriodoVacacion::where('servidor_id', $this->servidor->id)
        ->where('anio', 2025)->value('estado'))->toBe('cerrado');
});

test('el acumulado descuenta lo gozado en vez de sumar lo generado', function () {
    $this->servicio->generarPeriodo($this->servidor, 2026);
    $this->servicio->descontarDias($this->servidor->id, 4, 2026);

    $this->servicio->generarPeriodo($this->servidor, 2026);
    $periodo = PeriodoVacacion::where('servidor_id', $this->servidor->id)->firstOrFail();

    // Sin períodos anteriores abiertos, el acumulado es el saldo del año.
    expect((float) $periodo->saldo_acumulado)->toBe((float) $periodo->dias_saldo);
});

// ── Los períodos cerrados no se tocan por rutina ────────────────

test('regenerar no recalcula un período cerrado', function () {
    $this->servidor->update(['fecha_ingreso_sector_publico' => '2009-01-01']);
    $this->servicio->generarPeriodo($this->servidor->fresh(), 2025);
    $this->servicio->descontarDias($this->servidor->id, 20, 2025);

    $periodo = PeriodoVacacion::where('servidor_id', $this->servidor->id)
        ->where('anio', 2025)->firstOrFail();
    $periodo->update(['estado' => 'cerrado']);

    $generadosAlCerrar = (float) $periodo->dias_generados;
    $saldoAlCerrar     = (float) $periodo->dias_saldo;

    // Se corrige la antigüedad y cruza de tramo: le corresponderían 15 en vez de 30.
    $this->servidor->update(['fecha_ingreso_sector_publico' => '2020-01-01']);
    $this->servicio->generarPeriodo($this->servidor->fresh(), 2025);

    $despues = $periodo->fresh();

    // El saldo certificado no se movió.
    expect((float) $despues->dias_generados)->toBe($generadosAlCerrar)
        ->and((float) $despues->dias_saldo)->toBe($saldoAlCerrar);
});

test('«generar todos» deja intactos los períodos cerrados', function () {
    $this->servicio->generarPeriodo($this->servidor, 2025);
    PeriodoVacacion::where('servidor_id', $this->servidor->id)
        ->where('anio', 2025)->update(['estado' => 'cerrado', 'dias_generados' => 25]);

    $this->servicio->generarPeriodosAnuales(2025);

    expect((float) PeriodoVacacion::where('servidor_id', $this->servidor->id)
        ->where('anio', 2025)->value('dias_generados'))->toBe(25.0);
});

test('forzar sí recalcula un período cerrado y lo deja en la bitácora', function () {
    // Las fechas cruzan tramo de la escala LOSEP a propósito: 2009 son 16 años
    // en 2025 (30 días) y 2020 son 5 (15 días). Dos fechas del mismo tramo
    // darían el mismo resultado y el test no probaría nada.
    $this->servidor->update(['fecha_ingreso_sector_publico' => '2009-01-01']);

    $this->servicio->generarPeriodo($this->servidor->fresh(), 2025);
    $periodo = PeriodoVacacion::where('servidor_id', $this->servidor->id)
        ->where('anio', 2025)->firstOrFail();
    $periodo->update(['estado' => 'cerrado']);

    $generadosAntes = (float) $periodo->dias_generados;
    expect($generadosAntes)->toBe(30.0);

    $this->servidor->update(['fecha_ingreso_sector_publico' => '2020-01-01']);
    $this->servicio->generarPeriodo($this->servidor->fresh(), 2025, forzar: true);

    $despues = $periodo->fresh();

    expect((float) $despues->dias_generados)->toBeLessThan($generadosAntes)
        // Sigue cerrado: forzar recalcula, no reabre.
        ->and($despues->estado)->toBe('cerrado');

    $registro = DB::table('activity_log')
        ->where('log_name', 'periodos-vacaciones')
        ->latest('id')->first();

    expect($registro)->not->toBeNull();

    $props = json_decode($registro->properties, true);
    expect($props['antes']['dias_generados'])->not->toBe($props['despues']['dias_generados'])
        ->and($props['anio'])->toBe(2025);
});

test('la previsualización anuncia el mismo saldo que después se guarda', function () {
    // Si el diálogo promete un saldo y forzar deja otro, la confirmación
    // informada deja de serlo. Este test ata las dos cifras.
    $this->servidor->update(['fecha_ingreso_sector_publico' => '2009-01-01']);
    $this->servicio->generarPeriodo($this->servidor->fresh(), 2025);
    $this->servicio->descontarDias($this->servidor->id, 4, 2025);

    PeriodoVacacion::where('servidor_id', $this->servidor->id)
        ->where('anio', 2025)->update(['estado' => 'cerrado']);

    $this->servidor->update(['fecha_ingreso_sector_publico' => '2020-01-01']);
    $servidor = $this->servidor->fresh();

    $previa = $this->servicio->previsualizarRecalculo($servidor, 2025);

    expect($previa['actual']['dias_generados'])->toBe(30.0)
        ->and($previa['propuesto']['dias_generados'])->toBe(15.0)
        // Lo gozado no cambia: 15 - 4.
        ->and($previa['propuesto']['dias_saldo'])->toBe(11.0)
        ->and($previa['estado'])->toBe('cerrado');

    $guardado = $this->servicio->generarPeriodo($servidor, 2025, forzar: true);

    expect((float) $guardado->dias_generados)->toBe($previa['propuesto']['dias_generados'])
        ->and((float) $guardado->dias_saldo)->toBe($previa['propuesto']['dias_saldo']);
});

test('previsualizar no escribe nada', function () {
    $this->servicio->generarPeriodo($this->servidor, 2025);
    PeriodoVacacion::where('servidor_id', $this->servidor->id)
        ->where('anio', 2025)->update(['estado' => 'cerrado', 'dias_generados' => 25]);

    $this->servicio->previsualizarRecalculo($this->servidor->fresh(), 2025);

    expect((float) PeriodoVacacion::where('servidor_id', $this->servidor->id)
        ->where('anio', 2025)->value('dias_generados'))->toBe(25.0);
});

test('previsualizar un año sin período devuelve null', function () {
    expect($this->servicio->previsualizarRecalculo($this->servidor, 2019))->toBeNull();
});

// ── A quien no genera vacaciones no se le abre período ───────────

test('no se abre un período para un contrato de servicios profesionales', function () {
    $this->servidor->update(['regimen_laboral' => 'servicios_profesionales']);

    expect(fn () => $this->servicio->generarPeriodo($this->servidor->fresh(), 2026))
        ->toThrow(\App\Exceptions\ReglaNegocioException::class, 'no genera vacaciones');

    expect(PeriodoVacacion::where('servidor_id', $this->servidor->id)->count())->toBe(0);
});

test('«generar todos» salta a los regímenes sin vacaciones', function () {
    $this->servidor->update(['regimen_laboral' => 'servicios_profesionales']);

    // No lanza: la generación masiva es de rutina y los filtra en la consulta.
    $resultados = $this->servicio->generarPeriodosAnuales(2026);

    expect($resultados)->toHaveCount(0)
        ->and(PeriodoVacacion::where('servidor_id', $this->servidor->id)->count())->toBe(0);
});

test('el período que ya existía sí se recalcula al cambiar de régimen', function () {
    // El corte es solo para períodos NUEVOS. Quien estuvo bajo otro régimen y
    // gozó días conserva su período: eso ocurrió y no se borra.
    $this->servicio->generarPeriodo($this->servidor, 2026);
    $this->servicio->descontarDias($this->servidor->id, 3, 2026);

    $this->servidor->update(['regimen_laboral' => 'servicios_profesionales']);
    $periodo = $this->servicio->generarPeriodo($this->servidor->fresh(), 2026);

    expect((float) $periodo->dias_generados)->toBe(0.0)
        ->and((float) $periodo->dias_utilizados)->toBe(3.0);
});
