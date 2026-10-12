<?php

namespace Tests\Feature\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Models\Estructura\Cargo;
use App\Models\Estructura\GrupoOcupacional;
use App\Models\Estructura\Puesto;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\LoteAccion;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\Expediente\Subrogacion;
use App\Models\User;
use App\Services\Expediente\AccionPersonalPdfService;
use App\Services\Expediente\MovimientoPersonalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| Fase 7.1b del diseño de Acciones de Personal: la revisión de la
| clasificación de los puestos ocupados (LOSEP Arts. 61 y 62; Reglamento a la
| LOSEP Arts. 163 y 173). Revalorización, reclasificación o ubicación, en un
| lote con un acto por ocupante: el puesto cambia de grado o de denominación,
| y la remuneración de cada uno sube —nunca baja—.
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

    $grupo = fn (string $codigo, int $grado, float $rmu) => GrupoOcupacional::firstOrCreate(['grado_codigo' => $codigo], [
        'grado_numerico' => $grado, 'grupo' => "Servidor Público {$grado}", 'denominacion_generica' => 'Profesional',
        'rmu' => $rmu, 'regimen' => 'losep', 'activo' => true,
    ]);
    $this->sp8 = $grupo('SP8', 8, 800);
    $this->sp10 = $grupo('SP10', 10, 1000);
    $this->sp12 = $grupo('SP12', 12, 1200);
    $this->analista = Cargo::firstOrCreate(['nombre' => 'Analista de Proyectos']);
    $this->especialista = Cargo::firstOrCreate(['nombre' => 'Especialista de Proyectos']);

    $this->unidad = unidadDePrueba(['nombre' => 'Dirección de Obras']);
    $this->puesto = Puesto::create([
        'unidad_administrativa_id' => $this->unidad->id, 'cargo_id' => $this->analista->id,
        'grupo_ocupacional_id' => $this->sp10->id, 'plazas' => 3, 'activo' => true,
    ]);

    $this->contador = 0;
    $this->ocupante = function (Puesto $puesto, float $remuneracion, string $nombramiento = 'nombramiento_permanente'): Servidor {
        $this->contador++;
        $servidor = Servidor::create([
            'cedula' => str_pad((string) (6700500000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre' => 'Servidor', 'apellido' => 'Revision'.$this->contador, 'regimen_laboral' => 'losep',
            'fecha_ingreso_institucion' => '2018-01-02',
        ]);
        ContratoServidor::create([
            'servidor_id' => $servidor->id, 'tipo_nombramiento' => $nombramiento,
            'unidad_administrativa_id' => $puesto->unidad_administrativa_id, 'puesto_id' => $puesto->id,
            'fecha_inicio' => '2018-01-02', 'estado' => 'vigente', 'remuneracion' => $remuneracion,
        ]);
        $servidor->update(['puesto_id' => $puesto->id, 'unidad_administrativa_id' => $puesto->unidad_administrativa_id]);

        return $servidor->fresh();
    };

    $this->primero = ($this->ocupante)($this->puesto, 1000);
    // Ya cobra más que el grado nuevo: conserva lo suyo.
    $this->segundo = ($this->ocupante)($this->puesto, 1300, 'nombramiento_provisional');

    $this->datos = fn (array $extra = []) => [
        'causal'                      => 'revalorizacion',
        'puestos'                     => [['puesto_id' => $this->puesto->id, 'grupo_ocupacional_id' => $this->sp12->id]],
        'documento_origen'            => 'RES-GADPE-2026-010',
        'dictamen_presupuestario_ref' => 'DP-2026-055',
        'descripcion'                 => 'Revalorización del puesto por la aplicación del manual institucional.',
        'fecha_efectiva'              => '2026-10-12',
        ...$extra,
    ];

    $this->preparar = fn (array $extra = []) => $this->postJson('/api/v1/expediente/lotes-accion/revision-clasificacion', ($this->datos)($extra));
    $this->previa = fn (array $extra = []) => $this->postJson('/api/v1/expediente/lotes-accion/revision-clasificacion/previsualizar', ($this->datos)($extra));
    $this->lote = fn (array $extra = []) => LoteAccion::findOrFail(($this->preparar)($extra)->assertCreated()->json('datos.id'));
});

// ── Preparar ────────────────────────────────────────────────────

test('la revalorización da un borrador por ocupante, con la remuneración del grado nuevo, que nunca baja', function () {
    $lote = ($this->lote)();
    $actos = $lote->movimientos()->orderBy('id')->get();

    expect($lote->tipo)->toBe(LoteAccion::REVISION_CLASIFICACION)
        ->and($lote->dictamen_presupuestario_ref)->toBe('DP-2026-055')
        ->and($actos)->toHaveCount(2)
        ->and((float) $actos[0]->remuneracion_propuesta)->toBe(1200.0)
        ->and((float) $actos[1]->remuneracion_propuesta)->toBe(1300.0);

    foreach ($actos as $acto) {
        expect($acto->clase->value)->toBe('revision_clasificacion')
            ->and($acto->subtipo_movimiento->value)->toBe('revalorizacion')
            ->and($acto->casilla()->value)->toBe('revalorizacion')
            ->and($acto->estado)->toBe(EstadoAccionPersonal::BORRADOR)
            ->and($acto->grupo_ocupacional_origen_id)->toBe($this->sp10->id)
            ->and($acto->grupo_ocupacional_destino_id)->toBe($this->sp12->id)
            ->and($acto->cargo_origen_id)->toBe($this->analista->id)
            ->and($acto->cargo_destino_id)->toBe($this->analista->id)
            ->and($acto->resolucion_numero)->toBe('RES-GADPE-2026-010');
    }

    expect($this->puesto->fresh()->grupo_ocupacional_id)->toBe($this->sp10->id);
});

test('si sube la remuneración de alguien, pide el dictamen presupuestario', function () {
    ($this->preparar)(['dictamen_presupuestario_ref' => null])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'dictamen presupuestario'));

    // La reclasificación que solo cambia la denominación no lo pide.
    ($this->preparar)([
        'causal' => 'reclasificacion', 'dictamen_presupuestario_ref' => null,
        'puestos' => [['puesto_id' => $this->puesto->id, 'cargo_id' => $this->especialista->id]],
    ])->assertCreated()->assertJsonPath('datos.dictamen_presupuestario_ref', null);
});

test('cada causal pide lo suyo, y el grado de un puesto ocupado no baja', function () {
    $impedimentos = fn (array $extra) => ($this->previa)($extra)->assertOk()->json('datos.puestos.0.impedimentos');

    expect($impedimentos(['puestos' => [['puesto_id' => $this->puesto->id, 'grupo_ocupacional_id' => $this->sp12->id, 'cargo_id' => $this->especialista->id]]]))
        ->toContain('La revalorización conserva la denominación: si cambia, es una reclasificación.')
        ->and(implode(' ', $impedimentos(['causal' => 'ubicacion', 'puestos' => [['puesto_id' => $this->puesto->id, 'grupo_ocupacional_id' => $this->sp8->id]]])))
        ->toContain('Baja el grado del puesto')
        ->and($impedimentos(['causal' => 'reclasificacion', 'puestos' => [['puesto_id' => $this->puesto->id, 'grupo_ocupacional_id' => $this->sp12->id]]]))
        ->toContain('La reclasificación cambia la denominación del puesto: elija la nueva.')
        ->and($impedimentos(['causal' => 'ubicacion', 'puestos' => [['puesto_id' => $this->puesto->id, 'grupo_ocupacional_id' => $this->sp10->id]]]))
        ->toBe(['No cambia nada: elija el grado o la denominación nuevos.']);

    ($this->previa)()
        ->assertJsonPath('datos.sube_la_remuneracion', true)
        ->assertJsonPath('datos.puestos.0.impedimentos', [])
        ->assertJsonPath('datos.puestos.0.ocupantes.1.remuneracion_nueva', 1300)
        ->assertJsonCount(3, 'datos.causales');
});

test('ni el Prefecto, ni el puesto de obreros, ni el que tiene una subrogación vigente se revisan', function () {
    $prefectura = Puesto::create([
        'unidad_administrativa_id' => unidadDePrueba(['nombre' => 'Prefectura'])->id, 'cargo_id' => Cargo::firstOrCreate(['nombre' => 'Prefecto/a'])->id,
        'grupo_ocupacional_id' => $this->sp10->id, 'plazas' => 1, 'activo' => true, 'rol_puesto' => 'dignatario',
    ]);
    ($this->ocupante)($prefectura, 5000, 'eleccion_popular');

    expect(($this->previa)(['puestos' => [['puesto_id' => $prefectura->id, 'grupo_ocupacional_id' => $this->sp12->id]]])->json('datos.puestos.0.impedimentos.0'))
        ->toContain('autoridad de elección popular');

    Subrogacion::create([
        'tipo' => 'subrogacion', 'servidor_subrogante_id' => $this->primero->id, 'unidad_administrativa_id' => $this->unidad->id,
        'puesto_subrogado_id' => $this->puesto->id, 'fecha_inicio' => '2026-10-01', 'fecha_fin' => '2026-10-30', 'motivo' => 'vacaciones',
        'estado' => 'activa', 'registrado_por' => $this->user->id,
    ]);

    expect(($this->previa)()->json('datos.puestos.0.impedimentos'))
        ->toContain('El puesto tiene una subrogación o un encargo vigente: termínelo antes de revisarlo.');
});

// ── Registrar y revertir ────────────────────────────────────────

test('un acto de la revisión no se tramita suelto: va con su lote', function () {
    $acto = ($this->lote)()->movimientos()->first();

    $this->getJson("/api/v1/expediente/movimientos/{$acto->id}")->assertJsonPath('datos.transiciones_permitidas', []);
    $this->putJson("/api/v1/expediente/movimientos/{$acto->id}/transicionar", ['estado' => 'suscrita'])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'desde el lote'));
});

test('registrar el lote cambia el grado del puesto y la remuneración de cada ocupante; revertirlo, lo devuelve', function () {
    $lote = ($this->lote)();

    $this->postJson("/api/v1/expediente/lotes-accion/{$lote->id}/registrar")->assertOk();

    expect($lote->movimientos()->pluck('estado')->map->value->unique()->all())->toBe(['registrada'])
        ->and($this->puesto->fresh()->grupo_ocupacional_id)->toBe($this->sp12->id)
        ->and((float) $this->primero->fresh()->contratoVigente->remuneracion)->toBe(1200.0)
        ->and((float) $this->segundo->fresh()->contratoVigente->remuneracion)->toBe(1300.0)
        ->and($lote->movimientos()->first()->dictamen_presupuestario_ref)->toBe('DP-2026-055');

    $registro = Activity::query()->where('subject_type', Puesto::class)->where('subject_id', $this->puesto->id)
        ->where('event', 'updated')->latest('id')->first();
    expect($registro->properties['causa'])->toContain("lote #{$lote->id}");

    $this->postJson("/api/v1/expediente/lotes-accion/{$lote->id}/revertir", ['motivo' => 'La resolución se dejó sin efecto.'])
        ->assertOk()
        ->assertJsonPath('datos.anulados', 2);

    expect($this->puesto->fresh()->grupo_ocupacional_id)->toBe($this->sp10->id)
        ->and((float) $this->primero->fresh()->contratoVigente->remuneracion)->toBe(1000.0)
        ->and((float) $this->segundo->fresh()->contratoVigente->remuneracion)->toBe(1300.0);
});

test('una ubicación abarca varios puestos a la vez', function () {
    $otro = Puesto::create([
        'unidad_administrativa_id' => $this->unidad->id, 'cargo_id' => $this->analista->id,
        'grupo_ocupacional_id' => $this->sp10->id, 'plazas' => 1, 'activo' => true,
    ]);
    $tercero = ($this->ocupante)($otro, 1000);

    $lote = ($this->lote)([
        'causal' => 'ubicacion',
        'puestos' => [
            ['puesto_id' => $this->puesto->id, 'grupo_ocupacional_id' => $this->sp12->id],
            ['puesto_id' => $otro->id, 'cargo_id' => $this->especialista->id, 'grupo_ocupacional_id' => $this->sp12->id],
        ],
    ]);

    expect($lote->movimientos()->count())->toBe(3);

    $this->postJson("/api/v1/expediente/lotes-accion/{$lote->id}/registrar")->assertOk();

    expect($otro->fresh()->cargo_id)->toBe($this->especialista->id)
        ->and($otro->fresh()->grupo_ocupacional_id)->toBe($this->sp12->id)
        ->and((float) $tercero->fresh()->contratoVigente->remuneracion)->toBe(1200.0);

    $this->getJson("/api/v1/expediente/lotes-accion/{$lote->id}")
        ->assertJsonPath('datos.revision.causal', 'Ubicación')
        ->assertJsonPath('datos.revision.puestos.1.antes', 'Analista de Proyectos · SP10')
        ->assertJsonPath('datos.revision.puestos.1.despues', 'Especialista de Proyectos · SP12');
});

// ── Lo firmado no se reescribe ──────────────────────────────────

test('el documento firmado conserva el grado y la denominación con que se firmó', function () {
    // Una acción cualquiera del ocupante, antes de la revisión.
    $licencia = app(MovimientoPersonalService::class)->registrar($this->primero->id, [
        'tipo_movimiento' => TipoMovimientoPersonal::LICENCIA_SIN_REMUNERACION->value,
        'subtipo_movimiento' => 'servicio_militar', 'descripcion' => 'Licencia por servicio militar.',
        'fecha_efectiva' => '2026-11-01', 'fecha_inicio' => '2026-11-01', 'fecha_fin' => '2026-11-30',
    ]);
    expect($licencia->grupo_ocupacional_origen_id)->toBe($this->sp10->id)
        ->and($licencia->cargo_origen_id)->toBe($this->analista->id);

    // Después, el puesto cambia de grado y de denominación.
    $this->puesto->update(['grupo_ocupacional_id' => $this->sp12->id, 'cargo_id' => $this->especialista->id]);

    ['vista' => $vista, 'datos' => $datos] = app(AccionPersonalPdfService::class)->documento($licencia->id, true);
    $texto = preg_replace('/\s+/', ' ', strip_tags(str_replace('</span>', '</span> ', view($vista, $datos)->render())));

    expect($texto)->toContain('Puesto Analista de Proyectos')
        ->and($texto)->toContain('Grado SP10')
        ->and($texto)->not->toContain('Especialista de Proyectos');
});
