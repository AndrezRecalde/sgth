<?php

namespace Tests\Feature\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Expediente\MovimientoPersonalService;
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

    $this->puestoActual = Puesto::create([
        'codigo' => 'P-ACTUAL', 'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 5,
    ]);

    $this->puestoNuevo = Puesto::create([
        'codigo' => 'P-NUEVO', 'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 5,
    ]);

    $this->service      = app(MovimientoPersonalService::class);
    $this->stateService = app(MovimientoPersonalStateService::class);

    $this->contador = 0;

    $this->servidorVinculado = function (
        TipoNombramiento $nombramiento = TipoNombramiento::PERMANENTE
    ): Servidor {
        $this->contador++;

        $servidor = Servidor::create([
            'user_id'                   => User::factory()->create()->id,
            'cedula'                    => str_pad((string) (8000000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre'                    => 'Servidor',
            'apellido'                  => 'Vinculado'.$this->contador,
            'regimen_laboral'           => 'losep',
            'puesto_id'                 => $this->puestoActual->id,
            'unidad_administrativa_id'  => $this->unidad->id,
            'fecha_ingreso_institucion' => '2018-01-01',
        ]);

        ContratoServidor::create([
            'servidor_id'              => $servidor->id,
            'tipo_nombramiento'        => $nombramiento->value,
            'unidad_administrativa_id' => $this->unidad->id,
            'puesto_id'                => $this->puestoActual->id,
            'fecha_inicio'             => '2018-01-01',
            'estado'                   => 'vigente',
        ]);

        return $servidor->fresh('contratoVigente');
    };

    /** Lleva una acción de BORRADOR a REGISTRADA. */
    $this->registrar = function (MovimientoPersonal $m): MovimientoPersonal {
        $m = $this->stateService->transicionar($m, EstadoAccionPersonal::SUSCRITA);

        return $this->stateService->transicionar($m->fresh(), EstadoAccionPersonal::REGISTRADA);
    };
});

// ── La cesación cierra el vínculo ───────────────────────────────

test('registrar una cesación de funciones cierra el contrato vigente', function () {
    $servidor = ($this->servidorVinculado)();

    $cesacion = $this->service->registrar($servidor->id, [
        'tipo_movimiento'    => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::RENUNCIA->value,
        'descripcion'        => 'Renuncia voluntaria',
        'fecha_efectiva'     => '2026-08-01',
    ]);

    expect($servidor->fresh()->contratoVigente)->not->toBeNull();

    ($this->registrar)($cesacion);

    $servidor = $servidor->fresh(['contratoVigente']);
    $contrato = ContratoServidor::where('servidor_id', $servidor->id)->first();

    expect($servidor->contratoVigente)->toBeNull()
        ->and($contrato->estado->value)->toBe('terminado')
        ->and($contrato->fecha_fin->toDateString())->toBe('2026-08-01')
        ->and($contrato->motivo_fin)->toContain('Renuncia');
});

test('una cesación sin vínculo vigente que cesar es rechazada', function () {
    $servidor = Servidor::create([
        'user_id'                   => User::factory()->create()->id,
        'cedula'                    => '7000000001',
        'nombre'                    => 'Sin',
        'apellido'                  => 'Vinculo',
        'regimen_laboral'           => 'losep',
        'fecha_ingreso_institucion' => '2018-01-01',
    ]);

    // Sin contrato vigente no hay tipo de nombramiento contra el que evaluar
    // la elegibilidad, así que se construye la acción directamente.
    $cesacion = MovimientoPersonal::create([
        'servidor_id'        => $servidor->id,
        'tipo_movimiento'    => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::RENUNCIA->value,
        'estado'             => EstadoAccionPersonal::SUSCRITA,
        'descripcion'        => 'Renuncia sin vínculo',
        'fecha_efectiva'     => '2026-08-01',
        'autorizado_por'     => $this->user->id,
    ]);

    expect(fn () => $this->stateService->transicionar($cesacion, EstadoAccionPersonal::REGISTRADA))
        ->toThrow(ReglaNegocioException::class, 'no tiene un vínculo laboral vigente');
});

// ── El ingreso ya no cierra el vínculo en silencio ──────────────

test('un ingreso sobre un servidor con vínculo vigente es rechazado', function () {
    $servidor = ($this->servidorVinculado)();

    $ingreso = $this->service->registrar($servidor->id, [
        'tipo_movimiento'             => TipoMovimientoPersonal::INGRESO->value,
        'tipo_nombramiento_propuesto' => TipoNombramiento::PERMANENTE->value,
        'remuneracion_propuesta'      => 1200,
        'puesto_destino_id'           => $this->puestoNuevo->id,
        'unidad_destino_id'           => $this->unidad->id,
        'requiere_dictamen_medico'    => false,
        'descripcion'                 => 'Ingreso al puesto nuevo sin cesar el anterior',
        'fecha_efectiva'              => '2026-08-01',
    ]);

    expect(fn () => ($this->registrar)($ingreso))
        ->toThrow(ReglaNegocioException::class, 'Registre primero la Cesación de Funciones');

    // El vínculo anterior sigue intacto: no se cerró de forma colateral.
    expect($servidor->fresh()->contratoVigente)->not->toBeNull();
});

test('el par cesación → ingreso y vinculación traslada al servidor al puesto nuevo', function () {
    $servidor = ($this->servidorVinculado)();

    $cesacion = ($this->registrar)($this->service->registrar($servidor->id, [
        'tipo_movimiento'    => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::RENUNCIA->value,
        'descripcion'        => 'Cesación del puesto actual para pasar al nuevo',
        'fecha_efectiva'     => '2026-08-01',
    ]));

    $ingreso = $this->service->registrar($servidor->id, [
        'tipo_movimiento'             => TipoMovimientoPersonal::INGRESO->value,
        'tipo_nombramiento_propuesto' => TipoNombramiento::PERMANENTE->value,
        'remuneracion_propuesta'      => 1500,
        'puesto_destino_id'           => $this->puestoNuevo->id,
        'unidad_destino_id'           => $this->unidad->id,
        'movimiento_previo_id'        => $cesacion->id,
        'numero_contrato'             => 'CT-2026-0010',
        'requiere_dictamen_medico'    => false,
        'descripcion'                 => 'Ingreso y Vinculación al puesto nuevo',
        'fecha_efectiva'              => '2026-08-02',
    ]);

    $ingreso = ($this->registrar)($ingreso);

    $vigente = $servidor->fresh(['contratoVigente'])->contratoVigente;

    expect($ingreso->movimiento_previo_id)->toBe($cesacion->id)
        ->and($vigente)->not->toBeNull()
        ->and($vigente->puesto_id)->toBe($this->puestoNuevo->id)
        ->and((float) $vigente->remuneracion)->toBe(1500.0)
        ->and(ContratoServidor::where('servidor_id', $servidor->id)->count())->toBe(2);
});

test('la cesación y el ingreso quedan encadenados en ambos sentidos', function () {
    $servidor = ($this->servidorVinculado)();

    $cesacion = ($this->registrar)($this->service->registrar($servidor->id, [
        'tipo_movimiento'    => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::RENUNCIA->value,
        'descripcion'        => 'Cesación previa',
        'fecha_efectiva'     => '2026-08-01',
    ]));

    $ingreso = $this->service->registrar($servidor->id, [
        'tipo_movimiento'             => TipoMovimientoPersonal::INGRESO->value,
        'tipo_nombramiento_propuesto' => TipoNombramiento::PERMANENTE->value,
        'remuneracion_propuesta'      => 1500,
        'puesto_destino_id'           => $this->puestoNuevo->id,
        'unidad_destino_id'           => $this->unidad->id,
        'movimiento_previo_id'        => $cesacion->id,
        'requiere_dictamen_medico'    => false,
        'descripcion'                 => 'Ingreso encadenado',
        'fecha_efectiva'              => '2026-08-02',
    ]);

    expect($ingreso->movimientoPrevio->id)->toBe($cesacion->id)
        ->and($cesacion->fresh()->movimientosHabilitados->pluck('id')->all())->toBe([$ingreso->id]);
});

// ── Validación del encadenamiento ───────────────────────────────

test('no se puede encadenar con una acción de otro servidor', function () {
    $servidorA = ($this->servidorVinculado)();
    $servidorB = ($this->servidorVinculado)();

    $cesacionA = ($this->registrar)($this->service->registrar($servidorA->id, [
        'tipo_movimiento'    => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::RENUNCIA->value,
        'descripcion'        => 'Cesación de A',
        'fecha_efectiva'     => '2026-08-01',
    ]));

    expect(fn () => $this->service->registrar($servidorB->id, [
        'tipo_movimiento'             => TipoMovimientoPersonal::INGRESO->value,
        'tipo_nombramiento_propuesto' => TipoNombramiento::PERMANENTE->value,
        'remuneracion_propuesta'      => 1500,
        'puesto_destino_id'           => $this->puestoNuevo->id,
        'unidad_destino_id'           => $this->unidad->id,
        'movimiento_previo_id'        => $cesacionA->id,
        'descripcion'                 => 'Ingreso de B encadenado a la cesación de A',
        'fecha_efectiva'              => '2026-08-02',
    ]))->toThrow(ReglaNegocioException::class, 'no corresponde a este servidor');
});

test('no se puede encadenar con una acción que no es cesación', function () {
    $servidor = ($this->servidorVinculado)();

    $otra = MovimientoPersonal::create([
        'servidor_id'     => $servidor->id,
        'tipo_movimiento' => TipoMovimientoPersonal::LICENCIA_SIN_REMUNERACION->value,
        'estado'          => EstadoAccionPersonal::REGISTRADA,
        'descripcion'     => 'Licencia',
        'fecha_efectiva'  => '2026-08-01',
        'autorizado_por'  => $this->user->id,
    ]);

    expect(fn () => $this->service->registrar($servidor->id, [
        'tipo_movimiento'             => TipoMovimientoPersonal::INGRESO->value,
        'tipo_nombramiento_propuesto' => TipoNombramiento::PERMANENTE->value,
        'remuneracion_propuesta'      => 1500,
        'puesto_destino_id'           => $this->puestoNuevo->id,
        'unidad_destino_id'           => $this->unidad->id,
        'movimiento_previo_id'        => $otra->id,
        'descripcion'                 => 'Ingreso encadenado a una licencia',
        'fecha_efectiva'              => '2026-08-02',
    ]))->toThrow(ReglaNegocioException::class, 'debe ser una Cesación de Funciones');
});

test('no se puede encadenar con una cesación aún en borrador', function () {
    $servidor = ($this->servidorVinculado)();

    $cesacionBorrador = $this->service->registrar($servidor->id, [
        'tipo_movimiento'    => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::RENUNCIA->value,
        'descripcion'        => 'Cesación todavía en borrador',
        'fecha_efectiva'     => '2026-08-01',
    ]);

    expect($cesacionBorrador->estado)->toBe(EstadoAccionPersonal::BORRADOR);

    expect(fn () => $this->service->registrar($servidor->id, [
        'tipo_movimiento'             => TipoMovimientoPersonal::INGRESO->value,
        'tipo_nombramiento_propuesto' => TipoNombramiento::PERMANENTE->value,
        'remuneracion_propuesta'      => 1500,
        'puesto_destino_id'           => $this->puestoNuevo->id,
        'unidad_destino_id'           => $this->unidad->id,
        'movimiento_previo_id'        => $cesacionBorrador->id,
        'descripcion'                 => 'Ingreso encadenado a una cesación en borrador',
        'fecha_efectiva'              => '2026-08-02',
    ]))->toThrow(ReglaNegocioException::class, 'debe estar registrada');
});

// ── Un ingreso limpio sigue funcionando ─────────────────────────

test('un ingreso de alguien sin vínculo previo no necesita cesación', function () {
    $servidor = Servidor::create([
        'user_id'         => User::factory()->create()->id,
        'cedula'          => '7000000002',
        'nombre'          => 'Nuevo',
        'apellido'        => 'Ingreso',
        'regimen_laboral' => 'losep',
    ]);

    $ingreso = $this->service->registrar($servidor->id, [
        'tipo_movimiento'             => TipoMovimientoPersonal::INGRESO->value,
        'tipo_nombramiento_propuesto' => TipoNombramiento::PERMANENTE->value,
        'remuneracion_propuesta'      => 1000,
        'puesto_destino_id'           => $this->puestoNuevo->id,
        'unidad_destino_id'           => $this->unidad->id,
        'numero_contrato'             => 'CT-2026-0011',
        'requiere_dictamen_medico'    => false,
        'descripcion'                 => 'Primer ingreso',
        'fecha_efectiva'              => '2026-08-01',
    ]);

    $registrado = ($this->registrar)($ingreso);

    expect($registrado->estado)->toBe(EstadoAccionPersonal::REGISTRADA)
        ->and($servidor->fresh(['contratoVigente'])->contratoVigente)->not->toBeNull();
});
