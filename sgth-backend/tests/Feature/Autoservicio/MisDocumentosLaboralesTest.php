<?php

namespace Tests\Feature\Autoservicio;

use App\Enums\EstadoAccionPersonal;
use App\Enums\InstrumentoActo;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Models\Estructura\Cargo;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| Fase 6.3a del diseño de Acciones de Personal (9.13; TH 26): «Mis documentos
| laborales». El servidor ve sus acciones desde que se le notifican —y las
| anuladas después, con su sello—, sus hojas de registro interno y constancias
| desde que se registran; descarga el PDF y acusa recibo de lo notificado por
| el portal, una sola vez. Nunca un borrador ni lo de otro.
|
| Hoy es el lunes 12 de octubre de 2026.
*/
beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-12 10:00'));

    $unidad = UnidadAdministrativa::create(['codigo' => 'MD-ADM', 'nombre' => 'Dirección Administrativa', 'nivel' => 2]);
    $puesto = Puesto::create([
        'unidad_administrativa_id' => $unidad->id, 'plazas' => 2, 'activo' => true,
        'cargo_id' => Cargo::firstOrCreate(['nombre' => 'Analista Administrativo'])->id,
    ]);

    $this->contador = 0;
    $this->servidor = function (string $apellido) use ($unidad, $puesto): Servidor {
        $this->contador++;
        $servidor = Servidor::create([
            'cedula' => str_pad((string) (6800000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre' => 'Servidor', 'apellido' => $apellido, 'regimen_laboral' => 'losep',
        ]);
        ContratoServidor::create([
            'servidor_id' => $servidor->id, 'tipo_nombramiento' => 'nombramiento_permanente',
            'unidad_administrativa_id' => $unidad->id, 'puesto_id' => $puesto->id,
            'fecha_inicio' => '2018-01-02', 'estado' => 'vigente',
        ]);

        return $servidor;
    };

    $this->yo = ($this->servidor)('Propio');
    $this->otro = ($this->servidor)('Ajeno');

    $this->usuario = User::factory()->create(['servidor_id' => $this->yo->id]);
    $this->actingAs($this->usuario, 'sanctum');

    // Con forceFill: los datos de la notificación no son asignables en masa —los
    // pone el servicio de estados al notificar— y `create()` los descartaría.
    $this->acto = function (Servidor $de, array $datos) use ($unidad, $puesto): MovimientoPersonal {
        $acto = new MovimientoPersonal();
        $acto->forceFill([
            'servidor_id' => $de->id,
            'tipo_movimiento' => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
            'subtipo_movimiento' => SubtipoMovimientoPersonal::RENUNCIA->value,
            'descripcion' => 'Renuncia voluntaria.', 'fecha_efectiva' => '2026-11-01',
            'unidad_origen_id' => $unidad->id, 'puesto_origen_id' => $puesto->id,
            ...$datos,
        ])->save();

        return $acto->fresh();
    };
    $this->notificadaPorPortal = fn (Servidor $de, string $codigo) => ($this->acto)($de, [
        'estado' => EstadoAccionPersonal::NOTIFICADA, 'codigo_registro' => $codigo,
        'fecha_notificacion' => '2026-10-12 09:00:00', 'notificacion_medio' => 'portal',
    ]);
});

test('ve lo notificado, lo anulado después y lo registrado sin notificación; nunca un borrador ni lo de otro', function () {
    $notificada = ($this->notificadaPorPortal)($this->yo, 'AP-2026-0901');
    $anulada = ($this->acto)($this->yo, [
        'estado' => EstadoAccionPersonal::ANULADA, 'codigo_registro' => 'AP-2026-0902',
        'fecha_notificacion' => '2026-10-01 09:00:00', 'notificacion_medio' => 'portal', 'motivo_anulacion' => 'Se registró por error.',
    ]);
    $hoja = ($this->acto)($this->yo, [
        'estado' => EstadoAccionPersonal::REGISTRADA, 'codigo_registro' => 'RI-2026-0903',
        'instrumento' => InstrumentoActo::DOCUMENTO_CT->value,
        'tipo_movimiento' => TipoMovimientoPersonal::INCREMENTO_REMUNERACION->value, 'subtipo_movimiento' => null,
    ]);

    // Lo que no ve: el borrador, la registrada sin notificar, la anulada que
    // nunca se le notificó, y lo de otro servidor.
    ($this->acto)($this->yo, ['estado' => EstadoAccionPersonal::BORRADOR]);
    ($this->acto)($this->yo, ['estado' => EstadoAccionPersonal::REGISTRADA, 'codigo_registro' => 'AP-2026-0904']);
    ($this->acto)($this->yo, ['estado' => EstadoAccionPersonal::ANULADA, 'codigo_registro' => 'AP-2026-0905', 'motivo_anulacion' => 'Error.']);
    ($this->notificadaPorPortal)($this->otro, 'AP-2026-0906');

    $datos = collect($this->getJson('/api/v1/autoservicio/mis-documentos')->assertOk()->json('datos'));

    expect($datos->pluck('id')->sort()->values()->all())->toBe(collect([$notificada->id, $anulada->id, $hoja->id])->sort()->values()->all())
        ->and($datos->firstWhere('id', $notificada->id)['puede_acusar'])->toBeTrue()
        ->and($datos->firstWhere('id', $anulada->id)['anulada'])->toBeTrue()
        ->and($datos->firstWhere('id', $anulada->id)['puede_acusar'])->toBeFalse()
        ->and($datos->firstWhere('id', $hoja->id)['puede_acusar'])->toBeFalse();
});

test('acusa recibo una sola vez, y solo de lo suyo notificado por el portal', function () {
    $notificada = ($this->notificadaPorPortal)($this->yo, 'AP-2026-0911');
    $ajena = ($this->notificadaPorPortal)($this->otro, 'AP-2026-0912');
    $enPersona = ($this->acto)($this->yo, [
        'estado' => EstadoAccionPersonal::NOTIFICADA, 'codigo_registro' => 'AP-2026-0913',
        'fecha_notificacion' => '2026-10-12 09:00:00', 'notificacion_medio' => 'personal',
        'notificacion_resultado' => 'negada', 'notificacion_razon' => 'No quiso firmar.',
        'notificacion_testigo_nombre' => 'Pedro Cortez', 'notificacion_testigo_cedula' => '0801234567',
    ]);

    $this->postJson("/api/v1/autoservicio/mis-documentos/{$notificada->id}/acuse")
        ->assertOk()
        ->assertJsonPath('datos.puede_acusar', false);
    expect($notificada->fresh()->acuse_en->toDateTimeString())->toBe('2026-10-12 10:00:00');

    $this->travel(2)->hours();
    $this->postJson("/api/v1/autoservicio/mis-documentos/{$notificada->id}/acuse")
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'Ya acusó recibo de este documento el 12/10/2026 10:00.');
    expect($notificada->fresh()->acuse_en->toDateTimeString())->toBe('2026-10-12 10:00:00');

    $this->postJson("/api/v1/autoservicio/mis-documentos/{$ajena->id}/acuse")->assertNotFound();
    $this->postJson("/api/v1/autoservicio/mis-documentos/{$enPersona->id}/acuse")
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'Este documento no espera su acuse: no se le notificó por el portal.');
});

test('descarga el PDF de lo suyo, y lo de otro no existe para él', function () {
    $notificada = ($this->notificadaPorPortal)($this->yo, 'AP-2026-0921');
    $ajena = ($this->notificadaPorPortal)($this->otro, 'AP-2026-0922');

    $this->get("/api/v1/autoservicio/mis-documentos/{$notificada->id}/pdf")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
    $this->getJson("/api/v1/autoservicio/mis-documentos/{$ajena->id}/pdf")->assertNotFound();
});

test('una cuenta sin servidor no tiene documentos', function () {
    $this->actingAs(User::factory()->create(['servidor_id' => null]), 'sanctum')
        ->getJson('/api/v1/autoservicio/mis-documentos')
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'Su cuenta no está vinculada a un servidor: no tiene documentos laborales.');
});
