<?php

namespace Tests\Feature\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Expediente\MovimientoPersonalService;
use App\Services\Expediente\MovimientoPersonalStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Anular una acción de personal YA REGISTRADA.
 *
 * Hasta el 2026-09-29 no se podía: el grafo solo permitía anular desde
 * borrador y suscrita, y una acción registrada tampoco se editaba (guarda de
 * inmutabilidad). Si el error aparecía después de registrar —que es cuando
 * aparece, porque se nota al leer el documento impreso— Talento Humano no
 * tenía ninguna salida. Lo único que existía era `corregir()`, que emitía un
 * SEGUNDO documento con otro correlativo dejando el primero vigente.
 *
 * Preguntado, TH: «Lo correcto es anularla, para emitir uno nuevo.»
 *
 * Anular después de registrar no es cambiarle el estado a una fila: hay que
 * deshacer lo que el registro hizo sobre el ContratoServidor. Eso es lo que
 * cubre este archivo, rama por rama.
 */
beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);

    $this->user = User::factory()->create();
    $this->user->assignRole('admin-uath');
    $this->actingAs($this->user, 'sanctum');

    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'UAN-01', 'nombre' => 'Unidad de Anulaciones', 'nivel' => 1,
    ]);
    $this->puestoA = Puesto::create([
        'codigo' => 'AN-A', 'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 5,
    ]);
    $this->puestoB = Puesto::create([
        'codigo' => 'AN-B', 'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 5,
    ]);

    $this->state   = app(MovimientoPersonalStateService::class);
    $this->service = app(MovimientoPersonalService::class);

    /** Lleva una acción de borrador a registrada. */
    $this->registrar = function (MovimientoPersonal $m): MovimientoPersonal {
        $m = $this->state->transicionar($m, EstadoAccionPersonal::SUSCRITA);

        return $this->state->transicionar($m->fresh(), EstadoAccionPersonal::REGISTRADA);
    };

    $this->anular = fn (MovimientoPersonal $m, string $motivo = 'Error de digitación.') => $this->state
        ->transicionar($m->fresh(), EstadoAccionPersonal::ANULADA, ['motivo_anulacion' => $motivo]);

    $this->servidorConContrato = function (string $cedula, string $nombramiento = 'nombramiento_permanente') {
        $servidor = Servidor::create([
            'user_id' => User::factory()->create()->id,
            'cedula' => $cedula, 'nombre' => 'Prueba', 'apellido' => 'Anulacion',
            'regimen_laboral' => 'losep',
            'puesto_id' => $this->puestoA->id,
            'unidad_administrativa_id' => $this->unidad->id,
        ]);

        ContratoServidor::create([
            'servidor_id' => $servidor->id,
            'tipo_nombramiento' => $nombramiento,
            'numero_contrato' => 'CT-AN-'.$cedula,
            'unidad_administrativa_id' => $this->unidad->id,
            'puesto_id' => $this->puestoA->id,
            'fecha_inicio' => '2020-01-01',
            'estado' => 'vigente',
        ]);

        return $servidor->fresh();
    };
});

// ── El grafo ────────────────────────────────────────────────────

test('una acción registrada y una notificada se pueden anular', function () {
    $servidor = ($this->servidorConContrato)('7001001001');

    foreach ([EstadoAccionPersonal::REGISTRADA, EstadoAccionPersonal::NOTIFICADA] as $desde) {
        // Una licencia: se registra sin tocar el vínculo, así que sirve para
        // probar el grafo sin arrastrar la reversión.
        $accion = $this->service->registrar($servidor->id, [
            'tipo_movimiento' => TipoMovimientoPersonal::LICENCIA_SIN_REMUNERACION->value,
            // Con causal desde la fase 2.2; el servicio militar no tiene tope.
            'subtipo_movimiento' => 'servicio_militar',
            'descripcion'     => 'Licencia sin remuneración',
            'fecha_efectiva'  => '2026-05-01',
            'fecha_inicio'    => '2026-05-01',
            'fecha_fin'       => '2026-06-01',
        ]);

        $accion = ($this->registrar)($accion);

        if ($desde === EstadoAccionPersonal::NOTIFICADA) {
            $accion = $this->state->transicionar($accion, EstadoAccionPersonal::NOTIFICADA);
        }

        $anulada = ($this->anular)($accion);

        expect($anulada->estado)->toBe(EstadoAccionPersonal::ANULADA, $desde->value)
            ->and($anulada->motivo_anulacion)->toBe('Error de digitación.');
    }
});

test('de anulada no se sale', function () {
    $servidor = ($this->servidorConContrato)('7001001002');

    $accion = $this->service->registrar($servidor->id, [
        'tipo_movimiento' => TipoMovimientoPersonal::LICENCIA_SIN_REMUNERACION->value,
        // Con causal desde la fase 2.2; el servicio militar no tiene tope.
        'subtipo_movimiento' => 'servicio_militar',
        'descripcion'     => 'Licencia',
        'fecha_efectiva'  => '2026-05-01',
        'fecha_inicio'    => '2026-05-01',
        'fecha_fin'       => '2026-06-01',
    ]);

    $anulada = ($this->anular)(($this->registrar)($accion));

    expect(fn () => $this->state->transicionar($anulada, EstadoAccionPersonal::NOTIFICADA))
        ->toThrow(ReglaNegocioException::class);
});

// ── Revertir: el ingreso ────────────────────────────────────────

/*
| El vínculo se BORRA, no se cierra. Cerrarlo dejaría en el expediente un
| contrato terminado que afirma que la persona estuvo vinculada y dejó de
| estarlo, y eso no ocurrió: el acto que lo creó quedó sin efecto. El borrado
| es lógico, así que la fila sigue ahí para auditoría.
*/
test('anular un ingreso registrado deshace el vínculo que creó', function () {
    $servidor = Servidor::create([
        'user_id' => User::factory()->create()->id,
        'cedula' => '7002002002', 'nombre' => 'Recién', 'apellido' => 'Ingresado',
        'regimen_laboral' => 'losep',
    ]);

    $ingreso = MovimientoPersonal::create([
        'servidor_id' => $servidor->id,
        'tipo_movimiento' => 'ingreso',
        'categoria' => \App\Enums\CategoriaEventoVinculo::ACCION_DE_PERSONAL,
        'estado' => EstadoAccionPersonal::BORRADOR,
        'descripcion' => 'Ingreso con el puesto equivocado',
        'fecha_efectiva' => '2026-02-01',
        'tipo_nombramiento_propuesto' => 'nombramiento_provisional',
        'puesto_destino_id' => $this->puestoA->id,
        'unidad_destino_id' => $this->unidad->id,
        'remuneracion_propuesta' => 900,
        'numero_contrato' => 'CT-ING-1',
        'autorizado_por' => $this->user->id,
    ]);

    $ingreso = ($this->registrar)($ingreso);

    $contrato = ContratoServidor::where('servidor_id', $servidor->id)->first();
    expect($contrato)->not->toBeNull()
        // El enlace que hace posible deshacerlo: sin él no había forma de saber
        // qué contrato había nacido de esta acción.
        ->and($contrato->movimiento_origen_id)->toBe($ingreso->id);

    ($this->anular)($ingreso);

    expect(ContratoServidor::where('servidor_id', $servidor->id)->exists())->toBeFalse()
        ->and(ContratoServidor::withTrashed()->where('servidor_id', $servidor->id)->count())->toBe(1);

    // Y el servidor vuelve a quedar sin puesto, como antes del ingreso.
    $servidor->refresh();
    expect($servidor->contratoVigente)->toBeNull()
        ->and($servidor->puesto_id)->toBeNull();
});

/*
| Y la consecuencia que lo hace útil: con el vínculo deshecho, el ingreso
| corregido SÍ se puede registrar. Era justo lo que `corregir()` no lograba
| —moría en `assertSinVinculoVigente`, porque el vínculo del ingreso original
| seguía en pie—, y es lo que TH pidió: anular y emitir uno nuevo.
*/
test('tras anular el ingreso se puede registrar el que lo reemplaza', function () {
    $servidor = Servidor::create([
        'user_id' => User::factory()->create()->id,
        'cedula' => '7002002003', 'nombre' => 'Segundo', 'apellido' => 'Intento',
        'regimen_laboral' => 'losep',
    ]);

    $datos = [
        'servidor_id' => $servidor->id,
        'tipo_movimiento' => 'ingreso',
        'categoria' => \App\Enums\CategoriaEventoVinculo::ACCION_DE_PERSONAL,
        'estado' => EstadoAccionPersonal::BORRADOR,
        'fecha_efectiva' => '2026-02-01',
        'tipo_nombramiento_propuesto' => 'nombramiento_provisional',
        'unidad_destino_id' => $this->unidad->id,
        'remuneracion_propuesta' => 900,
        'autorizado_por' => $this->user->id,
    ];

    $malo = ($this->registrar)(MovimientoPersonal::create([
        ...$datos,
        'descripcion' => 'Ingreso al puesto equivocado',
        'puesto_destino_id' => $this->puestoA->id,
        'numero_contrato' => 'CT-ING-MALO',
    ]));

    ($this->anular)($malo, 'Se registró sobre el puesto equivocado.');

    $bueno = ($this->registrar)(MovimientoPersonal::create([
        ...$datos,
        'descripcion' => 'Ingreso al puesto correcto',
        'puesto_destino_id' => $this->puestoB->id,
        'numero_contrato' => 'CT-ING-BUENO',
    ]));

    $servidor->refresh();

    expect($bueno->estado)->toBe(EstadoAccionPersonal::REGISTRADA)
        ->and($servidor->contratoVigente->puesto_id)->toBe($this->puestoB->id)
        ->and($servidor->contratoVigente->numero_contrato)->toBe('CT-ING-BUENO')
        // Dos correlativos, pero solo uno vigente: el otro lleva su sello.
        ->and($malo->fresh()->estado)->toBe(EstadoAccionPersonal::ANULADA);
});

// ── Revertir: la cesación ───────────────────────────────────────

test('anular una cesación registrada devuelve el vínculo a vigente', function () {
    $servidor = ($this->servidorConContrato)('7003003003');
    $contrato = $servidor->contratoVigente;

    $cesacion = $this->service->registrar($servidor->id, [
        'tipo_movimiento'    => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::RENUNCIA->value,
        'descripcion'        => 'Renuncia con la fecha equivocada',
        'fecha_efectiva'     => '2026-03-01',
    ]);

    $cesacion = ($this->registrar)($cesacion);

    $contrato->refresh();
    expect($contrato->estado->value)->toBe('terminado')
        ->and($contrato->movimiento_cierre_id)->toBe($cesacion->id);

    ($this->anular)($cesacion, 'La renuncia no se presentó en esa fecha.');

    $contrato->refresh();
    expect($contrato->estado->value)->toBe('vigente')
        ->and($contrato->fecha_fin)->toBeNull()
        ->and($contrato->motivo_fin)->toBeNull()
        ->and($contrato->movimiento_cierre_id)->toBeNull();

    // Y el servidor vuelve a su puesto, no solo el contrato.
    $servidor->refresh();
    expect($servidor->contratoVigente->id)->toBe($contrato->id)
        ->and($servidor->puesto_id)->toBe($this->puestoA->id);
});

// ── Revertir: el traspaso ───────────────────────────────────────

test('anular un traspaso registrado devuelve al servidor a su puesto de origen', function () {
    $servidor = ($this->servidorConContrato)('7004004004');

    $traspaso = $this->service->registrar($servidor->id, [
        'tipo_movimiento'    => TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::TRASPASO->value,
        'descripcion'        => 'Traspaso al puesto B',
        'fecha_efectiva'     => '2026-03-01',
        'puesto_destino_id'  => $this->puestoB->id,
        'unidad_destino_id'  => $this->unidad->id,
    ]);

    $traspaso = ($this->registrar)($traspaso);

    expect($servidor->fresh()->contratoVigente->puesto_id)->toBe($this->puestoB->id);

    ($this->anular)($traspaso, 'El traspaso no fue autorizado.');

    $servidor->refresh();

    // Vuelve al A, y sigue siendo EL MISMO contrato: un traspaso nunca creó
    // otro, y deshacerlo tampoco.
    expect($servidor->contratoVigente->puesto_id)->toBe($this->puestoA->id)
        ->and($servidor->puesto_id)->toBe($this->puestoA->id)
        ->and(ContratoServidor::where('servidor_id', $servidor->id)->count())->toBe(1);
});

// ── La guarda del orden ─────────────────────────────────────────

/*
| Revertir mira el estado de HOY del vínculo, no el de entonces. Si al traspaso
| de marzo le siguió otro en julio, deshacer el de marzo devolvería al servidor
| al puesto de enero y borraría de hecho el de julio, que sigue registrado y con
| su documento firmado.
*/
test('no se anula una acción si después se registró otra que toca el vínculo', function () {
    $servidor = ($this->servidorConContrato)('7005005005');

    $puestoC = Puesto::create([
        'codigo' => 'AN-C', 'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 5,
    ]);

    $primero = ($this->registrar)($this->service->registrar($servidor->id, [
        'tipo_movimiento'    => TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::TRASPASO->value,
        'descripcion'        => 'Primer traspaso: A → B',
        'fecha_efectiva'     => '2026-03-01',
        'puesto_destino_id'  => $this->puestoB->id,
        'unidad_destino_id'  => $this->unidad->id,
    ]));

    $segundo = ($this->registrar)($this->service->registrar($servidor->id, [
        'tipo_movimiento'    => TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::TRASPASO->value,
        'descripcion'        => 'Segundo traspaso: B → C',
        'fecha_efectiva'     => '2026-07-01',
        'puesto_destino_id'  => $puestoC->id,
        'unidad_destino_id'  => $this->unidad->id,
    ]));

    expect(fn () => ($this->anular)($primero))
        ->toThrow(ReglaNegocioException::class, $segundo->codigo_registro);

    // El vínculo no se movió ni a medias.
    expect($servidor->fresh()->contratoVigente->puesto_id)->toBe($puestoC->id);

    // De la última hacia atrás sí: anulado el segundo, el primero ya se deja.
    ($this->anular)($segundo);
    expect($servidor->fresh()->contratoVigente->puesto_id)->toBe($this->puestoB->id);

    ($this->anular)($primero);
    expect($servidor->fresh()->contratoVigente->puesto_id)->toBe($this->puestoA->id);
});

/*
| Pero una acción que NO toca el vínculo no bloquea a las de atrás: una licencia
| registrada después de un traspaso no dice nada sobre el puesto.
*/
test('una acción posterior que no toca el vínculo no impide anular', function () {
    $servidor = ($this->servidorConContrato)('7006006006');

    $traspaso = ($this->registrar)($this->service->registrar($servidor->id, [
        'tipo_movimiento'    => TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::TRASPASO->value,
        'descripcion'        => 'Traspaso al puesto B',
        'fecha_efectiva'     => '2026-03-01',
        'puesto_destino_id'  => $this->puestoB->id,
        'unidad_destino_id'  => $this->unidad->id,
    ]));

    ($this->registrar)($this->service->registrar($servidor->id, [
        'tipo_movimiento' => TipoMovimientoPersonal::LICENCIA_SIN_REMUNERACION->value,
        // Con causal desde la fase 2.2; el servicio militar no tiene tope.
        'subtipo_movimiento' => 'servicio_militar',
        'descripcion'     => 'Licencia posterior',
        'fecha_efectiva'  => '2026-08-01',
        'fecha_inicio'    => '2026-08-01',
        'fecha_fin'       => '2026-09-01',
    ]));

    ($this->anular)($traspaso);

    expect($servidor->fresh()->contratoVigente->puesto_id)->toBe($this->puestoA->id);
});

// ── Anular lo que nunca surtió efecto sigue igual ───────────────

test('anular un borrador no toca ningún vínculo', function () {
    $servidor = ($this->servidorConContrato)('7007007007');

    $traspaso = $this->service->registrar($servidor->id, [
        'tipo_movimiento'    => TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::TRASPASO->value,
        'descripcion'        => 'Traspaso que no llegó a aprobarse',
        'fecha_efectiva'     => '2026-03-01',
        'puesto_destino_id'  => $this->puestoB->id,
        'unidad_destino_id'  => $this->unidad->id,
    ]);

    ($this->anular)($traspaso);

    // Sigue en A porque el traspaso nunca se registró; y sin correlativo, así
    // que la reversión ni se plantea.
    expect($servidor->fresh()->contratoVigente->puesto_id)->toBe($this->puestoA->id)
        ->and($traspaso->fresh()->codigo_registro)->toBeNull();
});

// ── El documento de lo anulado ──────────────────────────────────

test('una acción anulada que llegó a registrarse conserva su documento', function () {
    $servidor = ($this->servidorConContrato)('7008008008');

    $traspaso = ($this->registrar)($this->service->registrar($servidor->id, [
        'tipo_movimiento'    => TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::TRASPASO->value,
        'descripcion'        => 'Traspaso anulado',
        'fecha_efectiva'     => '2026-03-01',
        'puesto_destino_id'  => $this->puestoB->id,
        'unidad_destino_id'  => $this->unidad->id,
    ]));

    ($this->anular)($traspaso, 'No hubo acto administrativo que lo respalde.');

    $pdf = app(\App\Services\Expediente\AccionPersonalPdfService::class)
        ->generarContent($traspaso->id);

    expect($pdf['content'])->toStartWith('%PDF')
        // El archivo se distingue del vigente en la carpeta de quien descarga
        // los dos.
        ->and($pdf['filename'])->toContain('_ANULADA');
});

test('un borrador anulado sigue sin documento: nunca fue nada', function () {
    $servidor = ($this->servidorConContrato)('7009009009');

    $traspaso = $this->service->registrar($servidor->id, [
        'tipo_movimiento'    => TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::TRASPASO->value,
        'descripcion'        => 'Nunca aprobado',
        'fecha_efectiva'     => '2026-03-01',
        'puesto_destino_id'  => $this->puestoB->id,
        'unidad_destino_id'  => $this->unidad->id,
    ]);

    ($this->anular)($traspaso);

    expect(fn () => app(\App\Services\Expediente\AccionPersonalPdfService::class)
        ->generarContent($traspaso->id))
        ->toThrow(ReglaNegocioException::class);
});

// ── tocaElVinculo() no se queda atrás ───────────────────────────

/*
| La reversión tiene las mismas tres ramas que `aplicarRegistro()`, y pregunta
| por ellas a través de `tocaElVinculo()`. Si un día se añade un tipo que toque
| el vínculo al registrarse y solo se actualiza una de las dos listas, su
| anulación dejaría el contrato afirmando algo que ningún acto respalda. Esto
| comprueba que las dos digan lo mismo.
*/
test('tocaElVinculo() cubre exactamente las ramas que aplican un efecto al registrar', function () {
    foreach (TipoMovimientoPersonal::cases() as $tipo) {
        $movimiento = new MovimientoPersonal(['tipo_movimiento' => $tipo->value]);

        $aplicaAlgo = $tipo->creaVinculo()
            || $movimiento->reubicaAlServidor()
            || (bool) $movimiento->subtipoEfectivo()?->cierraVinculo();

        expect($movimiento->tocaElVinculo())->toBe($aplicaAlgo, $tipo->value);
    }

    foreach (SubtipoMovimientoPersonal::cases() as $subtipo) {
        $movimiento = new MovimientoPersonal([
            'tipo_movimiento'    => TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO->value,
            'subtipo_movimiento' => $subtipo->value,
        ]);

        expect($movimiento->tocaElVinculo())
            ->toBe($subtipo->modificaPuesto() || $subtipo->cierraVinculo(), $subtipo->value);
    }
});
