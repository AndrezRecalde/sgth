<?php

namespace Tests\Feature\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\RolFirmaAccionPersonal;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\SupuestoProvisional;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estructura\GrupoOcupacional;
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

/*
| Fase 3.2 del diseño de Acciones de Personal: el supuesto del provisional
| (LOSEP 17 b), el período de prueba de tres meses (b.5), el acta del concurso
| y el nombramiento definitivo [TH N3].
*/
beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    permisosDeAccionesPersonal();

    $this->director = User::factory()->create();
    $this->director->assignRole('admin-uath');
    $this->actingAs($this->director, 'sanctum');

    $this->estados = app(MovimientoPersonalStateService::class);
    $this->servicio = app(MovimientoPersonalService::class);
    $this->hoy = now()->toDateString();

    $this->unidad = UnidadAdministrativa::create(['codigo' => 'PP', 'nombre' => 'Dirección de Prueba', 'nivel' => 1]);
    $this->puesto = Puesto::create(['unidad_administrativa_id' => $this->unidad->id, 'plazas' => 10]);
    $this->puestoNjs = Puesto::create([
        'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 1,
        'grupo_ocupacional_id' => GrupoOcupacional::create([
            'grado_codigo' => 'NJS-3', 'grado_numerico' => 3, 'grupo' => 'NJS', 'denominacion_generica' => 'Director',
            'rmu' => 3000, 'regimen' => 'losep', 'activo' => true,
        ])->id,
    ]);

    $this->contador = 0;
    $this->persona = function (): Servidor {
        $this->contador++;

        return Servidor::create([
            'cedula'          => str_pad((string) (8300000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre'          => 'Servidor',
            'apellido'        => 'Prueba'.$this->contador,
            'regimen_laboral' => 'losep',
            'fecha_ingreso_institucion' => '2026-01-05',
        ]);
    };

    /** Un vínculo provisional ya vigente, desde la fecha dada. */
    $this->provisional = function (?SupuestoProvisional $supuesto, string $desde) {
        $servidor = ($this->persona)();

        $contrato = ContratoServidor::create([
            'servidor_id'              => $servidor->id,
            'tipo_nombramiento'        => TipoNombramiento::PROVISIONAL->value,
            'supuesto_provisional'     => $supuesto?->value,
            'numero_contrato'          => 'NP-'.$this->contador,
            'unidad_administrativa_id' => $this->unidad->id,
            'puesto_id'                => $this->puesto->id,
            'fecha_inicio'             => $desde,
            'remuneracion'             => 1200,
            'estado'                   => 'vigente',
        ]);

        return [$servidor->fresh('contratoVigente'), $contrato];
    };

    $this->ingreso = fn (Servidor $s, array $extra = []) => $this->servicio->registrar($s->id, [
        'tipo_movimiento'             => TipoMovimientoPersonal::INGRESO->value,
        'tipo_nombramiento_propuesto' => TipoNombramiento::PROVISIONAL->value,
        'puesto_destino_id'           => $this->puesto->id,
        'unidad_destino_id'           => $this->unidad->id,
        'numero_contrato'             => 'NP-2026-0'.$this->contador,
        'remuneracion_propuesta'      => 1200,
        'requiere_dictamen_medico'    => false,
        'descripcion'                 => 'Ingreso del ganador del concurso',
        'fecha_efectiva'              => $this->hoy,
        ...$extra,
    ]);

    $this->registrar = function (MovimientoPersonal $m): MovimientoPersonal {
        $this->estados->transicionar($m->fresh(), EstadoAccionPersonal::SUSCRITA);

        return $this->estados->transicionar($m->fresh(), EstadoAccionPersonal::REGISTRADA);
    };
});

// ── El supuesto y el acta del ingreso ───────────────────────────

test('el ingreso de un provisional no se suscribe sin su supuesto, y lo suscrito antes se registra', function () {
    $ingreso = ($this->ingreso)(($this->persona)());

    expect(fn () => $this->estados->transicionar($ingreso->fresh(), EstadoAccionPersonal::SUSCRITA))
        ->toThrow(ReglaNegocioException::class, 'el supuesto del nombramiento provisional');

    // Lo que ya estaba suscrito cuando llegó el campo no se puede corregir: se
    // registra sin supuesto, como los provisionales de antes.
    $servidor = ($this->persona)();
    $deAntes = ($this->ingreso)($servidor, ['supuesto_provisional_propuesto' => 'puesto_vacante']);
    $this->estados->transicionar($deAntes->fresh(), EstadoAccionPersonal::SUSCRITA);
    MovimientoPersonal::query()->whereKey($deAntes->id)->update(['supuesto_provisional_propuesto' => null]);

    $this->estados->transicionar($deAntes->fresh(), EstadoAccionPersonal::REGISTRADA);

    $vigente = $servidor->fresh('contratoVigente')->contratoVigente;

    expect($vigente->tipo_nombramiento)->toBe(TipoNombramiento::PROVISIONAL)
        ->and($vigente->supuesto_provisional)->toBeNull();
});

test('el de prueba lleva el acta del concurso, y el vínculo nace de prueba', function () {
    $sinActa = ($this->ingreso)(($this->persona)(), ['supuesto_provisional_propuesto' => 'de_prueba']);

    expect(fn () => ($this->registrar)($sinActa))
        ->toThrow(ReglaNegocioException::class, 'el acta final del concurso');

    $servidor = ($this->persona)();
    ($this->registrar)(($this->ingreso)($servidor, [
        'supuesto_provisional_propuesto' => 'de_prueba',
        'acta_concurso_numero'           => 'ACTA-CONC-2026-004',
        'acta_concurso_fecha'            => now()->subDays(10)->toDateString(),
    ]));

    $vigente = $servidor->fresh('contratoVigente')->contratoVigente;

    expect($vigente->tipo_nombramiento)->toBe(TipoNombramiento::PROVISIONAL)
        ->and($vigente->supuesto_provisional)->toBe(SupuestoProvisional::DE_PRUEBA)
        ->and($vigente->finDelPeriodoDePrueba()->toDateString())->toBe(now()->addMonthsNoOverflow(3)->subDay()->toDateString());
});

test('el del nivel jerárquico superior es para un puesto de ese nivel', function () {
    expect(fn () => ($this->ingreso)(($this->persona)(), ['supuesto_provisional_propuesto' => 'nivel_jerarquico_superior']))
        ->toThrow(ReglaNegocioException::class, 'nivel jerárquico superior');

    $ingreso = ($this->ingreso)(($this->persona)(), [
        'supuesto_provisional_propuesto' => 'nivel_jerarquico_superior',
        'puesto_destino_id'              => $this->puestoNjs->id,
    ]);

    expect($ingreso->supuesto_provisional_propuesto)->toBe(SupuestoProvisional::NIVEL_JERARQUICO_SUPERIOR);
});

test('fuera de un provisional, el supuesto y el acta no significan nada', function () {
    $ingreso = ($this->ingreso)(($this->persona)(), [
        'tipo_nombramiento_propuesto'    => TipoNombramiento::PERMANENTE->value,
        'supuesto_provisional_propuesto' => 'de_prueba',
        'acta_concurso_numero'           => 'ACTA-X',
    ]);

    expect($ingreso->supuesto_provisional_propuesto)->toBeNull()
        ->and($ingreso->acta_concurso_numero)->toBeNull();
});

// ── Las causales según el supuesto ──────────────────────────────

test('al de prueba no se le remueve; al de un puesto vacante, no se le cesa por la prueba', function () {
    $cesar = fn (Servidor $s, SubtipoMovimientoPersonal $causal) => $this->servicio->registrar($s->id, [
        'tipo_movimiento'    => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
        'subtipo_movimiento' => $causal->value,
        'descripcion'        => 'Cesación',
        'fecha_efectiva'     => $this->hoy,
    ]);

    [$dePrueba] = ($this->provisional)(SupuestoProvisional::DE_PRUEBA, '2026-09-01');
    expect(fn () => $cesar($dePrueba, SubtipoMovimientoPersonal::REMOCION))
        ->toThrow(ReglaNegocioException::class, 'no se le remueve');
    expect($cesar($dePrueba, SubtipoMovimientoPersonal::PERIODO_PRUEBA_NO_SUPERADO)->estado)->toBe(EstadoAccionPersonal::BORRADOR);

    [$vacante] = ($this->provisional)(SupuestoProvisional::PUESTO_VACANTE, '2026-09-01');
    expect(fn () => $cesar($vacante, SubtipoMovimientoPersonal::PERIODO_PRUEBA_NO_SUPERADO))
        ->toThrow(ReglaNegocioException::class, 'causal del provisional de prueba');
    expect($cesar($vacante, SubtipoMovimientoPersonal::REMOCION)->estado)->toBe(EstadoAccionPersonal::BORRADOR);

    // El de antes, sin supuesto, conserva las dos.
    [$deAntes] = ($this->provisional)(null, '2024-01-01');
    expect($cesar($deAntes, SubtipoMovimientoPersonal::REMOCION)->estado)->toBe(EstadoAccionPersonal::BORRADOR);
});

// ── El período de prueba y el nombramiento definitivo ───────────

test('la pantalla enseña a quien está en prueba y cuándo termina', function () {
    $desde = now()->subMonth()->toDateString();
    [$servidor] = ($this->provisional)(SupuestoProvisional::DE_PRUEBA, $desde);
    ($this->provisional)(SupuestoProvisional::PUESTO_VACANTE, $desde);

    $this->getJson('/api/v1/expediente/periodos-de-prueba')
        ->assertOk()
        ->assertJsonCount(1, 'datos')
        ->assertJsonPath('datos.0.servidor.id', $servidor->id)
        ->assertJsonPath('datos.0.hasta', now()->subMonth()->addMonthsNoOverflow(3)->subDay()->toDateString())
        ->assertJsonPath('datos.0.concluida', false)
        ->assertJsonPath('datos.0.definitivo', null);
});

test('el definitivo se prepara cumplidos los tres meses, uno por período', function () {
    [, $enCurso] = ($this->provisional)(SupuestoProvisional::DE_PRUEBA, now()->subMonth()->toDateString());

    $this->postJson("/api/v1/expediente/contratos/{$enCurso->id}/nombramiento-definitivo")
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'cuando se cumplan los tres meses'));

    $desde = now()->subMonths(4)->toDateString();
    [, $concluido] = ($this->provisional)(SupuestoProvisional::DE_PRUEBA, $desde);
    $fin = $concluido->finDelPeriodoDePrueba();

    $this->postJson("/api/v1/expediente/contratos/{$concluido->id}/nombramiento-definitivo")
        ->assertCreated()
        ->assertJsonPath('datos.clase', 'nombramiento_definitivo')
        ->assertJsonPath('datos.estado', 'borrador')
        ->assertJsonPath('datos.fecha_efectiva', fn ($f) => str_starts_with($f, $fin->copy()->addDay()->toDateString()));

    $this->postJson("/api/v1/expediente/contratos/{$concluido->id}/nombramiento-definitivo")
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'en trámite'));
});

test('al registrarse, el mismo vínculo pasa a permanente y anularlo lo devuelve a prueba', function () {
    [$servidor, $contrato] = ($this->provisional)(SupuestoProvisional::DE_PRUEBA, now()->subMonths(4)->toDateString());
    $antiguedad = $servidor->inicioDelServicioContinuo();

    $id = $this->postJson("/api/v1/expediente/contratos/{$contrato->id}/nombramiento-definitivo")->json('datos.id');
    $definitivo = ($this->registrar)(MovimientoPersonal::findOrFail($id));

    $contrato->refresh();
    expect($contrato->tipo_nombramiento)->toBe(TipoNombramiento::PERMANENTE)
        ->and($contrato->supuesto_provisional)->toBeNull()
        ->and($contrato->estado->value)->toBe('vigente')
        ->and(ContratoServidor::where('servidor_id', $servidor->id)->count())->toBe(1)
        ->and($servidor->fresh()->inicioDelServicioContinuo())->toBe($antiguedad);

    // El expediente filtra y exporta por la copia del servidor.
    expect($servidor->fresh()->tipo_nombramiento)->toBe(TipoNombramiento::PERMANENTE)
        ->and($servidor->fresh()->fecha_nombramiento?->toDateString())->toBe($definitivo->fecha_efectiva->toDateString());

    $this->estados->transicionar($definitivo->fresh(), EstadoAccionPersonal::ANULADA, ['motivo_anulacion' => 'Error en la fecha.']);

    $contrato->refresh();
    expect($contrato->tipo_nombramiento)->toBe(TipoNombramiento::PROVISIONAL)
        ->and($contrato->supuesto_provisional)->toBe(SupuestoProvisional::DE_PRUEBA)
        ->and($servidor->fresh()->tipo_nombramiento)->toBe(TipoNombramiento::PROVISIONAL)
        ->and($servidor->fresh()->fecha_nombramiento)->toBeNull();
});

test('no rige antes de que termine la prueba', function () {
    [, $contrato] = ($this->provisional)(SupuestoProvisional::DE_PRUEBA, now()->subMonths(4)->toDateString());
    $definitivo = MovimientoPersonal::findOrFail(
        $this->postJson("/api/v1/expediente/contratos/{$contrato->id}/nombramiento-definitivo")->json('datos.id')
    );

    $definitivo->update(['fecha_efectiva' => $contrato->finDelPeriodoDePrueba()->toDateString()]);

    expect(fn () => ($this->registrar)($definitivo))
        ->toThrow(ReglaNegocioException::class, 'rige después del período de prueba');
});

test('el comando diario prepara el de los períodos que concluyeron', function () {
    [, $concluido] = ($this->provisional)(SupuestoProvisional::DE_PRUEBA, now()->subMonths(4)->toDateString());
    [, $enCurso] = ($this->provisional)(SupuestoProvisional::DE_PRUEBA, now()->subMonth()->toDateString());
    // Un provisional de antes, sin supuesto, no entra.
    [, $deAntes] = ($this->provisional)(null, now()->subYear()->toDateString());

    $this->artisan('sgth:acciones:aplicar-vigentes')->assertSuccessful();
    $this->artisan('sgth:acciones:aplicar-vigentes')->assertSuccessful();

    $definitivos = fn (ContratoServidor $c) => MovimientoPersonal::where('servidor_id', $c->servidor_id)
        ->where('tipo_movimiento', TipoMovimientoPersonal::NOMBRAMIENTO_DEFINITIVO->value)->count();

    expect($definitivos($concluido))->toBe(1)
        ->and($definitivos($enCurso))->toBe(0)
        ->and($definitivos($deAntes))->toBe(0);
});

// ── Catálogo y documento ────────────────────────────────────────

test('el catálogo trae los supuestos, y el definitivo no está en el formulario', function () {
    $catalogo = $this->getJson('/api/v1/expediente/acciones-personal/catalogo')->assertOk();

    expect(collect($catalogo->json('datos.supuestos_provisional'))->pluck('codigo')->all())
        ->toBe(['suspendido_o_destituido', 'puesto_vacante', 'nivel_jerarquico_superior', 'de_prueba'])
        ->and(collect($catalogo->json('datos.clases'))->firstWhere('codigo', 'nombramiento_definitivo')['se_crea_desde_formulario'])
        ->toBeFalse();
});

test('el documento del ingreso dice el supuesto y el acta del concurso', function () {
    $servidor = ($this->persona)();
    $ingreso = ($this->ingreso)($servidor, [
        'supuesto_provisional_propuesto' => 'de_prueba',
        'acta_concurso_numero'           => 'ACTA-CONC-2026-004',
        'acta_concurso_fecha'            => '2026-09-20',
    ]);

    $firma = fn (RolFirmaAccionPersonal $rol) => ['rotulo' => $rol->rotuloDocumento(), 'nombre' => null, 'cargo' => null];

    $html = view('pdf.expediente.accion-personal', [
        'movimiento'         => $ingreso,
        'servidor'           => $servidor,
        'firmaAutoridad'     => $firma(RolFirmaAccionPersonal::AUTORIDAD_NOMINADORA),
        'firmaTalentoHumano' => $firma(RolFirmaAccionPersonal::RESPONSABLE_TALENTO_HUMANO),
        'logo'               => public_path('images/logo-gadpe.png'),
    ])->render();

    expect($html)->toContain('De prueba')
        ->and($html)->toContain('LOSEP Art. 17 b.5')
        ->and($html)->toContain('ACTA-CONC-2026-004')
        ->and($html)->toContain('20/09/2026');
});
