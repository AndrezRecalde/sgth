<?php

namespace Tests\Feature\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\EstadoSubrogacion;
use App\Enums\RolFirmaAccionPersonal;
use App\Enums\TipoSubrogacion;
use App\Models\Estructura\Cargo;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\Expediente\Subrogacion;
use App\Models\User;
use App\Services\Expediente\FirmanteAccionPersonalService;
use App\Services\Expediente\MovimientoPersonalStateService;
use App\Services\Expediente\SubrogacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin-uath');
    $this->actingAs($this->user, 'sanctum');

    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'PREF-01', 'nombre' => 'Prefectura Provincial', 'nivel' => 1,
        'es_maxima_autoridad' => true,
    ]);

    $cargo = Cargo::firstOrCreate(['nombre' => 'Prefecto/a Provincial']);

    // La subrogación compromete presupuesto (Art. 105 LOSEP): sin partida
    // disponible su acción no puede suscribirse, que es justo lo que este
    // arreglo hace valer.
    //
    // El código es 510512, la partida de la diferencia que confirmó la
    // Dirección Financiera. Decía 510106 —que es «Salarios Unificados»— con la
    // descripción «Subrogaciones» encima, y funcionaba solo porque el guard del
    // Art. 105 aceptaba cualquier partida heredada del puesto. Desde que la
    // subrogación no hereda, el fixture tiene que nombrar la de verdad.
    $this->partida = \App\Models\Estructura\PartidaPresupuestaria::create([
        'codigo' => '510512', 'descripcion' => 'Subrogaciones',
        'grupo_gasto' => 'Gastos en Personal', 'activo' => true, 'disponible' => true,
    ]);

    $this->puesto = Puesto::create([
        'codigo' => 'P-PREF', 'unidad_administrativa_id' => $this->unidad->id,
        'plazas' => 1, 'cargo_id' => $cargo->id, 'es_jefe' => true,
        'partida_presupuestaria_id' => $this->partida->id,
    ]);

    $this->contador = 0;

    $this->servidorCon = function (?int $puestoId = null): Servidor {
        $this->contador++;

        $servidor = Servidor::create([
            'cedula'   => str_pad((string) (6000000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre'   => 'Servidor',
            'apellido' => 'Firma'.$this->contador,
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
            ]);
        }

        return $servidor->fresh();
    };

    $this->service      = app(SubrogacionService::class);
    $this->stateService = app(MovimientoPersonalStateService::class);
    $this->firmantes    = app(FirmanteAccionPersonalService::class);

    $this->registrarSubrogacion = function (Servidor $subrogante, Servidor $titular): Subrogacion {
        return $this->service->registrar([
            'tipo'                     => TipoSubrogacion::SUBROGACION->value,
            'servidor_subrogante_id'   => $subrogante->id,
            'servidor_subrogado_id'    => $titular->id,
            'unidad_administrativa_id' => $this->unidad->id,
            'puesto_subrogado_id'      => $this->puesto->id,
            'fecha_inicio'             => now()->subDay()->toDateString(),
            'fecha_fin'                => now()->addMonth()->toDateString(),
            'motivo'                   => 'vacaciones',
        ]);
    };
});

// ── El agujero que se cierra ────────────────────────────────────

/**
 * La subrogación nacía ACTIVA mientras su Acción de Personal quedaba en
 * BORRADOR. Como FirmanteAccionPersonalService antepone el subrogante al
 * titular consultando solo el estado de la subrogación, bastaba registrarla
 * para adquirir la facultad de firmar: sin suscripción, sin aprobación y sin
 * el dictamen presupuestario que exige el Art. 105 de la LOSEP.
 */
test('una subrogación recién registrada no otorga la firma', function () {
    $titular    = ($this->servidorCon)($this->puesto->id);
    $subrogante = ($this->servidorCon)();

    ($this->registrarSubrogacion)($subrogante, $titular);

    $firma = $this->firmantes->resolver(RolFirmaAccionPersonal::AUTORIDAD_NOMINADORA, now()->toDateString());

    expect($firma['subrogado'])->toBeFalse()
        ->and($firma['servidor']?->id)->toBe($titular->id);
});

test('nace pendiente y enlazada a su acción de personal', function () {
    $titular    = ($this->servidorCon)($this->puesto->id);
    $subrogante = ($this->servidorCon)();

    $subrogacion = ($this->registrarSubrogacion)($subrogante, $titular);

    expect($subrogacion->estado)->toBe(EstadoSubrogacion::PENDIENTE)
        ->and($subrogacion->movimiento_personal_id)->not->toBeNull()
        ->and($subrogacion->movimientoPersonal->estado)->toBe(EstadoAccionPersonal::BORRADOR);
});

test('la firma pasa al subrogante cuando la acción queda registrada', function () {
    $titular    = ($this->servidorCon)($this->puesto->id);
    $subrogante = ($this->servidorCon)();

    $subrogacion = ($this->registrarSubrogacion)($subrogante, $titular);
    $movimiento  = $subrogacion->movimientoPersonal;

    $movimiento = $this->stateService->transicionar($movimiento, EstadoAccionPersonal::SUSCRITA, [
        'dictamen_presupuestario_ref' => 'DIC-2026-001',
    ]);
    $this->stateService->transicionar($movimiento->fresh(), EstadoAccionPersonal::REGISTRADA);

    expect($subrogacion->fresh()->estado)->toBe(EstadoSubrogacion::ACTIVA);

    $firma = $this->firmantes->resolver(RolFirmaAccionPersonal::AUTORIDAD_NOMINADORA, now()->toDateString());

    expect($firma['subrogado'])->toBeTrue()
        ->and($firma['servidor']?->id)->toBe($subrogante->id);
});

test('anular la acción cancela la subrogación y devuelve la firma al titular', function () {
    $titular    = ($this->servidorCon)($this->puesto->id);
    $subrogante = ($this->servidorCon)();

    $subrogacion = ($this->registrarSubrogacion)($subrogante, $titular);
    $movimiento  = $subrogacion->movimientoPersonal;

    $movimiento = $this->stateService->transicionar($movimiento, EstadoAccionPersonal::SUSCRITA, [
        'dictamen_presupuestario_ref' => 'DIC-2026-002',
    ]);
    $this->stateService->transicionar($movimiento->fresh(), EstadoAccionPersonal::ANULADA);

    expect($subrogacion->fresh()->estado)->toBe(EstadoSubrogacion::CANCELADA);

    $firma = $this->firmantes->resolver(RolFirmaAccionPersonal::AUTORIDAD_NOMINADORA, now()->toDateString());

    expect($firma['subrogado'])->toBeFalse()
        ->and($firma['servidor']?->id)->toBe($titular->id);
});

// ── El titular tiene que ser el del puesto ──────────────────────

/**
 * Se podía nombrar titular a cualquiera: el registro decía "Fulano subroga a
 * Mengano en el puesto X" aunque Mengano nunca hubiera ocupado X. No daba poder
 * de más —firma y organigrama se resuelven por el puesto— pero producía un
 * documento firmado que afirma un reemplazo que no ocurrió.
 */
test('el titular debe ocupar el puesto que se va a subrogar', function () {
    ($this->servidorCon)($this->puesto->id);   // titular real del puesto
    $ajeno      = ($this->servidorCon)();      // no ocupa ningún puesto
    $subrogante = ($this->servidorCon)();

    expect(fn () => ($this->registrarSubrogacion)($subrogante, $ajeno))
        ->toThrow(
            \App\Exceptions\ReglaNegocioException::class,
            'no ocupa el puesto de Prefecto/a Provincial'
        );
});

test('un puesto vacante no se subroga: se encarga', function () {
    $cualquiera = ($this->servidorCon)();
    $subrogante = ($this->servidorCon)();

    // El puesto de la prueba nace sin titular.
    expect(fn () => ($this->registrarSubrogacion)($subrogante, $cualquiera))
        ->toThrow(
            \App\Exceptions\ReglaNegocioException::class,
            'está vacante: corresponde un encargo'
        );
});

test('un puesto con titular no se encarga: se subroga', function () {
    ($this->servidorCon)($this->puesto->id);
    $subrogante = ($this->servidorCon)();

    expect(fn () => $this->service->registrar([
        'tipo'                     => TipoSubrogacion::ENCARGO->value,
        'servidor_subrogante_id'   => $subrogante->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_subrogado_id'      => $this->puesto->id,
        'fecha_inicio'             => now()->subDay()->toDateString(),
        'fecha_fin'                => now()->addMonth()->toDateString(),
        'motivo'                   => 'encargo_vacante',
    ]))->toThrow(
        \App\Exceptions\ReglaNegocioException::class,
        'tiene titular: corresponde una subrogación'
    );
});

// ── Documento impreso ───────────────────────────────────────────

/**
 * La subrogación queda fuera de esAccionDePersonal() por un motivo de
 * construcción —se crea con su propio servicio— y eso le bloqueaba el PDF. Es
 * un acto formal (Art. 21 del Reglamento a la LOSEP) y se imprime con el mismo
 * formato de situación actual y propuesta que las demás.
 */
test('la acción de una subrogación registrada produce su documento', function () {
    $titular    = ($this->servidorCon)($this->puesto->id);
    $subrogante = ($this->servidorCon)();

    $subrogacion = ($this->registrarSubrogacion)($subrogante, $titular);
    $movimiento  = $subrogacion->movimientoPersonal;

    $movimiento = $this->stateService->transicionar($movimiento, EstadoAccionPersonal::SUSCRITA, [
        'dictamen_presupuestario_ref' => 'DIC-2026-010',
    ]);
    $this->stateService->transicionar($movimiento->fresh(), EstadoAccionPersonal::REGISTRADA);

    $pdf = app(\App\Services\Expediente\AccionPersonalPdfService::class)
        ->generarContent($movimiento->id);

    expect($pdf['content'])->toStartWith('%PDF');
});

test('un encargo no se imprime como subrogación', function () {
    $subrogante = ($this->servidorCon)();

    $subrogacion = $this->service->registrar([
        'tipo'                     => TipoSubrogacion::ENCARGO->value,
        'servidor_subrogante_id'   => $subrogante->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_subrogado_id'      => $this->puesto->id,
        'fecha_inicio'             => now()->subDay()->toDateString(),
        'fecha_fin'                => now()->addMonth()->toDateString(),
        'motivo'                   => 'encargo_vacante',
    ]);

    // Ambos comparten tipo_movimiento 'subrogacion'; solo la fila enlazada
    // distingue cuál es, y el documento debe decirlo por su nombre.
    expect($subrogacion->movimientoPersonal->subrogacion->tipo)
        ->toBe(TipoSubrogacion::ENCARGO)
        ->and($subrogacion->movimientoPersonal->descripcion)
        ->toStartWith('Encargo del puesto de Prefecto/a Provincial');
});

test('la explicación impresa nombra puesto, unidad, titular y plazo', function () {
    $titular    = ($this->servidorCon)($this->puesto->id);
    $subrogante = ($this->servidorCon)();

    $subrogacion = ($this->registrarSubrogacion)($subrogante, $titular);

    expect($subrogacion->movimientoPersonal->descripcion)
        ->toContain('Subrogación del puesto de Prefecto/a Provincial')
        ->toContain('en Prefectura Provincial')
        ->toContain('en reemplazo de '.$titular->apellido)
        ->toContain($subrogacion->fecha_fin->format('d/m/Y'));
});

test('una subrogación en borrador todavía no se imprime', function () {
    $titular    = ($this->servidorCon)($this->puesto->id);
    $subrogante = ($this->servidorCon)();

    $subrogacion = ($this->registrarSubrogacion)($subrogante, $titular);

    expect(fn () => app(\App\Services\Expediente\AccionPersonalPdfService::class)
        ->generarContent($subrogacion->movimiento_personal_id))
        ->toThrow(\App\Exceptions\ReglaNegocioException::class, 'registrada o notificada');
});

// ── Coherencia del estado nuevo ─────────────────────────────────

test('quién ejerce hoy sale del organigrama, no de un listado aparte', function () {
    $titular    = ($this->servidorCon)($this->puesto->id);
    $subrogante = ($this->servidorCon)();

    $subrogacion = ($this->registrarSubrogacion)($subrogante, $titular);

    // Pendiente: el organigrama sigue mostrando al titular.
    expect($this->unidad->fresh()->load('subrogacionesVigentes')->subrogacionesVigentes)
        ->toHaveCount(0);

    $subrogacion->update(['estado' => EstadoSubrogacion::ACTIVA->value]);

    expect($this->unidad->fresh()->load('subrogacionesVigentes')->subrogacionesVigentes)
        ->toHaveCount(1);
});

test('el listado de la pantalla sí muestra las pendientes', function () {
    $titular    = ($this->servidorCon)($this->puesto->id);
    $subrogante = ($this->servidorCon)();

    $subrogacion = ($this->registrarSubrogacion)($subrogante, $titular);

    $vigentes = $this->service->listarVigentes();

    expect($vigentes)->toHaveCount(1)
        ->and($vigentes->first()->id)->toBe($subrogacion->id)
        ->and($vigentes->first()->estado)->toBe(EstadoSubrogacion::PENDIENTE);
});

test('el listado de la pantalla deja fuera las canceladas', function () {
    $titular    = ($this->servidorCon)($this->puesto->id);
    $subrogante = ($this->servidorCon)();

    $subrogacion = ($this->registrarSubrogacion)($subrogante, $titular);
    $this->service->cancelar($subrogacion->id, 'Se resolvió de otra forma');

    expect($this->service->listarVigentes())->toHaveCount(0);
});

test('una pendiente se puede cancelar sin haber sido aprobada', function () {
    $titular    = ($this->servidorCon)($this->puesto->id);
    $subrogante = ($this->servidorCon)();

    $subrogacion = ($this->registrarSubrogacion)($subrogante, $titular);

    $this->service->cancelar($subrogacion->id, 'Se resolvió de otra forma');

    expect($subrogacion->fresh()->estado)->toBe(EstadoSubrogacion::CANCELADA);
});

test('el traslape también cuenta las pendientes', function () {
    $titular    = ($this->servidorCon)($this->puesto->id);
    $subrogante = ($this->servidorCon)();

    ($this->registrarSubrogacion)($subrogante, $titular);

    expect(fn () => ($this->registrarSubrogacion)($subrogante, $titular))
        ->toThrow(\App\Exceptions\ReglaNegocioException::class, 'ya cuenta con una subrogación');
});

test('sin dictamen presupuestario la acción no puede suscribirse', function () {
    $titular    = ($this->servidorCon)($this->puesto->id);
    $subrogante = ($this->servidorCon)();

    $subrogacion = ($this->registrarSubrogacion)($subrogante, $titular);

    expect(fn () => $this->stateService->transicionar(
        $subrogacion->movimientoPersonal,
        EstadoAccionPersonal::SUSCRITA
    ))->toThrow(\App\Exceptions\ReglaNegocioException::class);

    expect($subrogacion->fresh()->estado)->toBe(EstadoSubrogacion::PENDIENTE);
});

// ── Traslapes ───────────────────────────────────────────────────

/**
 * El control de traslapes miraba solo al subrogante, así que dos personas
 * podían quedar subrogando el mismo puesto el mismo mes. Con las dos activas,
 * quién firma lo decidía el `orderByDesc('fecha_inicio')->first()` de
 * subroganteDe(): para un puesto de jefatura, la facultad de firmar repartida
 * al azar entre dos personas sin que ningún acto lo diga.
 */
test('un puesto ya cubierto no admite una segunda subrogación en las mismas fechas', function () {
    $titular = ($this->servidorCon)($this->puesto->id);
    $primero = ($this->servidorCon)();
    $segundo = ($this->servidorCon)();

    ($this->registrarSubrogacion)($primero, $titular);

    expect(fn () => ($this->registrarSubrogacion)($segundo, $titular))
        ->toThrow(\App\Exceptions\ReglaNegocioException::class);

    expect(Subrogacion::where('puesto_subrogado_id', $this->puesto->id)->count())->toBe(1);
});

test('el mensaje nombra el puesto y el plazo que ya está cubierto', function () {
    $titular = ($this->servidorCon)($this->puesto->id);
    ($this->registrarSubrogacion)(($this->servidorCon)(), $titular);

    try {
        ($this->registrarSubrogacion)(($this->servidorCon)(), $titular);
        $this->fail('Se esperaba que la segunda subrogación fuera rechazada.');
    } catch (\App\Exceptions\ReglaNegocioException $e) {
        expect($e->getMessage())
            ->toContain('Prefecto/a Provincial')
            ->toContain(now()->subDay()->format('d/m/Y'));
    }
});

test('el puesto vuelve a estar libre cuando la anterior se cancela', function () {
    $titular = ($this->servidorCon)($this->puesto->id);
    $primera = ($this->registrarSubrogacion)(($this->servidorCon)(), $titular);

    $this->service->cancelar($primera->id, 'El titular no viajó.');

    $segunda = ($this->registrarSubrogacion)(($this->servidorCon)(), $titular);

    expect($segunda->estado)->toBe(EstadoSubrogacion::PENDIENTE);
});

test('otro puesto de la misma unidad no se estorba', function () {
    $otroPuesto = Puesto::create([
        'codigo' => 'P-DIR', 'unidad_administrativa_id' => $this->unidad->id,
        'plazas' => 1, 'cargo_id' => Cargo::firstOrCreate(['nombre' => 'Director/a Financiero'])->id,
        'partida_presupuestaria_id' => $this->partida->id,
    ]);

    $titularPref = ($this->servidorCon)($this->puesto->id);
    $titularDir  = ($this->servidorCon)($otroPuesto->id);

    ($this->registrarSubrogacion)(($this->servidorCon)(), $titularPref);

    $segunda = $this->service->registrar([
        'tipo'                     => TipoSubrogacion::SUBROGACION->value,
        'servidor_subrogante_id'   => ($this->servidorCon)()->id,
        'servidor_subrogado_id'    => $titularDir->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_subrogado_id'      => $otroPuesto->id,
        'fecha_inicio'             => now()->subDay()->toDateString(),
        'fecha_fin'                => now()->addMonth()->toDateString(),
        'motivo'                   => 'vacaciones',
    ]);

    expect($segunda->puesto_subrogado_id)->toBe($otroPuesto->id);
});

test('un plazo posterior sobre el mismo puesto sí se registra', function () {
    $titular = ($this->servidorCon)($this->puesto->id);
    ($this->registrarSubrogacion)(($this->servidorCon)(), $titular);

    $siguiente = $this->service->registrar([
        'tipo'                     => TipoSubrogacion::SUBROGACION->value,
        'servidor_subrogante_id'   => ($this->servidorCon)()->id,
        'servidor_subrogado_id'    => $titular->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_subrogado_id'      => $this->puesto->id,
        'fecha_inicio'             => now()->addMonths(2)->toDateString(),
        'fecha_fin'                => now()->addMonths(3)->toDateString(),
        'motivo'                   => 'vacaciones',
    ]);

    expect($siguiente->estado)->toBe(EstadoSubrogacion::PENDIENTE);
});

// ── La subrogación no se edita ──────────────────────────────────

/**
 * La regla existía desde el 2026-09-26 pero solo en la pantalla:
 * `AccionBotonEditar` esconde el botón y explica por qué. `PUT /movimientos/{id}`
 * no la comprobaba y la ruta pide únicamente `role:admin-uath`, así que se
 * alcanzaba sin pasar por ninguna pantalla — y entonces la acción y la fila de
 * `subrogaciones` quedaban diciendo cosas distintas: una manda el documento
 * impreso, la otra decide quién firma.
 */
test('el borrador de una subrogación no se edita desde el API', function () {
    $titular    = ($this->servidorCon)($this->puesto->id);
    $subrogante = ($this->servidorCon)();

    $movimiento = ($this->registrarSubrogacion)($subrogante, $titular)->movimientoPersonal;

    $this->putJson("/api/v1/expediente/movimientos/{$movimiento->id}", [
        'fecha_inicio' => now()->addMonths(6)->toDateString(),
        'fecha_fin'    => now()->addMonths(9)->toDateString(),
    ])->assertStatus(422);

    expect($movimiento->fresh()->fecha_inicio->toDateString())
        ->toBe(now()->subDay()->toDateString());
});

test('el mensaje dice qué hacer en su lugar', function () {
    $titular    = ($this->servidorCon)($this->puesto->id);
    $movimiento = ($this->registrarSubrogacion)(($this->servidorCon)(), $titular)->movimientoPersonal;

    $respuesta = $this->putJson("/api/v1/expediente/movimientos/{$movimiento->id}", [
        'puesto_destino_id' => $this->puesto->id,
    ])->assertStatus(422);

    expect($respuesta->json('mensaje'))->toContain('Cancélela y regístrela de nuevo');
});

test('el borrador de cualquier otro tipo se sigue editando', function () {
    $servidor = ($this->servidorCon)($this->puesto->id);

    $movimiento = MovimientoPersonal::create([
        'servidor_id'     => $servidor->id,
        'tipo_movimiento' => 'novedad_contrato',
        'estado'          => EstadoAccionPersonal::BORRADOR,
        'descripcion'     => 'Novedad de prueba',
        'fecha_efectiva'  => now()->toDateString(),
    ]);

    $this->putJson("/api/v1/expediente/movimientos/{$movimiento->id}", [
        'descripcion' => 'Novedad corregida',
    ])->assertOk();

    expect($movimiento->fresh()->descripcion)->toBe('Novedad corregida');
});
