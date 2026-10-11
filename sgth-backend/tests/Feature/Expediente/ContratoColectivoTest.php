<?php

namespace Tests\Feature\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\InstrumentoActo;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoColectivo;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\SalarioBasico;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Asistencia\PeriodoVacacionService;
use App\Services\Expediente\TerminacionObreroService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| Fase 4.6 del diseño de Acciones de Personal (9.15): el contrato colectivo de
| los obreros y sus cláusulas que cambian cálculos —los días adicionales de
| vacaciones, las bonificaciones por jubilación y por retiro voluntario con los
| topes del Mandato 2—, y el salario básico de cada año. Sin el dictamen del
| Ministerio de Finanzas, el contrato se tiene por inexistente.
*/
beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    Role::firstOrCreate(['name' => 'asistente-uath', 'guard_name' => 'sanctum']);
    permisosDeAccionesPersonal();

    $this->travelTo(Carbon::parse('2026-06-15 12:00'));

    $this->director = User::factory()->create();
    $this->director->assignRole('admin-uath');
    $this->actingAs($this->director, 'sanctum');

    $this->contrato = fn (array $extra = []) => $this->postJson('/api/v1/expediente/contratos-colectivos', [
        'numero'                      => 'CC-GADPE-2025',
        'vigencia_desde'              => '2025-01-01',
        'vigencia_hasta'              => '2026-12-31',
        'dictamen_finanzas_numero'    => 'MEF-2025-0123',
        'dictamen_finanzas_fecha'     => '2025-02-10',
        'dias_adicionales_vacaciones' => 5,
        'bonificacion_jubilacion_sbu' => 7,
        'bonificacion_retiro_sbu'     => 5,
        'incremento_pactado_porcentaje' => 4,
        ...$extra,
    ]);

    $this->unidad = UnidadAdministrativa::create(['codigo' => 'OOPP-C', 'nombre' => 'Obras Públicas C', 'nivel' => 1]);
    $this->puesto = Puesto::create([
        'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 10, 'regimen_laboral' => 'codigo_trabajo',
    ]);
    $this->contador = 0;

    /** Una terminación de obrero ya registrada, con la causal y desde cuándo servía. */
    $this->terminacion = function (string $causal, string $desde): MovimientoPersonal {
        $this->contador++;
        $servidor = Servidor::create([
            'cedula'          => str_pad((string) (8700000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre'          => 'Obrero',
            'apellido'        => 'Colectivo'.$this->contador,
            'regimen_laboral' => 'codigo_trabajo',
        ]);
        $terminacion = MovimientoPersonal::create([
            'servidor_id'         => $servidor->id,
            'tipo_movimiento'     => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
            'subtipo_movimiento'  => $causal,
            'instrumento'         => InstrumentoActo::DOCUMENTO_CT->value,
            'estado'              => EstadoAccionPersonal::REGISTRADA,
            'codigo_registro'     => 'RI-2026-'.str_pad((string) (200 + $this->contador), 4, '0', STR_PAD_LEFT),
            'descripcion'         => 'Terminación',
            'fecha_efectiva'      => '2026-06-15',
            'efecto_aplicado_en'  => now(),
            'remuneracion_origen' => 620,
        ]);
        ContratoServidor::create([
            'servidor_id'              => $servidor->id,
            'tipo_nombramiento'        => TipoNombramiento::CODIGO_TRABAJO->value,
            'unidad_administrativa_id' => $this->unidad->id,
            'puesto_id'                => $this->puesto->id,
            'fecha_inicio'             => $desde,
            'fecha_fin'                => '2026-06-15',
            'remuneracion'             => 620,
            'estado'                   => 'terminado',
            'movimiento_cierre_id'     => $terminacion->id,
        ]);

        return $terminacion;
    };
});

// ── El contrato ─────────────────────────────────────────────────

test('se registra con sus cláusulas, y solo rige el que tiene el dictamen de Finanzas', function () {
    $id = ($this->contrato)(['dictamen_finanzas_numero' => null, 'dictamen_finanzas_fecha' => null])
        ->assertCreated()
        ->assertJsonPath('datos.tiene_dictamen', false)
        ->json('datos.id');

    $this->getJson('/api/v1/expediente/contratos-colectivos')
        ->assertOk()
        ->assertJsonPath('datos.vigente', null)
        ->assertJsonCount(1, 'datos.contratos');

    $this->postJson("/api/v1/expediente/contratos-colectivos/{$id}", [
        'numero'                      => 'CC-GADPE-2025',
        'vigencia_desde'              => '2025-01-01',
        'vigencia_hasta'              => '2026-12-31',
        'dictamen_finanzas_numero'    => 'MEF-2025-0123',
        'dictamen_finanzas_fecha'     => '2025-02-10',
        'dias_adicionales_vacaciones' => 5,
    ])->assertOk();

    $this->getJson('/api/v1/expediente/contratos-colectivos')
        ->assertOk()
        ->assertJsonPath('datos.vigente.id', $id)
        ->assertJsonPath('datos.vigente.dias_adicionales_vacaciones', 5);
});

test('las cláusulas tienen los topes de la ley, y la vigencia su orden', function () {
    ($this->contrato)([
        'bonificacion_jubilacion_sbu' => 8,
        'dias_adicionales_vacaciones' => 31,
        'vigencia_hasta'              => '2024-12-31',
        'dictamen_finanzas_fecha'     => null,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(
            ['bonificacion_jubilacion_sbu', 'dias_adicionales_vacaciones', 'vigencia_hasta', 'dictamen_finanzas_fecha'],
            'errores'
        )
        ->assertJsonPath('errores.bonificacion_jubilacion_sbu.0', fn (string $m) => str_contains($m, 'Mandato Constituyente 2'));
});

test('dos contratos no rigen a la vez', function () {
    ($this->contrato)()->assertCreated();

    ($this->contrato)(['numero' => 'CC-GADPE-2026', 'vigencia_desde' => '2026-06-01', 'vigencia_hasta' => null])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'se cruza') && str_contains($m, 'CC-GADPE-2025'));

    ($this->contrato)(['numero' => 'CC-GADPE-2027', 'vigencia_desde' => '2027-01-01', 'vigencia_hasta' => null])
        ->assertCreated();
});

test('el PDF se adjunta y se descarga', function () {
    Storage::fake('local');

    $id = ($this->contrato)(['archivo' => UploadedFile::fake()->create('contrato.pdf', 200, 'application/pdf')])
        ->assertCreated()
        ->assertJsonPath('datos.tiene_documento', true)
        ->assertJsonMissingPath('datos.documento_ruta')
        ->json('datos.id');

    $this->get("/api/v1/expediente/contratos-colectivos/{$id}/documento")->assertOk();
});

test('el asistente lo consulta, pero lo carga el director', function () {
    $asistente = User::factory()->create();
    $asistente->assignRole('asistente-uath');
    $this->actingAs($asistente, 'sanctum');

    $this->getJson('/api/v1/expediente/contratos-colectivos')->assertOk();
    ($this->contrato)()->assertForbidden();
    $this->postJson('/api/v1/expediente/salarios-basicos', ['anio' => 2026, 'monto' => 482])->assertForbidden();
});

// ── El salario básico ───────────────────────────────────────────

test('el salario básico se registra por año, y se corrige', function () {
    $this->postJson('/api/v1/expediente/salarios-basicos', ['anio' => 2026, 'monto' => 470, 'acuerdo' => 'MDT-2025-200'])
        ->assertOk();
    $this->postJson('/api/v1/expediente/salarios-basicos', ['anio' => 2026, 'monto' => 482])
        ->assertOk()
        ->assertJsonPath('datos.monto', '482.00');

    expect(SalarioBasico::count())->toBe(1)
        ->and(SalarioBasico::delAnio(2026))->toBe(482.0)
        ->and(SalarioBasico::delAnio(2025))->toBeNull();
});

// ── Lo que cambia ───────────────────────────────────────────────

test('los días adicionales se suman a las vacaciones del obrero, no a las de la LOSEP', function () {
    $periodos = app(PeriodoVacacionService::class);

    // Sin contrato con dictamen, la ley sola: 15 + 1 por el sexto año.
    expect($periodos->calcularDiasGenerados('codigo_trabajo', 6, 2026))->toBe(16.0);

    ($this->contrato)()->assertCreated();

    expect($periodos->calcularDiasGenerados('codigo_trabajo', 6, 2026))->toBe(21.0)
        ->and($periodos->calcularDiasGenerados('losep', 6, 2026))->toBe(30.0)
        // En un año que el contrato no cubre, la ley sola.
        ->and($periodos->calcularDiasGenerados('codigo_trabajo', 6, 2024))->toBe(16.0);
});

test('la jubilación y el retiro pagan la bonificación pactada, con el tope del Mandato 2', function () {
    $liquidacion = fn (MovimientoPersonal $m) => app(TerminacionObreroService::class)->liquidacion($m);

    $jubilacion = ($this->terminacion)('jubilacion_ct', '2001-06-16');
    $retiro = ($this->terminacion)('retiro_voluntario_ct', '1986-06-16');

    // Sin contrato colectivo, nada que el sistema calcule.
    expect($liquidacion($jubilacion))->toBeNull();

    ($this->contrato)()->assertCreated();

    // Sin el salario básico del año, el monto queda pendiente.
    expect($liquidacion($jubilacion)['rubros'][0]['monto'])->toBeNull()
        ->and($liquidacion($jubilacion)['rubros'][0]['detalle'])->toContain('sin el salario básico de 2026');

    SalarioBasico::create(['anio' => 2026, 'monto' => 482]);

    // 25 años, con los bisiestos 25,02: 7 × 25,02 = 175,14 salarios básicos.
    $deJubilacion = $liquidacion($jubilacion);
    expect($deJubilacion['anios_servicio'])->toBe(25.02)
        ->and($deJubilacion['rubros'])->toHaveCount(1)
        ->and($deJubilacion['rubros'][0]['concepto'])->toBe('Bonificación por jubilación')
        ->and($deJubilacion['rubros'][0]['base_legal'])->toContain('CC-GADPE-2025')
        ->and($deJubilacion['total'])->toBe(round(175.14 * 482, 2));

    // 40,03 años: 5 × 40,03 = 200,15, por debajo del tope; con 7 por año
    // serían 280,21, y el Mandato 2 lo corta en 210.
    expect($liquidacion($retiro)['anios_servicio'])->toBe(40.03)
        ->and($liquidacion($retiro)['total'])->toBe(round(200.15 * 482, 2));

    ContratoColectivo::query()->update(['bonificacion_retiro_sbu' => 7]);
    expect($liquidacion($retiro)['rubros'][0]['detalle'])->toContain('(el tope)')
        ->and($liquidacion($retiro)['total'])->toBe(210 * 482.0);
});
