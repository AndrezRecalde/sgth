<?php

use App\Contracts\Reporteria\ReporteriaServiceInterface;
use App\Enums\RegimenLaboral;
use App\Exceptions\ReporteNoDisponibleException;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class, RefreshDatabase::class);

/**
 * Los generadores de reportes devolvían `'datos' => []` con una cabecera
 * verosímil: una lista vacía se lee como «este periodo no hubo movimientos»,
 * no como «esto no está construido».
 */
beforeEach(function () {
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();

    // Los reportes se cachean media hora; sin limpiar, un test leería lo que
    // dejó el anterior.
    Cache::flush();

    $this->unidad = unidadDePrueba(['nombre' => 'Direccion Reportes']);
    $this->puesto = puestoDePrueba($this->unidad);

    $this->servidor = Servidor::create([
        'cedula' => '0801234595', 'nombre' => 'Pedro', 'apellido' => 'Gomez',
        'puesto_id' => $this->puesto->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(2), 'estado' => true,
    ]);

    $this->servicio = app(ReporteriaServiceInterface::class);
});

// ── Los que sí salen de la base ────────────────────────────────────────────

test('el_distributivo_trae_a_los_servidores_con_su_unidad_y_su_puesto', function () {
    // Consultaba `servidores.nombres`, `apellidos` y `rmu`: ninguna de las tres
    // existe, y reventaba con un error de Postgres.
    $reporte = $this->servicio->generarDistributivoSueldos([]);

    expect($reporte['datos'])->toHaveCount(1);
    expect($reporte['datos'][0]->cedula)->toBe('0801234595');
    expect($reporte['datos'][0]->unidad)->toBe('Direccion Reportes');
    expect($reporte['metadata']['total_servidores'])->toBe(1);
});

test('el_distributivo_no_esconde_a_quien_no_tiene_puesto', function () {
    // Sin puesto no ocupa ninguna plaza y no sale en el distributivo, pero que
    // no salga no puede ser silencioso: si son muchos, lo que falla es la
    // asignación de puestos.
    Servidor::create([
        'cedula' => '0801234596', 'nombre' => 'Ana', 'apellido' => 'Mora',
        'unidad_administrativa_id' => $this->unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYear(), 'estado' => true,
    ]);

    $reporte = $this->servicio->generarDistributivoSueldos([]);

    expect($reporte['datos'])->toHaveCount(1);
    expect($reporte['metadata']['servidores_sin_puesto'])->toBe(1);
});

test('el_distributivo_ya_no_se_corta_en_diez', function () {
    // Traía `limit(10) // Simulado`. Un distributivo recortado no es un
    // distributivo.
    foreach (range(1, 12) as $i) {
        Servidor::create([
            'cedula' => '09000000' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            'nombre' => 'Servidor', 'apellido' => 'Numero ' . $i,
            'puesto_id' => $this->puesto->id,
            'unidad_administrativa_id' => $this->unidad->id,
            'regimen_laboral' => RegimenLaboral::LOSEP,
            'fecha_ingreso_institucion' => now()->subYear(), 'estado' => true,
        ]);
    }

    expect($this->servicio->generarDistributivoSueldos([])['datos'])
        ->toHaveCount(13);
});

test('la_nomina_consolidada_suma_lo_pagado_en_el_periodo', function () {
    $nomina = DB::table('nominas')->insertGetId([
        'periodo' => '2026-08', 'fecha_inicio' => '2026-08-01',
        'fecha_fin' => '2026-08-31', 'estado' => 'cerrada',
        'total_ingresos' => 0, 'total_descuentos' => 0, 'total_neto' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('roles_pago')->insert([
        'nomina_id' => $nomina, 'servidor_id' => $this->servidor->id,
        'total_ingresos' => 1000, 'total_descuentos' => 150, 'total_neto' => 850,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $reporte = $this->servicio->generarNominaConsolidada(['periodo' => '2026-08']);

    expect($reporte['datos'])->toHaveCount(1);
    expect($reporte['metadata']['suma_ingresos'])->toBe(1000.0);
    expect($reporte['metadata']['suma_neto'])->toBe(850.0);
});

test('la_nomina_consolidada_respeta_el_periodo_que_se_le_pide', function () {
    foreach ([['2026-07', 500], ['2026-08', 900]] as [$periodo, $monto]) {
        $nomina = DB::table('nominas')->insertGetId([
            'periodo' => $periodo, 'fecha_inicio' => $periodo . '-01',
            'fecha_fin' => $periodo . '-28', 'estado' => 'cerrada',
            'total_ingresos' => 0, 'total_descuentos' => 0, 'total_neto' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('roles_pago')->insert([
            'nomina_id' => $nomina, 'servidor_id' => $this->servidor->id,
            'total_ingresos' => $monto, 'total_descuentos' => 0,
            'total_neto' => $monto,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $reporte = $this->servicio->generarNominaConsolidada(['periodo' => '2026-07']);

    expect($reporte['datos'])->toHaveCount(1);
    expect($reporte['metadata']['suma_neto'])->toBe(500.0);
});

test('el_reporte_de_asistencia_cuenta_marcaciones_y_permisos', function () {
    DB::table('marcaciones')->insert([
        ['servidor_id' => $this->servidor->id, 'fecha_hora' => now(), 'tipo' => 'entrada', 'created_at' => now(), 'updated_at' => now()],
        ['servidor_id' => $this->servidor->id, 'fecha_hora' => now(), 'tipo' => 'salida',  'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('permisos_servidor')->insert([
        'servidor_id' => $this->servidor->id, 'tipo' => 'personal',
        'fecha' => now()->toDateString(), 'hora_inicio' => '08:00',
        'hora_fin' => '10:00', 'estado' => 'activo', 'folio' => 'PER-T-1',
        'vence_en' => now()->addDays(3),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $reporte = $this->servicio->generarReporteAsistencia([]);
    $fila    = collect($reporte['datos'])->firstWhere('cedula', '0801234595');

    expect((int) $fila->total_marcaciones)->toBe(2);
    expect((int) $fila->total_permisos)->toBe(1);
});

test('el_reporte_de_asistencia_incluye_a_quien_no_marco_nada', function () {
    // Un servidor sin marcaciones es precisamente el que hay que ver en un
    // reporte de asistencia: si el join lo dejara fuera, desaparecería.
    $reporte = $this->servicio->generarReporteAsistencia([]);
    $fila    = collect($reporte['datos'])->firstWhere('cedula', '0801234595');

    expect($fila)->not->toBeNull();
    expect((int) $fila->total_marcaciones)->toBe(0);
});

test('el_reporte_de_viaticos_suma_montos_y_deja_fuera_los_rechazados', function () {
    foreach ([['aprobado', 200], ['rechazado', 999]] as $i => [$estado, $monto]) {
        DB::table('viaticos')->insert([
            'codigo_viatico' => 'VIA-' . $i, 'servidor_id' => $this->servidor->id,
            'zona' => 'dentro_provincia', 'justificacion' => 'Comisión',
            'estado' => $estado, 'monto_calculado' => $monto,
            'monto_anticipo' => 0, 'total_dias' => 2,
            'datetime_salida' => now()->subDays(3),
            'datetime_llegada' => now()->subDay(),
            'fecha_solicitud' => now()->subDays(5),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $reporte = $this->servicio->generarReporteViaticos([]);

    // El rechazado no es gasto: mezclarlo falsearía cualquier suma.
    expect($reporte['datos'])->toHaveCount(1);
    expect($reporte['metadata']['suma_calculado'])->toBe(200.0);
});

test('la_accidentabilidad_suma_los_dias_de_reposo_y_agrupa_por_gravedad', function () {
    foreach ([['leve', 1], ['grave', 30]] as $i => [$gravedad, $dias]) {
        DB::table('accidentes_trabajo')->insert([
            'servidor_id' => $this->servidor->id,
            'fecha_accidente' => now()->subDays(10 + $i)->toDateString(),
            'hora_accidente'  => '09:30',
            'lugar_accidente' => 'Taller', 'descripcion_hechos' => 'Caída',
            'gravedad' => $gravedad, 'requirio_atencion_medica' => true,
            'dias_reposo_medico' => $dias, 'estado' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $reporte = $this->servicio->generarReporteAccidentabilidad([]);

    // Diez accidentes leves y uno que costó un mes no se parecen: contando
    // accidentes solamente, parecerían lo mismo.
    expect($reporte['metadata']['total_accidentes'])->toBe(2);
    expect($reporte['metadata']['dias_reposo_total'])->toBe(31);
    expect($reporte['metadata']['por_gravedad'])->toBe(['leve' => 1, 'grave' => 1]);
});

// ── Los que no salen de esta base ──────────────────────────────────────────

test('los_reportes_que_no_se_pueden_armar_se_niegan_y_dicen_que_falta', function () {
    // Devolvían una lista vacía, que en una planilla del IESS se lee como
    // «este mes no hubo aportes». Estos se presentan ante organismos de
    // control: es mejor que no salgan a que salgan mal.
    $casos = [
        'generarPlanillaIess'  => 'detalle por rubro',
        'generarFormulario107' => 'ingresos gravados',
        'generarInformePac'    => 'compras públicas',
    ];

    foreach ($casos as $metodo => $loQueDebeDecir) {
        try {
            $this->servicio->$metodo([]);
            $this->fail("{$metodo} debió negarse y devolvió un reporte.");
        } catch (ReporteNoDisponibleException $e) {
            expect($e->getMessage())->toContain($loQueDebeDecir);
        }
    }
});

test('el_reporte_en_segundo_plano_queda_en_error_con_el_motivo', function () {
    // Es el único camino que hoy pide estos reportes. El job atrapa el fallo y
    // lo deja en la caché de estado, que es lo que consulta la pantalla: así
    // quien lo pidió lee por qué no hay reporte en vez de esperar uno que no
    // va a llegar.
    $jobId = 'prueba-iess';

    (new App\Jobs\Reporteria\GenerarReporteJob(
        $jobId, $this->servidor->id, 'planilla_iess', []
    ))->handle($this->servicio);

    $estado = Cache::get("sgth:reporte_job:{$jobId}");

    expect($estado['estado'])->toBe('error');
    expect($estado['error'])->toContain('detalle por rubro');
    expect($estado['url_descarga'])->toBeNull();
});

test('el_renderizador_convierte_el_reporte_no_disponible_en_501', function () {
    // 501 y no 422: no hay nada que quien lo pide pueda corregir enviando otra
    // cosa. Sin el renderizador saldría como error interno.
    $respuesta = app(Illuminate\Contracts\Debug\ExceptionHandler::class)->render(
        Illuminate\Http\Request::create('/api/v1/reporteria/background', 'POST'),
        new ReporteNoDisponibleException('Planilla IESS', 'falta el detalle')
    );

    expect($respuesta->getStatusCode())->toBe(501);
    expect($respuesta->getData(true)['mensaje'])->toContain('Planilla IESS');
});
