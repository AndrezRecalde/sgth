<?php

/*
| El estado «gozada».
|
| Estaba en el enum desde la primera migración y nada lo asignaba: el filtro
| «Gozada» salía siempre vacío y una vacación de hace meses seguía «aprobada».
| Lo pone al día sgth:vacaciones:marcar-gozadas, que corre cada mañana.
*/

use App\Enums\RegimenLaboral;
use App\Exceptions\ReglaNegocioException;
use App\Models\Asistencia\Vacacion;
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

    $unidad = unidadDePrueba();

    $this->servidor = Servidor::create([
        'cedula'                   => '0800000801',
        'nombre'                   => 'Gonzalo',
        'apellido'                 => 'Gozada',
        'puesto_id'                => puestoDePrueba($unidad)->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral'          => RegimenLaboral::LOSEP,
        'estado'                   => true,
    ]);

    $this->vacacion = fn (string $estado, Carbon $fin) => Vacacion::create([
        'servidor_id'      => $this->servidor->id,
        'fecha_inicio'     => $fin->copy()->subDays(2)->toDateString(),
        'fecha_fin'        => $fin->toDateString(),
        'dias_solicitados' => 3,
        'tipo_dias'        => 'habiles',
        'estado'           => $estado,
        'motivo'           => 'vacaciones_anuales',
    ]);
});

test('las aprobadas que ya terminaron pasan a gozada, y nada más', function () {
    $terminada   = ($this->vacacion)('aprobada', Carbon::yesterday());
    // El último día todavía se está gozando.
    $terminaHoy  = ($this->vacacion)('aprobada', Carbon::today());
    $futura      = ($this->vacacion)('aprobada', Carbon::tomorrow());
    $pendiente   = ($this->vacacion)('pendiente', Carbon::yesterday());
    $rechazada   = ($this->vacacion)('rechazada', Carbon::yesterday());

    $this->artisan('sgth:vacaciones:marcar-gozadas')
        ->expectsOutputToContain('1 solicitud(es)')
        ->assertSuccessful();

    expect($terminada->fresh()->estado)->toBe('gozada')
        ->and($terminaHoy->fresh()->estado)->toBe('aprobada')
        ->and($futura->fresh()->estado)->toBe('aprobada')
        ->and($pendiente->fresh()->estado)->toBe('pendiente')
        ->and($rechazada->fresh()->estado)->toBe('rechazada');
});

test('la fecha de corte se puede fijar', function () {
    $vacacion = ($this->vacacion)('aprobada', Carbon::today()->addDays(5));

    $this->artisan('sgth:vacaciones:marcar-gozadas', [
        '--fecha' => Carbon::today()->addDays(10)->toDateString(),
    ])->assertSuccessful();

    expect($vacacion->fresh()->estado)->toBe('gozada');
});

test('una gozada ya no se anula', function () {
    $vacacion = ($this->vacacion)('aprobada', Carbon::yesterday());
    $this->artisan('sgth:vacaciones:marcar-gozadas')->assertSuccessful();

    $usuario = User::create([
        'email'        => 'uath-gozada@example.com',
        'usuario_ti'   => 'uathgoz',
        'password'     => bcrypt('123456'),
        'primer_login' => false,
    ]);

    expect(fn () => app(VacacionService::class)->anular($vacacion->id, 'Por error', $usuario))
        ->toThrow(ReglaNegocioException::class, 'gozada');
});
