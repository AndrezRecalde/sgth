<?php

namespace Tests\Feature\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\EstadoSubrogacion;
use App\Enums\TipoEventoVinculo;
use App\Enums\TipoSubrogacion;
use App\Models\Estructura\Cargo;
use App\Models\Estructura\PartidaPresupuestaria;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\EventoVinculo;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\Expediente\Subrogacion;
use App\Models\User;
use App\Services\Expediente\MovimientoPersonalStateService;
use App\Services\Expediente\SubrogacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * El estado de una subrogación es lo que decide quién firma, así que cada cambio
 * tiene que quedar registrado. Las tres transiciones que más importan —activar
 * al registrarse la acción, cancelar al anularla y caducar por vencimiento— se
 * hacían con un mass-update, que no dispara eventos de modelo: el observer
 * existía y no se enteraba de ninguna de las tres.
 */
beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin-uath');
    $this->actingAs($this->user, 'sanctum');

    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'PREF-01', 'nombre' => 'Prefectura Provincial', 'nivel' => 1,
        'es_maxima_autoridad' => true, 'estado' => true,
    ]);

    $this->partida = PartidaPresupuestaria::create([
        'codigo' => '510512', 'descripcion' => 'Subrogaciones',
        'grupo_gasto' => 'Gastos en Personal', 'activo' => true, 'disponible' => true,
    ]);

    $this->puesto = Puesto::create([
        'codigo' => 'P-PREF', 'unidad_administrativa_id' => $this->unidad->id,
        'plazas' => 1, 'es_jefe' => true, 'activo' => true, 'rmu' => 3000,
        'cargo_id' => Cargo::firstOrCreate(['nombre' => 'Prefecto/a Provincial'])->id,
        'partida_presupuestaria_id' => $this->partida->id,
    ]);

    $this->contador = 0;

    $this->servidorCon = function (?int $puestoId = null): Servidor {
        $this->contador++;

        $servidor = Servidor::create([
            'cedula'   => str_pad((string) (4000000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre'   => 'Servidor',
            'apellido' => 'Bitacora'.$this->contador,
            'fecha_ingreso_institucion' => '2018-01-01',
        ]);

        if ($puestoId) {
            ContratoServidor::create([
                'servidor_id'              => $servidor->id,
                'tipo_nombramiento'        => 'nombramiento_permanente',
                'unidad_administrativa_id' => $this->unidad->id,
                'puesto_id'                => $puestoId,
                'fecha_inicio'             => '2018-01-01',
                'estado'                   => 'vigente',
                'remuneracion'             => 1500,
            ]);
        }

        return $servidor->fresh();
    };

    $this->service      = app(SubrogacionService::class);
    $this->stateService = app(MovimientoPersonalStateService::class);

    $this->registrar = function (): Subrogacion {
        $titular = ($this->servidorCon)($this->puesto->id);

        return $this->service->registrar([
            'tipo'                     => TipoSubrogacion::SUBROGACION->value,
            'servidor_subrogante_id'   => ($this->servidorCon)()->id,
            'servidor_subrogado_id'    => $titular->id,
            'unidad_administrativa_id' => $this->unidad->id,
            'puesto_subrogado_id'      => $this->puesto->id,
            'fecha_inicio'             => now()->subDay()->toDateString(),
            'fecha_fin'                => now()->addMonth()->toDateString(),
            'motivo'                   => 'vacaciones',
            'observacion'              => 'El titular asiste a un congreso en Quito.',
        ]);
    };

    /*
    | `orderBy('id')` no es decoración: sin ORDER BY, PostgreSQL devuelve las
    | filas en el orden que le convenga, y los tests que miran `->first()` o
    | `->last()` dependían de que coincidiera con el de inserción. En local
    | coincidía; en CI no, y «caducar por vencimiento también» fallaba de forma
    | intermitente leyendo la línea de la activación en lugar de la de la
    | caducidad ('pendiente' donde esperaba 'activa'). La bitácora es una
    | secuencia y hay que pedirla como tal.
    */
    $this->cambiosDeEstado = fn (Subrogacion $s) => Activity::query()
        ->where('subject_type', Subrogacion::class)
        ->where('subject_id', $s->id)
        ->where('description', 'cambió de estado')
        ->orderBy('id')
        ->get();
});

// ── El estado de nacimiento ─────────────────────────────────────

test('una fila insertada sin pasar por el servicio nace pendiente', function () {
    $titular = ($this->servidorCon)($this->puesto->id);

    // Sin 'estado': el que ponga la base de datos. Decía 'activa', de cuando la
    // subrogación nacía surtiendo efecto.
    $subrogacion = Subrogacion::create([
        'tipo'                     => TipoSubrogacion::SUBROGACION->value,
        'servidor_subrogante_id'   => ($this->servidorCon)()->id,
        'servidor_subrogado_id'    => $titular->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_subrogado_id'      => $this->puesto->id,
        'fecha_inicio'             => now()->toDateString(),
        'fecha_fin'                => now()->addMonth()->toDateString(),
        'motivo'                   => 'vacaciones',
        'registrado_por'           => $this->user->id,
    ]);

    expect($subrogacion->fresh()->estado)->toBe(EstadoSubrogacion::PENDIENTE);
});

// ── La bitácora de las tres transiciones ────────────────────────

test('activarse al registrarse la acción deja su línea en la bitácora', function () {
    $subrogacion = ($this->registrar)();

    $this->service->activarPorMovimiento($subrogacion->movimientoPersonal);

    $lineas = ($this->cambiosDeEstado)($subrogacion);

    expect($subrogacion->fresh()->estado)->toBe(EstadoSubrogacion::ACTIVA)
        ->and($lineas)->toHaveCount(1)
        ->and($lineas->first()->properties['estado_anterior'])->toBe('pendiente')
        ->and($lineas->first()->properties['estado'])->toBe('activa');
});

test('cancelar por anulación de la acción también', function () {
    $subrogacion = ($this->registrar)();

    $this->service->cancelarPorMovimiento($subrogacion->movimientoPersonal);

    expect(($this->cambiosDeEstado)($subrogacion))->toHaveCount(1)
        ->and($subrogacion->fresh()->estado)->toBe(EstadoSubrogacion::CANCELADA);
});

test('caducar por vencimiento también', function () {
    $subrogacion = ($this->registrar)();
    $subrogacion->update(['estado' => EstadoSubrogacion::ACTIVA->value]);

    // Se vence el plazo sin tocar el estado: es lo que hace el paso del tiempo.
    Subrogacion::where('id', $subrogacion->id)->update([
        'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-03-31',
    ]);

    $this->service->caducarVencidas();

    $lineas = ($this->cambiosDeEstado)($subrogacion);

    expect($subrogacion->fresh()->estado)->toBe(EstadoSubrogacion::FINALIZADA)
        // La activación y la caducidad: dos líneas.
        ->and($lineas)->toHaveCount(2)
        ->and($lineas->last()->properties['estado_anterior'])->toBe('activa')
        ->and($lineas->last()->properties['estado'])->toBe('finalizada');
});

test('el comando sigue contando bien lo que cerró', function () {
    $subrogacion = ($this->registrar)();
    $subrogacion->update(['estado' => EstadoSubrogacion::ACTIVA->value]);
    Subrogacion::where('id', $subrogacion->id)->update([
        'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-03-31',
    ]);

    expect($this->service->caducarVencidas()['caducadas'])->toBe(1)
        // Ya cerrada, la segunda pasada no cuenta nada.
        ->and($this->service->caducarVencidas()['caducadas'])->toBe(0);
});

// ── La observación que se perdía ────────────────────────────────

/**
 * `cancelarPorMovimiento()` reemplazaba la observación de un golpe, así que
 * anular la acción borraba lo que Talento Humano hubiera anotado al registrarla
 * — mientras `cancelar()`, la cancelación a mano, sí la conservaba.
 */
test('anular la acción no borra lo que decía la observación', function () {
    $subrogacion = ($this->registrar)();

    $this->service->cancelarPorMovimiento($subrogacion->movimientoPersonal);

    expect($subrogacion->fresh()->observacion)
        ->toContain('El titular asiste a un congreso en Quito.')
        ->toContain('se anuló la Acción de Personal');
});

test('una sin observación previa queda solo con el motivo automático', function () {
    $titular = ($this->servidorCon)($this->puesto->id);
    $subrogacion = $this->service->registrar([
        'tipo'                     => TipoSubrogacion::SUBROGACION->value,
        'servidor_subrogante_id'   => ($this->servidorCon)()->id,
        'servidor_subrogado_id'    => $titular->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_subrogado_id'      => $this->puesto->id,
        'fecha_inicio'             => now()->subDay()->toDateString(),
        'fecha_fin'                => now()->addMonth()->toDateString(),
        'motivo'                   => 'vacaciones',
    ]);

    $this->service->cancelarPorMovimiento($subrogacion->movimientoPersonal);

    expect($subrogacion->fresh()->observacion)
        ->toBe('Cancelada automáticamente: se anuló la Acción de Personal que la respaldaba.');
});

// ── Que siga funcionando el camino completo ─────────────────────

test('registrar la acción de personal activa su subrogación, como antes', function () {
    $subrogacion = ($this->registrar)();
    $movimiento  = $subrogacion->movimientoPersonal;

    $movimiento->update(['dictamen_presupuestario_ref' => 'DICT-2026-001']);
    $this->stateService->transicionar($movimiento->fresh(), EstadoAccionPersonal::SUSCRITA, []);
    $this->stateService->transicionar($movimiento->fresh(), EstadoAccionPersonal::REGISTRADA, []);

    expect($subrogacion->fresh()->estado)->toBe(EstadoSubrogacion::ACTIVA);
});

// ── Quién la registró ───────────────────────────────────────────

/**
 * `registrado_por` se guardaba desde la primera migración y no salía en ningún
 * listado: no había forma de saber quién registró una subrogación sin abrir la
 * base de datos. La relación se llama `registradoPorUsuario` porque Eloquent
 * mezcla las relaciones encima de los atributos, y `registradoPor` habría
 * pisado el id de la columna con el objeto del usuario.
 */
test('el listado dice quién registró cada subrogación, sin perder el id', function () {
    ($this->registrar)();

    $fila = $this->service->listarVigentes()->items()[0]->toArray();

    expect($fila['registrado_por'])->toBe($this->user->id)
        ->and($fila['registrado_por_usuario']['id'])->toBe($this->user->id)
        ->and($fila['registrado_por_usuario'])->toHaveKey('nombre_completo');
});

test('el historial del servidor también', function () {
    $subrogacion = ($this->registrar)();

    $fila = $this->service->listarPorServidor($subrogacion->servidor_subrogante_id)
        ->first()->toArray();

    expect($fila['registrado_por_usuario']['id'])->toBe($this->user->id);
});

// ── Cancelar cierra también su Acción de Personal ───────────────

/**
 * El enlace funcionaba en una sola dirección: anular la acción cancelaba la
 * subrogación, pero cancelar la subrogación no tocaba la acción. El borrador se
 * quedaba en la bandeja de Talento Humano para siempre —y desde el 2026-09-28
 * ya no se puede ni editar—, esperando que alguien suscribiera un acto cuyo
 * objeto no existe.
 */
test('cancelar una pendiente anula su acción en borrador', function () {
    $subrogacion = ($this->registrar)();
    $movimiento  = $subrogacion->movimientoPersonal;

    expect($movimiento->estado)->toBe(EstadoAccionPersonal::BORRADOR);

    $this->service->cancelar($subrogacion->id, 'El titular no viajó.');

    expect($movimiento->fresh()->estado)->toBe(EstadoAccionPersonal::ANULADA)
        ->and($movimiento->fresh()->motivo_anulacion)
        ->toContain('Se canceló la subrogación')
        ->toContain('El titular no viajó.');
});

test('también si la acción ya estaba suscrita pero sin registrar', function () {
    $subrogacion = ($this->registrar)();
    $movimiento  = $subrogacion->movimientoPersonal;

    $movimiento->update(['dictamen_presupuestario_ref' => 'DICT-2026-001']);
    $this->stateService->transicionar($movimiento->fresh(), EstadoAccionPersonal::SUSCRITA, []);

    // Suscrita sin registrar: la subrogación sigue pendiente.
    expect($subrogacion->fresh()->estado)->toBe(EstadoSubrogacion::PENDIENTE);

    $this->service->cancelar($subrogacion->id, 'Se resolvió de otra forma.');

    expect($movimiento->fresh()->estado)->toBe(EstadoAccionPersonal::ANULADA);
});

/**
 * Con la acción ya registrada no se anula nada: un acto administrativo
 * registrado no se borra, y el grafo de estados tampoco lo permite
 * (registrada → [notificada]). Queda constancia en la bitácora del vínculo, con
 * el mismo criterio que la finalización anticipada.
 */
test('cancelar una activa deja constancia en vez de anular el acto', function () {
    $subrogacion = ($this->registrar)();
    $movimiento  = $subrogacion->movimientoPersonal;

    $movimiento->update(['dictamen_presupuestario_ref' => 'DICT-2026-001']);
    $this->stateService->transicionar($movimiento->fresh(), EstadoAccionPersonal::SUSCRITA, []);
    $this->stateService->transicionar($movimiento->fresh(), EstadoAccionPersonal::REGISTRADA, []);

    expect($subrogacion->fresh()->estado)->toBe(EstadoSubrogacion::ACTIVA);

    $antes = MovimientoPersonal::count();

    $this->service->cancelar($subrogacion->id, 'El titular se reincorporó.');

    $constancia = EventoVinculo::where('subrogacion_id', $subrogacion->id)->sole();

    // El acto original no se toca, y la constancia no es otro acto: hasta la
    // fase 1.2 era una fila más de acciones de personal, con el mismo tipo que
    // la de verdad y el botón del PDF.
    expect(MovimientoPersonal::count())->toBe($antes)
        ->and($movimiento->fresh()->estado)->toBe(EstadoAccionPersonal::REGISTRADA)
        ->and($constancia->tipo)->toBe(TipoEventoVinculo::SUBROGACION_CANCELADA)
        ->and($constancia->servidor_id)->toBe($subrogacion->servidor_subrogante_id)
        ->and($constancia->movimiento_personal_id)->toBe($movimiento->id)
        ->and($constancia->descripcion)->toContain('Cancelación de Subrogación')
        ->and($constancia->descripcion)->toContain('El titular se reincorporó.');
});

test('terminar antes una activa también deja su constancia en la bitácora', function () {
    $subrogacion = ($this->registrar)();
    $movimiento  = $subrogacion->movimientoPersonal;

    $movimiento->update(['dictamen_presupuestario_ref' => 'DICT-2026-001']);
    $this->stateService->transicionar($movimiento->fresh(), EstadoAccionPersonal::SUSCRITA, []);
    $this->stateService->transicionar($movimiento->fresh(), EstadoAccionPersonal::REGISTRADA, []);

    $antes = MovimientoPersonal::count();

    $this->service->finalizar($subrogacion->id);

    $constancia = EventoVinculo::where('subrogacion_id', $subrogacion->id)->sole();

    expect($subrogacion->fresh()->estado)->toBe(EstadoSubrogacion::FINALIZADA)
        ->and(MovimientoPersonal::count())->toBe($antes)
        ->and($constancia->tipo)->toBe(TipoEventoVinculo::SUBROGACION_FINALIZADA)
        ->and($constancia->movimiento_personal_id)->toBe($movimiento->id)
        ->and($constancia->fecha->toDateString())->toBe(now()->toDateString())
        ->and($constancia->descripcion)->toContain('Finalización anticipada de Subrogación');
});

/**
 * Anular la acción dispara `aplicarAnulacion()`, que llama a
 * `cancelarPorMovimiento()`. No hay bucle porque para entonces la subrogación
 * ya está CANCELADA y ese método solo mira pendientes y activas.
 *
 * El orden es la regla, y esta prueba lo fija: si se anulara la acción ANTES de
 * marcar la subrogación, la cascada la encontraría viva y le escribiría su nota
 * —«Cancelada automáticamente: se anuló la Acción de Personal que la
 * respaldaba»—, y encima quedaría la de cancelar(): dos notas, y la primera
 * invirtiendo la causa.
 */
test('anular la acción desde aquí no vuelve a cancelar la subrogación', function () {
    $subrogacion = ($this->registrar)();

    $this->service->cancelar($subrogacion->id, 'Cambio de planes.');

    $lineas = ($this->cambiosDeEstado)($subrogacion);

    expect($subrogacion->fresh()->estado)->toBe(EstadoSubrogacion::CANCELADA)
        // Un solo cambio de estado: pendiente → cancelada. Si la cascada hubiera
        // vuelto a entrar, habría dos.
        ->and($lineas)->toHaveCount(1)
        // Y la observación lleva el motivo escrito una sola vez, no el
        // automático de la cascada encima.
        ->and($subrogacion->fresh()->observacion)->toContain('Cancelado: Cambio de planes.')
        ->and($subrogacion->fresh()->observacion)
        ->not->toContain('Cancelada automáticamente');
});

test('una subrogación sin acción enlazada se cancela igual', function () {
    $titular = ($this->servidorCon)($this->puesto->id);

    $suelta = Subrogacion::create([
        'tipo'                     => TipoSubrogacion::SUBROGACION->value,
        'servidor_subrogante_id'   => ($this->servidorCon)()->id,
        'servidor_subrogado_id'    => $titular->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_subrogado_id'      => $this->puesto->id,
        'fecha_inicio'             => now()->toDateString(),
        'fecha_fin'                => now()->addMonth()->toDateString(),
        'motivo'                   => 'vacaciones',
        'registrado_por'           => $this->user->id,
    ]);

    $this->service->cancelar($suelta->id, 'Anterior al enlace con la acción.');

    expect($suelta->fresh()->estado)->toBe(EstadoSubrogacion::CANCELADA);
});
