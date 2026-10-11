<?php

namespace Tests\Feature\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\RolFirmaAccionPersonal;
use App\Enums\SubtipoMovimientoPersonal;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| Fase 3.1 del diseño de Acciones de Personal: el traslado de la LOSEP Art. 35
| —a otro puesto vacante de la institución, con igual remuneración y previo
| informe de la UATH— y las jefaturas que no se cubren con reemplazo [TH 19].
*/
beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    permisosDeAccionesPersonal();

    $this->director = User::factory()->create();
    $this->director->assignRole('admin-uath');
    $this->actingAs($this->director, 'sanctum');

    $this->estados = app(MovimientoPersonalStateService::class);
    $this->hoy = now()->toDateString();

    $this->unidadA = UnidadAdministrativa::create(['codigo' => 'TR-A', 'nombre' => 'Dirección A', 'nivel' => 1]);
    $this->unidadB = UnidadAdministrativa::create(['codigo' => 'TR-B', 'nombre' => 'Dirección B', 'nivel' => 1]);

    $grupo = fn (string $codigo, float $rmu) => GrupoOcupacional::create([
        'grado_codigo' => $codigo, 'grado_numerico' => 1, 'grupo' => $codigo,
        'denominacion_generica' => $codigo, 'rmu' => $rmu, 'regimen' => 'losep', 'activo' => true,
    ]);
    $sp5 = $grupo('SP5', 1500);
    $sp7 = $grupo('SP7', 1800);

    $puesto = fn (UnidadAdministrativa $u, ?GrupoOcupacional $g, array $extra = []) => Puesto::create([
        'unidad_administrativa_id' => $u->id, 'plazas' => 1, 'grupo_ocupacional_id' => $g?->id, ...$extra,
    ]);

    $this->origen      = $puesto($this->unidadA, $sp5);
    $this->igual       = $puesto($this->unidadB, $sp5);
    $this->mayor       = $puesto($this->unidadB, $sp7);
    $this->sinEscala   = $puesto($this->unidadB, null);
    $this->inactivo    = $puesto($this->unidadB, $sp5, ['activo' => false]);
    $this->jefatura    = $puesto($this->unidadA, $sp5, ['es_jefe' => true]);

    $this->partidaDeAntes = PartidaPresupuestaria::create([
        'codigo' => '510105', 'descripcion' => 'Remuneraciones Unificadas',
        'grupo_gasto' => 'Gastos en Personal', 'activo' => true, 'disponible' => true,
    ]);
    $this->otraPartida = PartidaPresupuestaria::create([
        'codigo' => '510106', 'descripcion' => 'Salarios Unificados',
        'grupo_gasto' => 'Gastos en Personal', 'activo' => true, 'disponible' => true,
    ]);

    $this->contador = 0;

    $this->servidor = function (TipoNombramiento $nombramiento = TipoNombramiento::PERMANENTE, ?Puesto $en = null): Servidor {
        $this->contador++;
        $p = $en ?? $this->origen;

        $servidor = Servidor::create([
            'cedula'          => str_pad((string) (8100000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre'          => 'Servidor',
            'apellido'        => 'Trasladado'.$this->contador,
            'regimen_laboral' => 'losep',
            'fecha_ingreso_institucion' => '2018-01-01',
        ]);

        ContratoServidor::create([
            'servidor_id'               => $servidor->id,
            'tipo_nombramiento'         => $nombramiento->value,
            'unidad_administrativa_id'  => $p->unidad_administrativa_id,
            'puesto_id'                 => $p->id,
            'partida_presupuestaria_id' => $this->partidaDeAntes->id,
            'remuneracion'              => 1500,
            'fecha_inicio'              => '2018-01-01',
            'fecha_fin'                 => $nombramiento->exigePlazo() ? now()->addYear()->toDateString() : null,
            'estado'                    => 'vigente',
        ]);

        return $servidor->fresh('contratoVigente');
    };

    $this->trasladar = fn (Servidor $s, Puesto $p, array $extra = []) => $this->postJson(
        "/api/v1/expediente/servidores/{$s->id}/movimientos",
        [
            'clase'                    => 'traslado',
            'descripcion'              => 'Traslado por necesidad institucional',
            'fecha_efectiva'           => $this->hoy,
            'unidad_destino_id'        => $p->unidad_administrativa_id,
            'puesto_destino_id'        => $p->id,
            'informe_uath_numero'      => 'INF-UATH-2026-010',
            'requiere_dictamen_medico' => false,
            ...$extra,
        ]
    );

    $this->registrar = function (int $id): MovimientoPersonal {
        $this->estados->transicionar(MovimientoPersonal::findOrFail($id), EstadoAccionPersonal::SUSCRITA);

        return $this->estados->transicionar(MovimientoPersonal::findOrFail($id), EstadoAccionPersonal::REGISTRADA);
    };
});

// ── Lo que pide el Art. 35 ──────────────────────────────────────

test('se prepara con el informe de la UATH', function () {
    ($this->trasladar)(($this->servidor)(), $this->igual)
        ->assertCreated()
        ->assertJsonPath('datos.clase', 'traslado')
        ->assertJsonPath('datos.informe_uath_numero', 'INF-UATH-2026-010');

    ($this->trasladar)(($this->servidor)(), $this->igual, ['informe_uath_numero' => null])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'informe técnico de la UATH'));
});

test('a un puesto con igual remuneración', function () {
    ($this->trasladar)(($this->servidor)(), $this->mayor)
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'igual remuneración')
            && str_contains($m, '1,500.00') && str_contains($m, '1,800.00'));

    ($this->trasladar)(($this->servidor)(), $this->sinEscala)
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'no tiene grupo ocupacional'));
});

test('a un puesto activo, distinto del suyo y de la unidad indicada', function () {
    $servidor = ($this->servidor)();

    ($this->trasladar)($servidor, $this->inactivo)
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'inactivo'));

    ($this->trasladar)($servidor, $this->origen)
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'el mismo que ocupa'));

    ($this->trasladar)($servidor, $this->igual, ['unidad_destino_id' => $this->unidadA->id])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'no es de la unidad indicada'));
});

test('las reglas valen igual para el provisional, que se guarda como prestación de servicios', function () {
    $provisional = ($this->servidor)(TipoNombramiento::PROVISIONAL);

    $id = ($this->trasladar)($provisional, $this->igual)->assertCreated()->json('datos.id');
    expect(MovimientoPersonal::findOrFail($id)->tipo_movimiento)->toBe(TipoMovimientoPersonal::PRESTACION_SERVICIOS);

    ($this->trasladar)(($this->servidor)(TipoNombramiento::PROVISIONAL), $this->mayor)
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'igual remuneración'));
});

test('servicios profesionales conserva su prestación de servicios sin estas reglas', function () {
    ($this->trasladar)(($this->servidor)(TipoNombramiento::SERVICIOS_PROFESIONALES), $this->mayor, [
        'informe_uath_numero' => null,
    ])->assertCreated();
});

test('corregir el borrador no se salta las reglas', function () {
    $id = ($this->trasladar)(($this->servidor)(), $this->igual)->json('datos.id');

    $this->putJson("/api/v1/expediente/movimientos/{$id}", ['informe_uath_numero' => ''])
        ->assertStatus(422);

    $this->putJson("/api/v1/expediente/movimientos/{$id}", [
        'puesto_destino_id' => $this->mayor->id,
        'unidad_destino_id' => $this->unidadB->id,
    ])->assertStatus(422);
});

// ── Registrar y anular ──────────────────────────────────────────

test('al registrarse el vínculo toma puesto, unidad y la partida elegida; anularlo lo devuelve', function () {
    $servidor = ($this->servidor)();

    $traslado = ($this->registrar)(($this->trasladar)($servidor, $this->igual, [
        'partida_presupuestaria_id' => $this->otraPartida->id,
    ])->json('datos.id'));

    $vigente = $servidor->fresh('contratoVigente')->contratoVigente;

    expect($vigente->puesto_id)->toBe($this->igual->id)
        ->and($vigente->unidad_administrativa_id)->toBe($this->unidadB->id)
        ->and($vigente->partida_presupuestaria_id)->toBe($this->otraPartida->id);

    $this->estados->transicionar($traslado->fresh(), EstadoAccionPersonal::ANULADA, ['motivo_anulacion' => 'Error de puesto.']);

    $vigente = $servidor->fresh('contratoVigente')->contratoVigente;

    expect($vigente->puesto_id)->toBe($this->origen->id)
        ->and($vigente->unidad_administrativa_id)->toBe($this->unidadA->id)
        ->and($vigente->partida_presupuestaria_id)->toBe($this->partidaDeAntes->id);
});

test('no se anula si su puesto de origen ya no tiene plaza', function () {
    $servidor = ($this->servidor)();
    $traslado = ($this->registrar)(($this->trasladar)($servidor, $this->igual)->json('datos.id'));

    // Otro ocupa la única plaza que dejó.
    ($this->servidor)(TipoNombramiento::PERMANENTE, $this->origen);

    expect(fn () => $this->estados->transicionar($traslado->fresh(), EstadoAccionPersonal::ANULADA, [
        'motivo_anulacion' => 'Error de puesto.',
    ]))->toThrow(ReglaNegocioException::class, 'ya no tiene plaza libre');
});

test('al registrarse se vuelve a comprobar la remuneración', function () {
    $id = ($this->trasladar)(($this->servidor)(), $this->igual)->json('datos.id');

    // Entre el borrador y el registro, el puesto de destino cambió de escala.
    $this->igual->update(['grupo_ocupacional_id' => $this->mayor->grupo_ocupacional_id]);

    expect(fn () => ($this->registrar)($id))
        ->toThrow(ReglaNegocioException::class, 'igual remuneración');
});

// ── Jefaturas sin reemplazo [TH 19] ─────────────────────────────

test('la ausencia de una jefatura no se cubre con un reemplazo contratado', function () {
    $jefe = ($this->servidor)(TipoNombramiento::PERMANENTE, $this->jefatura);

    $comision = MovimientoPersonal::create([
        'servidor_id'         => $jefe->id,
        'tipo_movimiento'     => TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO->value,
        'subtipo_movimiento'  => SubtipoMovimientoPersonal::COMISION_CON_REMUNERACION->value,
        'estado'              => EstadoAccionPersonal::REGISTRADA,
        'codigo_registro'     => 'AP-2020-0500',
        'descripcion'         => 'Comisión del jefe',
        'institucion_destino' => 'Ministerio del Trabajo',
        'fecha_efectiva'      => '2026-01-01',
        'fecha_inicio'        => '2026-01-01',
        'fecha_fin'           => now()->addMonths(6)->toDateString(),
        'puesto_origen_id'    => $this->jefatura->id,
    ]);

    // El panel de ausencias la marca, para no ofrecerla como hueco a cubrir.
    $this->getJson('/api/v1/expediente/ausencias-temporales')
        ->assertOk()
        ->assertJsonPath('datos.0.id', $comision->id)
        ->assertJsonPath('datos.0.es_jefatura', true);

    $suplente = Servidor::create([
        'cedula' => '8199999999', 'nombre' => 'Suplente', 'apellido' => 'Jefatura',
        'regimen_laboral' => 'losep', 'fecha_ingreso_institucion' => $this->hoy,
    ]);

    expect(fn () => app(MovimientoPersonalService::class)->registrar($suplente->id, [
        'tipo_movimiento'             => TipoMovimientoPersonal::INGRESO->value,
        'tipo_nombramiento_propuesto' => TipoNombramiento::SERVICIOS_OCASIONALES->value,
        'puesto_destino_id'           => $this->jefatura->id,
        'unidad_destino_id'           => $this->unidadA->id,
        'requiere_dictamen_medico'    => false,
        'descripcion'                 => 'Reemplazo del jefe',
        'fecha_efectiva'              => $this->hoy,
        'cubre_movimiento_id'         => $comision->id,
    ]))->toThrow(ReglaNegocioException::class, 'subrogación o encargo');

    expect(fn () => app(ContratoServidorService::class)->crear($suplente->id, [
        'tipo_nombramiento'        => TipoNombramiento::SERVICIOS_OCASIONALES->value,
        'unidad_administrativa_id' => $this->unidadA->id,
        'puesto_id'                => $this->jefatura->id,
        'fecha_inicio'             => $this->hoy,
        'fecha_fin'                => now()->addMonths(3)->toDateString(),
        'cubre_movimiento_id'      => $comision->id,
        'estado'                   => 'vigente',
    ]))->toThrow(ReglaNegocioException::class, 'subrogación o encargo');
});

// ── Catálogo y documento ────────────────────────────────────────

test('el catálogo dice que el traslado pide el informe', function () {
    $clases = collect($this->getJson('/api/v1/expediente/acciones-personal/catalogo')->json('datos.clases'))->keyBy('codigo');

    // A los de la LOSEP; Servicios Profesionales no, hasta su bloque.
    expect($clases['traslado']['pide_informe_uath_para'])->toEqualCanonicalizing([
        'nombramiento_permanente', 'nombramiento_provisional', 'servicios_ocasionales', 'libre_nombramiento_remocion',
    ])->and($clases['ingreso']['pide_informe_uath_para'])->toBe([]);
});

test('el documento imprime el informe de la UATH', function () {
    $servidor = ($this->servidor)();
    $traslado = MovimientoPersonal::findOrFail(($this->trasladar)($servidor, $this->igual)->json('datos.id'));

    $firma = fn (RolFirmaAccionPersonal $rol) => ['rotulo' => $rol->rotuloDocumento(), 'nombre' => null, 'cargo' => null];

    $html = view('pdf.expediente.accion-personal', [
        'movimiento'         => $traslado,
        'servidor'           => $servidor,
        'firmaAutoridad'     => $firma(RolFirmaAccionPersonal::AUTORIDAD_NOMINADORA),
        'firmaTalentoHumano' => $firma(RolFirmaAccionPersonal::RESPONSABLE_TALENTO_HUMANO),
        'logo'               => public_path('images/logo-gadpe.png'),
    ])->render();

    expect($html)->toContain('Informe técnico de la UATH')
        ->and($html)->toContain('INF-UATH-2026-010');
});
