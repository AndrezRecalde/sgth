<?php

use App\Contracts\Nomina\NominaServiceInterface;
use App\Enums\CategoriaEventoVinculo;
use App\Enums\EstadoAccionPersonal;
use App\Enums\EstadoDescuentoRecurrente;
use App\Enums\EstadoNomina;
use App\Enums\TipoConcepto;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\SalarioBasico;
use App\Models\Expediente\Servidor;
use App\Models\Handoff\HandoffErp;
use App\Models\Nomina\ConceptoNomina;
use App\Models\Nomina\DescuentoRecurrente;
use App\Models\Nomina\Nomina;
use App\Models\Nomina\RolPago;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
| La nómina del SGTH (fase 7.3c del diseño de Acciones de Personal): la base
| sale del vínculo —sus días en el mes de treinta, por tramos según cada cambio
| de remuneración—, aplica las novedades del mes y es referencial mientras dura
| la corrida en paralelo con la de Financiero.
|
| Hoy es el miércoles 20 de mayo de 2026; la nómina es la de mayo.
*/
beforeEach(function () {
    Storage::fake('local'); // Para evitar escritura real de XML
    $this->travelTo(Carbon::parse('2026-05-20 10:00'));

    $this->user = User::factory()->create();

    $this->unidad = unidadDePrueba(['nombre' => 'Dirección de Talento Humano']);
    // puestoDePrueba lo pone en el grado SP10, con RMU de $1.000: la nómina ya
    // no la lee.
    $this->puesto = puestoDePrueba($this->unidad, 'Analista', ['plazas' => 20]);

    foreach ([
        ['IESS_PERSONAL', 'IESS Personal', TipoConcepto::DESCUENTO, 9.45],
        ['SUELDO_BASE', 'Sueldo Base', TipoConcepto::INGRESO, null],
        ['DECIMO_TERCERO', 'Décimo Tercero', TipoConcepto::INGRESO, null],
        ['DECIMO_CUARTO', 'Décimo Cuarto', TipoConcepto::INGRESO, null],
        ['PRESTAMO_IESS', 'Préstamo Quirografario', TipoConcepto::DESCUENTO, null],
    ] as [$codigo, $nombre, $tipo, $porcentaje]) {
        ConceptoNomina::create(['codigo' => $codigo, 'nombre' => $nombre, 'tipo' => $tipo, 'porcentaje' => $porcentaje, 'activo' => true]);
    }

    $this->contador = 0;
    $this->servidor = function (array $contrato = [], bool $conContrato = true): Servidor {
        $this->contador++;
        $servidor = Servidor::create([
            'cedula' => str_pad((string) (7500000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre' => 'Servidor', 'apellido' => 'Nomina'.$this->contador, 'regimen_laboral' => 'losep',
            'puesto_id' => $this->puesto->id, 'unidad_administrativa_id' => $this->unidad->id, 'estado' => true,
        ]);

        if ($conContrato) {
            ContratoServidor::create([
                'servidor_id' => $servidor->id, 'tipo_nombramiento' => 'nombramiento_permanente',
                'unidad_administrativa_id' => $this->unidad->id, 'puesto_id' => $this->puesto->id,
                'fecha_inicio' => '2018-01-02', 'estado' => 'vigente', 'remuneracion' => 1200,
                ...$contrato,
            ]);
        }

        return $servidor;
    };

    $this->acto = fn (Servidor $s, array $datos) => MovimientoPersonal::create([
        'servidor_id' => $s->id, 'categoria' => CategoriaEventoVinculo::ACCION_DE_PERSONAL,
        'estado' => EstadoAccionPersonal::REGISTRADA, 'descripcion' => 'Acto de prueba para la nómina.',
        'codigo_registro' => 'AP-2026-'.str_pad((string) (++$this->contador), 4, '0', STR_PAD_LEFT),
        'autorizado_por' => $this->user->id, 'efecto_aplicado_en' => now(),
        ...$datos,
    ]);

    $this->calcular = fn (string $periodo = '2026-05') => app(NominaServiceInterface::class)->calcularNomina($periodo);

    /** Lo que el rol de un servidor tiene en un concepto. */
    $this->valor = fn (Nomina $n, Servidor $s, string $codigo) => (float) $n->detalles()
        ->where('servidor_id', $s->id)
        ->whereHas('concepto', fn ($q) => $q->where('codigo', $codigo))
        ->sum('valor');
});

// ── La base ─────────────────────────────────────────────────────

test('paga la remuneración del vínculo por sus días, no la RMU del puesto, y sin Servicios Profesionales', function () {
    $antiguo = ($this->servidor)();
    $nuevo = ($this->servidor)(['fecha_inicio' => '2026-05-16', 'remuneracion' => 900]);
    ($this->servidor)(['tipo_nombramiento' => 'servicios_profesionales', 'fecha_fin' => '2026-12-31', 'remuneracion' => 2000]);
    ($this->servidor)([], conContrato: false);

    $nomina = ($this->calcular)();

    expect($nomina->referencial)->toBeTrue()
        ->and($nomina->rolesPago()->count())->toBe(2)
        ->and(($this->valor)($nomina, $antiguo, 'SUELDO_BASE'))->toBe(1200.0)
        ->and(($this->valor)($nomina, $nuevo, 'SUELDO_BASE'))->toBe(450.0)
        ->and(RolPago::where('servidor_id', $nuevo->id)->value('dias'))->toBe(15)
        // 9,45 % de lo aportable.
        ->and(($this->valor)($nomina, $antiguo, 'IESS_PERSONAL'))->toBe(113.4);
});

test('el obrero cobra los décimos, con el salario básico del año', function () {
    $obrero = ($this->servidor)(['tipo_nombramiento' => 'codigo_trabajo', 'remuneracion' => 482]);
    SalarioBasico::create(['anio' => 2026, 'monto' => 482]);

    $nomina = ($this->calcular)();

    expect(($this->valor)($nomina, $obrero, 'DECIMO_TERCERO'))->toBe(40.17)
        ->and(($this->valor)($nomina, $obrero, 'DECIMO_CUARTO'))->toBe(40.17);
});

// ── Los cambios y las novedades ─────────────────────────────────

test('el cambio a mitad de mes va por tramos, y el registrado tarde, como retroactivo', function () {
    $tramos = ($this->servidor)();
    ($this->acto)($tramos, [
        'tipo_movimiento' => 'revision_clasificacion', 'fecha_efectiva' => '2026-05-16', 'fecha_registro' => '2026-05-10',
        'remuneracion_origen' => 1000, 'remuneracion_propuesta' => 1200,
    ]);

    $tarde = ($this->servidor)(['remuneracion' => 1100]);
    ($this->acto)($tarde, [
        'tipo_movimiento' => 'revision_clasificacion', 'fecha_efectiva' => '2026-03-01', 'fecha_registro' => '2026-05-05',
        'remuneracion_origen' => 1000, 'remuneracion_propuesta' => 1100,
    ]);

    $nomina = ($this->calcular)();

    // 15 días a $1.000 y 15 a $1.200.
    expect(($this->valor)($nomina, $tramos, 'SUELDO_BASE'))->toBe(1100.0)
        // Mayo ya va con la nueva; marzo y abril se deben: $100 cada uno.
        ->and(($this->valor)($nomina, $tarde, 'SUELDO_BASE'))->toBe(1100.0)
        ->and(($this->valor)($nomina, $tarde, 'RETROACTIVO'))->toBe(200.0);
});

test('aplica las novedades del mes: los días sin remuneración, la subrogación y su IESS', function () {
    $servidor = ($this->servidor)();
    ($this->acto)($servidor, [
        'tipo_movimiento' => 'licencia_sin_remuneracion', 'subtipo_movimiento' => 'asuntos_particulares',
        'fecha_efectiva' => '2026-05-11', 'fecha_inicio' => '2026-05-11', 'fecha_fin' => '2026-05-20',
        'remuneracion_origen' => 1200,
    ]);

    $nomina = ($this->calcular)();

    expect(($this->valor)($nomina, $servidor, 'SUELDO_BASE'))->toBe(1200.0)
        ->and(($this->valor)($nomina, $servidor, 'DIAS_SIN_REMUNERACION'))->toBe(400.0)
        // El IESS, sobre los $800 que se pagan.
        ->and(($this->valor)($nomina, $servidor, 'IESS_PERSONAL'))->toBe(75.6)
        ->and((float) RolPago::where('servidor_id', $servidor->id)->value('total_neto'))->toBe(724.4);
});

// ── Las cuotas y el cierre ──────────────────────────────────────

test('recalcular no vuelve a sumar la cuota: se cuenta al cerrar, una vez', function () {
    $servidor = ($this->servidor)();
    $prestamo = ConceptoNomina::where('codigo', 'PRESTAMO_IESS')->sole();
    $descuento = DescuentoRecurrente::create([
        'servidor_id' => $servidor->id, 'concepto_nomina_id' => $prestamo->id, 'estado' => EstadoDescuentoRecurrente::ACTIVO,
        'fecha_inicio' => '2026-01-01', 'numero_cuotas_total' => 10, 'numero_cuotas_pagadas' => 9, 'valor_cuota' => 50,
        'registrado_por' => $this->user->id,
    ]);

    ($this->calcular)();
    $nomina = ($this->calcular)();

    expect($descuento->fresh()->numero_cuotas_pagadas)->toBe(9)
        ->and(($this->valor)($nomina, $servidor, 'PRESTAMO_IESS'))->toBe(50.0)
        ->and($nomina->detalles()->whereNotNull('descuento_recurrente_id')->value('observacion'))->toStartWith('Cuota 10 de 10');

    app(NominaServiceInterface::class)->cerrarNomina($nomina->id, $this->user->id);

    expect($descuento->fresh()->numero_cuotas_pagadas)->toBe(10)
        ->and($descuento->fresh()->estado)->toBe(EstadoDescuentoRecurrente::COMPLETADO)
        ->and(fn () => ($this->calcular)())->toThrow(\App\Exceptions\ReglaNegocioException::class);
});

test('la referencial se cierra sin salir al ERP; la que ya no lo es, sí', function () {
    ($this->servidor)();

    $referencial = ($this->calcular)();
    app(NominaServiceInterface::class)->cerrarNomina($referencial->id, $this->user->id);

    expect($referencial->fresh()->estado)->toBe(EstadoNomina::CERRADA)
        ->and(HandoffErp::where('referencia_id', $referencial->id)->exists())->toBeFalse();

    config(['nomina.referencial' => false]);
    $deVerdad = ($this->calcular)('2026-06');
    app(NominaServiceInterface::class)->cerrarNomina($deVerdad->id, $this->user->id);

    $handoff = HandoffErp::where('referencia_id', $deVerdad->id)->where('tipo', 'nomina')->first();
    expect($deVerdad->referencial)->toBeFalse()
        ->and($handoff)->not->toBeNull()
        ->and($handoff->hash_integridad)->not->toBeEmpty();
});

// ── Quién ───────────────────────────────────────────────────────

test('el asistente la calcula y no la cierra; el servidor ve su rol solo de una cerrada que no es referencial', function () {
    foreach (['ver-nomina-todas', 'procesar-nomina', 'cerrar-nomina'] as $permiso) {
        Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'sanctum']);
    }
    $asistente = User::factory()->create();
    $asistente->assignRole(Role::firstOrCreate(['name' => 'asistente-uath', 'guard_name' => 'sanctum'])
        ->givePermissionTo(['ver-nomina-todas', 'procesar-nomina']));
    $servidor = ($this->servidor)();

    $this->actingAs($asistente, 'sanctum');
    $id = $this->postJson('/api/v1/nomina', ['periodo' => '2026-05'])
        ->assertOk()
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'referencial'))
        ->json('datos.id');
    $this->postJson("/api/v1/nomina/{$id}/cerrar")->assertForbidden();
    $this->get("/api/v1/nomina/{$id}/excel")->assertOk()->assertDownload('nomina_2026-05.xlsx');

    $propio = User::factory()->create(['servidor_id' => $servidor->id]);
    $this->actingAs($propio, 'sanctum');
    $this->getJson("/api/v1/nomina/{$id}/rol-pago/{$servidor->id}")->assertForbidden();
    $this->getJson('/api/v1/autoservicio/mis-roles-pago')->assertOk()->assertJsonPath('datos', []);

    Nomina::whereKey($id)->update(['referencial' => false, 'estado' => EstadoNomina::CERRADA]);
    $this->getJson("/api/v1/nomina/{$id}/rol-pago/{$servidor->id}")->assertOk();
});
