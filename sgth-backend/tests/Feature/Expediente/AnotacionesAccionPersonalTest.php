<?php

namespace Tests\Feature\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\TipoAnotacionAccion;
use App\Enums\TipoMovimientoPersonal;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\AnotacionAccionPersonal;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Expediente\MovimientoPersonalStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| Fase 1.4 del diseño de Acciones de Personal (8.1): desde que un acto está
| registrado no cambia ningún campo de contenido, y lo que pasa después se anota
| aparte.
*/
beforeEach(function () {
    foreach (['admin-uath', 'asistente-uath'] as $rol) {
        Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum']);
    }
    permisosDeAccionesPersonal();

    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'UATH-ANO', 'nombre' => 'Unidad de Talento Humano', 'nivel' => 1,
    ]);

    $this->puesto = Puesto::create([
        'codigo' => 'P-ANO', 'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 10,
    ]);

    $this->contador = 0;

    $this->servidor = function (): Servidor {
        $this->contador++;

        return Servidor::create([
            'cedula'                    => str_pad((string) (8300000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre'                    => 'Servidor',
            'apellido'                  => 'Anotado'.$this->contador,
            'regimen_laboral'           => 'losep',
            'puesto_id'                 => $this->puesto->id,
            'unidad_administrativa_id'  => $this->unidad->id,
            'fecha_ingreso_institucion' => '2018-01-01',
        ]);
    };

    $this->usuario = function (?string $rol, ?Servidor $servidor = null): User {
        $usuario = User::factory()->create(['servidor_id' => $servidor?->id]);

        if ($rol) {
            $usuario->assignRole($rol);
        }

        return $usuario;
    };

    $this->accion = fn (Servidor $servidor, EstadoAccionPersonal $estado) => MovimientoPersonal::create([
        'servidor_id'     => $servidor->id,
        'tipo_movimiento' => TipoMovimientoPersonal::LICENCIA_SIN_REMUNERACION->value,
        'estado'          => $estado,
        'codigo_registro' => $estado === EstadoAccionPersonal::BORRADOR ? null : 'AP-2026-'.str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT),
        'descripcion'     => 'Licencia sin remuneración por estudios',
        'fecha_efectiva'  => '2026-10-15',
        'fecha_inicio'    => '2026-10-15',
        'fecha_fin'       => '2026-11-15',
    ]);

    $this->anotar = fn (MovimientoPersonal $m, string $texto = 'El servidor entregó tarde el certificado.') => $this->postJson(
        "/api/v1/expediente/movimientos/{$m->id}/anotaciones",
        ['texto' => $texto],
    );
});

// ── El candado ──────────────────────────────────────────────────

test('de una registrada solo cambia el estado, con los datos de ese paso', function () {
    $this->actingAs(($this->usuario)('admin-uath'), 'sanctum');
    $registrada = ($this->accion)(($this->servidor)(), EstadoAccionPersonal::REGISTRADA);

    $notificada = app(MovimientoPersonalStateService::class)
        ->transicionar($registrada, EstadoAccionPersonal::NOTIFICADA);

    expect($notificada->estado)->toBe(EstadoAccionPersonal::NOTIFICADA)
        ->and($notificada->fecha_notificacion)->not->toBeNull();

    $anulada = app(MovimientoPersonalStateService::class)
        ->transicionar($notificada, EstadoAccionPersonal::ANULADA, ['motivo_anulacion' => 'Se emitió por error.']);

    expect($anulada->motivo_anulacion)->toBe('Se emitió por error.');
});

test('un dato del paso no viaja en otro: notificar no admite un motivo de anulación', function () {
    $registrada = ($this->accion)(($this->servidor)(), EstadoAccionPersonal::REGISTRADA);

    expect(fn () => $registrada->update([
        'estado'           => EstadoAccionPersonal::NOTIFICADA,
        'motivo_anulacion' => 'No corresponde aquí',
    ]))->toThrow(ReglaNegocioException::class, 'campos: motivo_anulacion');
});

test('una anulada no cambia nada más', function () {
    $anulada = ($this->accion)(($this->servidor)(), EstadoAccionPersonal::ANULADA);

    expect(fn () => $anulada->update(['observacion' => 'Nota tardía']))
        ->toThrow(ReglaNegocioException::class, 'anulada no se modifica');
});

test('un borrador se sigue corrigiendo', function () {
    $borrador = ($this->accion)(($this->servidor)(), EstadoAccionPersonal::BORRADOR);

    $borrador->update(['descripcion' => 'Licencia corregida']);

    expect($borrador->fresh()->descripcion)->toBe('Licencia corregida');
});

// ── Anotar ──────────────────────────────────────────────────────

test('Talento Humano anota en una acción registrada sin tocar el acto', function () {
    $this->actingAs(($this->usuario)('asistente-uath'), 'sanctum');
    $registrada = ($this->accion)(($this->servidor)(), EstadoAccionPersonal::REGISTRADA);

    ($this->anotar)($registrada)->assertCreated()
        ->assertJsonPath('datos.tipo', 'nota')
        ->assertJsonPath('datos.etiqueta', 'Nota de Talento Humano');

    $this->actingAs(($this->usuario)('admin-uath'), 'sanctum');

    $detalle = $this->getJson("/api/v1/expediente/movimientos/{$registrada->id}")->assertOk()->json('datos');

    expect($detalle['anotaciones'])->toHaveCount(1)
        ->and($detalle['anotaciones'][0]['texto'])->toBe('El servidor entregó tarde el certificado.')
        ->and($detalle['anotaciones'][0]['registrado_por'])->not->toBeNull()
        ->and($detalle['descripcion'])->toBe('Licencia sin remuneración por estudios');
});

test('un borrador no se anota: se corrige', function () {
    $this->actingAs(($this->usuario)('admin-uath'), 'sanctum');
    $borrador = ($this->accion)(($this->servidor)(), EstadoAccionPersonal::BORRADOR);

    ($this->anotar)($borrador)->assertStatus(422);
});

test('nadie anota en sus propias acciones', function () {
    $director = ($this->servidor)();
    $this->actingAs(($this->usuario)('admin-uath', $director), 'sanctum');

    ($this->anotar)(($this->accion)($director, EstadoAccionPersonal::REGISTRADA))
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'sobre usted mismo'));
});

test('sin el permiso de preparar no se anota', function () {
    $this->actingAs(($this->usuario)(null), 'sanctum');

    ($this->anotar)(($this->accion)(($this->servidor)(), EstadoAccionPersonal::REGISTRADA))->assertForbidden();
});

test('la anotación pide un texto de verdad', function () {
    $this->actingAs(($this->usuario)('admin-uath'), 'sanctum');

    ($this->anotar)(($this->accion)(($this->servidor)(), EstadoAccionPersonal::REGISTRADA), 'ok')
        ->assertUnprocessable()
        ->assertJsonStructure(['errores' => ['texto']]);
});

test('una anotación tampoco se edita', function () {
    $anotacion = AnotacionAccionPersonal::create([
        'movimiento_personal_id' => ($this->accion)(($this->servidor)(), EstadoAccionPersonal::REGISTRADA)->id,
        'tipo'                   => TipoAnotacionAccion::NOTA,
        'texto'                  => 'Primera versión',
    ]);

    expect(fn () => $anotacion->update(['texto' => 'Otra versión']))
        ->toThrow(ReglaNegocioException::class, 'no se edita');
});

test('cada acción dice si quien mira puede anotar en ella', function () {
    $director = ($this->servidor)();
    $ajena = ($this->accion)(($this->servidor)(), EstadoAccionPersonal::REGISTRADA);
    $borrador = ($this->accion)(($this->servidor)(), EstadoAccionPersonal::BORRADOR);
    $suya = ($this->accion)($director, EstadoAccionPersonal::REGISTRADA);

    $this->actingAs(($this->usuario)('admin-uath', $director), 'sanctum');

    $puede = fn (MovimientoPersonal $m) => $this->getJson("/api/v1/expediente/movimientos/{$m->id}")
        ->assertOk()->json('datos.puede_anotar');

    expect($puede($ajena))->toBeTrue()
        ->and($puede($borrador))->toBeFalse()
        ->and($puede($suya))->toBeFalse();
});
