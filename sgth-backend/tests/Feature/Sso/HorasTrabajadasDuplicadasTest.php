<?php

use App\Models\Sso\HorasTrabajadasPeriodo;
use App\Models\User;
use App\Services\Sso\SsoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(Tests\TestCase::class, RefreshDatabase::class);

/**
 * Cargar dos veces el mismo período de horas trabajadas.
 *
 * Era un `updateOrCreate`: el segundo registro pisaba el primero en silencio y
 * la pantalla respondía «Horas registradas». Ese número es el denominador de
 * los tres índices del CD 513 que se reportan al IESS, así que un mes tecleado
 * dos veces movía los tres sin dejar rastro de que había cambiado.
 */

beforeEach(function () {
    $this->usuario = User::create([
        'email' => 'horas@gadpe.gob.ec',
        'usuario_ti' => 'horas',
        'password' => bcrypt('secreto'),
        'primer_login' => false,
        'activo' => true,
    ]);
    $this->actingAs($this->usuario);

    $this->unidad = unidadDePrueba();
    $this->servicio = app(SsoService::class);
});

test('el total institucional de un período solo se carga una vez', function () {
    $this->servicio->registrarHorasTrabajadas(['periodo' => '2026-01', 'total_horas' => 98_000]);

    expect(fn() => $this->servicio->registrarHorasTrabajadas([
        'periodo' => '2026-01',
        'total_horas' => 12,
    ]))->toThrow(ValidationException::class);

    // Y lo cargado sigue como estaba: el rechazo no deja a medias el registro
    // anterior.
    expect(HorasTrabajadasPeriodo::where('periodo', '2026-01')->count())->toBe(1);
    expect(HorasTrabajadasPeriodo::where('periodo', '2026-01')->value('total_horas'))->toBe(98_000);
});

test('el mensaje del rechazo cae en el campo del período', function () {
    // El formulario reparte los errores por campo: si la clave no fuera
    // `periodo`, el aviso saldría como notificación suelta y la persona no
    // sabría cuál de los tres campos corregir.
    $this->servicio->registrarHorasTrabajadas(['periodo' => '2026', 'total_horas' => 1_180_000]);

    try {
        $this->servicio->registrarHorasTrabajadas(['periodo' => '2026', 'total_horas' => 5]);
        $this->fail('Se esperaba que el duplicado fuera rechazado.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('periodo');
        expect($e->errors()['periodo'][0])->toContain('2026');
    }
});

test('una unidad no choca con el total institucional del mismo período', function () {
    // Son dos filas legítimas: el total de la institución y el de una unidad.
    $this->servicio->registrarHorasTrabajadas(['periodo' => '2026-03', 'total_horas' => 98_000]);

    $deUnidad = $this->servicio->registrarHorasTrabajadas([
        'periodo' => '2026-03',
        'unidad_administrativa_id' => $this->unidad->id,
        'total_horas' => 22_000,
    ]);

    expect($deUnidad->unidad_administrativa_id)->toBe($this->unidad->id);
    expect(HorasTrabajadasPeriodo::where('periodo', '2026-03')->count())->toBe(2);
});

test('dos unidades distintas pueden cargar el mismo período', function () {
    $otra = unidadDePrueba(['nombre' => 'Dirección de Talento Humano']);

    $this->servicio->registrarHorasTrabajadas([
        'periodo' => '2026-04',
        'unidad_administrativa_id' => $this->unidad->id,
        'total_horas' => 22_000,
    ]);
    $this->servicio->registrarHorasTrabajadas([
        'periodo' => '2026-04',
        'unidad_administrativa_id' => $otra->id,
        'total_horas' => 18_000,
    ]);

    expect(HorasTrabajadasPeriodo::where('periodo', '2026-04')->count())->toBe(2);
});

test('la misma unidad no repite período', function () {
    $this->servicio->registrarHorasTrabajadas([
        'periodo' => '2026-05',
        'unidad_administrativa_id' => $this->unidad->id,
        'total_horas' => 22_000,
    ]);

    expect(fn() => $this->servicio->registrarHorasTrabajadas([
        'periodo' => '2026-05',
        'unidad_administrativa_id' => $this->unidad->id,
        'total_horas' => 1,
    ]))->toThrow(ValidationException::class);
});

test('borrado el registro, el período se puede volver a cargar', function () {
    // Es la salida que se le deja a quien se equivocó: borrar y cargar de
    // nuevo, que es una decisión deliberada y deja el registro en la auditoría.
    $primero = $this->servicio->registrarHorasTrabajadas(['periodo' => '2026-06', 'total_horas' => 1]);
    $this->servicio->eliminarHorasTrabajadas($primero->id);

    $segundo = $this->servicio->registrarHorasTrabajadas(['periodo' => '2026-06', 'total_horas' => 97_500]);

    expect($segundo->total_horas)->toBe(97_500);
    expect(HorasTrabajadasPeriodo::where('periodo', '2026-06')->count())->toBe(1);
});
