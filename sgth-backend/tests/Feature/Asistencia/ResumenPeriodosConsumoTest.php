<?php

/*
| El resumen de períodos cuenta como gastado solo lo que de verdad se descontó.
|
| Los días por permisos personales se atribuían por la FECHA del permiso y con
| un filtro de estado por exclusión —«todos menos anulado y pendiente»—, y de
| ahí salían tres cifras falsas a la vez:
|
| - Un permiso RECHAZADO por Recepción y una FALTA INJUSTIFICADA aparecían como
|   días descontados, cuando ninguno de los dos pasa por la confirmación, que es
|   lo único que toca el saldo.
| - Un permiso de un servidor del Código del Trabajo también, aunque en ese
|   régimen los permisos no descuentan de vacaciones.
| - Las horas se cargaban al período del año del permiso, no al período del que
|   de verdad salieron: el descuento se reparte del más antiguo al más nuevo.
|
| Ahora se leen de `permiso_descuentos`, igual que las vacaciones se leen de
| `vacacion_descuentos`: un permiso que no descontó no tiene fila que sumar.
*/

use App\Enums\EstadoPermiso;
use App\Enums\RegimenLaboral;
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
    PermisoServidor::unguard();

    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $unidad = unidadDePrueba();

    $this->crearServidor = fn (string $cedula, RegimenLaboral $regimen) => Servidor::create([
        'cedula'                   => $cedula,
        'nombre'                   => 'Rosa',
        'apellido'                 => 'Resumen',
        'puesto_id'                => puestoDePrueba($unidad)->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral'          => $regimen,
        'estado'                   => true,
    ]);

    $this->servidor = ($this->crearServidor)('0800006001', RegimenLaboral::LOSEP);

    $this->jefe = ($this->crearServidor)('0800006099', RegimenLaboral::LOSEP);

    $usuarioCon = function (string $rol) {
        $usuario = User::create([
            'email'        => uniqid('res').'@example.com',
            'usuario_ti'   => uniqid('res'),
            'password'     => bcrypt('123456'),
            'primer_login' => false,
        ]);
        $usuario->assignRole($rol);

        return $usuario;
    };

    $this->uath      = $usuarioCon('admin-uath');
    $this->recepcion = $usuarioCon('recepcion');

    $this->periodo = fn (Servidor $servidor, int $anio, float $generados, float $utilizados) => PeriodoVacacion::create([
        'servidor_id'          => $servidor->id,
        'anio'                 => $anio,
        'fecha_inicio_periodo' => Carbon::create($anio, 1, 1),
        'fecha_fin_periodo'    => Carbon::create($anio, 12, 31),
        'regimen'              => $servidor->regimen_laboral === RegimenLaboral::LOSEP
            ? 'losep' : 'codigo_trabajo',
        'anios_antiguedad'     => 3,
        'dias_generados'       => $generados,
        'dias_utilizados'      => $utilizados,
        'dias_saldo'           => $generados - $utilizados,
        'saldo_acumulado'      => $generados - $utilizados,
        'estado'               => 'abierto',
    ]);

    $this->anio = now()->year;

    // Un día hábil: el permiso personal no se registra en fin de semana.
    $this->fecha = Carbon::today()->addDay();
    while ($this->fecha->isWeekend()) {
        $this->fecha->addDay();
    }

    // 4 horas son 0,5 días.
    $this->registrar = fn (Servidor $servidor) => $this->actingAs($this->uath, 'sanctum')
        ->postJson('/api/v1/asistencia/permisos', [
            'servidor_id' => $servidor->id,
            'jefe_id'     => $this->jefe->id,
            'tipo'        => 'personal',
            'fecha'       => $this->fecha->toDateString(),
            'hora_inicio' => '08:00',
            'hora_fin'    => '12:00',
        ]);

    $this->resumen = fn (Servidor $servidor) => $this->actingAs($this->uath, 'sanctum')
        ->getJson("/api/v1/asistencia/periodos-vacaciones/servidores/{$servidor->id}/resumen")
        ->assertOk()
        ->json('datos');
});

test('un permiso rechazado por Recepción no aparece como días descontados', function () {
    ($this->periodo)($this->servidor, $this->anio, 30, 0);

    $permiso = ($this->registrar)($this->servidor)->assertCreated()->json('datos');

    $this->actingAs($this->recepcion, 'sanctum')
        ->postJson("/api/v1/asistencia/permisos/{$permiso['id']}/rechazar", [
            'motivo' => 'El papel llegó sin la firma del jefe.',
        ])
        ->assertOk();

    $resumen = ($this->resumen)($this->servidor);

    expect((float) $resumen['total_permisos_personales'])->toBe(0.0)
        ->and((float) $resumen['periodos'][0]['dias_permisos_personales'])->toBe(0.0);
});

test('una falta injustificada no aparece como días descontados', function () {
    ($this->periodo)($this->servidor, $this->anio, 30, 0);

    $permiso = ($this->registrar)($this->servidor)->assertCreated()->json('datos');

    // Lo que hace VencerPermisosJob al pasar el plazo: de pendiente a falta,
    // sin tocar ningún saldo.
    PermisoServidor::whereKey($permiso['id'])
        ->update(['estado' => EstadoPermiso::FALTA_INJUSTIFICADA->value]);

    $resumen = ($this->resumen)($this->servidor);

    expect((float) $resumen['total_permisos_personales'])->toBe(0.0);
});

test('un permiso del Código del Trabajo no aparece como días descontados', function () {
    // El módulo de permisos está cerrado por régimen desde el 2026-08-29, así
    // que hoy este permiso no se puede registrar por el API. Lo que sí hay son
    // los de antes de ese cierre, que quedaron en la base con su estado activo
    // y sin tramo de descuento, porque en el Código del Trabajo el permiso
    // personal no descuenta de vacaciones. Atribuidos por fecha, aparecían como
    // días gastados de un saldo que nunca se movió.
    $servidor = ($this->crearServidor)('0800006002', RegimenLaboral::CODIGO_TRABAJO);
    ($this->periodo)($servidor, $this->anio, 15, 0);

    PermisoServidor::create([
        'servidor_id' => $servidor->id,
        'jefe_id'     => $this->jefe->id,
        'tipo'        => 'personal',
        'fecha'       => $this->fecha->toDateString(),
        'hora_inicio' => '08:00',
        'hora_fin'    => '12:00',
        'folio'       => 'CT-ANTIGUO-1',
        'estado'      => EstadoPermiso::ACTIVO->value,
        'vence_en'    => $this->fecha->copy()->addDays(3),
    ]);

    $resumen = ($this->resumen)($servidor);

    expect((float) $resumen['total_permisos_personales'])->toBe(0.0)
        ->and((float) $resumen['periodos'][0]['dias_saldo'])->toBe(15.0);
});

test('el permiso se carga al período del que salió, no al del año de su fecha', function () {
    $anterior = ($this->periodo)($this->servidor, $this->anio - 1, 30, 25); // quedan 5
    $actual   = ($this->periodo)($this->servidor, $this->anio, 30, 0);

    $permiso = ($this->registrar)($this->servidor)->assertCreated()->json('datos');

    $this->actingAs($this->recepcion, 'sanctum')
        ->postJson("/api/v1/asistencia/permisos/confirmar/{$permiso['folio']}")
        ->assertOk();

    $resumen = ($this->resumen)($this->servidor);
    $porAnio = collect($resumen['periodos'])->keyBy('anio');

    // El permiso es de este año, pero sus 0,5 días salieron del período
    // anterior, que es el que se gasta primero.
    expect((float) $porAnio[$anterior->anio]['dias_permisos_personales'])->toBe(0.5)
        ->and((float) $porAnio[$actual->anio]['dias_permisos_personales'])->toBe(0.0)
        ->and((float) $resumen['total_permisos_personales'])->toBe(0.5);
});

test('revertir la confirmación devuelve los días y los saca del resumen', function () {
    ($this->periodo)($this->servidor, $this->anio, 30, 0);

    $permiso = ($this->registrar)($this->servidor)->assertCreated()->json('datos');

    $this->actingAs($this->recepcion, 'sanctum')
        ->postJson("/api/v1/asistencia/permisos/confirmar/{$permiso['folio']}")
        ->assertOk();

    expect((float) ($this->resumen)($this->servidor)['total_permisos_personales'])->toBe(0.5);

    $this->actingAs($this->uath, 'sanctum')
        ->postJson("/api/v1/asistencia/permisos/{$permiso['id']}/revertir-confirmacion", [
            'motivo' => 'Recepción confirmó el folio equivocado.',
        ])
        ->assertOk();

    expect((float) ($this->resumen)($this->servidor)['total_permisos_personales'])->toBe(0.0);
});

test('las vacaciones aprobadas siguen contándose por el período del que salieron', function () {
    $anterior = ($this->periodo)($this->servidor, $this->anio - 1, 30, 25); // quedan 5
    $actual   = ($this->periodo)($this->servidor, $this->anio, 30, 0);

    $inicio = Carbon::create($this->anio, 6, 1)->next(Carbon::MONDAY);

    $vacacion = Vacacion::create([
        'servidor_id'      => $this->servidor->id,
        'fecha_inicio'     => $inicio->toDateString(),
        'fecha_fin'        => $inicio->copy()->addDays(2)->toDateString(),
        'dias_solicitados' => 3,
        'tipo_dias'        => 'habiles',
        'estado'           => 'pendiente',
        'motivo'           => 'vacaciones_anuales',
    ]);

    $this->actingAs($this->uath, 'sanctum')
        ->putJson("/api/v1/asistencia/vacaciones/{$vacacion->id}", ['estado' => 'aprobada'])
        ->assertOk();

    $porAnio = collect(($this->resumen)($this->servidor)['periodos'])->keyBy('anio');

    // Los 3 días caben en los 5 que quedaban del período anterior, así que es
    // ese el que los cuenta, no el del año de la solicitud.
    expect((float) $porAnio[$anterior->anio]['dias_vacaciones_aprobadas'])->toBe(3.0)
        ->and((float) $porAnio[$actual->anio]['dias_vacaciones_aprobadas'])->toBe(0.0);
});
