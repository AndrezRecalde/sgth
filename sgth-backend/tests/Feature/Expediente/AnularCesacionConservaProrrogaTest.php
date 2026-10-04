<?php

use App\Enums\EstadoAccionPersonal;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Expediente\ContratoServidorService;
use App\Services\Expediente\MovimientoPersonalService;
use App\Services\Expediente\MovimientoPersonalStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
 * Anular una cesación devuelve el contrato al plazo que tenía justo antes de
 * cerrarse, no al del ingreso.
 *
 * Hasta el 2026-10-03 se recuperaba de la acción de ingreso: un contrato
 * prorrogado al 30/06/2027 volvía a vencer el 31/12/2026 al anular su
 * cesación, y la prórroga se perdía sin aviso.
 */

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    $uath = User::factory()->create();
    $uath->assignRole('admin-uath');
    $this->actingAs($uath, 'sanctum');

    $unidad = unidadDePrueba(['nombre' => 'Unidad Prórrogas']);
    $puesto = puestoDePrueba($unidad, 'Puesto Prórrogas');
    $this->servidor = Servidor::forceCreate([
        'cedula' => '0805050501', 'nombre' => 'Olga', 'apellido' => 'Prórroga',
        'puesto_id' => $puesto->id, 'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => 'losep', 'estado' => true,
    ]);
    $this->contrato = ContratoServidor::create([
        'servidor_id' => $this->servidor->id,
        'tipo_nombramiento' => 'servicios_ocasionales',
        'unidad_administrativa_id' => $unidad->id,
        'puesto_id' => $puesto->id,
        'fecha_inicio' => '2026-01-01',
        'fecha_fin' => '2026-12-31',
        'estado' => 'vigente',
    ]);

    // La prórroga: lo único editable de un vínculo, con su motivo.
    app(ContratoServidorService::class)->reprogramarPlazo($this->contrato, [
        'fecha_fin' => '2027-06-30', 'motivo' => 'Prórroga por necesidad institucional.',
    ]);

    $state = app(MovimientoPersonalStateService::class);
    $cesacion = app(MovimientoPersonalService::class)->registrar($this->servidor->id, [
        'tipo_movimiento'    => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::RENUNCIA->value,
        'descripcion'        => 'Renuncia con la fecha equivocada',
        'fecha_efectiva'     => '2026-03-01',
    ]);
    $cesacion = $state->transicionar($cesacion, EstadoAccionPersonal::SUSCRITA);
    $this->cesacion = $state->transicionar($cesacion->fresh(), EstadoAccionPersonal::REGISTRADA);

    $this->anular = fn (MovimientoPersonal $m) => $state->transicionar(
        $m->fresh(), EstadoAccionPersonal::ANULADA, ['motivo_anulacion' => 'La renuncia no se presentó.'],
    );
});

test('anular la cesación devuelve el contrato a su plazo prorrogado', function () {
    expect($this->contrato->fresh())
        ->estado->value->toBe('terminado')
        ->fecha_fin->toDateString()->toBe('2026-03-01');

    ($this->anular)($this->cesacion);

    expect($this->contrato->fresh())
        ->estado->value->toBe('vigente')
        ->fecha_fin->toDateString()->toBe('2027-06-30');
});

test('un cierre anterior al cambio recupera la última prórroga registrada', function () {
    // Como lo dejaba el cierre hasta el 2026-10-03: sin `fecha_fin_previa`.
    $cierre = Activity::where('subject_id', $this->contrato->id)
        ->where('description', 'Contrato cerrado')->sole();
    $cierre->properties = $cierre->properties->except('fecha_fin_previa');
    $cierre->save();

    ($this->anular)($this->cesacion);

    expect($this->contrato->fresh()->fecha_fin->toDateString())->toBe('2027-06-30');
});
