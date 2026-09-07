<?php

/*
| Las reglas que el módulo decía tener y no comprobaba.
|
| El tope de 4 horas se validaba por solicitud aunque el comentario dijera «por
| día», nadie miraba si dos permisos del mismo día se pisaban, cualquier fecha
| era buena, el folio salía de un `count()` y el descuento de vacaciones no
| tenía vuelta atrás. Cada test de aquí abajo falla contra el código anterior.
*/

use App\Enums\EstadoPermiso;
use App\Enums\RegimenLaboral;
use App\Enums\TipoPermiso;
use App\Models\Asistencia\FeriadoInstitucional;
use App\Models\Asistencia\PeriodoVacacion;
use App\Models\Asistencia\PermisoServidor;
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
    PermisoServidor::unguard();

    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $this->unidad = unidadDePrueba(['nombre' => 'Dirección de Prueba']);

    $this->servidor = Servidor::create([
        'cedula' => '0809999991',
        'nombre' => 'Ana',
        'apellido' => 'Prueba',
        'puesto_id' => puestoDePrueba($this->unidad)->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'estado' => true,
    ]);

    $this->uath = User::create([
        'email' => 'uath@example.com', 'usuario_ti' => 'uath_u',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $this->uath->assignRole('admin-uath');

    $this->recepcion = User::create([
        'email' => 'rec@example.com', 'usuario_ti' => 'rec_u',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $this->recepcion->assignRole('recepcion');

    // Los permisos personales se pagan con vacaciones, así que casi todo lo de
    // aquí necesita un período abierto con saldo.
    $this->periodo = periodoAbiertoDePrueba($this->servidor, 15.0);
});

function periodoAbiertoDePrueba(Servidor $servidor, float $saldo): PeriodoVacacion
{
    return PeriodoVacacion::create([
        'servidor_id'          => $servidor->id,
        'anio'                 => (int) now()->format('Y'),
        'fecha_inicio_periodo' => now()->startOfYear()->toDateString(),
        'fecha_fin_periodo'    => now()->endOfYear()->toDateString(),
        'regimen'              => 'losep',
        'anios_antiguedad'     => 3,
        'dias_generados'       => $saldo,
        'dias_utilizados'      => 0,
        'dias_saldo'           => $saldo,
        'saldo_acumulado'      => $saldo,
        'estado'               => 'abierto',
    ]);
}

/** Una fecha hábil segura para un permiso planificable. */
function proximoDiaHabil(): Carbon
{
    $fecha = Carbon::today()->addDay();

    while ($fecha->isWeekend() || FeriadoInstitucional::esFeriado($fecha)->exists()) {
        $fecha->addDay();
    }

    return $fecha;
}

function crearPermiso(array $datos = []): \Illuminate\Testing\TestResponse
{
    return test()->actingAs(test()->uath, 'sanctum')->postJson('/api/v1/asistencia/permisos', array_merge([
        'servidor_id' => test()->servidor->id,
        'tipo'        => TipoPermiso::PERSONAL->value,
        'fecha'       => proximoDiaHabil()->toDateString(),
        'hora_inicio' => '08:00',
        'hora_fin'    => '10:00',
    ], $datos));
}

// ── Tope diario y solapamiento ───────────────────────────────────────

test('el tope de 4 horas es por día y no por solicitud', function () {
    $fecha = proximoDiaHabil()->toDateString();

    crearPermiso(['fecha' => $fecha, 'hora_inicio' => '08:00', 'hora_fin' => '12:00'])
        ->assertStatus(201);

    // Cuatro horas más, en una franja que no se solapa.
    $segundo = crearPermiso([
        'fecha' => $fecha, 'hora_inicio' => '13:00', 'hora_fin' => '17:00',
    ]);

    $segundo->assertStatus(422);
    expect($segundo->json('mensaje'))->toContain('4 horas por día');
});

test('dos permisos del mismo día no pueden solaparse', function () {
    $fecha = proximoDiaHabil()->toDateString();

    crearPermiso(['fecha' => $fecha, 'hora_inicio' => '08:00', 'hora_fin' => '10:00'])
        ->assertStatus(201);

    $solapado = crearPermiso([
        'tipo' => TipoPermiso::OFICIAL->value,
        'observacion' => 'Reunión en la Prefectura',
        'fecha' => $fecha, 'hora_inicio' => '09:00', 'hora_fin' => '11:00',
    ]);

    $solapado->assertStatus(422);
    expect($solapado->json('mensaje'))->toContain('no pueden solaparse');
});

test('dos permisos del mismo día en franjas separadas sí se conceden', function () {
    $fecha = proximoDiaHabil()->toDateString();

    crearPermiso(['fecha' => $fecha, 'hora_inicio' => '08:00', 'hora_fin' => '10:00'])
        ->assertStatus(201);

    crearPermiso(['fecha' => $fecha, 'hora_inicio' => '14:00', 'hora_fin' => '16:00'])
        ->assertStatus(201);

    expect(PermisoServidor::where('servidor_id', $this->servidor->id)->count())->toBe(2);
});

test('un permiso anulado deja libre la franja que ocupaba', function () {
    $fecha = proximoDiaHabil()->toDateString();

    $primero = crearPermiso(['fecha' => $fecha, 'hora_inicio' => '08:00', 'hora_fin' => '12:00'])
        ->assertStatus(201);

    $this->actingAs($this->uath, 'sanctum')
        ->putJson("/api/v1/asistencia/permisos/{$primero->json('datos.id')}/anular")
        ->assertStatus(200);

    crearPermiso(['fecha' => $fecha, 'hora_inicio' => '08:00', 'hora_fin' => '12:00'])
        ->assertStatus(201);
});

// ── Fechas ───────────────────────────────────────────────────────────

test('un permiso personal no se registra con más de 3 días hábiles de atraso', function () {
    $respuesta = crearPermiso(['fecha' => Carbon::today()->subDays(20)->toDateString()]);

    $respuesta->assertStatus(422);
    expect($respuesta->json('mensaje'))->toContain('días hábiles de atraso');
});

test('un permiso personal sí se registra dentro de la tolerancia', function () {
    // Ayer, saltando fin de semana y feriado hacia atrás.
    $ayerHabil = Carbon::today();
    do {
        $ayerHabil->subDay();
    } while ($ayerHabil->isWeekend() || FeriadoInstitucional::esFeriado($ayerHabil)->exists());

    crearPermiso(['fecha' => $ayerHabil->toDateString()])->assertStatus(201);
});

test('un permiso por enfermedad no puede tener fecha futura', function () {
    $respuesta = crearPermiso([
        'tipo'  => TipoPermiso::ENFERMEDAD->value,
        'fecha' => Carbon::today()->addDays(5)->toDateString(),
    ]);

    $respuesta->assertStatus(422);
    expect($respuesta->json('mensaje'))->toContain('fecha futura');
});

test('un permiso por enfermedad sí se registra hacia atrás, que para eso existe el plazo', function () {
    crearPermiso([
        'tipo'  => TipoPermiso::ENFERMEDAD->value,
        'fecha' => Carbon::today()->subDays(2)->toDateString(),
    ])->assertStatus(201);
});

// ── Saldo de vacaciones como tope ────────────────────────────────────

test('sin período de vacaciones abierto no se concede un permiso personal', function () {
    $this->periodo->update(['estado' => 'cerrado']);

    $respuesta = crearPermiso();

    $respuesta->assertStatus(422);
    expect($respuesta->json('mensaje'))->toContain('período de vacaciones abierto');
});

test('con saldo insuficiente no se concede un permiso personal', function () {
    // 2 horas son 0,25 días; queda menos que eso.
    $this->periodo->update(['dias_saldo' => 0.1]);

    $respuesta = crearPermiso();

    $respuesta->assertStatus(422);
    expect($respuesta->json('mensaje'))->toContain('Saldo de vacaciones insuficiente');
});

test('un permiso oficial no mira el saldo, porque no lo descuenta', function () {
    $this->periodo->update(['dias_saldo' => 0]);

    crearPermiso([
        'tipo' => TipoPermiso::OFICIAL->value,
        'observacion' => 'Diligencia en el Ministerio',
    ])->assertStatus(201);
});

// ── Folio ────────────────────────────────────────────────────────────

test('el folio no se reutiliza después de un borrado en blando', function () {
    $primero = crearPermiso(['hora_inicio' => '08:00', 'hora_fin' => '10:00'])
        ->assertStatus(201);

    // El `count()` anterior no veía los borrados: al desaparecer una fila la
    // secuencia retrocedía y el siguiente permiso chocaba contra el índice único.
    PermisoServidor::find($primero->json('datos.id'))->delete();

    $segundo = crearPermiso(['hora_inicio' => '08:00', 'hora_fin' => '10:00'])
        ->assertStatus(201);

    expect($segundo->json('datos.folio'))->not->toBe($primero->json('datos.folio'));
});

test('el folio arranca en 00001 y sigue la secuencia del año', function () {
    $anio = now()->format('Y');

    expect(crearPermiso(['hora_inicio' => '08:00', 'hora_fin' => '09:00'])->json('datos.folio'))
        ->toBe("PER-{$anio}-00001");

    expect(crearPermiso(['hora_inicio' => '10:00', 'hora_fin' => '11:00'])->json('datos.folio'))
        ->toBe("PER-{$anio}-00002");
});

// ── Vencimiento con feriados ─────────────────────────────────────────

test('el plazo de 72 horas laborables salta los feriados', function () {
    // Un miércoles limpio, y el jueves y viernes siguientes de feriado: el
    // plazo tiene que estirarse hasta el miércoles de la semana siguiente.
    $miercoles = Carbon::today()->next(Carbon::WEDNESDAY);

    foreach ([1, 2] as $dias) {
        $feriado = $miercoles->copy()->addDays($dias);
        FeriadoInstitucional::create([
            'fecha' => $feriado->toDateString(),
            'descripcion' => 'Feriado de prueba',
            'es_nacional' => true,
            'es_movil' => true,
        ]);
    }

    $permiso = PermisoServidor::find(
        crearPermiso(['fecha' => $miercoles->toDateString()])->json('datos.id')
    );

    // Sin feriados habría vencido el lunes; con ellos, el miércoles siguiente.
    expect(Carbon::parse($permiso->vence_en)->toDateString())
        ->toBe($miercoles->copy()->addWeek()->toDateString());
});

// ── Confirmar, revertir y rechazar ───────────────────────────────────

test('confirmar descuenta el saldo, y revertir lo devuelve', function () {
    $folio = crearPermiso(['hora_inicio' => '08:00', 'hora_fin' => '12:00'])
        ->json('datos.folio');

    $this->actingAs($this->recepcion, 'sanctum')
        ->postJson("/api/v1/asistencia/permisos/confirmar/{$folio}")
        ->assertStatus(200);

    // 4 horas = medio día.
    expect((float) $this->periodo->fresh()->dias_saldo)->toBe(14.5);

    $permiso = PermisoServidor::where('folio', $folio)->first();

    $this->actingAs($this->uath, 'sanctum')
        ->postJson("/api/v1/asistencia/permisos/{$permiso->id}/revertir-confirmacion", [
            'motivo' => 'Recepción confirmó el folio equivocado.',
        ])->assertStatus(200);

    $permiso->refresh();

    expect((float) $this->periodo->fresh()->dias_saldo)->toBe(15.0)
        ->and($permiso->estado)->toBe(EstadoPermiso::PENDIENTE)
        ->and($permiso->confirmado_por)->toBeNull();
});

test('confirmar sin período abierto ya no concede horas en silencio', function () {
    $folio = crearPermiso()->json('datos.folio');

    $this->periodo->update(['estado' => 'cerrado']);

    $respuesta = $this->actingAs($this->recepcion, 'sanctum')
        ->postJson("/api/v1/asistencia/permisos/confirmar/{$folio}");

    $respuesta->assertStatus(422);
    expect($respuesta->json('mensaje'))->toContain('período de vacaciones abierto');
    expect(PermisoServidor::where('folio', $folio)->first()->estado)
        ->toBe(EstadoPermiso::PENDIENTE);
});

test('recepción rechaza el documento que llega mal', function () {
    $id = crearPermiso()->json('datos.id');

    $this->actingAs($this->recepcion, 'sanctum')
        ->postJson("/api/v1/asistencia/permisos/{$id}/rechazar", [
            'motivo' => 'Llegó sin la firma del jefe inmediato.',
        ])->assertStatus(200);

    $permiso = PermisoServidor::find($id);

    expect($permiso->estado)->toBe(EstadoPermiso::RECHAZADO)
        ->and($permiso->motivo_rechazo)->toBe('Llegó sin la firma del jefe inmediato.')
        ->and($permiso->rechazado_por)->toBe($this->recepcion->id);
});

test('el rechazo exige un motivo', function () {
    $id = crearPermiso()->json('datos.id');

    $this->actingAs($this->recepcion, 'sanctum')
        ->postJson("/api/v1/asistencia/permisos/{$id}/rechazar", ['motivo' => ''])
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['motivo']]);
});

test('no se rechaza un permiso ya confirmado: para eso está revertir', function () {
    $folio = crearPermiso()->json('datos.folio');

    $this->actingAs($this->recepcion, 'sanctum')
        ->postJson("/api/v1/asistencia/permisos/confirmar/{$folio}")
        ->assertStatus(200);

    $id = PermisoServidor::where('folio', $folio)->first()->id;

    $this->actingAs($this->recepcion, 'sanctum')
        ->postJson("/api/v1/asistencia/permisos/{$id}/rechazar", [
            'motivo' => 'Me equivoqué al confirmar.',
        ])->assertStatus(422);
});

test('recepción no puede revertir una confirmación: eso devuelve saldo', function () {
    $folio = crearPermiso()->json('datos.folio');

    $this->actingAs($this->recepcion, 'sanctum')
        ->postJson("/api/v1/asistencia/permisos/confirmar/{$folio}")
        ->assertStatus(200);

    $id = PermisoServidor::where('folio', $folio)->first()->id;

    $this->actingAs($this->recepcion, 'sanctum')
        ->postJson("/api/v1/asistencia/permisos/{$id}/revertir-confirmacion", [
            'motivo' => 'Quiero deshacerlo.',
        ])->assertStatus(403);
});

// ── Consolidado ──────────────────────────────────────────────────────

test('el consolidado no cuenta las faltas injustificadas como permiso', function () {
    $folio = crearPermiso(['hora_inicio' => '08:00', 'hora_fin' => '10:00'])
        ->json('datos.folio');

    PermisoServidor::where('folio', $folio)->update([
        'estado' => EstadoPermiso::FALTA_INJUSTIFICADA->value,
    ]);

    $respuesta = $this->actingAs($this->uath, 'sanctum')->getJson(
        '/api/v1/asistencia/consolidado-permisos'
        . '?fecha_inicio=' . Carbon::today()->subMonth()->toDateString()
        . '&fecha_fin=' . Carbon::today()->addMonth()->toDateString()
    )->assertStatus(200);

    expect($respuesta->json('datos.totales.total_permisos'))->toBe(0);
});
