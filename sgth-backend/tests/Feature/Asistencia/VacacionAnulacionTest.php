<?php

/*
| Anular una solicitud de vacaciones.
|
| Hasta ahora una aprobación no tenía vuelta atrás: rechazar solo se admite
| desde pendiente, y los días quedaban descontados aunque la aprobación fuera
| un error o la unidad pidiera postergar las vacaciones.
|
| Se anula una pendiente, o una aprobada que todavía no comenzó. La aprobada
| devuelve cada día al período del que salió.
*/

use App\Enums\RegimenLaboral;
use App\Models\Asistencia\PeriodoVacacion;
use App\Models\Asistencia\Vacacion;
use App\Models\Asistencia\VacacionDescuento;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Asistencia\VacacionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();
    Vacacion::unguard();

    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $this->unidad = unidadDePrueba();

    $this->servidor = Servidor::create([
        'cedula'                       => '0800000701',
        'nombre'                       => 'Ana',
        'apellido'                     => 'Anulación',
        'puesto_id'                    => puestoDePrueba($this->unidad)->id,
        'unidad_administrativa_id'     => $this->unidad->id,
        'regimen_laboral'              => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion'    => now()->subYears(8),
        'fecha_ingreso_sector_publico' => now()->subYears(8),
        'estado'                       => true,
    ]);

    $this->uath = User::create([
        'email'        => 'uath-anulacion@example.com',
        'usuario_ti'   => 'uathanu',
        'password'     => bcrypt('123456'),
        'primer_login' => false,
    ]);
    $this->uath->assignRole('admin-uath');
    $this->actingAs($this->uath, 'sanctum');

    // Año próximo y fecha fija: la vacación no ha comenzado, sea cual sea el
    // día en que corra el test.
    $this->anio   = now()->year + 1;
    $this->inicio = Carbon::create($this->anio, 3, 1)->next(Carbon::MONDAY);

    $this->periodo = fn (int $anio, float $generados, float $utilizados) => PeriodoVacacion::create([
        'servidor_id'          => $this->servidor->id,
        'anio'                 => $anio,
        'fecha_inicio_periodo' => Carbon::create($anio, 1, 1),
        'fecha_fin_periodo'    => Carbon::create($anio, 12, 31),
        'regimen'              => 'losep',
        'anios_antiguedad'     => 8,
        'dias_generados'       => $generados,
        'dias_utilizados'      => $utilizados,
        'dias_saldo'           => $generados - $utilizados,
        'saldo_acumulado'      => $generados - $utilizados,
        'estado'               => 'abierto',
    ]);

    $this->vacacion = fn (float $dias, string $estado = 'pendiente', string $motivo = 'vacaciones_anuales', ?Carbon $inicio = null) => Vacacion::create([
        'servidor_id'      => $this->servidor->id,
        'fecha_inicio'     => ($inicio ?? $this->inicio)->toDateString(),
        'fecha_fin'        => ($inicio ?? $this->inicio)->copy()->addDays((int) $dias - 1)->toDateString(),
        'dias_solicitados' => $dias,
        'tipo_dias'        => 'habiles',
        'estado'           => $estado,
        'motivo'           => $motivo,
    ]);

    $this->anular = fn (Vacacion $v, ?string $motivo = 'La unidad pidió postergarlas') =>
        $this->postJson("/api/v1/asistencia/vacaciones/{$v->id}/anular", array_filter(['motivo' => $motivo]));
});

test('anular una aprobada devuelve los días a los períodos de donde salieron', function () {
    $anterior = ($this->periodo)($this->anio - 1, 20, 10);
    $actual   = ($this->periodo)($this->anio, 15, 0);
    $vacacion = ($this->vacacion)(12);

    $this->putJson("/api/v1/asistencia/vacaciones/{$vacacion->id}", ['estado' => 'aprobada'])->assertOk();

    $respuesta = ($this->anular)($vacacion);

    $respuesta->assertOk();
    expect($respuesta->json('mensaje'))->toContain('12.00 días');

    expect((float) $anterior->fresh()->dias_saldo)->toBe(10.0)
        ->and((float) $actual->fresh()->dias_saldo)->toBe(15.0)
        ->and((float) $actual->fresh()->saldo_acumulado)->toBe(25.0);

    $vacacion->refresh();
    expect($vacacion->estado)->toBe('anulada')
        ->and($vacacion->motivo_anulacion)->toBe('La unidad pidió postergarlas')
        ->and($vacacion->anulado_por)->toBe($this->uath->id)
        ->and($vacacion->anulado_en)->not->toBeNull();

    // Los tramos no se borran: quedan marcados como devueltos.
    expect(VacacionDescuento::where('vacacion_id', $vacacion->id)->count())->toBe(2)
        ->and(VacacionDescuento::whereNull('devuelto_en')->count())->toBe(0);
});

test('anular una pendiente no toca el saldo', function () {
    $periodo  = ($this->periodo)($this->anio, 15, 0);
    $vacacion = ($this->vacacion)(5);

    $respuesta = ($this->anular)($vacacion);

    $respuesta->assertOk();
    expect($respuesta->json('mensaje'))->toBe('Solicitud anulada.')
        ->and((float) $periodo->fresh()->dias_utilizados)->toBe(0.0)
        ->and($vacacion->fresh()->estado)->toBe('anulada');
});

test('una aprobada que ya comenzó no se anula', function () {
    $vacacion = ($this->vacacion)(5, 'aprobada', inicio: Carbon::yesterday());

    $respuesta = ($this->anular)($vacacion);

    $respuesta->assertStatus(422);
    expect($respuesta->json('mensaje'))->toContain('comenzó')
        ->and($vacacion->fresh()->estado)->toBe('aprobada');
});

test('ni una rechazada ni una ya anulada', function () {
    ($this->anular)(($this->vacacion)(3, 'rechazada'))->assertStatus(422);

    $pendiente = ($this->vacacion)(3);
    ($this->anular)($pendiente)->assertOk();
    ($this->anular)($pendiente)->assertStatus(422);
});

test('una anulada libera sus fechas', function () {
    $servicio = app(VacacionService::class);
    $datos = [
        'motivo'       => 'matrimonio',
        'fecha_inicio' => $this->inicio->toDateString(),
        'fecha_fin'    => $this->inicio->copy()->addDay()->toDateString(),
    ];

    $primera = $servicio->solicitar($datos, $this->servidor->id);
    ($this->anular)($primera)->assertOk();

    expect($servicio->solicitar($datos, $this->servidor->id)->estado)->toBe('pendiente');
});

test('sin aprobar-vacaciones no se anula, y el motivo es obligatorio', function () {
    $vacacion = ($this->vacacion)(3);

    $jefe = User::create([
        'email'        => 'jefe-anulacion@example.com',
        'usuario_ti'   => 'jefeanu',
        'password'     => bcrypt('123456'),
        'primer_login' => false,
    ]);
    $jefe->assignRole('jefe-unidad');

    $this->actingAs($jefe, 'sanctum');
    ($this->anular)($vacacion)->assertForbidden();

    $this->actingAs($this->uath, 'sanctum');
    ($this->anular)($vacacion, null)->assertStatus(422);

    expect($vacacion->fresh()->estado)->toBe('pendiente');
});

test('una aprobada antes del registro de descuentos devuelve al período de su año', function () {
    // Así quedaban las aprobadas hasta ahora: descontadas del período de su
    // año, sin tramos anotados.
    $periodo  = ($this->periodo)($this->anio, 15, 5);
    $vacacion = ($this->vacacion)(5, 'aprobada');

    $respuesta = ($this->anular)($vacacion);

    $respuesta->assertOk();
    expect($respuesta->json('mensaje'))->toContain('5.00 días')
        ->and((float) $periodo->fresh()->dias_utilizados)->toBe(0.0)
        ->and((float) $periodo->fresh()->dias_saldo)->toBe(15.0);
});

test('un motivo que no descuenta no devuelve nada', function () {
    $periodo  = ($this->periodo)($this->anio, 15, 5);
    $vacacion = ($this->vacacion)(3, 'aprobada', 'matrimonio');

    ($this->anular)($vacacion)->assertOk();

    expect((float) $periodo->fresh()->dias_utilizados)->toBe(5.0);
});

test('nadie anula su propia solicitud', function () {
    $this->uath->update(['servidor_id' => $this->servidor->id]);
    $vacacion = ($this->vacacion)(3);

    $respuesta = ($this->anular)($vacacion);

    $respuesta->assertStatus(422);
    expect($respuesta->json('mensaje'))->toContain('propia solicitud');
});
