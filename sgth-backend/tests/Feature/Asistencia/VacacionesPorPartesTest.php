<?php

/*
| Las vacaciones tomadas por partes (TH, 2026-10-20; criterio del Ministerio del
| Trabajo, oficio MDT-DJTE-2017-0878-OF): si la parte termina justo antes de un
| descanso obligatorio —sábado, domingo o feriado—, esos días se cuentan hasta la
| víspera del regreso; los que van antes del inicio, no. El regreso lo calcula
| el sistema: el primer día laborable después del fin.
|
| Del 9 al 30 de noviembre de 2026 no hay feriados nacionales: los lunes son el
| 9, el 16, el 23 y el 30.
*/

use App\Enums\RegimenLaboral;
use App\Models\Asistencia\FeriadoInstitucional;
use App\Models\Asistencia\PeriodoVacacion;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Asistencia\ConteoDeVacaciones;
use App\Services\Asistencia\VacacionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-20 10:00'));
    $this->conteo = app(ConteoDeVacaciones::class);
    $this->cuenta = fn (string $desde, string $hasta) => [
        $this->conteo->dias(Carbon::parse($desde), Carbon::parse($hasta)),
        $this->conteo->retorno(Carbon::parse($hasta))->toDateString(),
    ];
});

test('la parte que termina antes del fin de semana lo cuenta hasta la víspera del regreso', function () {
    // De martes a viernes: seis días, y vuelve el lunes.
    expect(($this->cuenta)('2026-11-10', '2026-11-13'))->toBe([6, '2026-11-16'])
        // Un viernes suelto: tres.
        ->and(($this->cuenta)('2026-11-13', '2026-11-13'))->toBe([3, '2026-11-16']);
});

test('la que termina antes de un día laborable cuenta solo lo pedido', function () {
    // De lunes a jueves: cuatro, y vuelve el viernes.
    expect(($this->cuenta)('2026-11-16', '2026-11-19'))->toBe([4, '2026-11-20'])
        // El fin de semana de en medio ya estaba dentro, y el de antes no cuenta.
        ->and(($this->cuenta)('2026-11-12', '2026-11-18'))->toBe([7, '2026-11-19']);
});

test('el feriado que sigue a la parte también se cuenta', function () {
    FeriadoInstitucional::unguard();
    FeriadoInstitucional::create([
        'fecha' => '2026-11-27', 'descripcion' => 'Feriado de prueba', 'es_nacional' => false, 'es_movil' => true,
    ]);

    // De lunes a jueves, con el viernes feriado: hasta el domingo, siete.
    expect(app(ConteoDeVacaciones::class)->dias(Carbon::parse('2026-11-23'), Carbon::parse('2026-11-26')))->toBe(7)
        ->and(app(ConteoDeVacaciones::class)->retorno(Carbon::parse('2026-11-26'))->toDateString())->toBe('2026-11-30');
});

test('la solicitud guarda esos días y el regreso que calcula el sistema', function () {
    Servidor::unguard();
    $unidad = unidadDePrueba();
    $servidor = Servidor::create([
        'cedula' => '0800000777', 'nombre' => 'Ana', 'apellido' => 'Partes',
        'puesto_id' => puestoDePrueba($unidad)->id, 'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP, 'fecha_ingreso_institucion' => '2018-01-02',
        'fecha_ingreso_sector_publico' => '2018-01-02', 'estado' => true,
    ]);
    PeriodoVacacion::create([
        'servidor_id' => $servidor->id, 'anio' => 2026, 'fecha_inicio_periodo' => '2026-01-01',
        'fecha_fin_periodo' => '2026-12-31', 'regimen' => 'losep', 'anios_antiguedad' => 8,
        'dias_generados' => 30, 'dias_utilizados' => 0, 'dias_saldo' => 30, 'saldo_acumulado' => 30, 'estado' => 'abierto',
    ]);

    // El retorno que mande quien la pide no cuenta.
    $vacacion = (new VacacionService())->solicitar([
        'motivo' => 'vacaciones_anuales', 'fecha_inicio' => '2026-11-10', 'fecha_fin' => '2026-11-13',
        'fecha_retorno' => '2026-11-14',
    ], $servidor->id);

    expect((float) $vacacion->dias_solicitados)->toBe(6.0)
        ->and($vacacion->fecha_retorno->toDateString())->toBe('2026-11-16');
});

test('el formulario pregunta los días al backend, que conoce los feriados', function () {
    User::unguard();
    $usuario = User::create([
        'email' => 'conteo@example.com', 'usuario_ti' => 'conteo', 'password' => bcrypt('123456'), 'primer_login' => false,
    ]);

    $this->actingAs($usuario, 'sanctum')
        ->getJson('/api/v1/asistencia/vacaciones/conteo?fecha_inicio=2026-11-10&fecha_fin=2026-11-13')
        ->assertOk()
        ->assertJsonPath('datos', ['dias' => 6, 'fecha_retorno' => '2026-11-16', 'dias_del_rango' => 4]);

    $this->actingAs($usuario, 'sanctum')
        ->getJson('/api/v1/asistencia/vacaciones/conteo?fecha_inicio=2026-11-13&fecha_fin=2026-11-10')
        ->assertUnprocessable();
});
