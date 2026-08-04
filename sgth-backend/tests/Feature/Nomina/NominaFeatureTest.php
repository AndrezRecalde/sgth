<?php

use App\Enums\EstadoDescuentoRecurrente;
use App\Enums\EstadoNomina;
use App\Enums\RegimenLaboral;
use App\Enums\TipoConcepto;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\Nomina\ConceptoNomina;
use App\Models\Nomina\DescuentoRecurrente;
use App\Models\Handoff\HandoffErp;
use App\Models\User;
use App\Services\Nomina\NominaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Evitar errores de asignación masiva en tests
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();

    Storage::fake('local'); // Para evitar escritura real de XML

    $this->user = User::create([
        'email' => 'admin_'.uniqid().'@example.com',
        'usuario_ti' => 'admin_'.uniqid(),
        'password' => bcrypt('123456'),
        'primer_login' => false,
    ]);

    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'U01_'.uniqid(),
        'nombre' => 'Dirección de Talento Humano',
        'estado' => true,
        'nivel' => 1,
    ]);

    $this->puesto = Puesto::create([
        'codigo' => 'P01_'.uniqid(),
        'denominacion' => 'Analista',
        'unidad_administrativa_id' => $this->unidad->id,
        'grupo_ocupacional' => 'Profesional',
        'grado_rmu' => 10,
        'rmu' => 1000.00,
        'nivel' => 1,
        'estado' => true,
    ]);

    $this->conceptoIess = ConceptoNomina::create([
        'codigo' => 'IESS_PERSONAL',
        'nombre' => 'IESS Personal',
        'tipo' => TipoConcepto::DESCUENTO,
        'porcentaje' => 9.45,
        'activo' => true,
    ]);

    $this->conceptoSueldo = ConceptoNomina::create([
        'codigo' => 'SUELDO_BASE',
        'nombre' => 'Sueldo Base',
        'tipo' => TipoConcepto::INGRESO,
        'activo' => true,
    ]);

    $this->conceptoDecimoTercero = ConceptoNomina::create([
        'codigo' => 'DECIMO_TERCERO',
        'nombre' => 'Décimo Tercero',
        'tipo' => TipoConcepto::INGRESO,
        'activo' => true,
    ]);

    $this->conceptoDecimoCuarto = ConceptoNomina::create([
        'codigo' => 'DECIMO_CUARTO',
        'nombre' => 'Décimo Cuarto',
        'tipo' => TipoConcepto::INGRESO,
        'activo' => true,
    ]);

    $this->conceptoPrestamo = ConceptoNomina::create([
        'codigo' => 'PRESTAMO_IESS',
        'nombre' => 'Préstamo Quirografario',
        'tipo' => TipoConcepto::DESCUENTO,
        'activo' => true,
    ]);
});

test('calculo_iess_correcto_regimen_losep', function () {
    $servidor = Servidor::create([
        'cedula' => '0801234567',
        'nombre' => 'Juan',
        'apellido' => 'Pérez',
        'user_id' => $this->user->id,
        'puesto_id' => $this->puesto->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'estado' => true,
    ]);

    $service = new NominaService();
    $nomina = $service->calcularNomina('2026-05');

    $detalleIess = $nomina->detalles()
        ->where('servidor_id', $servidor->id)
        ->where('concepto_nomina_id', $this->conceptoIess->id)
        ->first();

    // 9.45% de 1000 = 94.50
    expect($detalleIess->valor)->toEqual(94.50);
});

test('calculo_iess_correcto_regimen_codigo_trabajo', function () {
    $servidor = Servidor::create([
        'cedula' => '0801234568',
        'nombre' => 'María',
        'apellido' => 'Gómez',
        'user_id' => $this->user->id,
        'puesto_id' => $this->puesto->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'regimen_laboral' => RegimenLaboral::CODIGO_TRABAJO,
        'estado' => true,
    ]);

    $service = new NominaService();
    $nomina = $service->calcularNomina('2026-06');

    $detalleIess = $nomina->detalles()
        ->where('servidor_id', $servidor->id)
        ->where('concepto_nomina_id', $this->conceptoIess->id)
        ->first();

    // Debe ser calculado sobre el sueldo base
    expect($detalleIess->valor)->toEqual(94.50);
});

test('cierre_nomina_genera_handoff_erp', function () {
    $servidor = Servidor::create([
        'cedula' => '0801234569',
        'nombre' => 'Luis',
        'apellido' => 'Mena',
        'user_id' => $this->user->id,
        'puesto_id' => $this->puesto->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'estado' => true,
    ]);

    $service = new NominaService();
    $nomina = $service->calcularNomina('2026-07');

    $service->cerrarNomina($nomina->id, $this->user->id);

    // Verify state
    $nomina->refresh();
    expect($nomina->estado)->toBe(EstadoNomina::CERRADA);
    
    // Check if handoff was created (job ran synchronously by default)
    $handoff = HandoffErp::where('referencia_id', $nomina->id)
        ->where('tipo', 'nomina')
        ->first();
        
    expect($handoff)->not->toBeNull();
    expect($handoff->hash_integridad)->not->toBeEmpty();
});

test('descuento_recurrente_aplicado_automaticamente', function () {
    $servidor = Servidor::create([
        'cedula' => '0801234570',
        'nombre' => 'Ana',
        'apellido' => 'Vera',
        'user_id' => $this->user->id,
        'puesto_id' => $this->puesto->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'estado' => true,
    ]);

    $descuento = DescuentoRecurrente::create([
        'servidor_id' => $servidor->id,
        'concepto_nomina_id' => $this->conceptoPrestamo->id,
        'estado' => EstadoDescuentoRecurrente::ACTIVO,
        'fecha_inicio' => '2026-01-01',
        'numero_cuotas_total' => 10,
        'numero_cuotas_pagadas' => 0,
        'valor_cuota' => 50.00,
        'registrado_por' => $this->user->id,
    ]);

    $service = new NominaService();
    $nomina = $service->calcularNomina('2026-08');

    $detalle = $nomina->detalles()
        ->where('servidor_id', $servidor->id)
        ->where('concepto_nomina_id', $this->conceptoPrestamo->id)
        ->first();

    expect($detalle)->not->toBeNull();
    expect($detalle->valor)->toEqual(50.00);
    
    $descuento->refresh();
    expect($descuento->numero_cuotas_pagadas)->toBe(1);
    expect($descuento->estado)->toBe(EstadoDescuentoRecurrente::ACTIVO);
});

test('descuento_recurrente_se_completa_al_pagar_ultima_cuota', function () {
    $servidor = Servidor::create([
        'cedula' => '0801234571',
        'nombre' => 'Pedro',
        'apellido' => 'Luna',
        'user_id' => $this->user->id,
        'puesto_id' => $this->puesto->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'estado' => true,
    ]);

    $descuento = DescuentoRecurrente::create([
        'servidor_id' => $servidor->id,
        'concepto_nomina_id' => $this->conceptoPrestamo->id,
        'estado' => EstadoDescuentoRecurrente::ACTIVO,
        'fecha_inicio' => '2026-01-01',
        'numero_cuotas_total' => 10,
        'numero_cuotas_pagadas' => 9, // Falta una
        'valor_cuota' => 50.00,
        'registrado_por' => $this->user->id,
    ]);

    $service = new NominaService();
    $nomina = $service->calcularNomina('2026-09');

    $descuento->refresh();
    expect($descuento->numero_cuotas_pagadas)->toBe(10);
    expect($descuento->estado)->toBe(EstadoDescuentoRecurrente::COMPLETADO);
});
