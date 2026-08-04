<?php

namespace Tests\Feature\Seleccion;

use App\Enums\EstadoConvocatoria;
use App\Enums\EstadoPostulante;
use App\Models\Dispensario\SolicitudCertificacionMedica;
use App\Models\Estructura\Cargo;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Seleccion\Convocatoria;
use App\Models\Seleccion\Postulante;
use App\Models\User;
use App\Services\Seleccion\SeleccionService;
use Database\Seeders\ContenedorExpressSeeder;
use App\Exceptions\ReglaNegocioException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);

    $this->user = User::factory()->create();
    $this->user->assignRole('admin-uath');
    $this->actingAs($this->user, 'sanctum');

    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'UATH-01', 'nombre' => 'Unidad de Talento Humano', 'nivel' => 1,
    ]);

    $cargo = Cargo::create(['nombre' => 'Analista', 'clasificacion_personal' => 'empleado']);

    $this->puesto = Puesto::create([
        'unidad_administrativa_id' => $this->unidad->id,
        'cargo_id' => $cargo->id, 'plazas' => 30, 'rmu' => 1200,
    ]);

    $this->service = app(SeleccionService::class);
    $this->contador = 0;

    $this->convocatoriaCon = function (int $vacantes): Convocatoria {
        $this->contador++;

        return Convocatoria::create([
            'codigo' => "CONV-MG-{$this->contador}",
            'titulo' => 'Concurso con varias vacantes',
            'descripcion' => 'x', 'tipo' => 'externa', 'tipo_proceso' => 'formal',
            'estado' => EstadoConvocatoria::EN_EVALUACION->value,
            'vacantes' => $vacantes,
            'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-02-01',
            'puesto_id' => $this->puesto->id,
        ]);
    };

    $this->aspirante = function (
        Convocatoria $convocatoria,
        EstadoPostulante $estado = EstadoPostulante::APROBADO,
        ?int $puestoId = null
    ): Postulante {
        $this->contador++;

        return Postulante::create([
            'convocatoria_id' => $convocatoria->id,
            'puesto_id' => $puestoId,
            'fecha_inscripcion' => '2026-01-15',
            'cedula' => str_pad((string) (1800000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombres' => 'Aspirante', 'apellidos' => 'Numero'.$this->contador,
            'correo' => "asp{$this->contador}@test.ec",
            'estado' => $estado->value,
        ]);
    };
});

// ── Concurso formal con varias vacantes ─────────────────────────

test('se pueden declarar tantos ganadores como vacantes', function () {
    $convocatoria = ($this->convocatoriaCon)(3);
    $a = ($this->aspirante)($convocatoria);
    $b = ($this->aspirante)($convocatoria);
    $c = ($this->aspirante)($convocatoria);

    $ganadores = $this->service->declararGanadores(
        $convocatoria->id, [$a->id, $b->id, $c->id], $this->user->id
    );

    expect($ganadores)->toHaveCount(3)
        ->and($ganadores->every(fn ($g) => $g->estado === EstadoPostulante::GANADOR_POTENCIAL))->toBeTrue();
});

test('cada ganador genera su propia solicitud al dispensario', function () {
    $convocatoria = ($this->convocatoriaCon)(2);
    $a = ($this->aspirante)($convocatoria);
    $b = ($this->aspirante)($convocatoria);

    $this->service->declararGanadores($convocatoria->id, [$a->id, $b->id], $this->user->id);

    $solicitudes = SolicitudCertificacionMedica::where('convocatoria_id', $convocatoria->id)->get();

    expect($solicitudes)->toHaveCount(2)
        ->and($solicitudes->pluck('postulante_id')->sort()->values()->all())
        ->toBe(collect([$a->id, $b->id])->sort()->values()->all())
        ->and($solicitudes->every(fn ($s) => $s->estado === 'pendiente'))->toBeTrue();
});

test('no se pueden declarar más ganadores que vacantes', function () {
    $convocatoria = ($this->convocatoriaCon)(2);
    $a = ($this->aspirante)($convocatoria);
    $b = ($this->aspirante)($convocatoria);
    $c = ($this->aspirante)($convocatoria);

    expect(fn () => $this->service->declararGanadores(
        $convocatoria->id, [$a->id, $b->id, $c->id], $this->user->id
    ))->toThrow(ReglaNegocioException::class, '2 vacante(s) y se intentan declarar 3');

    // Nada quedó a medias.
    expect(SolicitudCertificacionMedica::count())->toBe(0)
        ->and($a->fresh()->estado)->toBe(EstadoPostulante::APROBADO);
});

test('los aprobados que no ganaron pasan a lista de espera', function () {
    $convocatoria = ($this->convocatoriaCon)(2);
    $a = ($this->aspirante)($convocatoria);
    $b = ($this->aspirante)($convocatoria);
    $perdedor = ($this->aspirante)($convocatoria);
    $reprobado = ($this->aspirante)($convocatoria, EstadoPostulante::REPROBADO);

    $this->service->declararGanadores($convocatoria->id, [$a->id, $b->id], $this->user->id);

    expect($perdedor->fresh()->estado)->toBe(EstadoPostulante::LISTA_ESPERA)
        // Un reprobado no entra a lista de espera.
        ->and($reprobado->fresh()->estado)->toBe(EstadoPostulante::REPROBADO)
        ->and($convocatoria->fresh()->estado)->toBe(EstadoConvocatoria::EN_EVALUACION_MEDICA);
});

test('todos los declarados deben estar aprobados', function () {
    $convocatoria = ($this->convocatoriaCon)(3);
    $a = ($this->aspirante)($convocatoria);
    $b = ($this->aspirante)($convocatoria, EstadoPostulante::REPROBADO);

    expect(fn () => $this->service->declararGanadores(
        $convocatoria->id, [$a->id, $b->id], $this->user->id
    ))->toThrow(ReglaNegocioException::class, 'deben estar aprobados');
});

test('no se pueden declarar ganadores de otra convocatoria', function () {
    $convocatoria = ($this->convocatoriaCon)(2);
    $otra = ($this->convocatoriaCon)(2);

    $propio = ($this->aspirante)($convocatoria);
    $ajeno = ($this->aspirante)($otra);

    expect(fn () => $this->service->declararGanadores(
        $convocatoria->id, [$propio->id, $ajeno->id], $this->user->id
    ))->toThrow(ReglaNegocioException::class, 'no pertenece a esta convocatoria');
});

test('un concurso ya en evaluación médica no admite más declaraciones', function () {
    $convocatoria = ($this->convocatoriaCon)(3);
    $a = ($this->aspirante)($convocatoria);
    $b = ($this->aspirante)($convocatoria);

    $this->service->declararGanadores($convocatoria->id, [$a->id], $this->user->id);

    expect(fn () => $this->service->declararGanadores(
        $convocatoria->id, [$b->id], $this->user->id
    ))->toThrow(ReglaNegocioException::class, 'ya tiene candidatos en evaluación médica');
});

// ── Contenedor express: sin competencia entre aspirantes ────────

test('despachar a un aspirante express no toca a los demás ni al contenedor', function () {
    $this->seed(ContenedorExpressSeeder::class);
    $contenedor = Convocatoria::where('codigo', 'EXP-PROFESIONALES')->firstOrFail();

    $despachado = ($this->aspirante)($contenedor, EstadoPostulante::APROBADO, $this->puesto->id);
    $otro = ($this->aspirante)($contenedor, EstadoPostulante::APROBADO, $this->puesto->id);

    $this->service->declararGanadores($contenedor->id, [$despachado->id], $this->user->id);

    expect($despachado->fresh()->estado)->toBe(EstadoPostulante::GANADOR_POTENCIAL)
        // El otro sigue aprobado: no compite por una vacante.
        ->and($otro->fresh()->estado)->toBe(EstadoPostulante::APROBADO)
        // Y el contenedor sigue abierto para el resto de la modalidad.
        ->and($contenedor->fresh()->estado)->toBe(EstadoConvocatoria::PUBLICADA);
});

test('el contenedor express no está limitado por vacantes', function () {
    $this->seed(ContenedorExpressSeeder::class);
    $contenedor = Convocatoria::where('codigo', 'EXP-OCASIONAL')->firstOrFail();

    expect($contenedor->vacantes)->toBe(1);

    $aspirantes = collect(range(1, 4))->map(
        fn () => ($this->aspirante)($contenedor, EstadoPostulante::APROBADO, $this->puesto->id)
    );

    $ganadores = $this->service->declararGanadores(
        $contenedor->id, $aspirantes->pluck('id')->all(), $this->user->id
    );

    expect($ganadores)->toHaveCount(4)
        ->and(SolicitudCertificacionMedica::where('convocatoria_id', $contenedor->id)->count())->toBe(4);
});

test('la solicitud express toma el puesto del aspirante', function () {
    $this->seed(ContenedorExpressSeeder::class);
    $contenedor = Convocatoria::where('codigo', 'EXP-PROFESIONALES')->firstOrFail();

    $aspirante = ($this->aspirante)($contenedor, EstadoPostulante::APROBADO, $this->puesto->id);

    $this->service->declararGanadores($contenedor->id, [$aspirante->id], $this->user->id);

    $solicitud = SolicitudCertificacionMedica::where('postulante_id', $aspirante->id)->firstOrFail();

    expect($solicitud->puesto_solicitado)->toBe('Analista');
});

// ── Endpoints ───────────────────────────────────────────────────

test('el endpoint acepta una lista de ganadores', function () {
    $convocatoria = ($this->convocatoriaCon)(2);
    $a = ($this->aspirante)($convocatoria);
    $b = ($this->aspirante)($convocatoria);

    $this->postJson("/api/v1/seleccion/convocatorias/{$convocatoria->id}/declarar-ganador", [
        'postulante_ganador_ids' => [$a->id, $b->id],
    ])->assertOk()->assertJsonPath('mensaje', fn ($m) => str_contains($m, '2 candidato(s)'));

    expect($a->fresh()->estado)->toBe(EstadoPostulante::GANADOR_POTENCIAL)
        ->and($b->fresh()->estado)->toBe(EstadoPostulante::GANADOR_POTENCIAL);
});

test('el endpoint sigue aceptando un único id', function () {
    $convocatoria = ($this->convocatoriaCon)(1);
    $a = ($this->aspirante)($convocatoria);

    $this->postJson("/api/v1/seleccion/convocatorias/{$convocatoria->id}/declarar-ganador", [
        'postulante_ganador_id' => $a->id,
    ])->assertOk();

    expect($a->fresh()->estado)->toBe(EstadoPostulante::GANADOR_POTENCIAL);
});

test('el endpoint exige al menos un ganador', function () {
    $convocatoria = ($this->convocatoriaCon)(2);

    $this->postJson("/api/v1/seleccion/convocatorias/{$convocatoria->id}/declarar-ganador", [])
        ->assertStatus(422)
        ->assertJsonStructure(['errores']);
});

test('confirmar finaliza el concurso y marca a todos los ganadores', function () {
    $convocatoria = ($this->convocatoriaCon)(2);
    $a = ($this->aspirante)($convocatoria);
    $b = ($this->aspirante)($convocatoria);

    $this->service->declararGanadores($convocatoria->id, [$a->id, $b->id], $this->user->id);

    $this->postJson("/api/v1/seleccion/convocatorias/{$convocatoria->id}/confirmar-ganador")
        ->assertOk()
        ->assertJsonPath('mensaje', fn ($m) => str_contains($m, '2 ganador(es)'));

    expect($a->fresh()->estado)->toBe(EstadoPostulante::SELECCIONADO)
        ->and($b->fresh()->estado)->toBe(EstadoPostulante::SELECCIONADO)
        ->and($convocatoria->fresh()->estado)->toBe(EstadoConvocatoria::FINALIZADA);
});
