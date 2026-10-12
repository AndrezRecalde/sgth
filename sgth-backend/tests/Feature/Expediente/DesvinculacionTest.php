<?php

namespace Tests\Feature\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\DeclaracionJuramentada;
use App\Models\Expediente\Desvinculacion;
use App\Models\Expediente\EmisionCertificadoLaboral;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\InventarioTi\AsignacionBien;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\Marca;
use App\Models\InventarioTi\OrigenBien;
use App\Models\InventarioTi\TipoBien;
use App\Models\User;
use App\Services\Expediente\MovimientoPersonalStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| Fase 7.4a del diseño de Acciones de Personal (9.9): la desvinculación. Se
| abre sola cuando el cese cierra el vínculo, sus pasos se leen de su módulo
| —bienes, usuario, certificado, declaración— o los marca el Director —la
| liquidación y la indemnización—, se cierra sola y se cancela si el cese se
| anula.
|
| Hoy es el lunes 19 de octubre de 2026.
*/
beforeEach(function () {
    foreach (['admin-uath', 'asistente-uath', 'tecnico-dtic'] as $rol) {
        Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum']);
    }
    permisosDeAccionesPersonal();
    $this->travelTo(Carbon::parse('2026-10-19 10:00'));

    $this->director = User::factory()->create();
    $this->director->assignRole('admin-uath');
    $this->actingAs($this->director, 'sanctum');

    $this->unidad = unidadDePrueba(['nombre' => 'Dirección de Obras']);
    $this->puesto = puestoDePrueba($this->unidad, 'Analista', ['plazas' => 10]);
    $this->estados = app(MovimientoPersonalStateService::class);

    $this->contador = 0;
    $this->servidor = function (string $nombramiento = 'nombramiento_permanente'): Servidor {
        $this->contador++;
        $servidor = Servidor::create([
            'cedula' => str_pad((string) (7600000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre' => 'Servidor', 'apellido' => 'Sale'.$this->contador, 'regimen_laboral' => 'losep',
            'fecha_ingreso_institucion' => '2018-01-02',
            'puesto_id' => $this->puesto->id, 'unidad_administrativa_id' => $this->unidad->id,
        ]);
        ContratoServidor::create([
            'servidor_id' => $servidor->id, 'tipo_nombramiento' => $nombramiento,
            'unidad_administrativa_id' => $this->unidad->id, 'puesto_id' => $this->puesto->id,
            'fecha_inicio' => '2018-01-02', 'estado' => 'vigente', 'remuneracion' => 1000,
            'fecha_fin' => $nombramiento === 'servicios_profesionales' ? '2026-12-31' : null,
        ]);

        return $servidor->fresh('contratoVigente');
    };

    /** Una cesación registrada que rige hoy: el motor cierra el vínculo en el acto. */
    $this->cesar = function (Servidor $servidor, SubtipoMovimientoPersonal $causal = SubtipoMovimientoPersonal::RENUNCIA): MovimientoPersonal {
        $cesacion = MovimientoPersonal::create([
            'servidor_id' => $servidor->id, 'tipo_movimiento' => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
            'subtipo_movimiento' => $causal->value, 'estado' => EstadoAccionPersonal::SUSCRITA,
            'descripcion' => 'Cesación de prueba.', 'fecha_efectiva' => now()->toDateString(), 'requiere_dictamen_medico' => false,
        ]);

        return $this->estados->transicionar($cesacion, EstadoAccionPersonal::REGISTRADA);
    };

    $this->pasos = fn (Desvinculacion $d) => collect($this->getJson("/api/v1/expediente/desvinculaciones/{$d->id}")
        ->assertOk()->json('datos.pasos'))->keyBy('paso');
});

// ── Cuándo nace ─────────────────────────────────────────────────

test('al cesar sin otro vínculo se abre la desvinculación, con sus pasos', function () {
    $servidor = ($this->servidor)();
    User::factory()->create(['servidor_id' => $servidor->id, 'usuario_ti' => 'sale.uno', 'activo' => true]);

    $cesacion = ($this->cesar)($servidor);

    $desvinculacion = Desvinculacion::where('movimiento_cierre_id', $cesacion->id)->sole();
    expect($desvinculacion->estado)->toBe(Desvinculacion::ABIERTA)
        ->and($desvinculacion->fecha_salida->toDateString())->toBe('2026-10-19');

    $this->getJson('/api/v1/expediente/desvinculaciones')
        ->assertOk()
        ->assertJsonPath('datos.data.0.servidor_id', $servidor->id)
        ->assertJsonPath('datos.data.0.pasos_total', 6);

    $pasos = ($this->pasos)($desvinculacion);
    expect($pasos)->toHaveCount(6)
        ->and($pasos['bienes']['estado'])->toBe('hecho')
        ->and($pasos['usuario']['estado'])->toBe('pendiente')
        ->and($pasos['usuario']['detalle'])->toContain('sale.uno')
        ->and($pasos['vacaciones']['estado'])->toBe('pendiente')
        ->and($pasos['declaracion']['estado'])->toBe('pendiente');
});

test('al contrato civil, lo que no le toca no aplica desde que nace', function () {
    $civil = ($this->servidor)('servicios_profesionales');

    $cesacion = ($this->cesar)($civil, SubtipoMovimientoPersonal::TERMINACION_MUTUO_ACUERDO);
    $pasos = ($this->pasos)(Desvinculacion::where('movimiento_cierre_id', $cesacion->id)->sole());

    expect($pasos['vacaciones']['estado'])->toBe('no_aplica')
        ->and($pasos['indemnizacion']['estado'])->toBe('no_aplica')
        ->and($pasos['declaracion']['estado'])->toBe('no_aplica')
        ->and($pasos['certificado']['estado'])->toBe('pendiente');
});

// ── Los pasos que se leen ───────────────────────────────────────

test('los pasos se leen de su módulo: el bien devuelto, el usuario de baja, el certificado y la declaración', function () {
    $servidor = ($this->servidor)();
    $usuario = User::factory()->create(['servidor_id' => $servidor->id, 'activo' => true]);
    $bien = BienInformatico::create([
        'codigo_institucional' => 'BI-777', 'codigo_qr' => 'QR-777', 'numero_serie' => 'SN-777', 'modelo' => 'Latitude',
        'tipo_bien_id' => TipoBien::create(['nombre' => 'Laptop'])->id, 'marca_id' => Marca::create(['nombre' => 'Dell'])->id,
        'origen_bien_id' => OrigenBien::create(['tipo_origen' => 'compra_publica', 'entidad_origen' => 'Proveedor', 'fecha_adquisicion' => '2024-01-10'])->id,
    ]);
    $asignacion = AsignacionBien::create([
        'bien_informatico_id' => $bien->id, 'servidor_id' => $servidor->id, 'fecha_asignacion' => '2024-02-01', 'estado' => 'activa',
    ]);

    $desvinculacion = Desvinculacion::where('movimiento_cierre_id', ($this->cesar)($servidor)->id)->sole();
    $pasos = ($this->pasos)($desvinculacion);
    expect($pasos['bienes']['estado'])->toBe('pendiente')
        ->and($pasos['bienes']['bienes'][0]['bien'])->toBe('Laptop Dell Latitude');

    $this->postJson("/api/v1/expediente/desvinculaciones/{$desvinculacion->id}/bienes/{$asignacion->id}/devolver", [
        'fecha_devolucion' => '2026-10-19', 'observaciones' => 'Acta TI-2026-118.',
    ])->assertOk()->assertJsonPath('datos.pasos.2.estado', 'hecho');

    expect($asignacion->fresh()->estado)->toBe('devuelta');

    $usuario->update(['activo' => false]);
    EmisionCertificadoLaboral::create([
        'codigo' => 'CL-2026-0042', 'servidor_id' => $servidor->id, 'tipo' => 'laboral', 'con_remuneracion' => false,
        'emitido_por' => $this->director->id, 'emitido_en' => now(), 'vence_en' => now()->addMonths(3), 'datos' => [],
    ]);
    DeclaracionJuramentada::create([
        'servidor_id' => $servidor->id, 'fecha_declaracion' => '2026-10-19', 'codigo_barras' => 'CGE-123', 'tipo_declaracion' => 'fin_gestion',
    ]);

    $pasos = ($this->pasos)($desvinculacion);
    expect($pasos['usuario']['estado'])->toBe('hecho')
        ->and($pasos['certificado']['estado'])->toBe('hecho')
        ->and($pasos['declaracion']['estado'])->toBe('hecho');
});

test('el Inventario TI registra la devolución, una vez', function () {
    $servidor = ($this->servidor)();
    $bien = BienInformatico::create([
        'codigo_institucional' => 'BI-778', 'codigo_qr' => 'QR-778', 'numero_serie' => 'SN-778',
        'tipo_bien_id' => TipoBien::create(['nombre' => 'Monitor'])->id, 'marca_id' => Marca::create(['nombre' => 'LG'])->id,
        'origen_bien_id' => OrigenBien::create(['tipo_origen' => 'compra_publica', 'entidad_origen' => 'Proveedor', 'fecha_adquisicion' => '2024-01-10'])->id,
    ]);
    $asignacion = AsignacionBien::create([
        'bien_informatico_id' => $bien->id, 'servidor_id' => $servidor->id, 'fecha_asignacion' => '2024-02-01', 'estado' => 'activa',
    ]);
    $tecnico = User::factory()->create();
    $tecnico->assignRole('tecnico-dtic');
    $this->actingAs($tecnico, 'sanctum');

    $this->postJson("/api/v1/inventario/asignaciones/{$asignacion->id}/devolver", ['fecha_devolucion' => '2026-10-19'])->assertOk();
    $this->postJson("/api/v1/inventario/asignaciones/{$asignacion->id}/devolver", ['fecha_devolucion' => '2026-10-19'])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'Ese bien ya se devolvió.');
});

// ── Los pasos que se marcan ─────────────────────────────────────

test('la liquidación la marca el Director, lo que no aplica lleva su motivo, y se cierra sola', function () {
    $servidor = ($this->servidor)();
    $desvinculacion = Desvinculacion::where('movimiento_cierre_id', ($this->cesar)($servidor)->id)->sole();
    $marcar = fn (string $paso, string $estado, ?string $nota = null) => $this->postJson(
        "/api/v1/expediente/desvinculaciones/{$desvinculacion->id}/pasos/{$paso}",
        array_filter(['estado' => $estado, 'nota' => $nota]),
    );

    $asistente = User::factory()->create();
    $asistente->assignRole('asistente-uath');
    $this->actingAs($asistente, 'sanctum');
    $marcar('vacaciones', 'hecho', 'Liquidada por Financiero, memorando 45.')
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'lo marca el Director'));
    // Lo que se lee del dato no se marca como hecho a mano.
    $marcar('certificado', 'hecho', 'Se entregó en papel.')->assertStatus(422);
    $marcar('certificado', 'no_aplica', 'Lo pidió por escrito y no lo quiere.')->assertOk();
    $marcar('declaracion', 'no_aplica')->assertStatus(422);
    $marcar('declaracion', 'no_aplica', 'Falleció: la presentan sus herederos.')->assertOk();

    $this->actingAs($this->director, 'sanctum');
    $marcar('vacaciones', 'hecho', 'Liquidada por Financiero, memorando 45.')->assertOk();
    $marcar('indemnizacion', 'no_aplica', 'Renuncia sin compensación.')->assertOk()
        ->assertJsonPath('datos.estado', 'cerrada');

    expect($desvinculacion->fresh()->estado)->toBe(Desvinculacion::CERRADA);

    // Reabrir un paso la vuelve a abrir.
    $marcar('indemnizacion', 'pendiente')->assertOk()->assertJsonPath('datos.estado', 'abierta');
});

test('si el cese se anula, la desvinculación se cancela', function () {
    $servidor = ($this->servidor)();
    $cesacion = ($this->cesar)($servidor);
    $desvinculacion = Desvinculacion::where('movimiento_cierre_id', $cesacion->id)->sole();

    $this->estados->transicionar($cesacion->fresh(), EstadoAccionPersonal::ANULADA, ['motivo_anulacion' => 'Se registró a la persona equivocada.']);

    expect($desvinculacion->fresh()->estado)->toBe(Desvinculacion::CANCELADA)
        ->and($desvinculacion->fresh()->motivo_cancelacion)->toContain('el vínculo volvió')
        ->and($servidor->fresh('contratoVigente')->contratoVigente)->not->toBeNull();

    $this->postJson("/api/v1/expediente/desvinculaciones/{$desvinculacion->id}/pasos/vacaciones", [
        'estado' => 'hecho', 'nota' => 'Liquidada por Financiero.',
    ])->assertStatus(422);
});
