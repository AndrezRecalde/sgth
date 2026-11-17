<?php

use App\Contracts\Reporteria\ReporteriaServiceInterface;
use App\Enums\RegimenLaboral;
use App\Exceptions\ReporteNoDisponibleException;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
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

    /** Un vínculo vigente: el distributivo sale de los contratos desde el 2026-11-17. */
    $this->vinculo = fn (Servidor $servidor, array $datos = []) => ContratoServidor::create([
        'servidor_id' => $servidor->id, 'tipo_nombramiento' => 'nombramiento_permanente',
        'unidad_administrativa_id' => $this->unidad->id, 'puesto_id' => $this->puesto->id,
        'fecha_inicio' => now()->subYear()->toDateString(), 'estado' => 'vigente', 'remuneracion' => 1000,
        ...$datos,
    ]);
});

// ── Los que sí salen de la base ────────────────────────────────────────────

test('el_distributivo_trae_cada_vinculo_con_la_remuneracion_de_su_contrato', function () {
    // Consultaba `servidores.nombres`, `apellidos` y `rmu`: ninguna de las tres
    // existe, y reventaba con un error de Postgres. Después tomaba la RMU del
    // grado del puesto, 1000 aquí, aunque el ocasional cobre otra cosa
    // (docs/diseno-masa-salarial.md, D10).
    ($this->vinculo)($this->servidor, [
        'tipo_nombramiento' => 'servicios_ocasionales', 'remuneracion' => 1250,
        'fecha_fin' => now()->addMonths(3)->toDateString(),
    ]);

    $reporte = $this->servicio->generarDistributivoSueldos([]);

    expect($reporte['datos'])->toHaveCount(1)
        ->and($reporte['datos'][0])->toMatchArray([
            'cedula' => '0801234595', 'servidor' => 'Gomez Pedro', 'unidad' => 'Direccion Reportes', 'grado' => 'SP10',
            'regimen' => 'LOSEP', 'tipo_nombramiento' => 'Contrato de Servicios Ocasionales', 'rmu' => 1250,
        ])
        ->and($reporte['metadata']['total_servidores'])->toBe(1)
        ->and($reporte['metadata']['fecha'])->toBe(now()->toDateString());
});

test('el_distributivo_no_esconde_a_quien_no_tiene_vinculo', function () {
    // Sin vínculo vigente no sale en el distributivo, aunque tenga un puesto
    // anotado, pero que no salga no puede ser silencioso: si son muchos, lo
    // que falla es la carga de los vínculos.
    ($this->vinculo)($this->servidor);
    Servidor::create([
        'cedula' => '0801234596', 'nombre' => 'Ana', 'apellido' => 'Mora',
        'puesto_id' => $this->puesto->id, 'unidad_administrativa_id' => $this->unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYear(), 'estado' => true,
    ]);

    $reporte = $this->servicio->generarDistributivoSueldos([]);

    expect($reporte['datos'])->toHaveCount(1)
        ->and($reporte['metadata']['servidores_sin_vinculo'])->toBe(1)
        ->and($reporte['metadata']['vinculos_sin_remuneracion'])->toBe(0);
});

test('el_distributivo_ya_no_se_corta_en_diez', function () {
    // Traía `limit(10) // Simulado`. Un distributivo recortado no es un
    // distributivo.
    foreach (range(1, 12) as $i) {
        ($this->vinculo)(Servidor::create([
            'cedula' => '09000000' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            'nombre' => 'Servidor', 'apellido' => 'Numero ' . $i,
            'puesto_id' => $this->puesto->id,
            'unidad_administrativa_id' => $this->unidad->id,
            'regimen_laboral' => RegimenLaboral::LOSEP,
            'fecha_ingreso_institucion' => now()->subYear(), 'estado' => true,
        ]));
    }

    // Los doce con vínculo; el de cada prueba no tiene.
    expect($this->servicio->generarDistributivoSueldos([])['datos'])
        ->toHaveCount(12);
});

test('el_reporte_de_viaticos_suma_montos_y_deja_fuera_los_rechazados', function () {
    foreach ([['aprobado', 200], ['rechazado', 999]] as $i => [$estado, $monto]) {
        DB::table('viaticos')->insert([
            'codigo_viatico' => 'VIA-' . $i, 'servidor_id' => $this->servidor->id,
            'zona' => 'dentro_provincia', 'justificacion' => 'Comisión',
            'estado' => $estado, 'monto_calculado' => $monto,
            'monto_anticipo' => 0, 'noches' => 2,
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
    // Los tres del rol de pagos, desde el 2026-10-20: el SGTH no calcula la
    // nómina, y lo pagado lo tiene Financiero.
    $casos = [
        'generarNominaConsolidada' => 'no calcula la nómina',
        'generarPlanillaIess'      => 'no calcula la nómina',
        'generarFormulario107'     => 'no calcula la nómina',
        'generarInformePac'        => 'compras públicas',
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
    expect($estado['error'])->toContain('no calcula la nómina');
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
