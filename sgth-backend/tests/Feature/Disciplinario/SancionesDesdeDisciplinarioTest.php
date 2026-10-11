<?php

use App\Enums\EstadoAccionPersonal;
use App\Enums\EstadoSumario;
use App\Enums\RolFirmaAccionPersonal;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Models\Disciplinario\SancionDisciplinaria;
use App\Models\Disciplinario\Sumario;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Expediente\MovimientoPersonalStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
 * Fase 2.5 del diseño de Acciones de Personal (4.2 y 9.6; TH N13): la sanción
 * solo nace en Disciplinario, con su causal; la amonestación escrita lleva
 * acción; y anular la acción deja la sanción sin efecto.
 */

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    permisosDeAccionesPersonal();
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin-uath');
    $this->actingAs($this->admin, 'sanctum');

    $this->estados = app(MovimientoPersonalStateService::class);

    $unidad = UnidadAdministrativa::create(['codigo' => 'UATH-SD', 'nombre' => 'Talento Humano SD', 'nivel' => 1]);
    $puesto = Puesto::create(['codigo' => 'P-SD', 'unidad_administrativa_id' => $unidad->id, 'plazas' => 10]);

    $this->contador = 0;
    $this->servidorCon = function (TipoNombramiento $nombramiento) use ($unidad, $puesto): Servidor {
        $this->contador++;
        $servidor = Servidor::create([
            'cedula'                    => str_pad((string) (6300000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre'                    => 'Servidor',
            'apellido'                  => 'Sancionado'.$this->contador,
            'regimen_laboral'           => $nombramiento === TipoNombramiento::CODIGO_TRABAJO ? 'codigo_trabajo' : 'losep',
            'puesto_id'                 => $puesto->id,
            'unidad_administrativa_id'  => $unidad->id,
            'fecha_ingreso_institucion' => '2018-01-01',
        ]);
        ContratoServidor::create([
            'servidor_id'              => $servidor->id,
            'tipo_nombramiento'        => $nombramiento->value,
            'unidad_administrativa_id' => $unidad->id,
            'puesto_id'                => $puesto->id,
            'fecha_inicio'             => '2018-01-01',
            'remuneracion'             => 1212,
            'estado'                   => 'vigente',
        ]);

        return $servidor;
    };

    $this->sumario = fn (Servidor $servidor) => Sumario::create([
        'servidor_id'    => $servidor->id,
        'motivo'         => 'Sumario de prueba',
        'estado'         => EstadoSumario::CON_INFORME,
        'fecha_apertura' => '2026-09-01',
        'notificado_sn'  => true,
        'fecha_informe'  => '2026-09-20',
    ]);

    $this->resolver = fn (Sumario $sumario, array $sancion) => $this->postJson(
        "/api/v1/disciplinario/sumarios/{$sumario->id}/resolver",
        $sancion + ['fecha_efectiva' => '2026-10-06']
    );

    $this->accionDe = fn (Servidor $s) => MovimientoPersonal::where('servidor_id', $s->id)->sole();

    $this->anular = fn (MovimientoPersonal $m, string $motivo = 'La resolución se dictó con un error.') => $this->estados
        ->transicionar($m->fresh(), EstadoAccionPersonal::ANULADA, ['motivo_anulacion' => $motivo]);
});

// ── Solo desde Disciplinario ────────────────────────────────────

test('la sanción ya no se crea desde el formulario', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    $this->postJson("/api/v1/expediente/servidores/{$servidor->id}/movimientos", [
        'clase'          => 'sancion',
        'causal'         => 'sancion_pecuniaria',
        'descripcion'    => 'Multa sin sumario',
        'fecha_efectiva' => '2026-10-06',
    ])->assertStatus(422);

    $sancion = collect($this->getJson('/api/v1/expediente/acciones-personal/catalogo')->json('datos.clases'))
        ->firstWhere('codigo', 'sancion');

    expect($sancion['se_crea_desde_formulario'])->toBeFalse();
});

// ── Con sus datos ───────────────────────────────────────────────

test('la multa lleva su causal y su base legal', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE);
    ($this->resolver)(($this->sumario)($servidor), ['tipo_falta' => 'leve', 'tipo_sancion' => 'multa', 'porcentaje_multa' => 5])
        ->assertOk()
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'quedó en borrador'));

    $accion = ($this->accionDe)($servidor);

    $this->getJson("/api/v1/expediente/movimientos/{$accion->id}")
        ->assertOk()
        ->assertJsonPath('datos.clase', 'sancion')
        ->assertJsonPath('datos.causal', 'sancion_pecuniaria')
        ->assertJsonPath('datos.causal_etiqueta', 'Sanción pecuniaria administrativa')
        ->assertJsonPath('datos.causal_base_legal', 'LOSEP Art. 43 c');
});

test('la suspensión lleva su período en la acción', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE);
    ($this->resolver)(($this->sumario)($servidor), ['tipo_falta' => 'grave', 'tipo_sancion' => 'suspension', 'dias_suspension' => 5])
        ->assertOk();

    $accion = ($this->accionDe)($servidor);

    expect($accion->subtipo_movimiento)->toBe(SubtipoMovimientoPersonal::SUSPENSION_SIN_REMUNERACION)
        ->and($accion->fecha_inicio->toDateString())->toBe('2026-10-06')
        ->and($accion->fecha_fin->toDateString())->toBe('2026-10-10');
});

test('la amonestación escrita lleva acción; la verbal no [TH N13]', function () {
    $escrita = ($this->servidorCon)(TipoNombramiento::PERMANENTE);
    ($this->resolver)(($this->sumario)($escrita), ['tipo_falta' => 'leve', 'tipo_sancion' => 'amonestacion_escrita'])
        ->assertOk();

    $accion = ($this->accionDe)($escrita);

    expect($accion->tipo_movimiento)->toBe(TipoMovimientoPersonal::REGIMEN_DISCIPLINARIO)
        ->and($accion->subtipo_movimiento)->toBe(SubtipoMovimientoPersonal::AMONESTACION_ESCRITA)
        ->and($accion->estado)->toBe(EstadoAccionPersonal::BORRADOR)
        ->and($accion->descripcion)->toContain('amonestación escrita por falta leve')
        ->and($accion->descripcion)->toContain('Arts. 42 literal a) y 43');

    $verbal = ($this->servidorCon)(TipoNombramiento::PERMANENTE);
    ($this->resolver)(($this->sumario)($verbal), ['tipo_falta' => 'leve', 'tipo_sancion' => 'amonestacion_verbal'])
        ->assertOk();

    expect(MovimientoPersonal::where('servidor_id', $verbal->id)->exists())->toBeFalse();
});

test('al obrero su amonestación escrita le llega sin acción, hasta su bloque', function () {
    $obrero = ($this->servidorCon)(TipoNombramiento::CODIGO_TRABAJO);
    ($this->resolver)(($this->sumario)($obrero), ['tipo_falta' => 'leve', 'tipo_sancion' => 'amonestacion_escrita'])
        ->assertOk();

    expect(MovimientoPersonal::where('servidor_id', $obrero->id)->exists())->toBeFalse()
        ->and(SancionDisciplinaria::count())->toBe(1);
});

test('la de antes, sin causal, se enseña como no indicada', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    $antigua = MovimientoPersonal::create([
        'servidor_id'        => $servidor->id,
        'tipo_movimiento'    => TipoMovimientoPersonal::REGIMEN_DISCIPLINARIO->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::SANCION_DISCIPLINARIA->value,
        'estado'             => EstadoAccionPersonal::REGISTRADA,
        'codigo_registro'    => 'AP-2025-0001',
        'descripcion'        => 'Sanción registrada a mano',
        'fecha_efectiva'     => '2025-05-01',
    ]);

    $this->getJson("/api/v1/expediente/movimientos/{$antigua->id}")
        ->assertOk()
        ->assertJsonPath('datos.clase', 'sancion')
        ->assertJsonPath('datos.causal', null)
        ->assertJsonPath('datos.causal_etiqueta', 'No indicada (histórico)');
});

test('el documento de la amonestación dice su causal', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE);
    ($this->resolver)(($this->sumario)($servidor), ['tipo_falta' => 'leve', 'tipo_sancion' => 'amonestacion_escrita']);
    $accion = ($this->accionDe)($servidor);

    $firma = fn (RolFirmaAccionPersonal $rol) => ['rotulo' => $rol->rotuloDocumento(), 'nombre' => null, 'cargo' => null];

    $html = view('pdf.expediente.accion-personal', [
        'movimiento'         => $accion,
        'servidor'           => $accion->servidor,
        'firmaAutoridad'     => $firma(RolFirmaAccionPersonal::AUTORIDAD_NOMINADORA),
        'firmaTalentoHumano' => $firma(RolFirmaAccionPersonal::RESPONSABLE_TALENTO_HUMANO),
        'logo'               => public_path('images/logo-gadpe.png'),
    ])->render();

    expect($html)->toContain('Sanción Disciplinaria')
        ->and($html)->toContain('Amonestación escrita')
        ->and($html)->toContain('Situación propuesta');
});

// ── Anular ↔ sanción ────────────────────────────────────────────

test('anular la acción deja la sanción sin efecto y el sumario vuelve a resolverse', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE);
    $sumario = ($this->sumario)($servidor);
    ($this->resolver)($sumario, ['tipo_falta' => 'leve', 'tipo_sancion' => 'multa', 'porcentaje_multa' => 5])->assertOk();

    ($this->anular)(($this->accionDe)($servidor), 'El porcentaje era otro.');

    $anterior = SancionDisciplinaria::where('sumario_id', $sumario->id)->sole();

    expect($anterior->estaSinEfecto())->toBeTrue()
        ->and($anterior->motivo_anulacion)->toContain('El porcentaje era otro.')
        ->and($anterior->anulada_por)->toBe($this->admin->id)
        ->and($sumario->fresh()->estado)->toBe(EstadoSumario::CON_INFORME)
        ->and($sumario->fresh()->fecha_resolucion)->toBeNull()
        ->and($sumario->fresh()->sancion)->toBeNull();

    // Se dicta la correcta, con su propia acción.
    ($this->resolver)($sumario, ['tipo_falta' => 'leve', 'tipo_sancion' => 'multa', 'porcentaje_multa' => 3])
        ->assertOk()
        ->assertJsonPath('datos.sancion.porcentaje_multa', '3.00')
        ->assertJsonPath('datos.sanciones_sin_efecto.0.id', $anterior->id);

    expect(SancionDisciplinaria::where('sumario_id', $sumario->id)->count())->toBe(2)
        ->and(MovimientoPersonal::where('servidor_id', $servidor->id)->where('estado', '!=', 'anulada')->count())->toBe(1);
});

test('el listado de sumarios enseña la sanción sin efecto', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE);
    $sumario = ($this->sumario)($servidor);
    ($this->resolver)($sumario, ['tipo_falta' => 'leve', 'tipo_sancion' => 'amonestacion_escrita']);
    ($this->anular)(($this->accionDe)($servidor));

    $this->getJson('/api/v1/disciplinario/sumarios')
        ->assertOk()
        ->assertJsonPath('datos.data.0.sancion', null)
        ->assertJsonPath('datos.data.0.sanciones_sin_efecto.0.tipo_sancion', 'amonestacion_escrita')
        ->assertJsonPath('datos.data.0.sanciones_sin_efecto.0.movimiento_personal.estado', 'anulada');
});

test('en un sumario apelado la sanción queda sin efecto y el sumario no se mueve', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE);
    $sumario = ($this->sumario)($servidor);
    ($this->resolver)($sumario, ['tipo_falta' => 'grave', 'tipo_sancion' => 'suspension', 'dias_suspension' => 5]);
    $sumario->fresh()->update(['estado' => EstadoSumario::APELADO]);

    ($this->anular)(($this->accionDe)($servidor), 'La apelación revocó la sanción.');

    expect($sumario->fresh()->estado)->toBe(EstadoSumario::APELADO)
        ->and(SancionDisciplinaria::where('sumario_id', $sumario->id)->sole()->estaSinEfecto())->toBeTrue();
});

test('anular la cesación de una destitución también la deja sin efecto', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE);
    $sumario = ($this->sumario)($servidor);
    ($this->resolver)($sumario, ['tipo_falta' => 'grave', 'tipo_sancion' => 'destitucion'])->assertOk();

    $cesacion = ($this->accionDe)($servidor);
    expect($cesacion->subtipo_movimiento)->toBe(SubtipoMovimientoPersonal::DESTITUCION);

    ($this->anular)($cesacion);

    expect(SancionDisciplinaria::where('sumario_id', $sumario->id)->sole()->estaSinEfecto())->toBeTrue()
        ->and($sumario->fresh()->estado)->toBe(EstadoSumario::CON_INFORME);
});

test('anular otra acción no toca ninguna sanción', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE);
    $sumario = ($this->sumario)($servidor);
    ($this->resolver)($sumario, ['tipo_falta' => 'leve', 'tipo_sancion' => 'multa', 'porcentaje_multa' => 5]);

    $renuncia = MovimientoPersonal::create([
        'servidor_id'        => $servidor->id,
        'tipo_movimiento'    => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::RENUNCIA->value,
        'estado'             => EstadoAccionPersonal::BORRADOR,
        'descripcion'        => 'Renuncia',
        'fecha_efectiva'     => '2026-11-01',
    ]);

    ($this->anular)($renuncia);

    expect($sumario->fresh()->estado)->toBe(EstadoSumario::RESUELTO)
        ->and($sumario->fresh()->sancion)->not->toBeNull();
});
