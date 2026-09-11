<?php

/*
| Días en que no hay jornada de la que pedir permiso.
|
| - Un permiso personal se aceptaba en sábado, domingo o feriado, y descontaba
|   vacaciones por un día sin jornada (reproducido en la auditoría del módulo).
|   Decidido con Talento Humano: se bloquea solo el personal; oficial,
|   enfermedad y calamidad se siguen admitiendo, por el personal con turnos.
| - Un permiso se aceptaba dentro de unas vacaciones ya aprobadas del mismo
|   servidor: ese día no hay jornada, y un personal descontaba el saldo dos
|   veces. Bloquean las vacaciones pendientes, aprobadas y gozadas.
*/

use App\Enums\RegimenLaboral;
use App\Models\Asistencia\FeriadoInstitucional;
use App\Models\Asistencia\PeriodoVacacion;
use App\Models\Asistencia\PermisoServidor;
use App\Models\Asistencia\Vacacion;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();
    Vacacion::unguard();
    FeriadoInstitucional::unguard();

    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $unidad = unidadDePrueba();

    $this->servidor = Servidor::create([
        'cedula'                   => '0800006001',
        'nombre'                   => 'Diana',
        'apellido'                 => 'Laborable',
        'puesto_id'                => puestoDePrueba($unidad)->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral'          => RegimenLaboral::LOSEP,
        'estado'                   => true,
    ]);

    PeriodoVacacion::create([
        'servidor_id'          => $this->servidor->id,
        'anio'                 => now()->year,
        'fecha_inicio_periodo' => Carbon::create(now()->year, 1, 1),
        'fecha_fin_periodo'    => Carbon::create(now()->year, 12, 31),
        'regimen'              => 'losep',
        'anios_antiguedad'     => 3,
        'dias_generados'       => 15,
        'dias_utilizados'      => 0,
        'dias_saldo'           => 15,
        'saldo_acumulado'      => 15,
        'estado'               => 'abierto',
    ]);

    $this->uath = User::create([
        'email'        => 'uath-laborable@example.com',
        'usuario_ti'   => 'uathlab',
        'password'     => bcrypt('123456'),
        'primer_login' => false,
    ]);
    $this->uath->assignRole('admin-uath');

    $this->crear = fn (array $datos) => $this->actingAs($this->uath, 'sanctum')
        ->postJson('/api/v1/asistencia/permisos', array_merge([
            'servidor_id' => $this->servidor->id,
            'tipo'        => 'personal',
            'hora_inicio' => '08:00',
            'hora_fin'    => '10:00',
            'observacion' => 'Comisión de servicio',
        ], $datos));

    $folio = 0;
    $this->vacacion = function (string $estado, Carbon $desde, Carbon $hasta) use (&$folio) {
        return Vacacion::create([
            'servidor_id'      => $this->servidor->id,
            'fecha_inicio'     => $desde->toDateString(),
            'fecha_fin'        => $hasta->toDateString(),
            'dias_solicitados' => $desde->diffInDays($hasta) + 1,
            'tipo_dias'        => 'habiles',
            'estado'           => $estado,
            'motivo'           => 'vacaciones_anuales',
            'folio'            => sprintf('VAC-2099-%05d', ++$folio),
        ]);
    };

    // La semana que viene: el lunes es siempre futuro, sea cual sea el día en
    // que corra el test.
    $this->lunes = Carbon::today()->next(Carbon::MONDAY);
});

// ── Día no laborable ────────────────────────────────────────────────

test('un permiso personal no se registra en fin de semana', function (int $dia, string $nombre) {
    $respuesta = ($this->crear)(['fecha' => Carbon::today()->next($dia)->toDateString()]);

    $respuesta->assertStatus(422);
    expect($respuesta->json('mensaje'))->toContain("es {$nombre}")
        ->and(PermisoServidor::count())->toBe(0);
})->with([
    'sábado'  => [Carbon::SATURDAY, 'sábado'],
    'domingo' => [Carbon::SUNDAY, 'domingo'],
]);

test('ni en un feriado', function () {
    $miercoles = $this->lunes->copy()->addDays(2);
    FeriadoInstitucional::create([
        'fecha'       => $miercoles->toDateString(),
        'descripcion' => 'Feriado de prueba',
        'es_nacional' => true,
        'es_movil'    => true,
    ]);

    $respuesta = ($this->crear)(['fecha' => $miercoles->toDateString()]);

    $respuesta->assertStatus(422);
    expect($respuesta->json('mensaje'))->toContain('es feriado');
});

test('oficial, enfermedad y calamidad sí se admiten en fin de semana', function () {
    // Un lunes a media mañana: el sábado anterior está dentro del plazo de
    // respaldo de enfermedad y calamidad, y el siguiente es futuro para el
    // oficial.
    $lunes = $this->lunes->copy()->setTime(10, 0);
    $this->travelTo($lunes);

    ($this->crear)([
        'tipo'  => 'oficial',
        'fecha' => $lunes->copy()->next(Carbon::SATURDAY)->toDateString(),
    ])->assertCreated();

    foreach (['enfermedad', 'calamidad'] as $i => $tipo) {
        ($this->crear)([
            'tipo'        => $tipo,
            'fecha'       => $lunes->copy()->subDays(2)->toDateString(),
            'hora_inicio' => $i === 0 ? '08:00' : '10:00',
            'hora_fin'    => $i === 0 ? '10:00' : '12:00',
        ])->assertCreated();
    }
});

test('un permiso personal en día laborable sigue admitiéndose', function () {
    ($this->crear)(['fecha' => $this->lunes->toDateString()])->assertCreated();
});

// ── Cruce con vacaciones ────────────────────────────────────────────

test('ningún permiso cae dentro de unas vacaciones vigentes', function (string $estado) {
    $vacacion = ($this->vacacion)($estado, $this->lunes, $this->lunes->copy()->addDays(4));
    $miercoles = $this->lunes->copy()->addDays(2)->toDateString();

    foreach (['personal', 'oficial'] as $tipo) {
        $respuesta = ($this->crear)(['tipo' => $tipo, 'fecha' => $miercoles]);

        $respuesta->assertStatus(422);
        expect($respuesta->json('mensaje'))->toContain($vacacion->folio);
    }

    expect(PermisoServidor::count())->toBe(0);
})->with(['pendiente', 'aprobada', 'gozada']);

test('una vacación rechazada deja la fecha libre', function () {
    ($this->vacacion)('rechazada', $this->lunes, $this->lunes->copy()->addDays(4));

    ($this->crear)(['fecha' => $this->lunes->copy()->addDays(2)->toDateString()])->assertCreated();
});

test('el día siguiente a las vacaciones sí se admite', function () {
    ($this->vacacion)('aprobada', $this->lunes, $this->lunes->copy()->addDays(2));

    ($this->crear)(['fecha' => $this->lunes->copy()->addDays(3)->toDateString()])->assertCreated();
});
