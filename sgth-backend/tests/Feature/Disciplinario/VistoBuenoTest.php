<?php

namespace Tests\Feature\Disciplinario;

use App\Enums\CausalVistoBueno;
use App\Enums\EstadoAccionPersonal;
use App\Enums\EstadoSumario;
use App\Enums\EstadoVistoBueno;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Enums\TipoSancion;
use App\Exceptions\ReglaNegocioException;
use App\Models\Disciplinario\Sumario;
use App\Models\Disciplinario\VistoBueno;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Disciplinario\DisciplinarioService;
use App\Services\Disciplinario\VistoBuenoService;
use App\Services\Expediente\MovimientoPersonalStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);

    $this->user = User::factory()->create();
    $this->user->assignRole('admin-uath');
    $this->actingAs($this->user, 'sanctum');

    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'UATH-01', 'nombre' => 'Unidad de Talento Humano', 'nivel' => 1,
    ]);

    $this->puesto = Puesto::create([
        'codigo' => 'P-VB', 'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 10,
    ]);

    $this->vistoBuenoService   = app(VistoBuenoService::class);
    $this->disciplinarioService = app(DisciplinarioService::class);
    $this->stateService        = app(MovimientoPersonalStateService::class);

    $this->contador = 0;

    $this->servidorCon = function (TipoNombramiento $nombramiento): Servidor {
        $this->contador++;

        $servidor = Servidor::create([
            'user_id'                   => User::factory()->create()->id,
            'cedula'                    => str_pad((string) (6000000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre'                    => 'Trabajador',
            'apellido'                  => 'Prueba'.$this->contador,
            'regimen_laboral'           => $nombramiento === TipoNombramiento::CODIGO_TRABAJO ? 'codigo_trabajo' : 'losep',
            'puesto_id'                 => $this->puesto->id,
            'unidad_administrativa_id'  => $this->unidad->id,
            'fecha_ingreso_institucion' => '2018-01-01',
        ]);

        ContratoServidor::create([
            'servidor_id'              => $servidor->id,
            'tipo_nombramiento'        => $nombramiento->value,
            'unidad_administrativa_id' => $this->unidad->id,
            'puesto_id'                => $this->puesto->id,
            'fecha_inicio'             => '2018-01-01',
            'estado'                   => 'vigente',
        ]);

        return $servidor->fresh('contratoVigente');
    };

    $this->solicitarVistoBueno = function (Servidor $servidor): VistoBueno {
        return $this->vistoBuenoService->solicitar($servidor->id, [
            'causal'          => CausalVistoBueno::INDISCIPLINA_DESOBEDIENCIA->value,
            'hechos'          => 'Desobediencia reiterada a los reglamentos internos.',
            'fecha_solicitud' => now()->toDateString(),
        ], $this->user->id);
    };
});

// ── Separación de regímenes ─────────────────────────────────────

test('no se puede destituir a un obrero por sumario administrativo', function () {
    $obrero = ($this->servidorCon)(TipoNombramiento::CODIGO_TRABAJO);

    $sumario = Sumario::create([
        'servidor_id'    => $obrero->id,
        'motivo'         => 'Indisciplina',
        'estado'         => EstadoSumario::CON_INFORME,
        'fecha_apertura' => now()->subMonth()->toDateString(),
        'notificado_sn'  => true,
    ]);

    expect(fn () => $this->disciplinarioService->resolverSumario($sumario->id, [
        'tipo_falta'     => 'grave',
        'tipo_sancion'   => TipoSancion::DESTITUCION->value,
        'fecha_efectiva' => now()->toDateString(),
    ], $this->user->id))
        ->toThrow(ReglaNegocioException::class, 'visto bueno ante el Inspector del Trabajo');

    // El sumario no quedó a medio resolver.
    expect($sumario->fresh()->estado)->toBe(EstadoSumario::CON_INFORME);
});

test('a un obrero sí se le puede aplicar una sanción menor por sumario', function () {
    $obrero = ($this->servidorCon)(TipoNombramiento::CODIGO_TRABAJO);

    $sumario = Sumario::create([
        'servidor_id'    => $obrero->id,
        'motivo'         => 'Atraso reiterado',
        'estado'         => EstadoSumario::CON_INFORME,
        'fecha_apertura' => now()->subMonth()->toDateString(),
        'notificado_sn'  => true,
    ]);

    $resuelto = $this->disciplinarioService->resolverSumario($sumario->id, [
        'tipo_falta'     => 'leve',
        'tipo_sancion'   => TipoSancion::AMONESTACION_ESCRITA->value,
        'fecha_efectiva' => now()->toDateString(),
    ], $this->user->id);

    expect($resuelto->estado)->toBe(EstadoSumario::RESUELTO);
});

test('no se puede abrir un sumario administrativo a un obrero', function () {
    $obrero = ($this->servidorCon)(TipoNombramiento::CODIGO_TRABAJO);

    expect(fn () => $this->disciplinarioService->abrirSumario($obrero->id, [
        'motivo' => 'Indisciplina',
    ], $this->user->id))
        ->toThrow(ReglaNegocioException::class, 'visto bueno ante el Inspector del Trabajo');
});

test('el visto bueno no aplica a un servidor LOSEP', function () {
    $permanente = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    expect(fn () => ($this->solicitarVistoBueno)($permanente))
        ->toThrow(ReglaNegocioException::class, 'solo aplica a obreros bajo Código del Trabajo');
});

test('no se puede abrir un segundo visto bueno mientras haya uno en curso', function () {
    $obrero = ($this->servidorCon)(TipoNombramiento::CODIGO_TRABAJO);

    ($this->solicitarVistoBueno)($obrero);

    expect(fn () => ($this->solicitarVistoBueno)($obrero))
        ->toThrow(ReglaNegocioException::class, 'ya tiene un trámite de visto bueno en curso');
});

// ── Máquina de estados del trámite ──────────────────────────────

test('el trámite nace solicitado', function () {
    $obrero = ($this->servidorCon)(TipoNombramiento::CODIGO_TRABAJO);

    $tramite = ($this->solicitarVistoBueno)($obrero);

    expect($tramite->estado)->toBe(EstadoVistoBueno::SOLICITADO)
        ->and($tramite->causal)->toBe(CausalVistoBueno::INDISCIPLINA_DESOBEDIENCIA)
        ->and($tramite->movimiento_personal_id)->toBeNull();
});

test('no se puede saltar de solicitado directo a concedido', function () {
    $obrero  = ($this->servidorCon)(TipoNombramiento::CODIGO_TRABAJO);
    $tramite = ($this->solicitarVistoBueno)($obrero);

    expect(fn () => $this->vistoBuenoService->transicionar(
        $tramite,
        EstadoVistoBueno::CONCEDIDO,
        ['resolucion_detalle' => 'Concedido'],
        $this->user->id
    ))->toThrow(ReglaNegocioException::class, "No se puede pasar de 'Solicitado' a 'Concedido'");
});

test('resolver sin detalle de la resolución del inspector es rechazado', function () {
    $obrero  = ($this->servidorCon)(TipoNombramiento::CODIGO_TRABAJO);
    $tramite = ($this->solicitarVistoBueno)($obrero);

    $tramite = $this->vistoBuenoService->transicionar($tramite, EstadoVistoBueno::NOTIFICADO, [], $this->user->id);
    $tramite = $this->vistoBuenoService->transicionar($tramite, EstadoVistoBueno::EN_INVESTIGACION, [], $this->user->id);

    expect(fn () => $this->vistoBuenoService->transicionar(
        $tramite,
        EstadoVistoBueno::CONCEDIDO,
        [],
        $this->user->id
    ))->toThrow(ReglaNegocioException::class, 'detalle de la resolución');
});

// ── Enganche con la acción de personal ──────────────────────────

test('conceder el visto bueno genera la cesación en borrador sin cerrar el vínculo', function () {
    $obrero  = ($this->servidorCon)(TipoNombramiento::CODIGO_TRABAJO);
    $tramite = ($this->solicitarVistoBueno)($obrero);

    $tramite = $this->vistoBuenoService->transicionar($tramite, EstadoVistoBueno::NOTIFICADO, [
        'numero_tramite_mdt' => 'MDT-VB-2026-0042',
    ], $this->user->id);
    $tramite = $this->vistoBuenoService->transicionar($tramite, EstadoVistoBueno::EN_INVESTIGACION, [], $this->user->id);

    $tramite = $this->vistoBuenoService->transicionar($tramite, EstadoVistoBueno::CONCEDIDO, [
        'resolucion_detalle' => 'El Inspector concede el visto bueno solicitado.',
        'fecha_resolucion'   => now()->toDateString(),
    ], $this->user->id);

    $movimiento = $tramite->movimientoPersonal;

    expect($tramite->estado)->toBe(EstadoVistoBueno::CONCEDIDO)
        ->and($movimiento)->not->toBeNull()
        ->and($movimiento->tipo_movimiento)->toBe(TipoMovimientoPersonal::CESACION_FUNCIONES)
        ->and($movimiento->subtipo_movimiento)->toBe(SubtipoMovimientoPersonal::VISTO_BUENO)
        ->and($movimiento->estado)->toBe(EstadoAccionPersonal::BORRADOR)
        ->and($movimiento->resolucion_numero)->toBe('MDT-VB-2026-0042')
        ->and($movimiento->descripcion)->toContain('Art. 172 núm. 2');

    // El vínculo sigue vigente: lo cierra la acción de personal al registrarse,
    // no el trámite.
    expect($obrero->fresh()->contratoVigente)->not->toBeNull();
});

test('registrar la cesación generada cierra el vínculo del obrero', function () {
    $obrero  = ($this->servidorCon)(TipoNombramiento::CODIGO_TRABAJO);
    $tramite = ($this->solicitarVistoBueno)($obrero);

    $tramite = $this->vistoBuenoService->transicionar($tramite, EstadoVistoBueno::NOTIFICADO, [], $this->user->id);
    $tramite = $this->vistoBuenoService->transicionar($tramite, EstadoVistoBueno::EN_INVESTIGACION, [], $this->user->id);
    $tramite = $this->vistoBuenoService->transicionar($tramite, EstadoVistoBueno::CONCEDIDO, [
        'resolucion_detalle' => 'Concedido.',
    ], $this->user->id);

    $movimiento = $tramite->movimientoPersonal;
    $movimiento = $this->stateService->transicionar($movimiento, EstadoAccionPersonal::SUSCRITA);
    $this->stateService->transicionar($movimiento->fresh(), EstadoAccionPersonal::REGISTRADA);

    $contrato = ContratoServidor::where('servidor_id', $obrero->id)->first();

    expect($obrero->fresh()->contratoVigente)->toBeNull()
        ->and($contrato->estado->value)->toBe('terminado')
        ->and($contrato->motivo_fin)->toContain('Visto Bueno');
});

test('negar el visto bueno no genera ninguna acción de personal', function () {
    $obrero  = ($this->servidorCon)(TipoNombramiento::CODIGO_TRABAJO);
    $tramite = ($this->solicitarVistoBueno)($obrero);

    $tramite = $this->vistoBuenoService->transicionar($tramite, EstadoVistoBueno::NOTIFICADO, [], $this->user->id);
    $tramite = $this->vistoBuenoService->transicionar($tramite, EstadoVistoBueno::EN_INVESTIGACION, [], $this->user->id);
    $tramite = $this->vistoBuenoService->transicionar($tramite, EstadoVistoBueno::NEGADO, [
        'resolucion_detalle' => 'El Inspector niega el visto bueno por falta de prueba.',
    ], $this->user->id);

    expect($tramite->estado)->toBe(EstadoVistoBueno::NEGADO)
        ->and($tramite->movimiento_personal_id)->toBeNull()
        ->and($obrero->fresh()->contratoVigente)->not->toBeNull();
});

// ── Elegibilidad del subtipo ────────────────────────────────────

test('el subtipo visto bueno es exclusivo de Código del Trabajo', function () {
    foreach (TipoNombramiento::cases() as $nombramiento) {
        expect(SubtipoMovimientoPersonal::VISTO_BUENO->elegiblePara($nombramiento))
            ->toBe($nombramiento === TipoNombramiento::CODIGO_TRABAJO, "Nombramiento '{$nombramiento->value}'");
    }
});

// ── Control de plazos ───────────────────────────────────────────

test('un trámite sin notificar dentro del plazo aparece en las alertas', function () {
    $obrero = ($this->servidorCon)(TipoNombramiento::CODIGO_TRABAJO);

    $tramite = $this->vistoBuenoService->solicitar($obrero->id, [
        'causal'          => CausalVistoBueno::FALTA_PROBIDAD->value,
        'hechos'          => 'Hechos.',
        'fecha_solicitud' => now()->subDays(15)->toDateString(),
    ], $this->user->id);

    $alertas = $this->vistoBuenoService->controlarPlazosLegales();

    expect($alertas)->toHaveCount(1)
        ->and($alertas[0]['visto_bueno_id'])->toBe($tramite->id)
        ->and($alertas[0]['plazo'])->toBe('notificacion');
});

test('un trámite recién solicitado no genera alerta', function () {
    $obrero = ($this->servidorCon)(TipoNombramiento::CODIGO_TRABAJO);

    ($this->solicitarVistoBueno)($obrero);

    expect($this->vistoBuenoService->controlarPlazosLegales())->toBeEmpty();
});

// ── Sumario: secuencia procesal ─────────────────────────────────

test('el sumario avanza hito por hito y registra sus fechas', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    $sumario = $this->disciplinarioService->abrirSumario($servidor->id, [
        'motivo' => 'Presunta falta grave',
    ], $this->user->id);

    expect($sumario->estado)->toBe(EstadoSumario::ABIERTO)
        ->and($sumario->notificado_sn)->toBeFalse();

    $sumario = $this->disciplinarioService->avanzarSumario(
        $sumario, EstadoSumario::EN_INSTRUCCION->value, [], $this->user->id
    );

    expect($sumario->notificado_sn)->toBeTrue()
        ->and($sumario->fecha_notificacion)->not->toBeNull();

    $sumario = $this->disciplinarioService->avanzarSumario(
        $sumario, EstadoSumario::EN_PRUEBA->value, [], $this->user->id
    );
    $sumario = $this->disciplinarioService->avanzarSumario(
        $sumario, EstadoSumario::CON_INFORME->value, [], $this->user->id
    );

    expect($sumario->estado)->toBe(EstadoSumario::CON_INFORME)
        ->and($sumario->fecha_informe)->not->toBeNull();
});

test('el sumario no puede saltarse hitos procesales', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    $sumario = $this->disciplinarioService->abrirSumario($servidor->id, [
        'motivo' => 'Presunta falta grave',
    ], $this->user->id);

    expect(fn () => $this->disciplinarioService->avanzarSumario(
        $sumario, EstadoSumario::CON_INFORME->value, [], $this->user->id
    ))->toThrow(ReglaNegocioException::class, 'No se puede pasar de');
});

test('no se puede abrir un segundo sumario mientras haya uno en curso', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    $this->disciplinarioService->abrirSumario($servidor->id, ['motivo' => 'Primero'], $this->user->id);

    expect(fn () => $this->disciplinarioService->abrirSumario(
        $servidor->id, ['motivo' => 'Segundo'], $this->user->id
    ))->toThrow(ReglaNegocioException::class, 'ya tiene un sumario administrativo en curso');
});

test('la destitución por sumario genera la cesación con subtipo destitución', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    $sumario = Sumario::create([
        'servidor_id'    => $servidor->id,
        'motivo'         => 'Falta grave',
        'estado'         => EstadoSumario::CON_INFORME,
        'fecha_apertura' => now()->subMonth()->toDateString(),
        'notificado_sn'  => true,
    ]);

    $this->disciplinarioService->resolverSumario($sumario->id, [
        'tipo_falta'     => 'grave',
        'tipo_sancion'   => TipoSancion::DESTITUCION->value,
        'fecha_efectiva' => now()->toDateString(),
    ], $this->user->id);

    $movimiento = \App\Models\Expediente\MovimientoPersonal::where('servidor_id', $servidor->id)->latest('id')->first();

    expect($movimiento->tipo_movimiento)->toBe(TipoMovimientoPersonal::CESACION_FUNCIONES)
        ->and($movimiento->subtipo_movimiento)->toBe(SubtipoMovimientoPersonal::DESTITUCION)
        ->and($movimiento->estado)->toBe(EstadoAccionPersonal::BORRADOR);
});
