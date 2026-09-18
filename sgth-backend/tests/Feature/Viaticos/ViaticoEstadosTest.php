<?php

/*
| Cuándo puede hacerse cada cosa con un viático.
|
| Cada acción miraba el estado a su manera, o no lo miraba: la liquidación se
| confirmaba desde `solicitado`, se rechazaba con el anticipo ya entregado, los
| tramos se cambiaban con el viático contabilizado y nadie impedía que
| Financiero aprobara su propio viático.
|
| Decidido con el usuario:
| - el titular corrige solo en `solicitado`; quien opera, hasta que se liquida;
| - se rechaza solo antes de entregar el anticipo, y con motivo;
| - el servidor cancela mientras está `solicitado`;
| - nadie aprueba, entrega el anticipo, contabiliza ni autoriza los vuelos de su
|   propio viático.
*/

use App\Enums\EstadoViatico;
use App\Enums\RegimenLaboral;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Models\Viatico\AutorizacionVuelo;
use App\Models\Viatico\CatalogoTransporte;
use App\Models\Viatico\EmpresaTransporte;
use App\Models\Viatico\LiquidacionViatico;
use App\Models\Viatico\TramoViatico;
use App\Models\Viatico\Viatico;
use App\Models\Viatico\ViaticoHistorialEstado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $unidad = unidadDePrueba(['codigo' => 'GFIN']);
    $cedula = 800006000;

    $this->servidor = function () use ($unidad, &$cedula) {
        return Servidor::create([
            'cedula'                   => '0'.(++$cedula),
            'nombre'                   => 'Tomás',
            'apellido'                 => 'Tramo',
            'puesto_id'                => puestoDePrueba($unidad)->id,
            'unidad_administrativa_id' => $unidad->id,
            'regimen_laboral'          => RegimenLaboral::LOSEP,
            'estado'                   => true,
        ]);
    };

    $this->usuario = function (string $rol) {
        $usuario = User::factory()->create(['servidor_id' => ($this->servidor)()->id]);
        $usuario->assignRole($rol);

        return $usuario;
    };

    $this->titular = ($this->usuario)('servidor');
    $this->financiero = ($this->usuario)('financiero');
    $this->otroFinanciero = ($this->usuario)('financiero');

    DB::table('tarifas_viatico')->insert([
        ['zona' => 'dentro_provincia', 'nivel' => 'servidor', 'tipo_tarifa' => 'con_pernocte', 'valor_diario' => 80],
        ['zona' => 'dentro_provincia', 'nivel' => 'servidor', 'tipo_tarifa' => 'subsistencia', 'valor_diario' => 40],
    ]);

    $bus = CatalogoTransporte::create(['nombre' => 'Bus', 'codigo' => 'BUS', 'tipo_vehiculo' => 'terrestre']);
    $avion = CatalogoTransporte::create(['nombre' => 'Aéreo', 'codigo' => 'AER', 'tipo_vehiculo' => 'aereo', 'requiere_autorizacion' => true]);
    $this->empresaBus = EmpresaTransporte::create(['catalogo_transporte_id' => $bus->id, 'nombre' => 'Trans Esmeraldas', 'codigo' => 'TE']);
    $this->empresaAerea = EmpresaTransporte::create(['catalogo_transporte_id' => $avion->id, 'nombre' => 'Avianca', 'codigo' => 'AV']);

    /** Un viático del servidor indicado, con un tramo en bus, en el estado pedido. */
    $this->viaticoDe = function (User $quien, EstadoViatico $estado = EstadoViatico::SOLICITADO, array $extra = []) {
        $viatico = Viatico::create(array_merge([
            'servidor_id'        => $quien->servidor_id,
            'zona'               => 'dentro_provincia',
            'datetime_salida'    => '2026-10-05 08:00:00',
            'datetime_llegada'   => '2026-10-07 18:00:00',
            'noches'             => 3,
            'justificacion'      => 'Supervisión de obras viales',
            'estado'             => $estado,
            'monto_calculado'    => 240,
            'monto_anticipo'     => 0,
            'modalidad_anticipo' => 'total',
        ], $extra));
        TramoViatico::create([
            'viatico_id' => $viatico->id, 'orden' => 1, 'tipo_tramo' => 'ida',
            'origen_tipo' => 'nacional', 'origen_ciudad' => 'Esmeraldas',
            'destino_tipo' => 'nacional', 'destino_ciudad' => 'Quinindé',
            'empresa_transporte_id' => $this->empresaBus->id,
            'datetime_salida' => '2026-10-05 08:00:00', 'datetime_llegada' => '2026-10-05 11:00:00',
        ]);
        // Sin tramo de regreso no se aprueba (2026-09-18).
        TramoViatico::create([
            'viatico_id' => $viatico->id, 'orden' => 2, 'tipo_tramo' => 'regreso',
            'origen_tipo' => 'nacional', 'origen_ciudad' => 'Quinindé',
            'destino_tipo' => 'nacional', 'destino_ciudad' => 'Esmeraldas',
            'empresa_transporte_id' => $this->empresaBus->id,
            'datetime_salida' => '2026-10-07 15:00:00', 'datetime_llegada' => '2026-10-07 18:00:00',
        ]);

        return $viatico;
    };

    $this->post = fn (User $quien, Viatico $viatico, string $accion, array $datos = []) =>
        $this->actingAs($quien, 'sanctum')->postJson("/api/v1/viaticos/{$viatico->id}/{$accion}", $datos);

    $this->motivo = ['motivo' => 'La documentación no corresponde'];

    // Entregar el anticipo y contabilizar piden el respaldo contable.
    $this->respaldo = ['numero_resolucion' => 'RES-2026-001', 'partida_presupuestaria_id' => partidaDeViatico()->id];
});

// ── Grafo de estados ─────────────────────────────────────────────────

it('cada acción solo sale del estado que le corresponde', function (string $accion, EstadoViatico $estado) {
    $viatico = ($this->viaticoDe)($this->titular, $estado);

    ($this->post)($this->financiero, $viatico, $accion, $this->motivo + $this->respaldo)->assertStatus(422);

    expect($viatico->fresh()->estado)->toBe($estado);
})->with([
    'aprobar lo aprobado'                => ['aprobar', EstadoViatico::APROBADO],
    'rechazar con el anticipo entregado' => ['rechazar', EstadoViatico::CON_ANTICIPO],
    'rechazar en comisión'               => ['rechazar', EstadoViatico::EN_COMISION],
    'rechazar lo liquidado'              => ['rechazar', EstadoViatico::LIQUIDADO],
    'cancelar lo aprobado'               => ['cancelar', EstadoViatico::APROBADO],
    'anticipo de una solicitud'          => ['entregar-anticipo', EstadoViatico::SOLICITADO],
    'comisión de una solicitud'          => ['marcar-en-comision', EstadoViatico::SOLICITADO],
    'pendiente sin comisión'             => ['marcar-pendiente-liquidacion', EstadoViatico::CON_ANTICIPO],
    'devolver lo pendiente'              => ['devolver-correccion', EstadoViatico::PENDIENTE_LIQUIDACION],
    'contabilizar lo pendiente'          => ['contabilizar', EstadoViatico::PENDIENTE_LIQUIDACION],
    'nada sobre lo contabilizado'        => ['rechazar', EstadoViatico::CONTABILIZADO],
    'nada sobre lo cancelado'            => ['aprobar', EstadoViatico::CANCELADO],
]);

it('el recorrido completo deja cada paso en el historial', function () {
    $viatico = ($this->viaticoDe)($this->titular);
    // Contabilizar pide todos los comprobantes aceptados.
    comprobanteAceptado(LiquidacionViatico::create([
        'viatico_id' => $viatico->id, 'total_facturas' => 0,
        'fecha_liquidacion' => now()->toDateString(),
    ]));

    foreach (['aprobar', 'entregar-anticipo', 'marcar-en-comision', 'marcar-pendiente-liquidacion'] as $accion) {
        ($this->post)($this->financiero, $viatico, $accion, $this->respaldo)->assertOk();
    }
    $viatico->update(['estado' => EstadoViatico::LIQUIDADO]);
    ($this->post)($this->financiero, $viatico, 'devolver-correccion', ['motivo' => 'Falta la factura del hotel'])->assertOk();

    $historial = ViaticoHistorialEstado::where('viatico_id', $viatico->id)->orderBy('id')->get();

    expect($historial->pluck('estado_nuevo')->all())->toBe([
        'aprobado', 'con_anticipo', 'en_comision', 'pendiente_liquidacion', 'pendiente_liquidacion',
    ])
        ->and($historial->last()->estado_anterior)->toBe('liquidado')
        ->and($historial->last()->motivo)->toBe('Falta la factura del hotel')
        ->and($historial->pluck('usuario_id')->unique()->all())->toBe([$this->financiero->id])
        ->and($viatico->fresh()->updated_by)->toBe($this->financiero->id)
        ->and((float) $viatico->fresh()->monto_anticipo)->toBe(168.0);

    $this->actingAs($this->titular, 'sanctum')
        ->getJson("/api/v1/viaticos/{$viatico->id}")
        ->assertOk()
        ->assertJsonCount(5, 'datos.historial')
        ->assertJsonPath('datos.historial.4.motivo', 'Falta la factura del hotel');
});

it('la solicitud nace con su primer paso en el historial', function () {
    $this->actingAs($this->titular, 'sanctum')->postJson('/api/v1/viaticos', [
        'zona' => 'dentro_provincia', 'justificacion' => 'Capacitación en Quinindé',
        'modalidad_anticipo' => 'total',
        'datetime_salida' => '2026-11-03 08:00:00', 'datetime_llegada' => '2026-11-04 18:00:00',
    ])->assertCreated();

    $paso = ViaticoHistorialEstado::sole();
    expect($paso->estado_anterior)->toBeNull()
        ->and($paso->estado_nuevo)->toBe('solicitado')
        ->and($paso->usuario_id)->toBe($this->titular->id);
});

// ── Motivos ──────────────────────────────────────────────────────────

it('rechazar y devolver a corrección exigen motivo', function (string $accion, EstadoViatico $estado) {
    $viatico = ($this->viaticoDe)($this->titular, $estado);

    ($this->post)($this->financiero, $viatico, $accion)
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['motivo']]);

    expect($viatico->fresh()->estado)->toBe($estado);
})->with([
    'rechazar'            => ['rechazar', EstadoViatico::APROBADO],
    'devolver a corrección' => ['devolver-correccion', EstadoViatico::LIQUIDADO],
]);

it('el motivo del rechazo queda en el viático', function () {
    $viatico = ($this->viaticoDe)($this->titular);

    ($this->post)($this->financiero, $viatico, 'rechazar', ['motivo' => 'No hay presupuesto este mes'])->assertOk();

    expect($viatico->fresh())
        ->estado->toBe(EstadoViatico::RECHAZADO)
        ->motivo_rechazo->toBe('No hay presupuesto este mes');
});

// ── Liquidación ──────────────────────────────────────────────────────

it('la liquidación solo se llena y se presenta con el viático pendiente de liquidación', function () {
    $viatico = ($this->viaticoDe)($this->titular);
    $base = "/api/v1/viaticos/{$viatico->id}/liquidacion";

    $this->actingAs($this->titular, 'sanctum')->postJson("{$base}/actividades", ['actividades' => [
        ['fecha' => '2026-10-05', 'descripcion' => 'Inspección', 'lugar' => 'Quinindé'],
    ]])->assertStatus(422);
    $this->actingAs($this->titular, 'sanctum')->postJson("{$base}/confirmar")->assertStatus(422);

    // Abrir la pantalla fuera de plazo tampoco crea la liquidación: devuelve
    // una vacía sin guardar.
    $this->actingAs($this->titular, 'sanctum')->getJson($base)->assertOk()->assertJsonPath('datos.id', null);

    expect($viatico->fresh()->estado)->toBe(EstadoViatico::SOLICITADO)
        ->and(LiquidacionViatico::count())->toBe(0);
});

// ── Edición ──────────────────────────────────────────────────────────

it('el titular corrige solo antes de la aprobación', function () {
    $viatico = ($this->viaticoDe)($this->titular, EstadoViatico::APROBADO);
    $tramo = TramoViatico::where('viatico_id', $viatico->id)->firstOrFail();

    $this->actingAs($this->titular, 'sanctum')
        ->patchJson("/api/v1/viaticos/{$viatico->id}", ['justificacion' => 'Otra justificación del viaje'])
        ->assertStatus(422);
    $this->actingAs($this->titular, 'sanctum')
        ->putJson("/api/v1/viaticos/{$viatico->id}/tramos/{$tramo->id}", ['destino_ciudad' => 'Atacames'])
        ->assertStatus(422);
    $this->actingAs($this->titular, 'sanctum')
        ->deleteJson("/api/v1/viaticos/{$viatico->id}/tramos/{$tramo->id}")
        ->assertStatus(422);

    expect($tramo->fresh()->destino_ciudad)->toBe('Quinindé');
});

it('quien opera corrige hasta que se liquida', function (EstadoViatico $estado, int $codigo) {
    $viatico = ($this->viaticoDe)($this->titular, $estado);

    $this->actingAs($this->financiero, 'sanctum')
        ->patchJson("/api/v1/viaticos/{$viatico->id}", ['justificacion' => 'Supervisión y reunión técnica'])
        ->assertStatus($codigo);
})->with([
    'aprobado'              => [EstadoViatico::APROBADO, 200],
    'en comisión'           => [EstadoViatico::EN_COMISION, 200],
    'pendiente liquidación' => [EstadoViatico::PENDIENTE_LIQUIDACION, 200],
    'liquidado'             => [EstadoViatico::LIQUIDADO, 422],
    'contabilizado'         => [EstadoViatico::CONTABILIZADO, 422],
    'rechazado'             => [EstadoViatico::RECHAZADO, 422],
]);

// ── Nadie decide sobre su propio viático ─────────────────────────────

it('Financiero no decide sobre un viático en el que viaja', function (string $accion, EstadoViatico $estado) {
    $viatico = ($this->viaticoDe)($this->financiero, $estado);
    // Contabilizar pide todos los comprobantes aceptados.
    comprobanteAceptado(LiquidacionViatico::create([
        'viatico_id' => $viatico->id, 'total_facturas' => 0,
        'fecha_liquidacion' => now()->toDateString(),
    ]));

    // Rechazar pide el motivo; las demás acciones lo ignoran.
    $datos = [...$this->respaldo, 'motivo' => 'No corresponde a la planificación'];

    ($this->post)($this->financiero, $viatico, $accion, $datos)
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'en el que viaja'));

    // Otra persona de Financiero sí puede.
    ($this->post)($this->otroFinanciero, $viatico, $accion, $datos)->assertOk();
})->with([
    'aprobar'           => ['aprobar', EstadoViatico::SOLICITADO],
    'rechazar'          => ['rechazar', EstadoViatico::SOLICITADO],
    'entregar anticipo' => ['entregar-anticipo', EstadoViatico::APROBADO],
    'contabilizar'      => ['contabilizar', EstadoViatico::LIQUIDADO],
]);

it('ni autoriza sus vuelos', function () {
    $viatico = ($this->viaticoDe)($this->financiero);
    $tramo = TramoViatico::where('viatico_id', $viatico->id)->firstOrFail();
    $tramo->update(['empresa_transporte_id' => $this->empresaAerea->id]);
    $autorizacion = AutorizacionVuelo::where('tramo_viatico_id', $tramo->id)->sole();

    $this->actingAs($this->financiero, 'sanctum')
        ->postJson("/api/v1/viaticos/vuelos/{$autorizacion->id}/aprobar")
        ->assertStatus(422);

    expect($autorizacion->fresh()->estado)->toBe('pendiente');
});

it('ni corrige su viático como Financiero una vez aprobado, ni fija su monto', function () {
    $aprobado = ($this->viaticoDe)($this->financiero, EstadoViatico::APROBADO);
    $solicitado = ($this->viaticoDe)($this->financiero);

    $this->actingAs($this->financiero, 'sanctum')
        ->patchJson("/api/v1/viaticos/{$aprobado->id}", ['justificacion' => 'Cambio después de aprobado'])
        ->assertStatus(422);
    $this->actingAs($this->financiero, 'sanctum')
        ->patchJson("/api/v1/viaticos/{$solicitado->id}", ['monto_calculado' => 900])
        ->assertStatus(422);

    expect((float) $solicitado->fresh()->monto_calculado)->toBe(240.0);
});

// ── Condiciones para aprobar y para el anticipo ──────────────────────

it('no se aprueba sin itinerario', function () {
    $viatico = ($this->viaticoDe)($this->titular);
    TramoViatico::where('viatico_id', $viatico->id)->delete();

    ($this->post)($this->financiero, $viatico, 'aprobar')->assertStatus(422);
});

it('no se aprueba con vuelos pendientes, y un vuelo se decide una sola vez', function () {
    $viatico = ($this->viaticoDe)($this->titular);
    $tramo = TramoViatico::where('viatico_id', $viatico->id)->firstOrFail();

    // Pasar el tramo de bus a avión crea la autorización.
    $tramo->update(['empresa_transporte_id' => $this->empresaAerea->id]);
    $autorizacion = AutorizacionVuelo::where('tramo_viatico_id', $tramo->id)->sole();

    ($this->post)($this->financiero, $viatico, 'aprobar')->assertStatus(422);

    $url = "/api/v1/viaticos/vuelos/{$autorizacion->id}";
    $this->actingAs($this->financiero, 'sanctum')->postJson("{$url}/aprobar")->assertOk();
    $this->actingAs($this->financiero, 'sanctum')->postJson("{$url}/rechazar")->assertStatus(422);

    ($this->post)($this->financiero, $viatico, 'aprobar')->assertOk();
});

it('no se aprueba a quien tiene liquidaciones vencidas', function () {
    ($this->viaticoDe)($this->titular, EstadoViatico::PENDIENTE_LIQUIDACION, [
        'datetime_salida' => now()->subDays(20), 'datetime_llegada' => now()->subDays(15),
    ]);
    $viatico = ($this->viaticoDe)($this->titular);

    ($this->post)($this->financiero, $viatico, 'aprobar')->assertStatus(422);
});

it('un viático sin anticipo no recibe anticipo', function () {
    $viatico = ($this->viaticoDe)($this->titular, EstadoViatico::APROBADO, ['modalidad_anticipo' => 'sin_anticipo']);

    ($this->post)($this->financiero, $viatico, 'entregar-anticipo', $this->respaldo)->assertStatus(422);
    ($this->post)($this->financiero, $viatico, 'marcar-en-comision')->assertOk();

    expect((float) $viatico->fresh()->monto_anticipo)->toBe(0.0);
});

// ── Respaldo contable ────────────────────────────────────────────────

/*
| Gestión Financiera asigna el número de resolución y la partida al entregar el
| anticipo (2026-09-15). Las dos columnas existían desde el principio y nadie
| las llenaba nunca.
*/

it('sin resolución ni partida no se entrega el anticipo', function () {
    $viatico = ($this->viaticoDe)($this->titular, EstadoViatico::APROBADO);

    $errores = ($this->post)($this->financiero, $viatico, 'entregar-anticipo')
        ->assertStatus(422)
        ->json('errores');

    expect(array_keys($errores))->toBe(['numero_resolucion', 'partida_presupuestaria_id'])
        ->and($viatico->fresh()->estado)->toBe(EstadoViatico::APROBADO);

    ($this->post)($this->financiero, $viatico, 'entregar-anticipo', $this->respaldo)->assertOk();

    expect($viatico->fresh())
        ->estado->toBe(EstadoViatico::CON_ANTICIPO)
        ->numero_resolucion->toBe('RES-2026-001')
        ->partida_presupuestaria_id->toBe(partidaDeViatico()->id);
});

it('el viático sin anticipo las pide al contabilizar, y el que ya las tiene no', function () {
    $sinAnticipo = ($this->viaticoDe)($this->titular, EstadoViatico::LIQUIDADO, ['modalidad_anticipo' => 'sin_anticipo']);
    comprobanteAceptado(LiquidacionViatico::create([
        'viatico_id' => $sinAnticipo->id, 'total_facturas' => 0,
        'fecha_liquidacion' => now()->toDateString(),
    ]));

    ($this->post)($this->financiero, $sinAnticipo, 'contabilizar')->assertStatus(422);
    ($this->post)($this->financiero, $sinAnticipo, 'contabilizar', $this->respaldo)->assertOk();

    expect($sinAnticipo->fresh()->numero_resolucion)->toBe('RES-2026-001');

    // Con el anticipo entregado ya vienen asignadas: no se vuelven a pedir.
    $conAnticipo = ($this->viaticoDe)($this->titular, EstadoViatico::LIQUIDADO, [
        'numero_resolucion' => 'RES-2026-002', 'partida_presupuestaria_id' => partidaDeViatico()->id,
    ]);
    comprobanteAceptado(LiquidacionViatico::create([
        'viatico_id' => $conAnticipo->id, 'total_facturas' => 0,
        'fecha_liquidacion' => now()->toDateString(),
    ]));

    ($this->post)($this->financiero, $conAnticipo, 'contabilizar')->assertOk();

    expect($conAnticipo->fresh()->numero_resolucion)->toBe('RES-2026-002');
});
