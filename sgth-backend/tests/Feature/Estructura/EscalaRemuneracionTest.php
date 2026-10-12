<?php

namespace Tests\Feature\Estructura;

use App\Enums\CategoriaEventoVinculo;
use App\Enums\EstadoAccionPersonal;
use App\Enums\TipoEventoVinculo;
use App\Models\Estructura\EscalaRemuneracion;
use App\Models\Estructura\GrupoOcupacional;
use App\Models\Estructura\Puesto;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\EventoVinculo;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| Fase 7.3b del diseño de Acciones de Personal: la nueva escala de
| remuneraciones de la LOSEP. Un acto general, sin acción de personal por
| servidor: sube la RMU del grado en el catálogo y la de cada vínculo, con su
| constancia en la bitácora, y lleva sus novedades a la nómina.
|
| Hoy es el martes 20 de octubre de 2026.
*/
beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    permisosDeAccionesPersonal();
    (require database_path('migrations/2026_10_13_000100_crear_permisos_novedades_nomina.php'))->up();
    $this->travelTo(Carbon::parse('2026-10-20 10:00'));

    $this->user = User::factory()->create();
    $this->user->assignRole('admin-uath');
    $this->actingAs($this->user, 'sanctum');

    $this->unidad = unidadDePrueba(['nombre' => 'Dirección de Obras']);
    // puestoDePrueba lo pone en el grado SP10, con RMU de $1.000.
    $this->puesto = puestoDePrueba($this->unidad, 'Analista', ['plazas' => 10]);
    $this->grado = $this->puesto->grupoOcupacional;

    $this->contador = 0;
    $this->ocupante = function (float $remuneracion, string $nombramiento = 'nombramiento_permanente', ?Puesto $puesto = null): Servidor {
        $this->contador++;
        $p = $puesto ?? $this->puesto;
        $servidor = Servidor::create([
            'cedula' => str_pad((string) (7400000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre' => 'Servidor', 'apellido' => 'Escala'.$this->contador, 'regimen_laboral' => 'losep',
            'fecha_ingreso_institucion' => '2018-01-02',
            'puesto_id' => $p->id, 'unidad_administrativa_id' => $p->unidad_administrativa_id,
        ]);
        ContratoServidor::create([
            'servidor_id' => $servidor->id, 'tipo_nombramiento' => $nombramiento,
            'unidad_administrativa_id' => $p->unidad_administrativa_id, 'puesto_id' => $p->id,
            'fecha_inicio' => '2018-01-02', 'estado' => 'vigente', 'remuneracion' => $remuneracion,
            'fecha_fin' => $nombramiento === 'servicios_profesionales' ? '2026-12-31' : null,
        ]);

        return $servidor->fresh('contratoVigente');
    };

    $this->datos = fn (float $rmu, array $mas = []) => [
        'instrumento' => 'Resolución GADPE-2026-045', 'fecha_instrumento' => '2026-10-15', 'rige_desde' => '2026-10-01',
        'dictamen_presupuestario_ref' => 'DF-2026-112',
        'grados' => [['grupo_ocupacional_id' => $this->grado->id, 'rmu_nueva' => $rmu]],
        ...$mas,
    ];
});

// ── La vista previa ─────────────────────────────────────────────

test('la vista previa dice a quién sube, y no baja a quien ya cobra más ni toca a Servicios Profesionales', function () {
    $a = ($this->ocupante)(1000);
    $b = ($this->ocupante)(1300);
    ($this->ocupante)(1500, 'servicios_profesionales');

    $vista = $this->postJson('/api/v1/estructura/escalas-remuneracion/previsualizar', ['grados' => ($this->datos)(1100)['grados']])
        ->assertOk()
        ->json('datos');

    $vinculos = collect($vista['vinculos'])->keyBy('servidor_id');

    expect($vista['impedimentos'])->toBe([])
        ->and($vista['grados'][0]['rmu_anterior'])->toEqual(1000)
        ->and($vista['grados'][0]['rmu_nueva'])->toEqual(1100)
        ->and($vinculos)->toHaveCount(2)
        ->and($vinculos[$a->id]['remuneracion_nueva'])->toEqual(1100)
        ->and($vinculos[$a->id]['sube'])->toBeTrue()
        ->and($vinculos[$b->id]['remuneracion_nueva'])->toEqual(1300)
        ->and($vinculos[$b->id]['sube'])->toBeFalse();
});

// ── El registro ─────────────────────────────────────────────────

test('registrarla sube el grado y cada vínculo, con su constancia en la bitácora', function () {
    $a = ($this->ocupante)(1000);
    $b = ($this->ocupante)(1300);

    $this->postJson('/api/v1/estructura/escalas-remuneracion', ($this->datos)(1100))
        ->assertCreated()
        ->assertJsonPath('datos.vinculos', 1);

    expect((float) $this->grado->fresh()->rmu)->toBe(1100.0)
        ->and((float) $a->contratoVigente->fresh()->remuneracion)->toBe(1100.0)
        ->and((float) $b->contratoVigente->fresh()->remuneracion)->toBe(1300.0);

    $evento = EventoVinculo::where('servidor_id', $a->id)->sole();
    expect($evento->tipo)->toBe(TipoEventoVinculo::ESCALA_APLICADA)
        ->and($evento->fecha->toDateString())->toBe('2026-10-01')
        ->and($evento->datos['remuneracion_nueva'])->toEqual(1100)
        ->and(EventoVinculo::where('servidor_id', $b->id)->exists())->toBeFalse();
});

test('ni baja, ni rige más adelante, ni toca a las autoridades electas ni a los obreros', function () {
    ($this->ocupante)(1000);

    $this->postJson('/api/v1/estructura/escalas-remuneracion', ($this->datos)(900))
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'la sube, no la baja'));

    $this->postJson('/api/v1/estructura/escalas-remuneracion', ($this->datos)(1100, ['rige_desde' => '2027-01-01']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['rige_desde'], 'errores');

    $prefecto = GrupoOcupacional::create([
        'grado_codigo' => 'NJS-10-PRUEBA', 'grado_numerico' => 10, 'grupo' => 'Nivel Jerárquico Superior',
        'denominacion_generica' => 'Prefecto/a', 'rmu' => 5060, 'regimen' => 'losep', 'rol_puesto' => 'dignatario', 'activo' => true,
    ]);
    $obreros = GrupoOcupacional::create([
        'grado_codigo' => 'CT-PRUEBA', 'grupo' => 'Trabajador Código del Trabajo', 'rmu' => 470,
        'regimen' => 'codigo_trabajo', 'rol_puesto' => 'codigo_trabajo', 'activo' => true,
    ]);

    $this->postJson('/api/v1/estructura/escalas-remuneracion/previsualizar', ['grados' => [
        ['grupo_ocupacional_id' => $prefecto->id, 'rmu_nueva' => 5500],
        ['grupo_ocupacional_id' => $obreros->id, 'rmu_nueva' => 480],
    ]])
        ->assertOk()
        ->assertJsonPath('datos.impedimentos.0', fn (string $m) => str_contains($m, 'autoridad de elección popular'))
        ->assertJsonPath('datos.impedimentos.1', fn (string $m) => str_contains($m, 'incremento en lote'));

    expect(EscalaRemuneracion::count())->toBe(0);
});

test('no se registra con acciones en trámite sobre los puestos del grado', function () {
    $servidor = Servidor::create(['cedula' => '7499999999', 'nombre' => 'Por', 'apellido' => 'Ingresar', 'regimen_laboral' => 'losep']);
    $ingreso = MovimientoPersonal::create([
        'servidor_id' => $servidor->id, 'tipo_movimiento' => 'ingreso', 'categoria' => CategoriaEventoVinculo::ACCION_DE_PERSONAL,
        'estado' => EstadoAccionPersonal::BORRADOR, 'descripcion' => 'Ingreso al puesto del grado.', 'fecha_efectiva' => '2026-11-01',
        'tipo_nombramiento_propuesto' => 'nombramiento_provisional', 'puesto_destino_id' => $this->puesto->id,
        'unidad_destino_id' => $this->unidad->id, 'remuneracion_propuesta' => 1000, 'autorizado_por' => $this->user->id,
    ]);

    $this->postJson('/api/v1/estructura/escalas-remuneracion', ($this->datos)(1100))
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'se registrarían con la remuneración de antes')
            && str_contains($m, "#{$ingreso->id}"));

    expect((float) $this->grado->fresh()->rmu)->toBe(1000.0);
});

test('quien sube con la escala no la registra', function () {
    $propio = ($this->ocupante)(1000);
    $this->user->update(['servidor_id' => $propio->id]);

    $this->postJson('/api/v1/estructura/escalas-remuneracion', ($this->datos)(1100))
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'la registra otra persona'));
});

// ── La reversión ────────────────────────────────────────────────

test('revertirla devuelve el grado y los vínculos, salvo que algo haya cambiado después', function () {
    $a = ($this->ocupante)(1000);
    $id = $this->postJson('/api/v1/estructura/escalas-remuneracion', ($this->datos)(1100))->assertCreated()->json('datos.id');

    $this->postJson("/api/v1/estructura/escalas-remuneracion/{$id}/revertir", ['motivo' => 'Se registró con la RMU equivocada.'])
        ->assertOk();

    expect((float) $this->grado->fresh()->rmu)->toBe(1000.0)
        ->and((float) $a->contratoVigente->fresh()->remuneracion)->toBe(1000.0)
        ->and(EventoVinculo::where('servidor_id', $a->id)->where('tipo', TipoEventoVinculo::ESCALA_REVERTIDA)->exists())->toBeTrue()
        ->and(EscalaRemuneracion::find($id)->estaRevertida())->toBeTrue();

    // Otra escala, y después otro cambio en el vínculo: ya no se revierte.
    $otra = $this->postJson('/api/v1/estructura/escalas-remuneracion', ($this->datos)(1150))->assertCreated()->json('datos.id');
    $a->contratoVigente->fresh()->update(['remuneracion' => 1200]);

    $this->postJson("/api/v1/estructura/escalas-remuneracion/{$otra}/revertir", ['motivo' => 'Se registró con la RMU equivocada.'])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'su remuneración cambió después'));

    expect((float) $this->grado->fresh()->rmu)->toBe(1150.0);
});

// ── La nómina ───────────────────────────────────────────────────

test('la escala retroactiva lleva la diferencia a cada mes, hasta el de su registro', function () {
    $this->travelTo(Carbon::parse('2026-03-10 10:00'));
    $a = ($this->ocupante)(1000);

    $this->postJson('/api/v1/estructura/escalas-remuneracion', ($this->datos)(1100, [
        'rige_desde' => '2026-01-01', 'fecha_instrumento' => '2026-03-05',
    ]))->assertCreated();

    $cambio = fn (string $periodo) => collect($this->getJson("/api/v1/nomina/novedades?periodo={$periodo}")->assertOk()->json('datos.novedades'))
        ->where('servidor_id', $a->id)->where('concepto', 'cambio_remuneracion')->values();

    expect($cambio('2026-01')->sole()['monto'])->toEqual(100)
        ->and($cambio('2026-02')->sole()['monto'])->toEqual(100)
        ->and($cambio('2026-02')->sole()['detalle'])->toContain('se registró el 10/03/2026')
        ->and($cambio('2026-03')->sole()['dias'])->toBe(30)
        ->and($cambio('2026-04'))->toBeEmpty();
});
