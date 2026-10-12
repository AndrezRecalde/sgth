<?php

namespace Tests\Feature\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\EstadoSubrogacion;
use App\Enums\TipoNombramiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estructura\Cargo;
use App\Models\Estructura\PartidaPresupuestaria;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\Expediente\Subrogacion;
use App\Models\User;
use App\Services\Expediente\MovimientoPersonalStateService;
use App\Services\Expediente\SubrogacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| Fase 5.2 del diseño de Acciones de Personal (9.5): subrogación y encargo, cada
| uno con su regla. La subrogación es de un puesto del nivel jerárquico
| superior (LOSEP, Art. 126); el encargo, de cualquier puesto vacante, y dura
| hasta la designación del titular (Art. 127). Al Prefecto lo subroga el
| Viceprefecto por ley, no este módulo.
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

    $this->unidad = UnidadAdministrativa::create(['codigo' => 'DF-NJS', 'nombre' => 'Dirección Financiera', 'nivel' => 2]);

    foreach (['510512' => 'Subrogaciones', '510513' => 'Encargos'] as $codigo => $descripcion) {
        PartidaPresupuestaria::create([
            'codigo' => $codigo, 'descripcion' => $descripcion,
            'grupo_gasto' => 'Gastos en Personal', 'activo' => true, 'disponible' => true,
        ]);
    }

    $puesto = fn (string $cargo, bool $njs) => Puesto::create([
        'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 1, 'activo' => true,
        'cargo_id' => Cargo::firstOrCreate(['nombre' => $cargo])->id,
        'grupo_ocupacional_id' => $njs ? grupoNjsDePrueba()->id : null,
    ]);
    $this->director = $puesto('Director/a Financiero', true);
    $this->analista = $puesto('Analista Financiero', false);

    $this->contador = 0;
    $this->servidor = function (?Puesto $en = null): Servidor {
        $this->contador++;
        $servidor = Servidor::create([
            'cedula' => str_pad((string) (6100000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre' => 'Servidor', 'apellido' => 'Njs'.$this->contador, 'regimen_laboral' => 'losep',
        ]);
        if ($en) {
            ContratoServidor::create([
                'servidor_id' => $servidor->id, 'tipo_nombramiento' => TipoNombramiento::PERMANENTE->value,
                'unidad_administrativa_id' => $this->unidad->id, 'puesto_id' => $en->id,
                'fecha_inicio' => '2018-01-02', 'estado' => 'vigente',
            ]);
        }

        return $servidor->fresh();
    };

    $this->service = app(SubrogacionService::class);
    $this->estados = app(MovimientoPersonalStateService::class);

    $this->subrogar = fn (Puesto $puesto, Servidor $titular, array $extra = []) => $this->service->registrar([
        'tipo' => 'subrogacion', 'servidor_subrogante_id' => ($this->servidor)()->id,
        'servidor_subrogado_id' => $titular->id, 'unidad_administrativa_id' => $this->unidad->id,
        'puesto_subrogado_id' => $puesto->id, 'fecha_inicio' => '2026-10-19', 'fecha_fin' => '2026-10-30',
        ...ausenciaOtraDePrueba(),
        ...$extra,
    ]);

    $this->encargar = fn (Puesto $puesto, array $extra = []) => $this->service->registrar([
        'tipo' => 'encargo', 'servidor_subrogante_id' => ($this->servidor)()->id,
        'unidad_administrativa_id' => $this->unidad->id, 'puesto_subrogado_id' => $puesto->id,
        'fecha_inicio' => '2026-10-12',
        ...$extra,
    ]);

    $this->registrarAccion = function (MovimientoPersonal $m): MovimientoPersonal {
        $this->estados->transicionar($m->fresh(), EstadoAccionPersonal::SUSCRITA, ['dictamen_presupuestario_ref' => 'DP-2026-001']);

        return $this->estados->transicionar($m->fresh(), EstadoAccionPersonal::REGISTRADA);
    };
});

// ── El nivel jerárquico superior ────────────────────────────────

test('la subrogación es de un puesto del nivel jerárquico superior', function () {
    expect(fn () => ($this->subrogar)($this->analista, ($this->servidor)($this->analista)))
        ->toThrow(ReglaNegocioException::class, 'nivel jerárquico superior (LOSEP, Art. 126)');

    expect(($this->subrogar)($this->director, ($this->servidor)($this->director))->estado)
        ->toBe(EstadoSubrogacion::PENDIENTE);
});

test('el encargo vale para cualquier puesto vacante, y sin fecha de fin', function () {
    $encargo = ($this->encargar)($this->analista);

    expect($encargo->fecha_fin)->toBeNull()
        ->and($encargo->movimientoPersonal->descripcion)->toContain('hasta la designación del titular del puesto')
        ->and($encargo->motivo->value)->toBe('encargo_vacante');

    // La subrogación, en cambio, tiene fin.
    expect(fn () => ($this->subrogar)($this->director, ($this->servidor)($this->director), ['fecha_fin' => null]))
        ->toThrow(ReglaNegocioException::class, 'tiene fecha de fin');
});

test('el encargo sin fin no vence, y se cruza con cualquiera que empiece después', function () {
    $encargo = ($this->encargar)($this->director);
    ($this->registrarAccion)($encargo->movimientoPersonal);

    expect($this->service->caducarVencidas('2027-06-01')['caducadas'])->toBe(0)
        ->and(Subrogacion::activaEnFecha('2027-06-01')->pluck('id')->all())->toBe([$encargo->id]);

    expect(fn () => ($this->encargar)($this->director, ['fecha_inicio' => '2027-03-01', 'fecha_fin' => '2027-03-31']))
        ->toThrow(ReglaNegocioException::class, 'sin fecha de fin');
});

test('llega el titular al puesto y el encargo termina la víspera', function () {
    $encargo = ($this->encargar)($this->director);
    ($this->registrarAccion)($encargo->movimientoPersonal);

    // Un ingreso al puesto, registrado: rige el 1 de noviembre.
    $nuevo = ($this->servidor)();
    $partida = PartidaPresupuestaria::create([
        'codigo' => '510510', 'descripcion' => 'Servicios ocasionales',
        'grupo_gasto' => 'Gastos en Personal', 'activo' => true, 'disponible' => true,
    ]);
    $id = $this->postJson("/api/v1/expediente/servidores/{$nuevo->id}/movimientos", [
        'clase' => 'ingreso', 'descripcion' => 'Designación del titular', 'fecha_efectiva' => '2026-11-01',
        'tipo_nombramiento_propuesto' => TipoNombramiento::SERVICIOS_OCASIONALES->value,
        'unidad_destino_id' => $this->unidad->id, 'puesto_destino_id' => $this->director->id,
        'numero_contrato' => 'CSO-2026-077', 'remuneracion_propuesta' => 2500,
        'fecha_fin_propuesta' => '2026-12-31', 'requiere_dictamen_medico' => false,
        'partida_presupuestaria_id' => $partida->id,
    ])->assertCreated()->json('datos.id');

    $this->travelTo(Carbon::parse('2026-11-01 09:00'));
    $ingreso = ($this->registrarAccion)(MovimientoPersonal::findOrFail($id));

    expect($ingreso->efecto_aplicado_en)->not->toBeNull()
        ->and($encargo->fresh()->fecha_fin->toDateString())->toBe('2026-10-31')
        ->and($encargo->fresh()->estado)->toBe(EstadoSubrogacion::FINALIZADA)
        ->and($encargo->fresh()->observacion)->toContain('llegó el titular del puesto');
});

test('finalizar un encargo sin fin le pone la fecha de hoy', function () {
    $encargo = ($this->encargar)($this->director);
    ($this->registrarAccion)($encargo->movimientoPersonal);

    $this->service->finalizar($encargo->id);

    expect($encargo->fresh()->fecha_fin->toDateString())->toBe('2026-10-12')
        ->and($encargo->fresh()->estado)->toBe(EstadoSubrogacion::FINALIZADA);
});

// ── El Prefecto ─────────────────────────────────────────────────

test('al Prefecto no se le subroga desde aquí: lo hace el Viceprefecto por ley', function () {
    $prefectura = UnidadAdministrativa::create([
        'codigo' => 'PREF-NJS', 'nombre' => 'Prefectura', 'nivel' => 1, 'es_maxima_autoridad' => true,
    ]);
    $puestoPrefecto = Puesto::create([
        'unidad_administrativa_id' => $prefectura->id, 'plazas' => 1, 'es_jefe' => true, 'activo' => true,
        'cargo_id' => Cargo::firstOrCreate(['nombre' => 'Prefecto/a Provincial'])->id,
        'grupo_ocupacional_id' => grupoNjsDePrueba()->id,
    ]);
    $prefecto = ($this->servidor)();
    ContratoServidor::create([
        'servidor_id' => $prefecto->id, 'tipo_nombramiento' => TipoNombramiento::ELECCION_POPULAR->value,
        'unidad_administrativa_id' => $prefectura->id, 'puesto_id' => $puestoPrefecto->id,
        'fecha_inicio' => '2023-05-14', 'fecha_fin' => '2027-05-13', 'estado' => 'vigente',
    ]);

    expect(fn () => $this->service->registrar([
        'tipo' => 'subrogacion', 'servidor_subrogante_id' => ($this->servidor)()->id,
        'servidor_subrogado_id' => $prefecto->id, 'unidad_administrativa_id' => $prefectura->id,
        'puesto_subrogado_id' => $puestoPrefecto->id, 'fecha_inicio' => '2026-10-19', 'fecha_fin' => '2026-10-30',
        ...ausenciaOtraDePrueba(),
    ]))->toThrow(ReglaNegocioException::class, 'Subrogación por ley');
});
