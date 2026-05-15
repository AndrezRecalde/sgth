<?php

use App\Enums\EstadoViatico;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\Handoff\HandoffErp;
use App\Models\User;
use App\Models\Viatico\LiquidacionViatico;
use App\Models\Viatico\TarifaViatico;
use App\Models\Viatico\Viatico;
use App\Services\Handoff\HandoffErpService;
use App\Services\Viatico\ViaticoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();
    Viatico::unguard();
    TarifaViatico::unguard();
    LiquidacionViatico::unguard();
    HandoffErp::unguard();

    $this->servidorUser = User::create([
        'name' => 'Usuario Normal',
        'email' => 'normal@example.com',
        'usuario_ti' => 'normal',
        'password' => bcrypt('123456'),
        'primer_login' => false,
    ]);

    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'U01',
        'nombre' => 'Direccion Test',
        'estado' => true,
        'nivel' => 1,
    ]);

    $this->puesto = Puesto::create([
        'codigo' => 'P01',
        'denominacion' => 'Analista',
        'unidad_administrativa_id' => $this->unidad->id,
        'grupo_ocupacional' => 'Profesional',
        'grado_rmu' => 10,
        'rmu' => 1000.00,
        'nivel' => 1,
        'es_jefe' => false,
        'estado' => true,
    ]);

    $this->servidor = Servidor::create([
        'cedula' => '0801234562',
        'nombre' => 'Pedro',
        'apellido' => 'Gomez',
        'user_id' => $this->servidorUser->id,
        'puesto_id' => $this->puesto->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'regimen_laboral' => \App\Enums\RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(2),
        'estado' => true,
    ]);
});

test('servidor_con_liquidacion_pendiente_no_puede_solicitar', function () {
    // Viático con fecha_fin hace 10 días (fuera de los 5 hábiles)
    Viatico::create([
        'servidor_id' => $this->servidor->id,
        'zona' => 'dentro_provincia',
        'tipo' => 'con_pernocte',
        'fecha_inicio' => now()->subDays(12),
        'fecha_fin' => now()->subDays(10), // Hace 10 dias calendario (mas de 5 habiles seguros)
        'justificacion' => 'Comisión anterior',
        'estado' => EstadoViatico::PENDIENTE_LIQUIDACION->value,
        'monto_calculado' => 80,
    ]);

    $service = new ViaticoService();
    
    expect(function () use ($service) {
        $service->solicitar($this->servidor->id, [
            'zona' => 'dentro_provincia',
            'tipo' => 'con_pernocte',
            'fecha_inicio' => now()->addDays(1)->format('Y-m-d H:i:s'),
            'fecha_fin' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'justificacion' => 'Nueva comision',
        ], $this->servidorUser->id);
    })->toThrow(\App\Exceptions\ReglaNegocioException::class, 'El servidor tiene bloqueada la solicitud de nuevos viáticos');
});

test('viatico_menos_10_horas_aplica_subsistencia', function () {
    // 1. Tarifa completa con pernocte: $80
    TarifaViatico::create([
        'zona' => 'dentro_provincia',
        'nivel' => 'servidor',
        'tipo_tarifa' => 'con_pernocte',
        'valor_diario' => 80.00,
    ]);

    // 2. Tarifa específica de subsistencia (menos de 10 horas sin pernocte): $40
    TarifaViatico::create([
        'zona' => 'dentro_provincia',
        'nivel' => 'servidor',
        'tipo_tarifa' => 'subsistencia',
        'valor_diario' => 40.00,
    ]);

    $service = new ViaticoService();

    // Comisión de 8 horas: de 08:00 a 16:00
    $viatico = $service->solicitar($this->servidor->id, [
        'zona' => 'dentro_provincia',
        'tipo' => 'sin_pernocte',
        'fecha_inicio' => now()->setTime(8, 0)->format('Y-m-d H:i:s'),
        'fecha_fin' => now()->setTime(16, 0)->format('Y-m-d H:i:s'),
        'justificacion' => 'Reunión rápida',
    ], $this->servidorUser->id);

    // Debe aplicar la tarifa de subsistencia ($40) porque es menor a 10 horas y no tiene pernocte
    expect((float)$viatico->monto_calculado)->toBe(40.00);
});

test('handoff_compromiso_generado_al_aprobar_financiero', function () {
    Storage::fake('local');

    $viatico = Viatico::create([
        'servidor_id' => $this->servidor->id,
        'zona' => 'dentro_provincia',
        'tipo' => 'con_pernocte',
        'fecha_inicio' => now()->addDays(2),
        'fecha_fin' => now()->addDays(4),
        'justificacion' => 'Supervisión de obras',
        'estado' => EstadoViatico::APROBADO_FINANCIERO->value,
        'monto_calculado' => 160.00,
        'monto_anticipo' => 160.00,
        'numero_resolucion' => 'RES-2026-001',
        'partida_presupuestaria' => '530303',
        'updated_by' => $this->servidorUser->id,
    ]);

    $service = new HandoffErpService();
    $handoff = $service->generarHandoffCompromisoViatico($viatico->id);

    expect($handoff)->toBeInstanceOf(HandoffErp::class);
    expect($handoff->tipo)->toBe('viatico_compromiso');
    expect($handoff->referencia_id)->toBe($viatico->id);
    
    // Validar que el archivo existe en el Storage
    Storage::disk('local')->assertExists($handoff->archivo_ruta);
});

test('handoff_devengado_generado_al_liquidar', function () {
    Storage::fake('local');

    $viatico = Viatico::create([
        'servidor_id' => $this->servidor->id,
        'zona' => 'dentro_provincia',
        'tipo' => 'con_pernocte',
        'fecha_inicio' => now()->subDays(4),
        'fecha_fin' => now()->subDays(2),
        'justificacion' => 'Supervisión de obras',
        'estado' => EstadoViatico::LIQUIDADO->value,
        'monto_calculado' => 160.00,
        'monto_anticipo' => 160.00,
        'numero_resolucion' => 'RES-2026-001',
        'partida_presupuestaria' => '530303',
        'updated_by' => $this->servidorUser->id,
    ]);

    $liquidacion = LiquidacionViatico::create([
        'viatico_id' => $viatico->id,
        'facturas' => json_encode([
            ['numero' => '001-001-000000001', 'proveedor' => 'Hotel Manta', 'monto' => 100.00],
        ]),
        'total_facturas' => 100.00,
        'monto_justificado' => 100.00,
        'diferencia_devolver' => 12.00, // 160 - (100 justificado + 48 exento)
        'fecha_retorno' => now()->subDays(2),
        'fecha_liquidacion' => now()->toDateString(),
        'created_by' => $this->servidorUser->id,
    ]);

    $service = new HandoffErpService();
    $handoff = $service->generarHandoffDevengadoViatico($liquidacion->id);

    expect($handoff)->toBeInstanceOf(HandoffErp::class);
    expect($handoff->tipo)->toBe('viatico_devengado');
    expect($handoff->referencia_id)->toBe($liquidacion->id);
    
    // Validar que el archivo existe en el Storage
    Storage::disk('local')->assertExists($handoff->archivo_ruta);

    // Opcional: leer el contenido y verificar nodos
    $contenidoXml = Storage::disk('local')->get($handoff->archivo_ruta);
    $xml = simplexml_load_string($contenidoXml);
    
    expect((string)$xml->TotalFacturas)->toBe('100.00');
    expect((string)$xml->DiferenciaDevolver)->toBe('12.00');
});

test('liquidacion_vence_a_los_5_dias_habiles', function () {
    // Configuramos el tiempo para el lunes 1 de Junio de 2026
    Carbon::setTestNow('2026-06-01 10:00:00');
    
    $fechaFin = Carbon::parse('2026-06-01 10:00:00');

    Viatico::create([
        'servidor_id' => $this->servidor->id,
        'zona' => 'dentro_provincia',
        'tipo' => 'con_pernocte',
        'fecha_inicio' => $fechaFin->copy()->subDays(2),
        'fecha_fin' => $fechaFin, // Lunes
        'justificacion' => 'Comisión en Quito',
        'estado' => EstadoViatico::PENDIENTE_LIQUIDACION->value,
        'monto_calculado' => 160.00,
    ]);

    $service = new ViaticoService();

    // 5 días hábiles a partir de fecha_fin = 1 junio
    // Dia 1 habil = 2 junio (martes)
    // Dia 2 habil = 3 junio (miercoles)
    // Dia 3 habil = 4 junio (jueves)
    // Dia 4 habil = 5 junio (viernes)
    // Fin de semana: sabado 6, domingo 7
    // Dia 5 habil = 8 junio (lunes) -> Este es el ULTIMO dia permitido
    // Dia 6 habil = 9 junio (martes) -> Vencido

    // Probamos el dia 5 habil (Lunes 8 de Junio) - No deberia estar vencido
    Carbon::setTestNow('2026-06-08 10:00:00');
    expect($service->verificarBloqueo($this->servidor->id))->toBeFalse();

    // Probamos el dia 6 habil (Martes 9 de Junio) - Deberia estar vencido (bloqueado)
    Carbon::setTestNow('2026-06-09 10:00:00');
    expect($service->verificarBloqueo($this->servidor->id))->toBeTrue();
});
