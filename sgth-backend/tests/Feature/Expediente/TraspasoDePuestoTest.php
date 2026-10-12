<?php

namespace Tests\Feature\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Models\Estructura\Puesto;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\LoteAccion;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Expediente\MovimientoPersonalStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| Fase 7.1a del diseño de Acciones de Personal: el traspaso de un puesto a
| otra unidad de la institución (LOSEP Art. 37; Reglamento a la LOSEP Art.
| 69). El puesto pasa con su partida, y cada ocupante recibe su acción en un
| lote que se registra y se anula entero.
|
| Hoy es el lunes 12 de octubre de 2026.
*/
beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    permisosDeAccionesPersonal();
    $this->travelTo(Carbon::parse('2026-10-12 10:00'));

    $this->user = User::factory()->create();
    $this->user->assignRole('admin-uath');
    $this->actingAs($this->user, 'sanctum');

    $this->origen = unidadDePrueba(['nombre' => 'Dirección de Obras']);
    $this->destino = unidadDePrueba(['nombre' => 'Dirección de Planificación']);
    $this->puesto = puestoDePrueba($this->origen, 'Analista de Proyectos', ['plazas' => 3]);

    $this->contador = 0;
    $this->ocupante = function (Puesto $puesto, string $nombramiento = 'nombramiento_permanente'): Servidor {
        $this->contador++;
        $servidor = Servidor::create([
            'cedula' => str_pad((string) (6800000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre' => 'Servidor', 'apellido' => 'Traspaso'.$this->contador, 'regimen_laboral' => 'losep',
            'fecha_ingreso_institucion' => '2018-01-02',
        ]);
        ContratoServidor::create([
            'servidor_id' => $servidor->id, 'tipo_nombramiento' => $nombramiento,
            'unidad_administrativa_id' => $puesto->unidad_administrativa_id, 'puesto_id' => $puesto->id,
            'fecha_inicio' => '2018-01-02', 'estado' => 'vigente', 'remuneracion' => 1000,
            // El contrato civil tiene su vencimiento.
            'fecha_fin' => $nombramiento === 'servicios_profesionales' ? '2026-12-31' : null,
        ]);
        $servidor->update(['puesto_id' => $puesto->id, 'unidad_administrativa_id' => $puesto->unidad_administrativa_id]);

        return $servidor->fresh();
    };

    $this->primero = ($this->ocupante)($this->puesto);
    $this->segundo = ($this->ocupante)($this->puesto, 'nombramiento_provisional');

    $this->datos = fn (array $extra = []) => [
        'puesto_id'           => $this->puesto->id,
        'unidad_destino_id'   => $this->destino->id,
        'criterio'            => 'reorganizacion_interna',
        'informe_uath_numero' => 'INF-UATH-2026-031',
        'descripcion'         => 'Traspaso del puesto por la reorganización de la Dirección de Obras.',
        'fecha_efectiva'      => '2026-10-12',
        ...$extra,
    ];

    $this->preparar = fn (array $extra = []) => $this->postJson('/api/v1/expediente/lotes-accion/traspaso-puesto', ($this->datos)($extra));

    $this->lote = fn (array $extra = []) => LoteAccion::findOrFail(($this->preparar)($extra)->assertCreated()->json('datos.id'));
});

// ── Preparar ────────────────────────────────────────────────────

test('prepara un borrador por ocupante: el mismo puesto, en la unidad nueva', function () {
    $lote = ($this->lote)();

    $actos = $lote->movimientos()->orderBy('id')->get();

    expect($lote->tipo)->toBe(LoteAccion::TRASPASO_PUESTO)
        ->and($lote->parametros['unidad_origen_id'])->toBe($this->origen->id)
        ->and($actos)->toHaveCount(2)
        ->and($actos->pluck('servidor_id')->all())->toBe([$this->primero->id, $this->segundo->id]);

    foreach ($actos as $acto) {
        expect($acto->clase->value)->toBe('traspaso_puesto')
            ->and($acto->estado)->toBe(EstadoAccionPersonal::BORRADOR)
            ->and($acto->puesto_destino_id)->toBe($this->puesto->id)
            ->and($acto->unidad_destino_id)->toBe($this->destino->id)
            ->and($acto->unidad_origen_id)->toBe($this->origen->id)
            ->and($acto->informe_uath_numero)->toBe('INF-UATH-2026-031')
            ->and($acto->casilla()->value)->toBe('traspaso');
    }

    // Preparar no mueve nada todavía.
    expect($this->puesto->fresh()->unidad_administrativa_id)->toBe($this->origen->id);
});

test('la vista previa dice quiénes lo recibirían y qué lo impide', function () {
    $this->postJson('/api/v1/expediente/lotes-accion/traspaso-puesto/previsualizar', ['puesto_id' => $this->puesto->id])
        ->assertOk()
        ->assertJsonCount(2, 'datos.ocupantes')
        ->assertJsonPath('datos.impedimentos', [])
        ->assertJsonCount(9, 'datos.criterios')
        ->assertJsonPath('datos.criterios.0.label', 'Reorganización interna (Art. 69, 1.a)');

    $vacante = puestoDePrueba($this->origen, 'Asistente');
    $this->postJson('/api/v1/expediente/lotes-accion/traspaso-puesto/previsualizar', [
        'puesto_id' => $vacante->id, 'unidad_destino_id' => $this->origen->id,
    ])
        ->assertOk()
        ->assertJsonPath('datos.impedimentos', fn (array $i) => count($i) === 2
            && str_contains($i[0], 'vacante') && str_contains($i[1], 'ya está en esa unidad'));
});

test('una jefatura no llega a una unidad que ya tiene la suya', function () {
    puestoJefeDePrueba($this->destino);
    $jefatura = puestoJefeDePrueba($this->origen);
    ($this->ocupante)($jefatura);

    $this->postJson('/api/v1/expediente/lotes-accion/traspaso-puesto', ($this->datos)(['puesto_id' => $jefatura->id]))
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'ya tiene su jefatura'));
});

test('el obrero y quien tiene Servicios Profesionales no entran, y se dice por qué', function () {
    $obreros = puestoDePrueba($this->origen, 'Jornalero', ['regimen_laboral' => 'codigo_trabajo']);
    ($this->ocupante)($obreros, 'codigo_trabajo');

    $this->postJson('/api/v1/expediente/lotes-accion/traspaso-puesto', ($this->datos)(['puesto_id' => $obreros->id]))
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'Código del Trabajo, Art. 192'));

    $profesional = ($this->ocupante)($this->puesto, 'servicios_profesionales');

    ($this->preparar)()
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, $profesional->apellido) && str_contains($m, 'adenda'));
});

test('con acciones en trámite sobre un ocupante no se prepara', function () {
    MovimientoPersonal::create([
        'servidor_id' => $this->primero->id, 'estado' => EstadoAccionPersonal::BORRADOR,
        'tipo_movimiento' => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::RENUNCIA->value,
        'descripcion' => 'Renuncia en trámite.', 'fecha_efectiva' => '2026-11-01',
    ]);

    ($this->preparar)()
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'en trámite o por regir'));
});

test('nadie traspasa el puesto que ocupa', function () {
    $this->user->update(['servidor_id' => $this->primero->id]);

    ($this->preparar)()
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'Usted ocupa este puesto'));
});

// ── Registrar ───────────────────────────────────────────────────

test('un acto del traspaso no se tramita suelto: va con su lote', function () {
    $acto = ($this->lote)()->movimientos()->first();

    $this->getJson("/api/v1/expediente/movimientos/{$acto->id}")
        ->assertJsonPath('datos.transiciones_permitidas', []);

    $this->putJson("/api/v1/expediente/movimientos/{$acto->id}/transicionar", ['estado' => 'suscrita'])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'desde el lote'));
});

test('registrar el lote mueve el puesto y a sus ocupantes, todos a la vez', function () {
    $lote = ($this->lote)();

    $this->postJson("/api/v1/expediente/lotes-accion/{$lote->id}/registrar")->assertOk();

    expect($lote->movimientos()->pluck('estado')->map->value->unique()->all())->toBe(['registrada'])
        ->and($this->puesto->fresh()->unidad_administrativa_id)->toBe($this->destino->id);

    foreach ([$this->primero, $this->segundo] as $servidor) {
        expect($servidor->fresh()->unidad_administrativa_id)->toBe($this->destino->id)
            ->and($servidor->fresh()->contratoVigente->unidad_administrativa_id)->toBe($this->destino->id)
            ->and($servidor->fresh()->contratoVigente->puesto_id)->toBe($this->puesto->id);
    }

    // El registro del puesto dice qué cambió y por qué.
    $registro = Activity::query()->where('subject_type', Puesto::class)->where('subject_id', $this->puesto->id)
        ->where('event', 'updated')->latest('id')->first();

    expect($registro->properties['antes']['unidad_administrativa_id'])->toBe($this->origen->id)
        ->and($registro->properties['despues']['unidad_administrativa_id'])->toBe($this->destino->id)
        ->and($registro->properties['causa'])->toContain("lote #{$lote->id}");

    // Cada uno se notifica por su lado.
    $this->getJson('/api/v1/expediente/movimientos/'.$lote->movimientos()->first()->id)
        ->assertJsonPath('datos.transiciones_permitidas', ['notificada']);
});

test('si uno no puede, no se registra ninguno', function () {
    $lote = ($this->lote)();

    // El segundo ya no está en el puesto.
    $this->segundo->contratoVigente->update(['puesto_id' => puestoDePrueba($this->origen, 'Otro')->id]);

    $this->postJson("/api/v1/expediente/lotes-accion/{$lote->id}/registrar")
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'Ningún acto del lote quedó registrado'));

    expect($lote->movimientos()->pluck('estado')->map->value->unique()->all())->toBe(['borrador'])
        ->and($this->puesto->fresh()->unidad_administrativa_id)->toBe($this->origen->id)
        ->and($this->primero->fresh()->unidad_administrativa_id)->toBe($this->origen->id);
});

test('lo que rige más tarde mueve el puesto el día en que rige', function () {
    $lote = ($this->lote)(['fecha_efectiva' => '2026-11-02']);

    $this->postJson("/api/v1/expediente/lotes-accion/{$lote->id}/registrar")->assertOk();

    expect($this->puesto->fresh()->unidad_administrativa_id)->toBe($this->origen->id);

    app(MovimientoPersonalStateService::class)->aplicarVigentes('2026-11-02');

    expect($this->puesto->fresh()->unidad_administrativa_id)->toBe($this->destino->id)
        ->and($this->segundo->fresh()->unidad_administrativa_id)->toBe($this->destino->id);
});

// ── Anular ──────────────────────────────────────────────────────

test('revertir el traspaso devuelve el puesto y a sus ocupantes', function () {
    $lote = ($this->lote)();
    $this->postJson("/api/v1/expediente/lotes-accion/{$lote->id}/registrar")->assertOk();

    $acto = $lote->movimientos()->first();
    $this->putJson("/api/v1/expediente/movimientos/{$acto->id}/transicionar", [
        'estado' => 'anulada', 'motivo_anulacion' => 'Solo este.',
    ])->assertStatus(422);

    $this->postJson("/api/v1/expediente/lotes-accion/{$lote->id}/revertir", ['motivo' => 'La reorganización no se aprobó.'])
        ->assertOk()
        ->assertJsonPath('datos.anulados', 2);

    expect($lote->movimientos()->pluck('estado')->map->value->unique()->all())->toBe(['anulada'])
        ->and($this->puesto->fresh()->unidad_administrativa_id)->toBe($this->origen->id)
        ->and($this->primero->fresh()->unidad_administrativa_id)->toBe($this->origen->id)
        ->and($this->segundo->fresh()->contratoVigente->unidad_administrativa_id)->toBe($this->origen->id);
});

test('en trámite se descarta; registrado, se revierte', function () {
    $enTramite = ($this->lote)();

    $this->postJson("/api/v1/expediente/lotes-accion/{$enTramite->id}/descartar", ['motivo' => 'Se preparó por error.'])
        ->assertOk()
        ->assertJsonPath('datos.anulados', 2);

    $registrado = ($this->lote)();
    $this->postJson("/api/v1/expediente/lotes-accion/{$registrado->id}/registrar")->assertOk();

    $this->postJson("/api/v1/expediente/lotes-accion/{$registrado->id}/descartar", ['motivo' => 'Ya no hace falta.'])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'reviértalo'));
});

test('el detalle del lote dice el puesto, las unidades y el criterio', function () {
    $lote = ($this->lote)();

    $this->getJson("/api/v1/expediente/lotes-accion/{$lote->id}")
        ->assertOk()
        ->assertJsonPath('datos.tipo', 'traspaso_puesto')
        ->assertJsonPath('datos.traspaso.puesto', 'Analista de Proyectos')
        ->assertJsonPath('datos.traspaso.unidad_origen', 'Dirección de Obras')
        ->assertJsonPath('datos.traspaso.unidad_destino', 'Dirección de Planificación')
        ->assertJsonPath('datos.traspaso.criterio', 'Reorganización interna (Art. 69, 1.a)')
        ->assertJsonCount(2, 'datos.actos');
});
