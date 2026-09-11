<?php

/*
| El tope de acumulación de vacaciones.
|
| LOSEP (art. 29): 60 días. Código del Trabajo (art. 75): tres años de
| vacaciones. Hasta ahora no se aplicaba en ningún sitio: `calcularCifras()`
| recortaba a 60 el acumulado que se mostraba, y el saldo real seguía
| creciendo.
|
| Nada vence solo. Se lista a quien está cerca o por encima de su tope, y
| Talento Humano vence el excedente de cada servidor, que queda en la
| bitácora.
*/

use App\Enums\RegimenLaboral;
use App\Models\Asistencia\PeriodoVacacion;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Asistencia\PeriodoVacacionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();

    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $unidad = unidadDePrueba(['nombre' => 'Dirección del Tope']);
    $puesto = puestoDePrueba($unidad);
    $this->anio = now()->year;

    $cedula = 800000900;
    $this->servidor = function (RegimenLaboral $regimen = RegimenLaboral::LOSEP) use ($unidad, $puesto, &$cedula) {
        return Servidor::create([
            'cedula'                       => '0'.(++$cedula),
            'nombre'                       => 'Teresa',
            'apellido'                     => 'Tope',
            'puesto_id'                    => $puesto->id,
            'unidad_administrativa_id'     => $unidad->id,
            'regimen_laboral'              => $regimen,
            // Doce años: los períodos de hace tres años generan 20 días, y así
            // regenerar uno no cambia lo generado.
            'fecha_ingreso_institucion'    => now()->subYears(12),
            'fecha_ingreso_sector_publico' => now()->subYears(12),
            'estado'                       => true,
        ]);
    };

    // Uno por año, terminando en el actual: [20, 20, 20, 15] son cuatro
    // períodos, el más antiguo hace tres años.
    $this->periodos = function (Servidor $servidor, array $saldos, string $regimen = 'losep', ?array $generados = null) {
        $inicio = $this->anio - count($saldos) + 1;

        return collect($saldos)->values()->map(function (float $saldo, int $i) use ($servidor, $regimen, $inicio, $generados) {
            $anio = $inicio + $i;
            $gen  = $generados[$i] ?? $saldo;

            return PeriodoVacacion::create([
                'servidor_id'          => $servidor->id,
                'anio'                 => $anio,
                'fecha_inicio_periodo' => Carbon::create($anio, 1, 1),
                'fecha_fin_periodo'    => Carbon::create($anio, 12, 31),
                'regimen'              => $regimen,
                'anios_antiguedad'     => 9,
                'dias_generados'       => $gen,
                'dias_utilizados'      => $gen - $saldo,
                'dias_saldo'           => $saldo,
                'saldo_acumulado'      => $saldo,
                'estado'               => 'abierto',
            ]);
        });
    };

    $this->uath = User::create([
        'email'        => 'uath-tope@example.com',
        'usuario_ti'   => 'uathtope',
        'password'     => bcrypt('123456'),
        'primer_login' => false,
    ]);
    $this->uath->assignRole('admin-uath');
    $this->actingAs($this->uath, 'sanctum');

    $this->excedentes = fn () => collect(
        $this->getJson('/api/v1/asistencia/periodos-vacaciones/excedentes')->assertOk()->json('datos')
    )->keyBy('servidor_id');
});

test('un LOSEP con 70 días excede en 10; con 60 está en alerta pero no excede; con 40 no aparece', function () {
    $excedido = ($this->servidor)();
    ($this->periodos)($excedido, [20, 20, 20, 10]);

    $enElTope = ($this->servidor)();
    ($this->periodos)($enElTope, [20, 20, 20]);

    $holgado = ($this->servidor)();
    ($this->periodos)($holgado, [20, 20]);

    $filas = ($this->excedentes)();

    expect($filas[$excedido->id]['excedente'])->toEqual(10)
        ->and($filas[$excedido->id]['tope'])->toEqual(60)
        ->and($filas[$enElTope->id]['excedente'])->toEqual(0)
        ->and($filas->has($holgado->id))->toBeFalse();
});

test('en el Código del Trabajo el tope son tres años de lo que genera', function () {
    $ct = ($this->servidor)(RegimenLaboral::CODIGO_TRABAJO);
    ($this->periodos)($ct, [16, 16, 16, 16], 'codigo_trabajo');

    $fila = ($this->excedentes)()[$ct->id];

    expect($fila['tope'])->toEqual(48)
        ->and($fila['excedente'])->toEqual(16);
});

test('servicios profesionales no tiene tope ni aparece', function () {
    $sp = ($this->servidor)(RegimenLaboral::SERVICIOS_PROFESIONALES);
    ($this->periodos)($sp, [30, 30, 30]);

    expect(($this->excedentes)()->has($sp->id))->toBeFalse();
});

test('vencer el excedente toma de los períodos más antiguos y queda en la bitácora', function () {
    $servidor = ($this->servidor)();
    $periodos = ($this->periodos)($servidor, [5, 20, 20, 25], generados: [20, 20, 20, 25]);

    $respuesta = $this->postJson("/api/v1/asistencia/periodos-vacaciones/servidores/{$servidor->id}/vencer-excedente");

    $respuesta->assertOk();
    expect($respuesta->json('mensaje'))->toContain('Vencieron 10.00 días')
        ->and($respuesta->json('datos.tramos'))->toBe([
            ['anio' => $this->anio - 3, 'dias' => 5],
            ['anio' => $this->anio - 2, 'dias' => 5],
        ]);

    [$primero, $segundo, $tercero, $cuarto] = $periodos->map->fresh()->all();

    expect((float) $primero->dias_vencidos)->toBe(5.0)
        ->and((float) $primero->dias_saldo)->toBe(0.0)
        ->and((float) $segundo->dias_vencidos)->toBe(5.0)
        ->and((float) $segundo->dias_saldo)->toBe(15.0)
        ->and((float) $tercero->dias_vencidos)->toBe(0.0)
        ->and((float) $cuarto->saldo_acumulado)->toBe(60.0)
        // No son días gozados: los utilizados no se tocan.
        ->and((float) $primero->dias_utilizados)->toBe(15.0);

    expect(app(PeriodoVacacionService::class)->saldoTotal($servidor->id))->toBe(60.0);

    $registro = Activity::where('log_name', 'periodos-vacaciones')
        ->where('description', 'Vencimiento de días sobre el tope de acumulación')
        ->first();

    expect($registro)->not->toBeNull()
        ->and($registro->causer_id)->toBe($this->uath->id)
        ->and($registro->properties['dias_vencidos'])->toEqual(10);
});

test('sin excedente no hay nada que vencer', function () {
    $servidor = ($this->servidor)();
    ($this->periodos)($servidor, [20, 20, 20]);

    $respuesta = $this->postJson("/api/v1/asistencia/periodos-vacaciones/servidores/{$servidor->id}/vencer-excedente");

    $respuesta->assertStatus(422);
    expect($respuesta->json('mensaje'))->toContain('no hay excedente');
});

test('regenerar un período no devuelve los días vencidos', function () {
    $servidor = ($this->servidor)();
    $periodos = ($this->periodos)($servidor, [20, 20, 20, 15]);

    $this->postJson("/api/v1/asistencia/periodos-vacaciones/servidores/{$servidor->id}/vencer-excedente")->assertOk();

    $antiguo = $periodos->first()->fresh();
    expect((float) $antiguo->dias_saldo)->toBe(5.0);

    app(PeriodoVacacionService::class)->generarPeriodo($servidor->fresh(), $this->anio - 3);

    // Regenerar recalcula lo generado —la LOSEP da 30, no los 20 del
    // fixture—, pero los 15 vencidos no vuelven: el saldo es lo generado
    // menos lo vencido.
    $regenerado = $antiguo->fresh();

    expect((float) $regenerado->dias_vencidos)->toBe(15.0)
        ->and((float) $regenerado->dias_saldo)->toBe((float) $regenerado->dias_generados - 15.0);
});

test('el asistente de Talento Humano ve la lista pero no vence', function () {
    $servidor = ($this->servidor)();
    ($this->periodos)($servidor, [20, 20, 20, 15]);

    $asistente = User::create([
        'email'        => 'asistente-tope@example.com',
        'usuario_ti'   => 'asistope',
        'password'     => bcrypt('123456'),
        'primer_login' => false,
    ]);
    $asistente->assignRole('asistente-uath');
    $this->actingAs($asistente, 'sanctum');

    $this->getJson('/api/v1/asistencia/periodos-vacaciones/excedentes')->assertOk();
    $this->postJson("/api/v1/asistencia/periodos-vacaciones/servidores/{$servidor->id}/vencer-excedente")
        ->assertForbidden();
});

test('el resumen avisa según el tope de su régimen', function () {
    $losep = ($this->servidor)();
    ($this->periodos)($losep, [20, 25]);

    $ct = ($this->servidor)(RegimenLaboral::CODIGO_TRABAJO);
    ($this->periodos)($ct, [15, 15], 'codigo_trabajo');

    $resumen = fn (Servidor $s) => $this->getJson(
        "/api/v1/asistencia/periodos-vacaciones/servidores/{$s->id}/resumen"
    )->assertOk()->json('datos');

    // LOSEP: 45 de 60 ya es alerta.
    expect($resumen($losep)['alerta_limite'])->toBeTrue()
        ->and($resumen($losep)['tope'])->toEqual(60)
        // Código del Trabajo: 30 de 45 no lo es. Antes daba el aviso LOSEP a
        // cualquiera desde 45, y a este no se lo daba nunca antes de tiempo.
        ->and($resumen($ct)['alerta_limite'])->toBeFalse()
        ->and($resumen($ct)['tope'])->toEqual(45);
});
