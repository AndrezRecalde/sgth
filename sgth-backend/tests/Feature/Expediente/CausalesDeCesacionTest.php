<?php

namespace Tests\Feature\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\EstadoSubrogacion;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoEventoVinculo;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Dispensario\FichaSaludOcupacional;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\EventoVinculo;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\Expediente\Subrogacion;
use App\Models\User;
use App\Services\Expediente\ContratoVencidoService;
use App\Services\Expediente\MovimientoPersonalStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| Fase 2.1 del diseño de Acciones de Personal: las causales de cesación de la
| tabla 4.3, dónde nace cada una, la protección de la ocasional embarazada o en
| lactancia, el borrador del ocasional vencido y lo que arrastra la salida.
*/
beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    permisosDeAccionesPersonal();

    $this->director = User::factory()->create();
    $this->director->assignRole('admin-uath');
    $this->actingAs($this->director, 'sanctum');

    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'UATH-CES', 'nombre' => 'Unidad de Talento Humano', 'nivel' => 1,
    ]);

    $this->puesto = Puesto::create([
        'codigo' => 'P-CES', 'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 10,
    ]);

    $this->estados = app(MovimientoPersonalStateService::class);

    $this->contador = 0;

    $this->servidorCon = function (TipoNombramiento $nombramiento, ?string $fin = null): Servidor {
        $this->contador++;

        $servidor = Servidor::create([
            'cedula'          => str_pad((string) (8500000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre'          => 'Servidora',
            'apellido'        => 'Cesada'.$this->contador,
            'regimen_laboral' => 'losep',
            'puesto_id'       => $this->puesto->id,
            'unidad_administrativa_id' => $this->unidad->id,
            'fecha_ingreso_institucion' => '2018-01-01',
        ]);

        ContratoServidor::create([
            'servidor_id'              => $servidor->id,
            'tipo_nombramiento'        => $nombramiento->value,
            'unidad_administrativa_id' => $this->unidad->id,
            'puesto_id'                => $this->puesto->id,
            'fecha_inicio'             => '2018-01-01',
            'fecha_fin'                => $fin ?? ($nombramiento === TipoNombramiento::SERVICIOS_OCASIONALES ? now()->addYear()->toDateString() : null),
            'estado'                   => 'vigente',
        ]);

        return $servidor->fresh('contratoVigente');
    };

    $this->protegida = fn (Servidor $s) => FichaSaludOcupacional::create([
        'servidor_id'      => $s->id,
        'evaluador_id'     => User::factory()->create()->id,
        'tipo_ficha'       => 'periodica',
        'fecha_evaluacion' => now()->subMonth()->toDateString(),
        'grupo_lactancia'  => true,
        'estado'           => true,
    ]);

    $this->cesar = fn (Servidor $s, string $causal) => $this->postJson(
        "/api/v1/expediente/servidores/{$s->id}/movimientos",
        [
            'clase'                    => 'cesacion',
            'causal'                   => $causal,
            'descripcion'              => 'Cesación de prueba',
            'fecha_efectiva'           => now()->toDateString(),
            'requiere_dictamen_medico' => false,
        ]
    );
});

// ── Las causales y a quién aplican ──────────────────────────────

test('cada causal nueva aplica a los nombramientos de la tabla 4.3', function () {
    $matriz = [
        'remocion'                    => ['nombramiento_provisional', 'libre_nombramiento_remocion'],
        'periodo_prueba_no_superado'  => ['nombramiento_provisional'],
        'fin_del_plazo'               => ['servicios_ocasionales'],
        'terminacion_unilateral'      => ['servicios_ocasionales'],
        'mutuo_acuerdo'               => ['servicios_ocasionales'],
        'evaluacion_insuficiente'     => ['servicios_ocasionales'],
        'retiro_voluntario'           => ['nombramiento_permanente'],
        'perdida_derechos_ciudadania' => ['nombramiento_permanente', 'nombramiento_provisional', 'servicios_ocasionales', 'libre_nombramiento_remocion'],
        'fallecimiento'               => ['nombramiento_permanente', 'nombramiento_provisional', 'servicios_ocasionales', 'libre_nombramiento_remocion'],
    ];

    foreach ($matriz as $causal => $esperados) {
        $obtenidos = array_map(
            fn (TipoNombramiento $n) => $n->value,
            SubtipoMovimientoPersonal::from($causal)->nombramientosElegibles(),
        );
        sort($obtenidos);
        sort($esperados);

        expect($obtenidos)->toBe($esperados, $causal)
            ->and(SubtipoMovimientoPersonal::from($causal)->cierraVinculo())->toBeTrue($causal)
            ->and(SubtipoMovimientoPersonal::from($causal)->baseLegal())->not->toBeNull($causal);
    }
});

test('una remoción se registra para un libre nombramiento, no para un permanente', function () {
    ($this->cesar)(($this->servidorCon)(TipoNombramiento::LIBRE_NOMBRAMIENTO), 'remocion')
        ->assertCreated()
        ->assertJsonPath('datos.causal', 'remocion')
        ->assertJsonPath('datos.causal_base_legal', 'LOSEP Art. 47 e; Reglamento Art. 105');

    ($this->cesar)(($this->servidorCon)(TipoNombramiento::PERMANENTE), 'remocion')
        ->assertStatus(422);
});

test('la destitución y el visto bueno no se registran desde el formulario', function () {
    $permanente = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    foreach (['destitucion', 'visto_bueno'] as $causal) {
        ($this->cesar)($permanente, $causal)
            ->assertStatus(422)
            ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'se registra desde Disciplinario'));
    }

    expect(MovimientoPersonal::where('servidor_id', $permanente->id)->exists())->toBeFalse();
});

test('el catálogo tampoco las ofrece', function () {
    $cesacion = collect($this->getJson('/api/v1/expediente/acciones-personal/catalogo')->json('datos.clases'))
        ->firstWhere('codigo', 'cesacion');

    expect(array_column($cesacion['causales'], 'codigo'))->not->toContain('destitucion')
        ->not->toContain('visto_bueno');
});

// ── La ocasional protegida ──────────────────────────────────────

test('a una ocasional en lactancia no se le termina el contrato unilateralmente', function () {
    $ocasional = ($this->servidorCon)(TipoNombramiento::SERVICIOS_OCASIONALES);
    ($this->protegida)($ocasional);

    ($this->cesar)($ocasional, 'terminacion_unilateral')
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'La terminación unilateral no procede'));
});

test('sin ese dato, la terminación unilateral sigue su curso', function () {
    ($this->cesar)(($this->servidorCon)(TipoNombramiento::SERVICIOS_OCASIONALES), 'terminacion_unilateral')
        ->assertCreated();
});

test('si el dato aparece después del borrador, no se deja registrar', function () {
    $ocasional = ($this->servidorCon)(TipoNombramiento::SERVICIOS_OCASIONALES);
    $borrador = MovimientoPersonal::find(($this->cesar)($ocasional, 'terminacion_unilateral')->json('datos.id'));

    ($this->protegida)($ocasional);

    $this->estados->transicionar($borrador, EstadoAccionPersonal::SUSCRITA);

    expect(fn () => $this->estados->transicionar($borrador->fresh(), EstadoAccionPersonal::REGISTRADA))
        ->toThrow(ReglaNegocioException::class, 'La terminación unilateral no procede');
});

test('el detalle avisa de la protección mientras la terminación se puede detener', function () {
    $ocasional = ($this->servidorCon)(TipoNombramiento::SERVICIOS_OCASIONALES);
    $id = ($this->cesar)($ocasional, 'fin_del_plazo')->json('datos.id');
    ($this->protegida)($ocasional);

    $this->getJson("/api/v1/expediente/movimientos/{$id}")
        ->assertOk()
        ->assertJsonPath('datos.aviso_proteccion', fn (?string $a) => str_contains((string) $a, 'la protege'));
});

// ── El ocasional vencido ────────────────────────────────────────

test('el ocasional cuyo plazo venció recibe su terminación en borrador', function () {
    $vencida = ($this->servidorCon)(TipoNombramiento::SERVICIOS_OCASIONALES, now()->subDays(3)->toDateString());

    $resultado = app(ContratoVencidoService::class)->generarCesacionesPendientes();

    $borrador = MovimientoPersonal::find($resultado['generadas'][0]['movimiento_id']);

    expect($resultado['generadas'])->toHaveCount(1)
        ->and($borrador->servidor_id)->toBe($vencida->id)
        ->and($borrador->estado)->toBe(EstadoAccionPersonal::BORRADOR)
        ->and($borrador->subtipo_movimiento)->toBe(SubtipoMovimientoPersonal::FIN_DEL_PLAZO)
        ->and($borrador->observacion)->toBeNull();

    // Y una sola vez.
    expect(app(ContratoVencidoService::class)->generarCesacionesPendientes()['generadas'])->toBe([]);
});

test('si la ocasional vencida está protegida, el borrador lo dice', function () {
    $vencida = ($this->servidorCon)(TipoNombramiento::SERVICIOS_OCASIONALES, now()->subDays(3)->toDateString());
    ($this->protegida)($vencida);

    $resultado = app(ContratoVencidoService::class)->generarCesacionesPendientes();

    expect(MovimientoPersonal::find($resultado['generadas'][0]['movimiento_id'])->observacion)
        ->toContain('la protege');
});

// ── Lo que arrastra la salida ───────────────────────────────────

/** Una cesación registrada que rige hoy: el efecto se aplica en el acto. */
function cesacionRegistrada(Servidor $servidor, MovimientoPersonalStateService $estados): MovimientoPersonal
{
    $cesacion = MovimientoPersonal::create([
        'servidor_id'        => $servidor->id,
        'tipo_movimiento'    => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::RENUNCIA->value,
        'estado'             => EstadoAccionPersonal::SUSCRITA,
        'descripcion'        => 'Renuncia',
        'fecha_efectiva'     => now()->toDateString(),
        'requiere_dictamen_medico' => false,
    ]);

    return $estados->transicionar($cesacion, EstadoAccionPersonal::REGISTRADA);
}

test('cuando cesa el subrogante o el titular, su subrogación activa termina', function () {
    $subrogante = ($this->servidorCon)(TipoNombramiento::PERMANENTE);
    $titular = ($this->servidorCon)(TipoNombramiento::PERMANENTE);
    $otroTitular = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    $subroga = fn (Servidor $quien, Servidor $aQuien) => Subrogacion::create([
        'tipo'                     => 'subrogacion',
        'servidor_subrogante_id'   => $quien->id,
        'servidor_subrogado_id'    => $aQuien->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_subrogado_id'      => $this->puesto->id,
        'fecha_inicio'             => now()->subMonth()->toDateString(),
        'fecha_fin'                => now()->addMonth()->toDateString(),
        'motivo'                   => 'vacaciones',
        'estado'                   => EstadoSubrogacion::ACTIVA,
        'registrado_por'           => $this->director->id,
    ]);

    $comoSubrogante = $subroga($subrogante, $otroTitular);
    $comoTitular = ($subroga)(($this->servidorCon)(TipoNombramiento::PERMANENTE), $titular);

    cesacionRegistrada($subrogante, $this->estados);
    cesacionRegistrada($titular, $this->estados);

    expect($comoSubrogante->fresh()->estado)->toBe(EstadoSubrogacion::FINALIZADA)
        ->and($comoTitular->fresh()->estado)->toBe(EstadoSubrogacion::FINALIZADA)
        ->and(EventoVinculo::where('subrogacion_id', $comoTitular->id)->value('descripcion'))
        ->toContain('por la cesación del titular');
});

test('la subrogación que aún no empezaba se cancela, y su acción en borrador se anula', function () {
    $subrogante = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    $accion = MovimientoPersonal::create([
        'servidor_id'     => $subrogante->id,
        'tipo_movimiento' => TipoMovimientoPersonal::SUBROGACION->value,
        'estado'          => EstadoAccionPersonal::BORRADOR,
        'descripcion'     => 'Subrogación futura',
        'fecha_efectiva'  => now()->addMonth()->toDateString(),
    ]);

    $pendiente = Subrogacion::create([
        'tipo'                     => 'subrogacion',
        'servidor_subrogante_id'   => $subrogante->id,
        'servidor_subrogado_id'    => ($this->servidorCon)(TipoNombramiento::PERMANENTE)->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_subrogado_id'      => $this->puesto->id,
        'fecha_inicio'             => now()->addMonth()->toDateString(),
        'fecha_fin'                => now()->addMonths(2)->toDateString(),
        'motivo'                   => 'vacaciones',
        'estado'                   => EstadoSubrogacion::PENDIENTE,
        'movimiento_personal_id'   => $accion->id,
        'registrado_por'           => $this->director->id,
    ]);

    cesacionRegistrada($subrogante, $this->estados);

    expect($pendiente->fresh()->estado)->toBe(EstadoSubrogacion::CANCELADA)
        ->and($accion->fresh()->estado)->toBe(EstadoAccionPersonal::ANULADA);
});

test('el reemplazo de un titular que cesa queda anotado y en la lista del comando', function () {
    $titular = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    $ausencia = MovimientoPersonal::create([
        'servidor_id'        => $titular->id,
        'tipo_movimiento'    => TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::COMISION_SIN_REMUNERACION->value,
        'estado'             => EstadoAccionPersonal::REGISTRADA,
        'codigo_registro'    => 'AP-2026-0960',
        'efecto_aplicado_en' => now(),
        'descripcion'        => 'Comisión en el Ministerio',
        'fecha_efectiva'     => now()->subMonth()->toDateString(),
        'fecha_inicio'       => now()->subMonth()->toDateString(),
        'fecha_fin'          => now()->addMonths(6)->toDateString(),
    ]);

    $reemplazo = Servidor::create([
        'cedula' => '8599999999', 'nombre' => 'Reemplazo', 'apellido' => 'Temporal', 'regimen_laboral' => 'losep',
    ]);
    $contrato = ContratoServidor::create([
        'servidor_id'              => $reemplazo->id,
        'tipo_nombramiento'        => 'servicios_ocasionales',
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id'                => $this->puesto->id,
        'fecha_inicio'             => now()->subMonth()->toDateString(),
        'fecha_fin'                => now()->addMonths(6)->toDateString(),
        'estado'                   => 'vigente',
        'cubre_movimiento_id'      => $ausencia->id,
    ]);

    cesacionRegistrada($titular, $this->estados);

    $novedad = EventoVinculo::where('contrato_servidor_id', $contrato->id)->sole();

    expect($novedad->tipo)->toBe(TipoEventoVinculo::TITULAR_CESADO)
        ->and($novedad->servidor_id)->toBe($reemplazo->id)
        ->and($contrato->fresh()->estado->value)->toBe('vigente');

    $this->artisan('sgth:acciones:aplicar-vigentes')
        ->expectsOutputToContain('1 reemplazo(s) cubren a alguien que ya cesó')
        ->assertSuccessful();
});
