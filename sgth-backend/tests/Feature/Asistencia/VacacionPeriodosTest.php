<?php

/*
| El descuento de una vacación se reparte entre los períodos abiertos.
|
| `descontarDias()` tocaba solo el período del año de la vacación: si no
| alcanzaba, el resto se perdía, aunque la solicitud hubiera pasado el control
| de saldo gracias a los años anteriores. Con 10 días de un año y 15 del
| siguiente, gozar 20 dejaba 10 en vez de 5.
|
| Ahora se gasta del período más antiguo al más nuevo, sin tocar años
| posteriores al de la vacación, y cada tramo queda anotado.
|
| Las fechas van al año próximo y fijas: así el año de la vacación es siempre
| el mismo y el test no depende de en qué día del año corra.
*/

use App\Enums\RegimenLaboral;
use App\Exceptions\ReglaNegocioException;
use App\Models\Asistencia\PeriodoVacacion;
use App\Models\Asistencia\Vacacion;
use App\Models\Asistencia\VacacionDescuento;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Asistencia\PeriodoVacacionService;
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

    $unidad = unidadDePrueba();

    $this->servidor = Servidor::create([
        'cedula'                       => '0800000601',
        'nombre'                       => 'Pedro',
        'apellido'                     => 'Períodos',
        'puesto_id'                    => puestoDePrueba($unidad)->id,
        'unidad_administrativa_id'     => $unidad->id,
        'regimen_laboral'              => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion'    => now()->subYears(8),
        'fecha_ingreso_sector_publico' => now()->subYears(8),
        'estado'                       => true,
    ]);

    $uath = User::create([
        'email'        => 'uath-periodos@example.com',
        'usuario_ti'   => 'uathper',
        'password'     => bcrypt('123456'),
        'primer_login' => false,
    ]);
    $uath->assignRole('admin-uath');
    $this->actingAs($uath, 'sanctum');

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

    $this->aprobarDias = function (float $dias) {
        $vacacion = Vacacion::create([
            'servidor_id'      => $this->servidor->id,
            'fecha_inicio'     => $this->inicio->toDateString(),
            'fecha_fin'        => $this->inicio->copy()->addDays((int) $dias - 1)->toDateString(),
            'dias_solicitados' => $dias,
            'tipo_dias'        => 'habiles',
            'estado'           => 'pendiente',
            'motivo'           => 'vacaciones_anuales',
        ]);

        return [
            $vacacion,
            $this->putJson("/api/v1/asistencia/vacaciones/{$vacacion->id}", ['estado' => 'aprobada']),
        ];
    };
});

test('lo que no alcanza en un período sale del siguiente, del más antiguo al más nuevo', function () {
    $anterior = ($this->periodo)($this->anio - 1, 20, 10);
    $actual   = ($this->periodo)($this->anio, 15, 0);

    [, $respuesta] = ($this->aprobarDias)(12);
    $respuesta->assertOk();

    $anterior->refresh();
    $actual->refresh();

    expect((float) $anterior->dias_utilizados)->toBe(20.0)
        ->and((float) $anterior->dias_saldo)->toBe(0.0)
        ->and((float) $actual->dias_utilizados)->toBe(2.0)
        ->and((float) $actual->dias_saldo)->toBe(13.0);
});

test('el caso de la auditoría: gozar 20 de 25 deja 5, no 10', function () {
    ($this->periodo)($this->anio - 1, 20, 10);
    ($this->periodo)($this->anio, 15, 0);

    [, $respuesta] = ($this->aprobarDias)(20);
    $respuesta->assertOk();

    expect(app(PeriodoVacacionService::class)->saldoTotal($this->servidor->id))->toBe(5.0);
});

test('cada período anota lo que se le tomó', function () {
    $anterior = ($this->periodo)($this->anio - 1, 20, 10);
    $actual   = ($this->periodo)($this->anio, 15, 0);

    [$vacacion] = ($this->aprobarDias)(12);

    $tramos = VacacionDescuento::where('vacacion_id', $vacacion->id)
        ->orderBy('id')
        ->get()
        ->map(fn ($d) => [$d->periodo_vacacion_id, $d->dias])
        ->all();

    expect($tramos)->toBe([[$anterior->id, 10.0], [$actual->id, 2.0]]);
});

test('no se toman días de un año posterior al de la vacación', function () {
    $actual    = ($this->periodo)($this->anio, 20, 17);
    $siguiente = ($this->periodo)($this->anio + 1, 15, 0);

    [$vacacion, $respuesta] = ($this->aprobarDias)(5);

    $respuesta->assertStatus(422);
    expect($respuesta->json('mensaje'))->toContain('Saldo insuficiente')
        ->and((float) $actual->fresh()->dias_utilizados)->toBe(17.0)
        ->and((float) $siguiente->fresh()->dias_utilizados)->toBe(0.0)
        ->and($vacacion->fresh()->estado)->toBe('pendiente');
});

test('el acumulado de cada período queda al día tras repartir', function () {
    $anterior = ($this->periodo)($this->anio - 1, 20, 10);
    $actual   = ($this->periodo)($this->anio, 15, 0);

    ($this->aprobarDias)(12);

    // El acumulado es lo que arrastran los años anteriores más el saldo propio.
    expect((float) $anterior->fresh()->saldo_acumulado)->toBe(0.0)
        ->and((float) $actual->fresh()->saldo_acumulado)->toBe(13.0);
});

test('al registrar, cuenta el saldo hasta el año de la vacación', function () {
    ($this->periodo)($this->anio, 15, 13);
    ($this->periodo)($this->anio + 1, 15, 0);

    // Lunes a viernes: 5 días hábiles contra 2 disponibles hasta ese año.
    expect(fn () => app(VacacionService::class)->solicitar([
        'motivo'       => 'vacaciones_anuales',
        'fecha_inicio' => $this->inicio->toDateString(),
        'fecha_fin'    => $this->inicio->copy()->addDays(4)->toDateString(),
    ], $this->servidor->id))->toThrow(ReglaNegocioException::class, 'Saldo insuficiente');
});
