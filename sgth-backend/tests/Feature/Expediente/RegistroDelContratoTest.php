<?php

namespace Tests\Feature\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\InstrumentoActo;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estructura\PartidaPresupuestaria;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Expediente\AccionPersonalPdfService;
use App\Services\Expediente\MovimientoPersonalService;
use App\Services\Expediente\MovimientoPersonalStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| Fase 2.7 del diseño de Acciones de Personal (8.1 y 9.11; TH N7): el ingreso
| de un ocasional es el registro de su contrato, sin acción de personal. La ley
| no la exige (LOSEP 18, último inciso; Reglamento 19).
*/
beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    permisosDeAccionesPersonal();

    $this->director = User::factory()->create();
    $this->director->assignRole('admin-uath');
    $this->actingAs($this->director, 'sanctum');

    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'UATH-RC', 'nombre' => 'Unidad de Talento Humano', 'nivel' => 1,
    ]);

    $this->partida = PartidaPresupuestaria::create([
        'codigo' => '510510', 'descripcion' => 'Servicios Personales por Contrato',
        'grupo_gasto' => 'Gastos en Personal', 'activo' => true, 'disponible' => true,
    ]);

    $this->puesto = Puesto::create([
        'codigo' => 'P-RC', 'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 5,
        'partida_presupuestaria_id' => $this->partida->id,
    ]);

    $this->estados = app(MovimientoPersonalStateService::class);
    $this->hoy = now()->toDateString();
    $this->contador = 0;

    $this->persona = function (): Servidor {
        $this->contador++;

        return Servidor::create([
            'cedula'          => str_pad((string) (8600000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre'          => 'Servidor',
            'apellido'        => 'Ocasional'.$this->contador,
            'regimen_laboral' => 'losep',
            'fecha_ingreso_institucion' => $this->hoy,
        ]);
    };

    $this->ingreso = fn (Servidor $s, string $nombramiento, array $extra = []) => $this->postJson(
        "/api/v1/expediente/servidores/{$s->id}/movimientos",
        [
            'clase'                       => 'ingreso',
            'descripcion'                 => 'Ingreso de prueba',
            'fecha_efectiva'              => $this->hoy,
            'tipo_nombramiento_propuesto' => $nombramiento,
            'unidad_destino_id'           => $this->unidad->id,
            'puesto_destino_id'           => $this->puesto->id,
            'numero_contrato'             => 'CSO-2026-0'.$this->contador,
            'remuneracion_propuesta'      => 1200,
            'fecha_fin_propuesta'         => now()->addMonths(11)->toDateString(),
            'requiere_dictamen_medico'    => false,
            ...$extra,
        ]
    );

    $this->suscribir = fn (int $id) => $this->estados->transicionar(MovimientoPersonal::findOrFail($id), EstadoAccionPersonal::SUSCRITA);
    $this->registrar = function (int $id): MovimientoPersonal {
        ($this->suscribir)($id);

        return $this->estados->transicionar(MovimientoPersonal::findOrFail($id), EstadoAccionPersonal::REGISTRADA);
    };
});

// ── Cuándo es registro del contrato ─────────────────────────────

test('el ingreso de un ocasional es el registro de su contrato', function () {
    ($this->ingreso)(($this->persona)(), 'servicios_ocasionales')
        ->assertCreated()
        ->assertJsonPath('datos.clase', 'ingreso')
        ->assertJsonPath('datos.instrumento', 'registro_contrato')
        ->assertJsonPath('datos.instrumento_etiqueta', 'Registro del contrato')
        ->assertJsonPath('datos.tiene_documento_imprimible', false);
});

test('el de un permanente sigue siendo acción de personal, y el de un profesional es del contrato civil', function () {
    ($this->ingreso)(($this->persona)(), 'nombramiento_permanente', ['fecha_fin_propuesta' => null])
        ->assertCreated()
        ->assertJsonPath('datos.instrumento', 'accion_personal')
        ->assertJsonPath('datos.tiene_documento_imprimible', true);

    // Desde la fase 4.2 (ContratoCivilTest).
    ($this->ingreso)(($this->persona)(), 'servicios_profesionales')
        ->assertCreated()
        ->assertJsonPath('datos.instrumento', 'contrato_civil');
});

test('cambiar el nombramiento del borrador cambia el instrumento', function () {
    $id = ($this->ingreso)(($this->persona)(), 'servicios_ocasionales')->json('datos.id');

    $this->putJson("/api/v1/expediente/movimientos/{$id}", [
        'tipo_nombramiento_propuesto' => 'nombramiento_provisional',
        'fecha_fin_propuesta'         => null,
    ])->assertOk()->assertJsonPath('datos.instrumento', 'accion_personal');

    $this->putJson("/api/v1/expediente/movimientos/{$id}", [
        'tipo_nombramiento_propuesto' => 'servicios_ocasionales',
        'fecha_fin_propuesta'         => now()->addMonths(6)->toDateString(),
    ])->assertOk()->assertJsonPath('datos.instrumento', 'registro_contrato');
});

test('los demás actos de un ocasional siguen siendo acciones de personal', function () {
    $servidor = ($this->persona)();
    ContratoServidor::create([
        'servidor_id' => $servidor->id, 'tipo_nombramiento' => 'servicios_ocasionales',
        'unidad_administrativa_id' => $this->unidad->id, 'puesto_id' => $this->puesto->id,
        'fecha_inicio' => '2026-01-01', 'fecha_fin' => now()->addMonths(3)->toDateString(), 'estado' => 'vigente',
    ]);

    $cesacion = app(MovimientoPersonalService::class)->registrar($servidor->id, [
        'tipo_movimiento'    => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::MUTUO_ACUERDO->value,
        'descripcion'        => 'Terminación por mutuo acuerdo',
        'fecha_efectiva'     => $this->hoy,
    ]);

    expect($cesacion->instrumento)->toBe(InstrumentoActo::ACCION_PERSONAL);
});

// ── Qué cambia ──────────────────────────────────────────────────

test('al registrarse lleva el número del contrato y no gasta el correlativo AP', function () {
    $antes = ($this->registrar)(($this->ingreso)(($this->persona)(), 'nombramiento_permanente', [
        'fecha_fin_propuesta' => null,
    ])->json('datos.id'));

    $registro = ($this->registrar)(($this->ingreso)(($this->persona)(), 'servicios_ocasionales', [
        'numero_contrato' => 'CSO-UATH-2026-0042',
    ])->json('datos.id'));

    $despues = ($this->registrar)(($this->ingreso)(($this->persona)(), 'nombramiento_permanente', [
        'fecha_fin_propuesta' => null,
    ])->json('datos.id'));

    expect($registro->codigo_registro)->toBe('CSO-UATH-2026-0042')
        ->and($registro->efecto_aplicado_en)->not->toBeNull()
        ->and((int) substr($despues->codigo_registro, -4))->toBe((int) substr($antes->codigo_registro, -4) + 1);

    // El vínculo nace igual.
    expect(ContratoServidor::where('movimiento_origen_id', $registro->id)->sole()->tipo_nombramiento)
        ->toBe(TipoNombramiento::SERVICIOS_OCASIONALES);
});

test('no se sellan firmantes ni se notifica', function () {
    $registro = ($this->registrar)(($this->ingreso)(($this->persona)(), 'servicios_ocasionales')->json('datos.id'));

    // Una acción sella la fecha y los cargos al suscribirse aunque el
    // organigrama no tenga a nadie; el registro no sella nada.
    $accion = ($this->suscribir)(($this->ingreso)(($this->persona)(), 'nombramiento_permanente', [
        'fecha_fin_propuesta' => null,
    ])->json('datos.id'));

    expect($accion->fecha_suscripcion)->not->toBeNull()
        ->and($accion->firmante_autoridad_cargo)->not->toBeNull()
        ->and($registro->fecha_suscripcion)->toBeNull()
        ->and($registro->firmante_autoridad_cargo)->toBeNull()
        ->and($registro->firmante_th_cargo)->toBeNull();

    $this->getJson("/api/v1/expediente/movimientos/{$registro->id}")
        ->assertOk()
        ->assertJsonPath('datos.transiciones_permitidas', ['anulada']);

    expect(fn () => $this->estados->transicionar($registro->fresh(), EstadoAccionPersonal::NOTIFICADA))
        ->toThrow(ReglaNegocioException::class, 'no se notifica');
});

test('no tiene documento de acción de personal', function () {
    $registro = ($this->registrar)(($this->ingreso)(($this->persona)(), 'servicios_ocasionales')->json('datos.id'));

    expect(fn () => app(AccionPersonalPdfService::class)->generarContent($registro->id))
        ->toThrow(ReglaNegocioException::class, 'el contrato se firma aparte');
});

test('pide el plazo, la partida y un número que no esté ya registrado', function () {
    $sinPlazo = ($this->ingreso)(($this->persona)(), 'servicios_ocasionales')->json('datos.id');
    MovimientoPersonal::whereKey($sinPlazo)->update(['fecha_fin_propuesta' => null]);

    expect(fn () => ($this->registrar)($sinPlazo))
        ->toThrow(ReglaNegocioException::class, 'el plazo del contrato');

    $this->puesto->update(['partida_presupuestaria_id' => null]);
    $sinPartida = ($this->ingreso)(($this->persona)(), 'servicios_ocasionales')->json('datos.id');

    expect(fn () => ($this->registrar)($sinPartida))
        ->toThrow(ReglaNegocioException::class, 'la partida presupuestaria');

    $this->puesto->update(['partida_presupuestaria_id' => $this->partida->id]);
    ($this->registrar)(($this->ingreso)(($this->persona)(), 'servicios_ocasionales', ['numero_contrato' => 'CSO-1'])->json('datos.id'));
    $repetido = ($this->ingreso)(($this->persona)(), 'servicios_ocasionales', ['numero_contrato' => 'CSO-1'])->json('datos.id');

    expect(fn () => ($this->registrar)($repetido))
        ->toThrow(ReglaNegocioException::class, 'ya está registrado');
});

test('anular el registro borra el vínculo, como en cualquier ingreso', function () {
    $servidor = ($this->persona)();
    $registro = ($this->registrar)(($this->ingreso)($servidor, 'servicios_ocasionales')->json('datos.id'));

    $this->estados->transicionar($registro->fresh(), EstadoAccionPersonal::ANULADA, ['motivo_anulacion' => 'Contrato mal registrado.']);

    expect($servidor->fresh('contratoVigente')->contratoVigente)->toBeNull();
});

// ── Catálogo y bandeja ──────────────────────────────────────────

test('el catálogo dice con qué nombramiento el ingreso es registro del contrato', function () {
    $catalogo = $this->getJson('/api/v1/expediente/acciones-personal/catalogo')->assertOk();

    $ingreso = collect($catalogo->json('datos.clases'))->firstWhere('codigo', 'ingreso');
    $traslado = collect($catalogo->json('datos.clases'))->firstWhere('codigo', 'traslado');

    expect($ingreso['registro_de_contrato_para'])->toBe(['servicios_ocasionales'])
        ->and($traslado['registro_de_contrato_para'])->toBe([])
        ->and(collect($catalogo->json('datos.instrumentos'))->pluck('codigo')->all())
        // Y el acto de autoridad electa desde la fase 4.1, el contrato civil
        // desde la 4.2 y el registro interno de los obreros desde la 4.3.
        ->toBe(['accion_personal', 'registro_contrato', 'acto_autoridad', 'contrato_civil', 'documento_ct']);
});

test('la bandeja se filtra por instrumento', function () {
    ($this->ingreso)(($this->persona)(), 'servicios_ocasionales')->assertCreated();
    ($this->ingreso)(($this->persona)(), 'nombramiento_permanente', ['fecha_fin_propuesta' => null])->assertCreated();

    $this->getJson('/api/v1/expediente/movimientos?instrumento=registro_contrato')
        ->assertOk()
        ->assertJsonCount(1, 'datos.data')
        ->assertJsonPath('datos.data.0.instrumento', 'registro_contrato');
});
