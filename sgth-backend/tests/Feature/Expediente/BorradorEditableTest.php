<?php

namespace Tests\Feature\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estructura\PartidaPresupuestaria;
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

    $this->puesto = Puesto::create([
        'codigo' => 'P-BORR', 'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 10,
    ]);

    $this->partida = PartidaPresupuestaria::create([
        'codigo' => '510105', 'descripcion' => 'Remuneraciones Unificadas',
        'grupo_gasto' => 'Gastos en Personal', 'activo' => true, 'disponible' => true,
    ]);

    $this->service      = app(MovimientoPersonalService::class);
    $this->stateService = app(MovimientoPersonalStateService::class);

    $this->contador = 0;

    $this->servidorNuevo = function (): Servidor {
        $this->contador++;

        return Servidor::create([
            'user_id'         => User::factory()->create()->id,
            'cedula'          => str_pad((string) (5000000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre'          => 'Aspirante',
            'apellido'        => 'Nuevo'.$this->contador,
            'regimen_laboral' => 'losep',
        ]);
    };

    $this->ingresoBorrador = function (array $extra = []) {
        $servidor = ($this->servidorNuevo)();

        return [$servidor, $this->service->registrar($servidor->id, [
            'tipo_movimiento'             => TipoMovimientoPersonal::INGRESO->value,
            'tipo_nombramiento_propuesto' => TipoNombramiento::PERMANENTE->value,
            'remuneracion_propuesta'      => 1200,
            'puesto_destino_id'           => $this->puesto->id,
            'unidad_destino_id'           => $this->unidad->id,
            'requiere_dictamen_medico'    => false,
            'descripcion'                 => 'Ingreso por concurso',
            'fecha_efectiva'              => '2026-08-01',
            ...$extra,
        ])];
    };
});

// ── puede_marcar: default por nombramiento ──────────────────────

test('el sugerido de puede_marcar sigue la regla de Talento Humano', function () {
    $marcan = [
        TipoNombramiento::PERMANENTE,
        TipoNombramiento::PROVISIONAL,
        TipoNombramiento::SERVICIOS_OCASIONALES,
    ];

    foreach (TipoNombramiento::cases() as $nombramiento) {
        expect($nombramiento->puedeMarcarPorDefecto())
            ->toBe(in_array($nombramiento, $marcan, true), "Nombramiento '{$nombramiento->value}'");
    }
});

test('un ingreso de servicios profesionales nace sin marcación', function () {
    $servidor = ($this->servidorNuevo)();

    $movimiento = $this->service->registrar($servidor->id, [
        'tipo_movimiento'             => TipoMovimientoPersonal::INGRESO->value,
        'tipo_nombramiento_propuesto' => TipoNombramiento::SERVICIOS_PROFESIONALES->value,
        'remuneracion_propuesta'      => 900,
        'puesto_destino_id'           => $this->puesto->id,
        'unidad_destino_id'           => $this->unidad->id,
        'descripcion'                 => 'Contrato civil',
        'fecha_efectiva'              => '2026-08-01',
    ]);

    expect($movimiento->puede_marcar)->toBeFalse();
});

test('Talento Humano puede forzar la marcación contra el default', function () {
    $servidor = ($this->servidorNuevo)();

    $movimiento = $this->service->registrar($servidor->id, [
        'tipo_movimiento'             => TipoMovimientoPersonal::INGRESO->value,
        'tipo_nombramiento_propuesto' => TipoNombramiento::CODIGO_TRABAJO->value,
        'remuneracion_propuesta'      => 800,
        'puesto_destino_id'           => $this->puesto->id,
        'unidad_destino_id'           => $this->unidad->id,
        'puede_marcar'                => true,
        'descripcion'                 => 'Obrero que sí marca',
        'fecha_efectiva'              => '2026-08-01',
    ]);

    expect($movimiento->puede_marcar)->toBeTrue();
});

test('una acción que no crea vínculo no opina sobre la marcación', function () {
    $servidor = ($this->servidorNuevo)();

    ContratoServidor::create([
        'servidor_id'              => $servidor->id,
        'tipo_nombramiento'        => TipoNombramiento::PERMANENTE->value,
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id'                => $this->puesto->id,
        'fecha_inicio'             => '2018-01-01',
        'estado'                   => 'vigente',
    ]);

    $movimiento = $this->service->registrar($servidor->id, [
        'tipo_movimiento' => TipoMovimientoPersonal::LICENCIA_SIN_REMUNERACION->value,
        'descripcion'     => 'Licencia',
        'fecha_efectiva'  => '2026-08-01',
    ]);

    expect($movimiento->puede_marcar)->toBeNull();
});

// ── Edición del borrador ────────────────────────────────────────

test('se puede editar un borrador y los cambios persisten', function () {
    [, $movimiento] = ($this->ingresoBorrador)();

    $actualizado = $this->service->actualizarBorrador($movimiento, [
        'numero_contrato'           => 'CT-2026-0099',
        'resolucion_numero'         => 'RES-2026-0012',
        'remuneracion_propuesta'    => 1450,
        'partida_presupuestaria_id' => $this->partida->id,
        'puede_marcar'              => false,
    ]);

    expect($actualizado->numero_contrato)->toBe('CT-2026-0099')
        ->and($actualizado->resolucion_numero)->toBe('RES-2026-0012')
        ->and((float) $actualizado->remuneracion_propuesta)->toBe(1450.0)
        ->and($actualizado->partida_presupuestaria_id)->toBe($this->partida->id)
        ->and($actualizado->puede_marcar)->toBeFalse();
});

test('cambiar el nombramiento propuesto recalcula el sugerido de marcación', function () {
    [, $movimiento] = ($this->ingresoBorrador)();

    expect($movimiento->puede_marcar)->toBeTrue();

    $actualizado = $this->service->actualizarBorrador($movimiento, [
        'tipo_nombramiento_propuesto' => TipoNombramiento::SERVICIOS_PROFESIONALES->value,
    ]);

    expect($actualizado->puede_marcar)->toBeFalse();
});

test('un puede_marcar explícito gana sobre el recálculo', function () {
    [, $movimiento] = ($this->ingresoBorrador)();

    $actualizado = $this->service->actualizarBorrador($movimiento, [
        'tipo_nombramiento_propuesto' => TipoNombramiento::SERVICIOS_PROFESIONALES->value,
        'puede_marcar'                => true,
    ]);

    expect($actualizado->puede_marcar)->toBeTrue();
});

test('no se puede editar una acción ya suscrita', function () {
    [, $movimiento] = ($this->ingresoBorrador)(['numero_contrato' => 'CT-2026-0001']);

    $movimiento = $this->stateService->transicionar($movimiento, EstadoAccionPersonal::SUSCRITA);

    expect(fn () => $this->service->actualizarBorrador($movimiento, [
        'numero_contrato' => 'CT-2026-0002',
    ]))->toThrow(ReglaNegocioException::class, 'Solo se puede editar una acción de personal en borrador');
});

test('el endpoint de edición rechaza una acción fuera de borrador', function () {
    [$servidor, $movimiento] = ($this->ingresoBorrador)(['numero_contrato' => 'CT-2026-0003']);

    $this->stateService->transicionar($movimiento, EstadoAccionPersonal::SUSCRITA);

    $this->putJson("/api/v1/expediente/movimientos/{$movimiento->id}", [
        'numero_contrato' => 'CT-2026-0004',
    ])->assertStatus(422);

    expect($servidor->fresh())->not->toBeNull();
});

test('el endpoint de edición actualiza el borrador', function () {
    [, $movimiento] = ($this->ingresoBorrador)();

    $this->putJson("/api/v1/expediente/movimientos/{$movimiento->id}", [
        'numero_contrato'           => 'CT-2026-0100',
        'partida_presupuestaria_id' => $this->partida->id,
    ])->assertOk();

    expect($movimiento->fresh()->numero_contrato)->toBe('CT-2026-0100');
});

// ── numero_contrato obligatorio para registrar el ingreso ───────

test('no se puede registrar un ingreso sin número de contrato', function () {
    [, $movimiento] = ($this->ingresoBorrador)();

    $movimiento = $this->stateService->transicionar($movimiento, EstadoAccionPersonal::SUSCRITA);

    expect(fn () => $this->stateService->transicionar($movimiento->fresh(), EstadoAccionPersonal::REGISTRADA))
        ->toThrow(ReglaNegocioException::class, 'sin número de contrato');
});

test('una cesación no necesita número de contrato', function () {
    $servidor = ($this->servidorNuevo)();

    ContratoServidor::create([
        'servidor_id'              => $servidor->id,
        'tipo_nombramiento'        => TipoNombramiento::PERMANENTE->value,
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id'                => $this->puesto->id,
        'fecha_inicio'             => '2018-01-01',
        'estado'                   => 'vigente',
    ]);

    $cesacion = $this->service->registrar($servidor->id, [
        'tipo_movimiento'    => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::RENUNCIA->value,
        'descripcion'        => 'Renuncia',
        'fecha_efectiva'     => '2026-08-01',
    ]);

    $cesacion = $this->stateService->transicionar($cesacion, EstadoAccionPersonal::SUSCRITA);
    $registrada = $this->stateService->transicionar($cesacion->fresh(), EstadoAccionPersonal::REGISTRADA);

    expect($registrada->estado)->toBe(EstadoAccionPersonal::REGISTRADA);
});

// ── Completar el vínculo al aprobar (opción B) ──────────────────

test('los datos del contrato se pueden enviar junto con la aprobación', function () {
    [$servidor, $movimiento] = ($this->ingresoBorrador)(['remuneracion_propuesta' => null]);

    // Nace sin remuneración: en Código del Trabajo y Servicios Profesionales
    // se pacta en el contrato, no se deriva del puesto.
    expect($movimiento->remuneracion_propuesta)->toBeNull()
        ->and($movimiento->numero_contrato)->toBeNull();

    $movimiento = $this->stateService->transicionar($movimiento, EstadoAccionPersonal::SUSCRITA);

    $registrado = $this->stateService->transicionar($movimiento->fresh(), EstadoAccionPersonal::REGISTRADA, [
        'numero_contrato'           => 'CT-2026-0777',
        'remuneracion_propuesta'    => 1350.50,
        'resolucion_numero'         => 'RES-2026-0088',
        'partida_presupuestaria_id' => $this->partida->id,
        'puede_marcar'              => false,
    ]);

    $contrato = ContratoServidor::where('servidor_id', $servidor->id)->firstOrFail();

    expect($registrado->estado)->toBe(EstadoAccionPersonal::REGISTRADA)
        ->and($contrato->numero_contrato)->toBe('CT-2026-0777')
        ->and((float) $contrato->remuneracion)->toBe(1350.50)
        ->and($contrato->resolucion_numero)->toBe('RES-2026-0088')
        ->and($contrato->puede_marcar)->toBeFalse();
});

test('aprobar un ingreso sin remuneración ni número de contrato nombra ambos faltantes', function () {
    [, $movimiento] = ($this->ingresoBorrador)(['remuneracion_propuesta' => null]);

    $movimiento = $this->stateService->transicionar($movimiento, EstadoAccionPersonal::SUSCRITA);

    expect(fn () => $this->stateService->transicionar($movimiento->fresh(), EstadoAccionPersonal::REGISTRADA))
        ->toThrow(ReglaNegocioException::class, 'sin número de contrato ni remuneración');
});

test('la fecha de fin pactada llega al contrato', function () {
    $servidor = ($this->servidorNuevo)();

    $movimiento = $this->service->registrar($servidor->id, [
        'tipo_movimiento'             => TipoMovimientoPersonal::INGRESO->value,
        'tipo_nombramiento_propuesto' => TipoNombramiento::SERVICIOS_OCASIONALES->value,
        'puesto_destino_id'           => $this->puesto->id,
        'unidad_destino_id'           => $this->unidad->id,
        'requiere_dictamen_medico'    => false,
        'descripcion'                 => 'Contrato ocasional con plazo pactado',
        'fecha_efectiva'              => '2026-03-01',
    ]);

    $movimiento = $this->stateService->transicionar($movimiento, EstadoAccionPersonal::SUSCRITA);

    $this->stateService->transicionar($movimiento->fresh(), EstadoAccionPersonal::REGISTRADA, [
        'numero_contrato'        => 'CT-2026-0999',
        'remuneracion_propuesta' => 950,
        'fecha_fin_propuesta'    => '2026-11-30',
    ]);

    $contrato = ContratoServidor::where('servidor_id', $servidor->id)->firstOrFail();

    expect($contrato->fecha_fin->toDateString())->toBe('2026-11-30')
        ->and((float) $contrato->remuneracion)->toBe(950.0);
});

// ── Orden del historial ─────────────────────────────────────────

/**
 * Varias acciones del mismo día es lo normal —una cesación y el ingreso que la
 * sigue—. Sin desempate, Postgres las devolvía en orden arbitrario y el
 * historial parecía barajarse entre recargas.
 */
test('el historial del servidor devuelve la última acción primero', function () {
    $servidor = ($this->servidorNuevo)();

    $ids = [];

    foreach (['Primera', 'Segunda', 'Tercera'] as $titulo) {
        $ids[] = $this->service->registrar($servidor->id, [
            'tipo_movimiento'             => TipoMovimientoPersonal::INGRESO->value,
            'tipo_nombramiento_propuesto' => TipoNombramiento::PERMANENTE->value,
            'puesto_destino_id'           => $this->puesto->id,
            'unidad_destino_id'           => $this->unidad->id,
            'requiere_dictamen_medico'    => false,
            'descripcion'                 => "{$titulo} acción del mismo día",
            // Misma fecha efectiva a propósito: es el caso que se desordenaba.
            'fecha_efectiva'              => '2026-08-03',
        ])->id;
    }

    $respuesta = $this->getJson("/api/v1/expediente/servidores/{$servidor->id}/movimientos")
        ->assertOk()
        ->json('datos');

    expect(array_column($respuesta, 'id'))->toBe(array_reverse($ids));
});

// ── Situación actual congelada ──────────────────────────────────

test('la acción congela remuneración y partida de origen, no las deriva del puesto', function () {
    $servidor = ($this->servidorNuevo)();

    $puestoOrigen = Puesto::create([
        'codigo' => 'P-ORIG', 'unidad_administrativa_id' => $this->unidad->id,
        'plazas' => 5, 'partida_presupuestaria_id' => $this->partida->id,
    ]);

    ContratoServidor::create([
        'servidor_id'              => $servidor->id,
        'tipo_nombramiento'        => TipoNombramiento::PERMANENTE->value,
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id'                => $puestoOrigen->id,
        'fecha_inicio'             => '2018-01-01',
        'remuneracion'             => 1450.75,
        'estado'                   => 'vigente',
    ]);

    $movimiento = $this->service->registrar($servidor->fresh()->id, [
        'tipo_movimiento'    => TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::TRASPASO->value,
        'descripcion'        => 'Traspaso con situación actual congelada',
        'fecha_efectiva'     => '2026-08-01',
        'puesto_destino_id'  => $this->puesto->id,
        'unidad_destino_id'  => $this->unidad->id,
    ]);

    expect((float) $movimiento->remuneracion_origen)->toBe(1450.75)
        ->and($movimiento->partida_origen_id)->toBe($this->partida->id)
        ->and($movimiento->puesto_origen_id)->toBe($puestoOrigen->id);

    // Si el puesto cambia de partida después, el documento no debe cambiar.
    $otraPartida = PartidaPresupuestaria::create([
        'codigo' => '510106', 'descripcion' => 'Salarios Unificados',
        'grupo_gasto' => 'Gastos en Personal', 'activo' => true, 'disponible' => true,
    ]);
    $puestoOrigen->update(['partida_presupuestaria_id' => $otraPartida->id]);

    expect($movimiento->fresh()->partida_origen_id)->toBe($this->partida->id);
});

test('un ingreso no inventa situación actual: no hay vínculo previo', function () {
    [, $movimiento] = ($this->ingresoBorrador)();

    expect($movimiento->remuneracion_origen)->toBeNull()
        ->and($movimiento->partida_origen_id)->toBeNull()
        ->and($movimiento->puesto_origen_id)->toBeNull();
});

/**
 * Guardia contra el fallo que ya se repitió dos veces: un campo que el
 * formulario envía pero que no está en las reglas de la request se pierde en
 * silencio, porque validated() solo devuelve lo declarado. Nadie se entera
 * hasta que alguien nota que el dato no quedó guardado.
 *
 * Esta prueba manda de una vez todo lo que manda el formulario y comprueba que
 * nada se cae por el camino.
 */
test('la ruta de creación conserva todos los campos que envía el formulario', function () {
    $servidor = ($this->servidorNuevo)();

    $this->postJson("/api/v1/expediente/servidores/{$servidor->id}/movimientos", [
        'tipo_movimiento'             => TipoMovimientoPersonal::INGRESO->value,
        'tipo_nombramiento_propuesto' => TipoNombramiento::SERVICIOS_OCASIONALES->value,
        'unidad_destino_id'           => $this->unidad->id,
        'puesto_destino_id'           => $this->puesto->id,
        'partida_presupuestaria_id'   => $this->partida->id,
        'remuneracion_propuesta'      => 940.25,
        'numero_contrato'             => 'CT-2026-FORM',
        'fecha_fin_propuesta'         => '2026-12-31',
        'puede_marcar'                => false,
        'lugar_trabajo'               => 'Esmeraldas',
        'resolucion_numero'           => 'RES-2026-FORM',
        'observacion'                 => 'Observación de prueba',
        'caucionado'                  => true,
        'caucion_numero'              => 'CAU-001',
        'caucion_fecha'               => '2026-08-01',
        'requiere_dictamen_medico'    => false,
        'descripcion'                 => 'Ingreso con el formulario completo',
        'fecha_efectiva'              => '2026-08-01',
    ])->assertCreated();

    $m = MovimientoPersonal::where('servidor_id', $servidor->id)->firstOrFail();

    expect($m->numero_contrato)->toBe('CT-2026-FORM')
        ->and($m->puede_marcar)->toBeFalse()
        ->and($m->partida_presupuestaria_id)->toBe($this->partida->id)
        ->and((float) $m->remuneracion_propuesta)->toBe(940.25)
        ->and($m->fecha_fin_propuesta->toDateString())->toBe('2026-12-31')
        ->and($m->lugar_trabajo)->toBe('Esmeraldas')
        ->and($m->resolucion_numero)->toBe('RES-2026-FORM')
        ->and($m->observacion)->toBe('Observación de prueba')
        ->and($m->caucionado)->toBeTrue()
        ->and($m->caucion_numero)->toBe('CAU-001')
        ->and($m->caucion_fecha->toDateString())->toBe('2026-08-01');
});

test('el número de contrato escrito al crear llega hasta el contrato', function () {
    $servidor = ($this->servidorNuevo)();

    $this->postJson("/api/v1/expediente/servidores/{$servidor->id}/movimientos", [
        'tipo_movimiento'             => TipoMovimientoPersonal::INGRESO->value,
        'tipo_nombramiento_propuesto' => TipoNombramiento::PERMANENTE->value,
        'unidad_destino_id'           => $this->unidad->id,
        'puesto_destino_id'           => $this->puesto->id,
        'remuneracion_propuesta'      => 1200,
        'numero_contrato'             => 'CT-2026-DIRECTO',
        'requiere_dictamen_medico'    => false,
        'descripcion'                 => 'Ingreso con número desde el formulario',
        'fecha_efectiva'              => '2026-08-01',
    ])->assertCreated();

    $movimiento = MovimientoPersonal::where('servidor_id', $servidor->id)->firstOrFail();

    // Sin pasar nada más en la aprobación: el número ya venía del formulario.
    $movimiento = $this->stateService->transicionar($movimiento, EstadoAccionPersonal::SUSCRITA);
    $this->stateService->transicionar($movimiento->fresh(), EstadoAccionPersonal::REGISTRADA);

    $contrato = ContratoServidor::where('servidor_id', $servidor->id)->firstOrFail();

    expect($contrato->numero_contrato)->toBe('CT-2026-DIRECTO');
});

test('la partida elegida en el formulario llega a la acción', function () {
    $servidor = ($this->servidorNuevo)();

    $this->postJson("/api/v1/expediente/servidores/{$servidor->id}/movimientos", [
        'tipo_movimiento'             => TipoMovimientoPersonal::INGRESO->value,
        'tipo_nombramiento_propuesto' => TipoNombramiento::PERMANENTE->value,
        'puesto_destino_id'           => $this->puesto->id,
        'unidad_destino_id'           => $this->unidad->id,
        'partida_presupuestaria_id'   => $this->partida->id,
        'requiere_dictamen_medico'    => false,
        'descripcion'                 => 'Ingreso con partida elegida',
        'fecha_efectiva'              => '2026-08-01',
    ])->assertCreated();

    $movimiento = MovimientoPersonal::where('servidor_id', $servidor->id)->firstOrFail();

    expect($movimiento->partida_presupuestaria_id)->toBe($this->partida->id);
});

// ── Propagación al contrato ─────────────────────────────────────

test('al registrar el ingreso los datos editados llegan al contrato', function () {
    [$servidor, $movimiento] = ($this->ingresoBorrador)();

    $this->service->actualizarBorrador($movimiento, [
        'numero_contrato'   => 'CT-2026-0555',
        'resolucion_numero' => 'RES-2026-0777',
        'puede_marcar'      => false,
    ]);

    $movimiento = $movimiento->fresh();
    $movimiento = $this->stateService->transicionar($movimiento, EstadoAccionPersonal::SUSCRITA);
    $this->stateService->transicionar($movimiento->fresh(), EstadoAccionPersonal::REGISTRADA);

    $contrato = ContratoServidor::where('servidor_id', $servidor->id)->firstOrFail();

    expect($contrato->numero_contrato)->toBe('CT-2026-0555')
        ->and($contrato->resolucion_numero)->toBe('RES-2026-0777')
        ->and($contrato->puede_marcar)->toBeFalse()
        ->and($servidor->fresh()->puede_marcar)->toBeFalse();
});

test('el plazo pactado en el borrador llega al contrato sin repetirlo al registrar', function () {
    [$servidor, $movimiento] = ($this->ingresoBorrador)([
        'tipo_nombramiento_propuesto' => TipoNombramiento::SERVICIOS_OCASIONALES->value,
    ]);

    $this->service->actualizarBorrador($movimiento, [
        'numero_contrato'     => 'CT-2026-0888',
        'fecha_fin_propuesta' => '2026-12-15',
    ]);

    $movimiento = $this->stateService->transicionar($movimiento->fresh(), EstadoAccionPersonal::SUSCRITA);
    $this->stateService->transicionar($movimiento->fresh(), EstadoAccionPersonal::REGISTRADA);

    $contrato = ContratoServidor::where('servidor_id', $servidor->id)->firstOrFail();

    expect($contrato->fecha_fin->toDateString())->toBe('2026-12-15');
});

test('la ruta de edición acepta los datos de contratación del borrador', function () {
    [, $movimiento] = ($this->ingresoBorrador)([
        'tipo_nombramiento_propuesto' => TipoNombramiento::SERVICIOS_OCASIONALES->value,
    ]);

    $this->putJson("/api/v1/expediente/movimientos/{$movimiento->id}", [
        'numero_contrato'           => 'CT-2026-0777',
        'resolucion_numero'         => 'RES-2026-0111',
        'remuneracion_propuesta'    => 880.50,
        'fecha_fin_propuesta'       => '2026-10-31',
        'partida_presupuestaria_id' => $this->partida->id,
        'puede_marcar'              => false,
    ])->assertOk();

    $movimiento->refresh();

    expect($movimiento->numero_contrato)->toBe('CT-2026-0777')
        ->and($movimiento->fecha_fin_propuesta->toDateString())->toBe('2026-10-31')
        ->and((float) $movimiento->remuneracion_propuesta)->toBe(880.50)
        ->and($movimiento->puede_marcar)->toBeFalse();
});

test('un ingreso permanente materializa el contrato con marcación activa', function () {
    [$servidor, $movimiento] = ($this->ingresoBorrador)(['numero_contrato' => 'CT-2026-0666']);

    $movimiento = $this->stateService->transicionar($movimiento, EstadoAccionPersonal::SUSCRITA);
    $this->stateService->transicionar($movimiento->fresh(), EstadoAccionPersonal::REGISTRADA);

    $contrato = ContratoServidor::where('servidor_id', $servidor->id)->firstOrFail();

    expect($contrato->puede_marcar)->toBeTrue()
        ->and($servidor->fresh()->puede_marcar)->toBeTrue();
});
