<?php

use App\Enums\EstadoSumario;
use App\Enums\InstrumentoActo;
use App\Enums\ProcedimientoDisciplinario;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Models\Disciplinario\FaltaReglamentoInterno;
use App\Models\Disciplinario\SancionDisciplinaria;
use App\Models\Disciplinario\Sumario;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Disciplinario\DisciplinarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
 * Fase 4.4 del diseño de Acciones de Personal (9.15): al obrero se le sanciona
 * con su reglamento interno, no con el sumario de la LOSEP. El Código del
 * Trabajo prohíbe imponer lo que el reglamento interno no prevé (Art. 44 a), y
 * su multa nunca pasa del 10 %.
 */
beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    permisosDeAccionesPersonal();
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin-uath');
    $this->actingAs($this->admin, 'sanctum');

    $unidad = UnidadAdministrativa::create(['codigo' => 'OOPP-RI', 'nombre' => 'Obras Públicas RI', 'nivel' => 1]);
    $puesto = Puesto::create(['codigo' => 'P-RI', 'unidad_administrativa_id' => $unidad->id, 'plazas' => 10]);

    $this->contador = 0;
    $this->servidorCon = function (TipoNombramiento $nombramiento) use ($unidad, $puesto): Servidor {
        $this->contador++;
        $servidor = Servidor::create([
            'cedula'                   => str_pad((string) (6300000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre'                   => 'Trabajador',
            'apellido'                 => 'Reglamento'.$this->contador,
            'regimen_laboral'          => $nombramiento === TipoNombramiento::CODIGO_TRABAJO ? 'codigo_trabajo' : 'losep',
            'puesto_id'                => $puesto->id,
            'unidad_administrativa_id' => $unidad->id,
        ]);
        ContratoServidor::create([
            'servidor_id'              => $servidor->id,
            'tipo_nombramiento'        => $nombramiento->value,
            'unidad_administrativa_id' => $unidad->id,
            'puesto_id'                => $puesto->id,
            'fecha_inicio'             => '2019-01-01',
            'remuneracion'             => 600,
            'estado'                   => 'vigente',
        ]);

        return $servidor;
    };

    $this->falta = fn (array $extra = []) => FaltaReglamentoInterno::create([
        'articulo'                    => 'Art. 45, núm. 3',
        'descripcion'                 => 'Atrasos injustificados por más de tres veces en un mes.',
        'gravedad'                    => 'Leve',
        'admite_amonestacion_escrita' => true,
        'admite_multa'                => true,
        'porcentaje_maximo_multa'     => 5,
        ...$extra,
    ]);

    $this->abrir = fn (Servidor $s, array $extra = []) => $this->postJson('/api/v1/disciplinario/sumarios', [
        'servidor_id' => $s->id,
        'motivo'      => 'Atrasos reiterados en septiembre',
        ...$extra,
    ]);

    $this->resolver = fn (int $id, array $sancion) => $this->postJson(
        "/api/v1/disciplinario/sumarios/{$id}/resolver",
        $sancion + ['fecha_efectiva' => now()->toDateString()],
    );
});

// ── El catálogo ─────────────────────────────────────────────────

test('Talento Humano carga las faltas del reglamento interno, con sus sanciones y el tope de la multa', function () {
    $this->postJson('/api/v1/disciplinario/reglamento-interno/faltas', [
        'articulo'                   => 'Art. 46, núm. 1',
        'descripcion'                => 'Abandonar el puesto sin autorización.',
        'admite_amonestacion_verbal' => true,
        'admite_multa'               => true,
        'porcentaje_maximo_multa'    => 8,
    ])->assertCreated()->assertJsonPath('datos.porcentaje_maximo_multa', '8.00');

    $this->postJson('/api/v1/disciplinario/reglamento-interno/faltas', [
        'articulo' => 'Art. 46, núm. 2', 'descripcion' => 'Multa sobre el tope.', 'admite_multa' => true, 'porcentaje_maximo_multa' => 12,
    ])->assertUnprocessable()->assertJsonValidationErrors(['porcentaje_maximo_multa'], 'errores');

    $this->postJson('/api/v1/disciplinario/reglamento-interno/faltas', [
        'articulo' => 'Art. 46, núm. 3', 'descripcion' => 'Sin sanción.',
    ])->assertUnprocessable()->assertJsonValidationErrors(['sanciones'], 'errores');

    $this->getJson('/api/v1/disciplinario/reglamento-interno/faltas')->assertOk()->assertJsonCount(1, 'datos');
});

test('la falta que ya se imputó no se reescribe, pero se puede desactivar', function () {
    $falta = ($this->falta)();
    ($this->abrir)(($this->servidorCon)(TipoNombramiento::CODIGO_TRABAJO), ['falta_reglamento_id' => $falta->id])->assertCreated();

    $ruta = "/api/v1/disciplinario/reglamento-interno/faltas/{$falta->id}";
    $datos = [
        'articulo' => $falta->articulo, 'descripcion' => $falta->descripcion, 'gravedad' => 'Leve',
        'admite_amonestacion_escrita' => true, 'admite_multa' => true, 'porcentaje_maximo_multa' => 5,
    ];

    $this->putJson($ruta, [...$datos, 'porcentaje_maximo_multa' => 10])
        ->assertUnprocessable()
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'no se reescribe'));

    $this->putJson($ruta, [...$datos, 'activo' => false])->assertOk()->assertJsonPath('datos.activo', false);
});

// ── El procedimiento ────────────────────────────────────────────

test('al obrero se le abre el procedimiento del reglamento interno, con la falta que se le imputa', function () {
    $falta = ($this->falta)();
    $obrero = ($this->servidorCon)(TipoNombramiento::CODIGO_TRABAJO);

    ($this->abrir)($obrero)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['falta_reglamento_id'], 'errores');

    ($this->abrir)($obrero, ['falta_reglamento_id' => $falta->id])
        ->assertCreated()
        ->assertJsonPath('datos.procedimiento', 'reglamento_interno')
        ->assertJsonPath('datos.falta_reglamento.articulo', 'Art. 45, núm. 3');
});

test('sin el reglamento interno cargado al obrero no se le sanciona', function () {
    ($this->abrir)(($this->servidorCon)(TipoNombramiento::CODIGO_TRABAJO))
        ->assertUnprocessable()
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'Art. 44 a'));
});

test('al servidor de la LOSEP se le sigue abriendo el sumario administrativo', function () {
    ($this->falta)();

    ($this->abrir)(($this->servidorCon)(TipoNombramiento::PERMANENTE), ['falta_reglamento_id' => null])
        ->assertCreated()
        ->assertJsonPath('datos.procedimiento', 'sumario_administrativo')
        ->assertJsonPath('datos.falta_reglamento_id', null);
});

// ── La resolución ───────────────────────────────────────────────

test('la sanción es una de las que el reglamento da a la falta, y la multa no pasa de su tope', function () {
    $falta = ($this->falta)();
    $id = ($this->abrir)(($this->servidorCon)(TipoNombramiento::CODIGO_TRABAJO), ['falta_reglamento_id' => $falta->id])->json('datos.id');

    ($this->resolver)($id, ['tipo_sancion' => 'amonestacion_verbal'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tipo_sancion'], 'errores');

    ($this->resolver)($id, ['tipo_sancion' => 'multa', 'porcentaje_multa' => 7])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['porcentaje_multa'], 'errores');

    ($this->resolver)($id, ['tipo_sancion' => 'suspension', 'dias_suspension' => 3])
        ->assertUnprocessable();

    expect(SancionDisciplinaria::count())->toBe(0);
});

test('la multa del obrero cita su reglamento y va en su hoja de registro interno', function () {
    $falta = ($this->falta)();
    $obrero = ($this->servidorCon)(TipoNombramiento::CODIGO_TRABAJO);
    $id = ($this->abrir)($obrero, ['falta_reglamento_id' => $falta->id])->json('datos.id');

    ($this->resolver)($id, ['tipo_sancion' => 'multa', 'porcentaje_multa' => 4])
        ->assertOk()
        ->assertJsonPath('datos.sancion.falta_reglamento.articulo', 'Art. 45, núm. 3')
        ->assertJsonPath('datos.sancion.tipo_falta', null)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'hoja de registro interno'));

    $accion = MovimientoPersonal::where('servidor_id', $obrero->id)->sole();

    expect($accion->subtipo_movimiento)->toBe(SubtipoMovimientoPersonal::SANCION_PECUNIARIA)
        ->and($accion->instrumento)->toBe(InstrumentoActo::DOCUMENTO_CT)
        ->and($accion->descripcion)->toContain('Art. 45, núm. 3 del Reglamento Interno de Trabajo')
        ->and($accion->descripcion)->toContain('Arts. 44 y 64 del Código del Trabajo')
        ->and($accion->descripcion)->not->toContain('Servicio Público');
});

test('la amonestación escrita del obrero también deja su hoja; la verbal, nada', function () {
    $falta = ($this->falta)(['admite_amonestacion_verbal' => true]);

    $escrita = ($this->servidorCon)(TipoNombramiento::CODIGO_TRABAJO);
    ($this->resolver)(($this->abrir)($escrita, ['falta_reglamento_id' => $falta->id])->json('datos.id'), [
        'tipo_sancion' => 'amonestacion_escrita',
    ])->assertOk();

    expect(MovimientoPersonal::where('servidor_id', $escrita->id)->sole()->subtipo_movimiento)
        ->toBe(SubtipoMovimientoPersonal::AMONESTACION_ESCRITA);

    $verbal = ($this->servidorCon)(TipoNombramiento::CODIGO_TRABAJO);
    ($this->resolver)(($this->abrir)($verbal, ['falta_reglamento_id' => $falta->id])->json('datos.id'), [
        'tipo_sancion' => 'amonestacion_verbal',
    ])->assertOk();

    expect(MovimientoPersonal::where('servidor_id', $verbal->id)->count())->toBe(0);
});

test('la resolución puede cambiar la falta por otra del catálogo vigente', function () {
    $imputada = ($this->falta)();
    $otra = ($this->falta)(['articulo' => 'Art. 47', 'admite_multa' => false, 'porcentaje_maximo_multa' => null, 'admite_amonestacion_verbal' => true]);
    $id = ($this->abrir)(($this->servidorCon)(TipoNombramiento::CODIGO_TRABAJO), ['falta_reglamento_id' => $imputada->id])->json('datos.id');

    // La nueva no admite multa.
    ($this->resolver)($id, ['falta_reglamento_id' => $otra->id, 'tipo_sancion' => 'multa', 'porcentaje_multa' => 2])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tipo_sancion'], 'errores');

    ($this->resolver)($id, ['falta_reglamento_id' => $otra->id, 'tipo_sancion' => 'amonestacion_verbal'])
        ->assertOk()
        ->assertJsonPath('datos.sancion.falta_reglamento.articulo', 'Art. 47');
});

test('el sumario de la LOSEP sigue pidiendo la gravedad', function () {
    $id = ($this->abrir)(($this->servidorCon)(TipoNombramiento::PERMANENTE))->json('datos.id');

    ($this->resolver)($id, ['tipo_sancion' => 'amonestacion_escrita'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tipo_falta'], 'errores');
});

// ── Los plazos ──────────────────────────────────────────────────

test('los plazos de la LOSEP no se le vigilan al procedimiento del reglamento interno', function () {
    $falta = ($this->falta)();
    $obrero = ($this->servidorCon)(TipoNombramiento::CODIGO_TRABAJO);
    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    foreach ([[$obrero, ProcedimientoDisciplinario::REGLAMENTO_INTERNO, $falta->id], [$servidor, ProcedimientoDisciplinario::SUMARIO_ADMINISTRATIVO, null]] as [$s, $procedimiento, $faltaId]) {
        Sumario::create([
            'servidor_id'         => $s->id,
            'procedimiento'       => $procedimiento,
            'falta_reglamento_id' => $faltaId,
            'motivo'              => 'Abierto hace un mes y sin notificar',
            'estado'              => EstadoSumario::ABIERTO,
            'fecha_apertura'      => now()->subMonth()->toDateString(),
            'notificado_sn'       => false,
        ]);
    }

    $alertas = app(DisciplinarioService::class)->controlarPlazosLegales();

    expect(collect($alertas)->pluck('servidor_id')->all())->toBe([$servidor->id]);
});
