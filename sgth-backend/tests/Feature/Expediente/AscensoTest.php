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
use App\Models\Estructura\PartidaPresupuestaria;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Expediente\ContratoServidorService;
use App\Services\Expediente\MovimientoPersonalService;
use App\Services\Expediente\MovimientoPersonalStateService;
use App\Services\Expediente\PeriodoDePruebaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| Fase 3.3 del diseño de Acciones de Personal: el ascenso (LOSEP Arts. 17 b.5,
| 68 y 81; TH N2). El servidor de carrera pasa al puesto que ganó en concurso,
| de escala superior, con nombramiento provisional de prueba por seis meses y su
| puesto anterior reservado. Si no la supera vuelve a él con el reintegro; si la
| supera, recibe el nombramiento definitivo.
*/
beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    permisosDeAccionesPersonal();

    $this->director = User::factory()->create();
    $this->director->assignRole('admin-uath');
    $this->actingAs($this->director, 'sanctum');

    $this->estados = app(MovimientoPersonalStateService::class);
    $this->hoy = now()->toDateString();

    $this->unidadA = UnidadAdministrativa::create(['codigo' => 'AS-A', 'nombre' => 'Dirección A', 'nivel' => 1]);
    $this->unidadB = UnidadAdministrativa::create(['codigo' => 'AS-B', 'nombre' => 'Dirección B', 'nivel' => 1]);

    $grupo = fn (string $codigo, float $rmu) => GrupoOcupacional::create([
        'grado_codigo' => $codigo, 'grado_numerico' => 1, 'grupo' => $codigo,
        'denominacion_generica' => $codigo, 'rmu' => $rmu, 'regimen' => 'losep', 'activo' => true,
    ]);
    $sp5 = $grupo('SP5', 1500);
    $sp7 = $grupo('SP7', 1800);

    $this->partidaDeAntes = PartidaPresupuestaria::create([
        'codigo' => '510105', 'descripcion' => 'Remuneraciones Unificadas',
        'grupo_gasto' => 'Gastos en Personal', 'activo' => true, 'disponible' => true,
    ]);
    $this->partidaDelGanado = PartidaPresupuestaria::create([
        'codigo' => '510105-02', 'descripcion' => 'Remuneraciones Unificadas (Dirección B)',
        'grupo_gasto' => 'Gastos en Personal', 'activo' => true, 'disponible' => true,
    ]);

    $puesto = fn (UnidadAdministrativa $u, ?GrupoOcupacional $g, array $extra = []) => Puesto::create([
        'unidad_administrativa_id' => $u->id, 'plazas' => 1, 'grupo_ocupacional_id' => $g?->id, ...$extra,
    ]);

    $this->origen    = $puesto($this->unidadA, $sp5, ['plazas' => 3]);
    $this->ganado    = $puesto($this->unidadB, $sp7, ['plazas' => 3, 'partida_presupuestaria_id' => $this->partidaDelGanado->id]);
    $this->igual     = $puesto($this->unidadB, $sp5);
    $this->sinEscala = $puesto($this->unidadB, null);

    $this->contador = 0;

    $this->servidor = function (array $extra = [], TipoNombramiento $nombramiento = TipoNombramiento::PERMANENTE): Servidor {
        $this->contador++;
        $remuneracion = $extra['remuneracion'] ?? 1500;
        unset($extra['remuneracion']);

        $servidor = Servidor::create([
            'cedula'                    => str_pad((string) (8400000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre'                    => 'Servidor',
            'apellido'                  => 'Ascendido'.$this->contador,
            'regimen_laboral'           => 'losep',
            'tipo_nombramiento'         => $nombramiento->value,
            'fecha_ingreso_institucion' => '2015-01-01',
            'fecha_nombramiento'        => '2015-01-01',
            'fecha_nacimiento'          => '1985-05-05',
            ...$extra,
        ]);

        ContratoServidor::create([
            'servidor_id'               => $servidor->id,
            'tipo_nombramiento'         => $nombramiento->value,
            'supuesto_provisional'      => $nombramiento === TipoNombramiento::PROVISIONAL ? 'puesto_vacante' : null,
            'unidad_administrativa_id'  => $this->origen->unidad_administrativa_id,
            'puesto_id'                 => $this->origen->id,
            'partida_presupuestaria_id' => $this->partidaDeAntes->id,
            'remuneracion'              => $remuneracion,
            'fecha_inicio'              => '2015-01-01',
            'estado'                    => 'vigente',
        ]);

        return $servidor->fresh('contratoVigente');
    };

    $this->ascender = fn (Servidor $s, Puesto $p, array $extra = []) => $this->postJson(
        "/api/v1/expediente/servidores/{$s->id}/movimientos",
        [
            'clase'                    => 'ascenso',
            'descripcion'              => 'Ascenso por concurso de méritos y oposición',
            'fecha_efectiva'           => $this->hoy,
            'unidad_destino_id'        => $p->unidad_administrativa_id,
            'puesto_destino_id'        => $p->id,
            'acta_concurso_numero'     => 'ACTA-CONC-2026-011',
            'acta_concurso_fecha'      => now()->subDays(5)->toDateString(),
            'requiere_dictamen_medico' => false,
            ...$extra,
        ]
    );

    $this->registrar = function (int $id): MovimientoPersonal {
        $this->estados->transicionar(MovimientoPersonal::findOrFail($id), EstadoAccionPersonal::SUSCRITA);

        return $this->estados->transicionar(MovimientoPersonal::findOrFail($id), EstadoAccionPersonal::REGISTRADA);
    };

    /** Un servidor ya ascendido, con el ascenso rigiendo desde la fecha dada. */
    $this->ascendido = function (string $desde): array {
        $servidor = ($this->servidor)();
        $id = ($this->ascender)($servidor, $this->ganado, ['fecha_efectiva' => $desde])->assertCreated()->json('datos.id');
        $ascenso = ($this->registrar)($id);

        return [$servidor->fresh('contratoVigente'), $ascenso, $servidor->fresh('contratoVigente')->contratoVigente];
    };

    $this->anular = fn (MovimientoPersonal $m) => $this->estados->transicionar(
        $m->fresh(), EstadoAccionPersonal::ANULADA, ['motivo_anulacion' => 'Registrado por error.']
    );
});

// ── Lo que pide el ascenso ──────────────────────────────────────

test('se prepara al puesto ganado, con el nombramiento de prueba y la remuneración de su escala', function () {
    ($this->ascender)(($this->servidor)(), $this->ganado)
        ->assertCreated()
        ->assertJsonPath('datos.clase', 'ascenso')
        ->assertJsonPath('datos.estado', 'borrador')
        ->assertJsonPath('datos.tipo_nombramiento_propuesto', 'nombramiento_provisional')
        ->assertJsonPath('datos.acta_concurso_numero', 'ACTA-CONC-2026-011');

    $ascenso = MovimientoPersonal::where('tipo_movimiento', TipoMovimientoPersonal::ASCENSO->value)->firstOrFail();

    expect((float) $ascenso->remuneracion_propuesta)->toBe(1800.0)
        // La partida del puesto ganado, si la acción no trae otra.
        ->and($ascenso->partida_presupuestaria_id)->toBe($this->partidaDelGanado->id)
        // Lo de antes queda congelado: es a donde vuelve si no supera la prueba.
        ->and($ascenso->puesto_origen_id)->toBe($this->origen->id)
        ->and((float) $ascenso->remuneracion_origen)->toBe(1500.0);
});

test('es a una escala superior: la igual es un traslado, y sin grupo ocupacional no se sabe', function () {
    ($this->ascender)(($this->servidor)(), $this->igual)
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'escala superior') && str_contains($m, 'traslado'));

    ($this->ascender)(($this->servidor)(), $this->sinEscala)
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'grupo ocupacional'));
});

test('es del servidor de carrera, y no a los 65 años', function () {
    ($this->ascender)(($this->servidor)([], TipoNombramiento::PROVISIONAL), $this->ganado)
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'no aplica'));

    ($this->ascender)(($this->servidor)(['fecha_nacimiento' => now()->subYears(65)->toDateString()]), $this->ganado)
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'Art. 81'));
});

test('conserva su remuneración si ya cobraba más que la escala ganada', function () {
    $id = ($this->ascender)(($this->servidor)(['remuneracion' => 2000]), $this->ganado)->json('datos.id');

    expect((float) MovimientoPersonal::findOrFail($id)->remuneracion_propuesta)->toBe(2000.0);
});

test('no se suscribe sin el acta del concurso', function () {
    $id = ($this->ascender)(($this->servidor)(), $this->ganado, [
        'acta_concurso_numero' => null, 'acta_concurso_fecha' => null,
    ])->assertCreated()->json('datos.id');

    expect(fn () => $this->estados->transicionar(MovimientoPersonal::findOrFail($id), EstadoAccionPersonal::SUSCRITA))
        ->toThrow(ReglaNegocioException::class, 'acta final del concurso');
});

test('al corregir el borrador se vuelve a comprobar el puesto y a calcular la remuneración', function () {
    $id = ($this->ascender)(($this->servidor)(), $this->ganado)->json('datos.id');
    $servicio = app(MovimientoPersonalService::class);

    expect(fn () => $servicio->actualizarBorrador(MovimientoPersonal::findOrFail($id), [
        'puesto_destino_id' => $this->igual->id, 'unidad_destino_id' => $this->igual->unidad_administrativa_id,
    ]))->toThrow(ReglaNegocioException::class, 'escala superior');

    $corregido = $servicio->actualizarBorrador(MovimientoPersonal::findOrFail($id), [
        'remuneracion_propuesta' => 5000, 'tipo_nombramiento_propuesto' => null,
    ]);

    expect((float) $corregido->remuneracion_propuesta)->toBe(1800.0)
        ->and($corregido->tipo_nombramiento_propuesto)->toBe(TipoNombramiento::PROVISIONAL);
});

// ── El efecto sobre el vínculo ──────────────────────────────────

test('al registrarse, el mismo vínculo pasa al puesto ganado, de prueba, y reserva el de antes', function () {
    $servidor = ($this->servidor)();
    $antiguedad = $servidor->inicioDelServicioContinuo();

    $ascenso = ($this->registrar)(($this->ascender)($servidor, $this->ganado)->json('datos.id'));
    $vigente = $servidor->fresh('contratoVigente')->contratoVigente;

    expect($vigente->puesto_id)->toBe($this->ganado->id)
        ->and($vigente->unidad_administrativa_id)->toBe($this->unidadB->id)
        ->and($vigente->partida_presupuestaria_id)->toBe($this->partidaDelGanado->id)
        ->and((float) $vigente->remuneracion)->toBe(1800.0)
        ->and($vigente->tipo_nombramiento)->toBe(TipoNombramiento::PROVISIONAL)
        ->and($vigente->supuesto_provisional)->toBe(SupuestoProvisional::ASCENSO)
        ->and($vigente->puesto_reservado_id)->toBe($this->origen->id)
        ->and($vigente->movimiento_ascenso_id)->toBe($ascenso->id)
        ->and($vigente->finDelPeriodoDePrueba()->toDateString())->toBe(now()->addMonthsNoOverflow(6)->subDay()->toDateString())
        ->and(ContratoServidor::where('servidor_id', $servidor->id)->count())->toBe(1)
        ->and($servidor->fresh()->inicioDelServicioContinuo())->toBe($antiguedad)
        // La copia del servidor también, que el expediente filtra por ella.
        ->and($servidor->fresh()->tipo_nombramiento)->toBe(TipoNombramiento::PROVISIONAL)
        ->and($servidor->fresh()->puesto_id)->toBe($this->ganado->id);
});

test('el puesto reservado sigue ocupado: no se nombra a otro en él', function () {
    $this->origen->update(['plazas' => 1]);
    ($this->ascendido)($this->hoy);

    // La plantilla de Estructura cuenta lo mismo: el ganado y el reservado.
    $ocupadas = app(\App\Services\Estructura\PlantillaService::class)->resumen()['plazas']['ocupadas'];

    expect($this->origen->fresh()->plazasOcupadas())->toBe(1)
        ->and($ocupadas)->toBe(2)
        ->and($this->origen->fresh()->tieneVacantes())->toBeFalse()
        ->and(fn () => app(ContratoServidorService::class)->validarVacante(
            $this->origen->id, TipoNombramiento::PERMANENTE->value,
        ))->toThrow(ReglaNegocioException::class, 'no tiene plazas');
});

test('anular el ascenso lo devuelve a su puesto, con su remuneración, su partida y su nombramiento', function () {
    [$servidor, $ascenso] = ($this->ascendido)($this->hoy);

    ($this->anular)($ascenso);
    $vigente = $servidor->fresh('contratoVigente')->contratoVigente;

    expect($vigente->puesto_id)->toBe($this->origen->id)
        ->and($vigente->unidad_administrativa_id)->toBe($this->unidadA->id)
        ->and($vigente->partida_presupuestaria_id)->toBe($this->partidaDeAntes->id)
        ->and((float) $vigente->remuneracion)->toBe(1500.0)
        ->and($vigente->tipo_nombramiento)->toBe(TipoNombramiento::PERMANENTE)
        ->and($vigente->supuesto_provisional)->toBeNull()
        ->and($vigente->puesto_reservado_id)->toBeNull()
        ->and($servidor->fresh()->tipo_nombramiento)->toBe(TipoNombramiento::PERMANENTE)
        ->and($servidor->fresh()->fecha_nombramiento?->toDateString())->toBe('2015-01-01');
});

// ── Cómo termina la prueba ──────────────────────────────────────

test('si no la supera, el reintegro lo devuelve a su puesto anterior con su remuneración anterior', function () {
    [$servidor, $ascenso, $contrato] = ($this->ascendido)(now()->subMonths(2)->toDateString());
    $url = "/api/v1/expediente/contratos/{$contrato->id}/reintegro-del-ascenso";

    // La ley lo condiciona a una evaluación: sin su informe no se prepara.
    $this->postJson($url, ['fecha_regreso' => $this->hoy, 'descripcion' => 'No superó la prueba'])
        ->assertStatus(422);

    $id = $this->postJson($url, [
        'fecha_regreso'       => $this->hoy,
        'informe_uath_numero' => 'INF-EVAL-2026-003',
        'descripcion'         => 'No superó la evaluación del período de prueba del ascenso.',
    ])->assertCreated()
        ->assertJsonPath('datos.clase', 'reintegro')
        ->json('datos.id');

    $reintegro = ($this->registrar)($id);
    $vigente = $servidor->fresh('contratoVigente')->contratoVigente;

    expect($reintegro->movimiento_relacionado_id)->toBe($ascenso->id)
        ->and($vigente->puesto_id)->toBe($this->origen->id)
        ->and((float) $vigente->remuneracion)->toBe(1500.0)
        ->and($vigente->partida_presupuestaria_id)->toBe($this->partidaDeAntes->id)
        ->and($vigente->tipo_nombramiento)->toBe(TipoNombramiento::PERMANENTE)
        ->and($vigente->puesto_reservado_id)->toBeNull()
        ->and($vigente->movimiento_ascenso_id)->toBeNull();

    // Anularlo lo devuelve al puesto ganado, con su prueba y su reserva.
    ($this->anular)($reintegro);
    $vigente = $servidor->fresh('contratoVigente')->contratoVigente;

    expect($vigente->puesto_id)->toBe($this->ganado->id)
        ->and($vigente->supuesto_provisional)->toBe(SupuestoProvisional::ASCENSO)
        ->and($vigente->puesto_reservado_id)->toBe($this->origen->id)
        ->and($vigente->movimiento_ascenso_id)->toBe($ascenso->id);
});

test('el reintegro cae dentro de la prueba', function () {
    [, , $contrato] = ($this->ascendido)(now()->subMonths(7)->toDateString());

    $this->postJson("/api/v1/expediente/contratos/{$contrato->id}/reintegro-del-ascenso", [
        'fecha_regreso'       => $this->hoy,
        'informe_uath_numero' => 'INF-EVAL-2026-004',
        'descripcion'         => 'No superó la prueba.',
    ])->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'a más tardar'));
});

test('a los seis meses se prepara el definitivo, que libera la reserva; anularlo la devuelve', function () {
    $this->origen->update(['plazas' => 1]);
    [$servidor, $ascenso, $contrato] = ($this->ascendido)(now()->subMonths(7)->toDateString());

    $id = $this->postJson("/api/v1/expediente/contratos/{$contrato->id}/nombramiento-definitivo")
        ->assertCreated()
        ->json('datos.id');

    $definitivo = ($this->registrar)($id);
    $vigente = $servidor->fresh('contratoVigente')->contratoVigente;

    expect($definitivo->movimiento_relacionado_id)->toBe($ascenso->id)
        ->and($vigente->puesto_id)->toBe($this->ganado->id)
        ->and($vigente->tipo_nombramiento)->toBe(TipoNombramiento::PERMANENTE)
        ->and($vigente->puesto_reservado_id)->toBeNull()
        ->and($this->origen->fresh()->tieneVacantes())->toBeTrue()
        // Ya era de carrera: su fecha de nombramiento no cambia.
        ->and($servidor->fresh()->fecha_nombramiento?->toDateString())->toBe('2015-01-01');

    ($this->anular)($definitivo);
    $vigente = $servidor->fresh('contratoVigente')->contratoVigente;

    expect($vigente->supuesto_provisional)->toBe(SupuestoProvisional::ASCENSO)
        ->and($vigente->puesto_reservado_id)->toBe($this->origen->id)
        ->and($this->origen->fresh()->tieneVacantes())->toBeFalse();
});

test('el definitivo no llega antes de los seis meses, ni con un reintegro en trámite', function () {
    [, , $contrato] = ($this->ascendido)(now()->subMonths(4)->toDateString());

    $this->postJson("/api/v1/expediente/contratos/{$contrato->id}/nombramiento-definitivo")
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'seis meses'));

    // Concluida la prueba, con el reintegro preparado en su último día.
    [, , $concluido] = ($this->ascendido)(now()->subMonths(7)->toDateString());
    $this->postJson("/api/v1/expediente/contratos/{$concluido->id}/reintegro-del-ascenso", [
        'fecha_regreso'       => $concluido->finDelPeriodoDePrueba()->toDateString(),
        'informe_uath_numero' => 'INF-EVAL-2026-005',
        'descripcion'         => 'No superó la prueba.',
    ])->assertCreated();

    $this->postJson("/api/v1/expediente/contratos/{$concluido->id}/nombramiento-definitivo")
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'reintegro'));

    // Y el comando diario no se lo prepara.
    expect(app(PeriodoDePruebaService::class)->prepararLosConcluidos($this->hoy)['preparados'])->toBe([]);
});

test('con el definitivo en trámite no se prepara el reintegro, ni se anula el ascenso', function () {
    [, $ascenso, $contrato] = ($this->ascendido)(now()->subMonths(7)->toDateString());
    $this->postJson("/api/v1/expediente/contratos/{$contrato->id}/nombramiento-definitivo")->assertCreated();

    $this->postJson("/api/v1/expediente/contratos/{$contrato->id}/reintegro-del-ascenso", [
        'fecha_regreso'       => $contrato->finDelPeriodoDePrueba()->toDateString(),
        'informe_uath_numero' => 'INF-EVAL-2026-006',
        'descripcion'         => 'No superó la prueba.',
    ])->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'nombramiento definitivo en trámite'));

    expect(fn () => ($this->anular)($ascenso))
        ->toThrow(ReglaNegocioException::class, 'anule antes el nombramiento definitivo');
});

test('en la prueba del ascenso no se le remueve ni se le cesa por la prueba', function () {
    [$servidor] = ($this->ascendido)($this->hoy);
    $servicio = app(MovimientoPersonalService::class);

    foreach ([SubtipoMovimientoPersonal::REMOCION, SubtipoMovimientoPersonal::PERIODO_PRUEBA_NO_SUPERADO] as $causal) {
        expect(fn () => $servicio->registrar($servidor->id, [
            'tipo_movimiento'    => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
            'subtipo_movimiento' => $causal->value,
            'descripcion'        => 'Cesación',
            'fecha_efectiva'     => $this->hoy,
        ]))->toThrow(ReglaNegocioException::class, 'reintegro');
    }
});

// ── Pantalla, catálogo y documento ──────────────────────────────

test('la pantalla de períodos de prueba enseña al ascendido y el puesto que se le guarda', function () {
    [$servidor, , $contrato] = ($this->ascendido)(now()->subMonths(2)->toDateString());

    $fila = collect($this->getJson('/api/v1/expediente/periodos-de-prueba')->assertOk()->json('datos'))
        ->firstWhere('contrato_id', $contrato->id);

    expect($fila['motivo'])->toBe('ascenso')
        ->and($fila['servidor']['id'])->toBe($servidor->id)
        ->and($fila['desde'])->toBe(now()->subMonths(2)->toDateString())
        ->and($fila['hasta'])->toBe($contrato->finDelPeriodoDePrueba()->toDateString())
        ->and($fila['concluida'])->toBeFalse()
        ->and($fila['reintegro'])->toBeNull();
});

test('el catálogo trae el ascenso para los permanentes, con el acta, y sin su supuesto en el ingreso', function () {
    $catalogo = $this->getJson('/api/v1/expediente/acciones-personal/catalogo')->assertOk();
    $ascenso = collect($catalogo->json('datos.clases'))->firstWhere('codigo', 'ascenso');

    expect($ascenso['familia'])->toBe('cambio_administrativo')
        ->and($ascenso['nombramientos_elegibles'])->toBe(['nombramiento_permanente'])
        ->and($ascenso['pide_acta_concurso'])->toBeTrue()
        ->and($ascenso['pide_situacion_propuesta'])->toBeTrue()
        ->and(collect($catalogo->json('datos.supuestos_provisional'))->pluck('codigo')->all())->not->toContain('ascenso');
});

test('el documento del ascenso dice la reserva y el acta; el del reintegro, la evaluación', function () {
    [$servidor, $ascenso, $contrato] = ($this->ascendido)(now()->subMonths(2)->toDateString());

    $firma = fn (RolFirmaAccionPersonal $rol) => ['rotulo' => $rol->rotuloDocumento(), 'nombre' => null, 'cargo' => null];
    $html = fn (MovimientoPersonal $m) => view('pdf.expediente.accion-personal', [
        'movimiento'         => $m->fresh(),
        'servidor'           => $servidor->fresh(),
        'firmaAutoridad'     => $firma(RolFirmaAccionPersonal::AUTORIDAD_NOMINADORA),
        'firmaTalentoHumano' => $firma(RolFirmaAccionPersonal::RESPONSABLE_TALENTO_HUMANO),
        'logo'               => public_path('images/logo-gadpe.png'),
    ])->render();

    expect($html($ascenso))->toContain('Ascenso')
        ->toContain('seis meses')
        ->toContain('ACTA-CONC-2026-011')
        ->toContain('Se le reserva su puesto');

    $id = $this->postJson("/api/v1/expediente/contratos/{$contrato->id}/reintegro-del-ascenso", [
        'fecha_regreso'       => $this->hoy,
        'informe_uath_numero' => 'INF-EVAL-2026-007',
        'descripcion'         => 'No superó la evaluación del período de prueba.',
    ])->json('datos.id');

    expect($html(MovimientoPersonal::findOrFail($id)))
        ->toContain('Informe de la evaluación del período de prueba')
        ->toContain('INF-EVAL-2026-007')
        ->toContain('remuneración anterior')
        ->not->toContain('El último día de la ausencia');
});
