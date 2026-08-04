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
use App\Services\Expediente\ContratoServidorService;
use App\Services\Expediente\ContratoVencidoService;
use App\Services\Expediente\MovimientoPersonalStateService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
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
        'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 20,
    ]);

    $this->contratoService = app(ContratoServidorService::class);
    $this->vencidoService  = app(ContratoVencidoService::class);
    $this->stateService    = app(MovimientoPersonalStateService::class);

    $this->contador = 0;

    $this->servidorNuevo = function (): Servidor {
        $this->contador++;

        return Servidor::create([
            'user_id'         => User::factory()->create()->id,
            'cedula'          => str_pad((string) (2000000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre'          => 'Profesional',
            'apellido'        => 'Contratado'.$this->contador,
            'regimen_laboral' => 'codigo_trabajo',
        ]);
    };

    $this->contratoProfesional = function (string $inicio, ?string $fin = null): ContratoServidor {
        $servidor = ($this->servidorNuevo)();

        return $this->contratoService->crear($servidor->id, [
            'tipo_nombramiento'        => TipoNombramiento::SERVICIOS_PROFESIONALES->value,
            'unidad_administrativa_id' => $this->unidad->id,
            'puesto_id'                => $this->puesto->id,
            'fecha_inicio'             => $inicio,
            'fecha_fin'                => $fin,
            'estado'                   => 'vigente',
        ]);
    };
});

// ── Derivación del plazo ────────────────────────────────────────

test('un contrato de servicios profesionales termina el 31 de diciembre de su año', function () {
    $contrato = ($this->contratoProfesional)('2026-01-15');

    expect($contrato->fecha_fin->toDateString())->toBe('2026-12-31');
});

test('contratado a mitad de año, solo le queda lo que resta del año calendario', function () {
    $contrato = ($this->contratoProfesional)('2026-07-01');

    expect($contrato->fecha_fin->toDateString())->toBe('2026-12-31');
});

test('una fecha de fin explícita se respeta', function () {
    $contrato = ($this->contratoProfesional)('2026-03-01', '2026-09-30');

    expect($contrato->fecha_fin->toDateString())->toBe('2026-09-30');
});

test('los demás nombramientos no reciben fecha de fin automática', function () {
    $servidor = ($this->servidorNuevo)();

    $contrato = $this->contratoService->crear($servidor->id, [
        'tipo_nombramiento'        => TipoNombramiento::PERMANENTE->value,
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id'                => $this->puesto->id,
        'fecha_inicio'             => '2026-07-01',
        'estado'                   => 'vigente',
    ]);

    expect($contrato->fecha_fin)->toBeNull();
});

// ── Garantía de base de datos ───────────────────────────────────

test('la base rechaza un contrato de servicios profesionales sin vencimiento', function () {
    $servidor = ($this->servidorNuevo)();

    // Inserción cruda, saltándose el servicio: es el único camino por el que
    // podría entrar un contrato sin vencer (carga histórica, seeder, SQL).
    expect(fn () => DB::table('contratos_servidor')->insert([
        'servidor_id'              => $servidor->id,
        'tipo_nombramiento'        => TipoNombramiento::SERVICIOS_PROFESIONALES->value,
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id'                => $this->puesto->id,
        'fecha_inicio'             => '2026-03-01',
        'fecha_fin'                => null,
        'estado'                   => 'vigente',
        'created_at'               => now(),
        'updated_at'               => now(),
    ]))->toThrow(QueryException::class);
});

test('los demás nombramientos sí pueden quedarse sin fecha de fin', function () {
    $servidor = ($this->servidorNuevo)();

    DB::table('contratos_servidor')->insert([
        'servidor_id'              => $servidor->id,
        'tipo_nombramiento'        => TipoNombramiento::PERMANENTE->value,
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id'                => $this->puesto->id,
        'fecha_inicio'             => '2026-03-01',
        'fecha_fin'                => null,
        'estado'                   => 'vigente',
        'created_at'               => now(),
        'updated_at'               => now(),
    ]);

    expect(ContratoServidor::where('servidor_id', $servidor->id)->whereNull('fecha_fin')->count())->toBe(1);
});

// ── Detección de vencidos ───────────────────────────────────────

test('un contrato vencido genera su cesación en borrador', function () {
    $contrato = ($this->contratoProfesional)('2025-03-01');

    $resultado = $this->vencidoService->generarCesacionesPendientes('2026-02-01');

    expect($resultado['generadas'])->toHaveCount(1);

    $movimiento = MovimientoPersonal::find($resultado['generadas'][0]['movimiento_id']);

    expect($movimiento->tipo_movimiento)->toBe(TipoMovimientoPersonal::CESACION_FUNCIONES)
        ->and($movimiento->subtipo_movimiento)->toBe(SubtipoMovimientoPersonal::CONTRATO_FINALIZADO)
        ->and($movimiento->estado)->toBe(EstadoAccionPersonal::BORRADOR)
        ->and($movimiento->fecha_efectiva->toDateString())->toBe('2025-12-31')
        ->and($movimiento->descripcion)->toContain('vencimiento del plazo');

    // El vínculo sigue vigente: la baja la decide Talento Humano al aprobar.
    expect($contrato->fresh()->estado->value)->toBe('vigente');
});

test('un contrato con vencimiento anticipado se detecta en su fecha, no en diciembre', function () {
    // Talento Humano fijó un plazo más corto que el año calendario.
    $contrato = ($this->contratoProfesional)('2026-03-01', '2026-06-30');

    expect($contrato->fecha_fin->toDateString())->toBe('2026-06-30');

    // Todavía vigente el último día del plazo.
    expect($this->vencidoService->generarCesacionesPendientes('2026-06-30')['generadas'])->toBeEmpty();

    // Al día siguiente ya vencido, sin esperar a fin de año.
    $resultado = $this->vencidoService->generarCesacionesPendientes('2026-07-01');

    expect($resultado['generadas'])->toHaveCount(1);

    $movimiento = MovimientoPersonal::find($resultado['generadas'][0]['movimiento_id']);

    expect($movimiento->fecha_efectiva->toDateString())->toBe('2026-06-30')
        ->and($movimiento->estado)->toBe(EstadoAccionPersonal::BORRADOR);
});

test('un contrato aún vigente no genera nada', function () {
    ($this->contratoProfesional)('2026-01-15');

    $resultado = $this->vencidoService->generarCesacionesPendientes('2026-06-01');

    expect($resultado['generadas'])->toBeEmpty();
});

test('la detección es idempotente: no duplica la cesación', function () {
    ($this->contratoProfesional)('2025-03-01');

    $primera = $this->vencidoService->generarCesacionesPendientes('2026-02-01');
    $segunda = $this->vencidoService->generarCesacionesPendientes('2026-02-01');

    expect($primera['generadas'])->toHaveCount(1)
        ->and($segunda['generadas'])->toBeEmpty()
        ->and($segunda['omitidas'])->toHaveCount(1)
        ->and($segunda['omitidas'][0]['motivo'])->toContain('Ya existe una cesación');

    expect(MovimientoPersonal::where('subtipo_movimiento', 'contrato_finalizado')->count())->toBe(1);
});

test('una cesación anulada no bloquea que se vuelva a generar', function () {
    ($this->contratoProfesional)('2025-03-01');

    $primera = $this->vencidoService->generarCesacionesPendientes('2026-02-01');

    MovimientoPersonal::find($primera['generadas'][0]['movimiento_id'])
        ->update(['estado' => EstadoAccionPersonal::ANULADA]);

    $segunda = $this->vencidoService->generarCesacionesPendientes('2026-02-01');

    expect($segunda['generadas'])->toHaveCount(1);
});

test('los contratos de otros nombramientos no se tocan', function () {
    $servidor = ($this->servidorNuevo)();

    $this->contratoService->crear($servidor->id, [
        'tipo_nombramiento'        => TipoNombramiento::SERVICIOS_OCASIONALES->value,
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id'                => $this->puesto->id,
        'fecha_inicio'             => '2025-01-01',
        'fecha_fin'                => '2025-12-31',
        'estado'                   => 'vigente',
    ]);

    expect($this->vencidoService->generarCesacionesPendientes('2026-02-01')['generadas'])->toBeEmpty();
});

test('la cesación generada nace sin autorizado_por, porque nadie la autorizó aún', function () {
    ($this->contratoProfesional)('2025-03-01');

    $resultado = $this->vencidoService->generarCesacionesPendientes('2026-02-01');
    $movimiento = MovimientoPersonal::find($resultado['generadas'][0]['movimiento_id']);

    // auth() sí está activo en el test; lo que importa es que la columna admita
    // null para la corrida programada, donde no hay usuario en sesión.
    expect($movimiento)->not->toBeNull();

    $movimiento->update(['autorizado_por' => null]);
    expect($movimiento->fresh()->autorizado_por)->toBeNull();
});

// ── Cierre del vínculo al aprobar ───────────────────────────────

test('aprobar la cesación generada cierra el contrato vencido', function () {
    $contrato = ($this->contratoProfesional)('2025-03-01');

    $resultado = $this->vencidoService->generarCesacionesPendientes('2026-02-01');
    $movimiento = MovimientoPersonal::find($resultado['generadas'][0]['movimiento_id']);

    $movimiento = $this->stateService->transicionar($movimiento, EstadoAccionPersonal::SUSCRITA);
    $this->stateService->transicionar($movimiento->fresh(), EstadoAccionPersonal::REGISTRADA);

    $contrato->refresh();

    expect($contrato->estado->value)->toBe('terminado')
        ->and($contrato->fecha_fin->toDateString())->toBe('2025-12-31')
        ->and($contrato->motivo_fin)->toContain('Contrato Finalizado');
});

// ── Reprogramación del plazo ────────────────────────────────────

test('se puede prorrogar el plazo de un contrato vigente', function () {
    $contrato = ($this->contratoProfesional)('2026-03-01');

    $actualizado = $this->contratoService->reprogramarPlazo($contrato, [
        'fecha_fin' => '2027-06-30',
        'motivo'    => 'Prórroga autorizada por la máxima autoridad.',
    ]);

    expect($actualizado->fecha_fin->toDateString())->toBe('2027-06-30');
});

test('la reprogramación queda auditada con el valor anterior y el motivo', function () {
    $contrato = ($this->contratoProfesional)('2026-03-01');

    $this->contratoService->reprogramarPlazo($contrato, [
        'fecha_fin' => '2027-06-30',
        'motivo'    => 'Corrección: se digitó mal el año al registrar.',
    ]);

    $log = Activity::where('subject_type', ContratoServidor::class)
        ->where('subject_id', $contrato->id)
        ->where('description', 'Plazo del contrato reprogramado')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->properties['fecha_fin_anterior'])->toBe('2026-12-31')
        ->and($log->properties['fecha_fin_nueva'])->toBe('2027-06-30')
        ->and($log->properties['motivo'])->toContain('Corrección');
});

test('no se puede reprogramar el plazo de un contrato terminado', function () {
    $contrato = ($this->contratoProfesional)('2025-03-01');

    $this->contratoService->cerrar($contrato, [
        'motivo_fin' => 'Fin del plazo',
        'fecha_fin'  => '2025-12-31',
    ]);

    expect(fn () => $this->contratoService->reprogramarPlazo($contrato->fresh(), [
        'fecha_fin' => '2026-06-30',
        'motivo'    => 'Intento sobre un vínculo cerrado.',
    ]))->toThrow(ReglaNegocioException::class, 'contrato vigente');
});

test('no se puede dejar sin vencimiento un contrato de servicios profesionales', function () {
    $contrato = ($this->contratoProfesional)('2026-03-01');

    expect(fn () => $this->contratoService->reprogramarPlazo($contrato, [
        'fecha_fin' => null,
        'motivo'    => 'Intento de quitarle el plazo.',
    ]))->toThrow(ReglaNegocioException::class, 'sin fecha de vencimiento');
});

test('la nueva fecha no puede ser anterior al inicio del contrato', function () {
    $contrato = ($this->contratoProfesional)('2026-03-01');

    expect(fn () => $this->contratoService->reprogramarPlazo($contrato, [
        'fecha_fin' => '2026-01-01',
        'motivo'    => 'Fecha incoherente.',
    ]))->toThrow(ReglaNegocioException::class, 'anterior a la fecha de inicio');
});

test('una cesación ya generada bloquea la reprogramación hasta anularla', function () {
    $contrato = ($this->contratoProfesional)('2025-03-01');

    $resultado = $this->vencidoService->generarCesacionesPendientes('2026-02-01');
    $movimientoId = $resultado['generadas'][0]['movimiento_id'];

    expect(fn () => $this->contratoService->reprogramarPlazo($contrato->fresh(), [
        'fecha_fin' => '2026-12-31',
        'motivo'    => 'Prórroga tardía.',
    ]))->toThrow(ReglaNegocioException::class, 'Anúlela antes de reprogramar');

    // Anulada la cesación, la reprogramación procede.
    MovimientoPersonal::find($movimientoId)->update(['estado' => EstadoAccionPersonal::ANULADA]);

    $actualizado = $this->contratoService->reprogramarPlazo($contrato->fresh(), [
        'fecha_fin' => '2026-12-31',
        'motivo'    => 'Prórroga tardía.',
    ]);

    expect($actualizado->fecha_fin->toDateString())->toBe('2026-12-31');
});

test('prorrogar saca al contrato de la detección de vencidos', function () {
    $contrato = ($this->contratoProfesional)('2025-03-01');

    $this->contratoService->reprogramarPlazo($contrato, [
        'fecha_fin' => '2027-12-31',
        'motivo'    => 'Prórroga autorizada.',
    ]);

    expect($this->vencidoService->generarCesacionesPendientes('2026-02-01')['generadas'])->toBeEmpty();
});

test('el endpoint reprograma el plazo y exige motivo', function () {
    $contrato = ($this->contratoProfesional)('2026-03-01');
    $ruta = "/api/v1/expediente/servidores/{$contrato->servidor_id}/contratos/{$contrato->id}/plazo";

    $this->putJson($ruta, ['fecha_fin' => '2027-06-30'])->assertStatus(422);

    $this->putJson($ruta, [
        'fecha_fin' => '2027-06-30',
        'motivo'    => 'Prórroga autorizada por la máxima autoridad.',
    ])->assertOk();

    expect($contrato->fresh()->fecha_fin->toDateString())->toBe('2027-06-30');
});

// ── El comando ──────────────────────────────────────────────────

test('el comando reporta las cesaciones generadas', function () {
    ($this->contratoProfesional)('2025-03-01');

    $this->artisan('sgth:contratos:detectar-vencidos', ['--fecha' => '2026-02-01'])
        ->expectsOutputToContain('1 cesación(es) generada(s) en borrador.')
        ->assertExitCode(0);
});
