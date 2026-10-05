<?php

use App\Enums\EstadoPostulante;
use App\Models\Estructura\GrupoOcupacional;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Seleccion\CalificacionPostulante;
use App\Models\Seleccion\Convocatoria;
use App\Models\Seleccion\CriterioEvaluacion;
use App\Models\Seleccion\OpcionCriterio;
use App\Models\Seleccion\PlantillaEvaluacion;
use App\Models\Seleccion\Postulante;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
 * La calificación por criterios (decisión 5 de TH, 2026-10-05): criterios que
 * suman 100, el aprobado en 70, y una calificación que solo acepta los
 * criterios y opciones de su convocatoria. Antes el checklist guardaba una
 * opción y sumaba todas, y cambiar los criterios borraba las calificaciones.
 */

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->assignRole(Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']));
    $this->actingAs($this->user, 'sanctum');

    $unidad = UnidadAdministrativa::create(['codigo' => 'CAL-01', 'nombre' => 'Unidad Calificación', 'nivel' => 1]);
    $grupo = GrupoOcupacional::create([
        'grado_codigo' => 'SP4', 'grado_numerico' => 4, 'grupo' => 'Profesional',
        'denominacion_generica' => 'Analista', 'rmu' => 1212.00, 'regimen' => 'losep', 'activo' => true,
    ]);
    $this->puesto = Puesto::create([
        'unidad_administrativa_id' => $unidad->id, 'grupo_ocupacional_id' => $grupo->id,
        'plazas' => 5, 'regimen_laboral' => 'losep', 'activo' => true,
    ]);

    $this->convocatoria = Convocatoria::create([
        'puesto_id' => $this->puesto->id, 'codigo' => 'CNV-CAL-1', 'titulo' => 'Analista',
        'descripcion' => 'Concurso', 'tipo' => 'externa', 'tipo_proceso' => 'formal',
        'estado' => 'borrador', 'vacantes' => 1,
        'fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-09-20',
    ]);

    // Tres criterios que suman 100: un radio (40), un checklist (30) y un número (30).
    $this->criterios = function (Convocatoria $c) {
        $radio = CriterioEvaluacion::create([
            'convocatoria_id' => $c->id, 'seccion' => 'meritos', 'nombre' => 'Formación',
            'puntaje_maximo' => 40, 'tipo_input' => 'radio', 'orden' => 1, 'activo' => true,
        ]);
        $checklist = CriterioEvaluacion::create([
            'convocatoria_id' => $c->id, 'seccion' => 'meritos', 'nombre' => 'Capacitación',
            'puntaje_maximo' => 30, 'tipo_input' => 'checklist', 'orden' => 2, 'activo' => true,
        ]);
        $numero = CriterioEvaluacion::create([
            'convocatoria_id' => $c->id, 'seccion' => 'oposicion', 'nombre' => 'Prueba técnica',
            'puntaje_maximo' => 30, 'tipo_input' => 'numero', 'orden' => 1, 'activo' => true,
        ]);
        $op = fn ($cri, $etiqueta, $puntaje, $orden) => OpcionCriterio::create([
            'criterio_id' => $cri->id, 'etiqueta' => $etiqueta, 'puntaje' => $puntaje, 'orden' => $orden,
        ]);

        return [
            'radio' => $radio, 'checklist' => $checklist, 'numero' => $numero,
            'cuarto' => $op($radio, 'Cuarto nivel', 40, 1), 'tercero' => $op($radio, 'Tercer nivel', 25, 2),
            'curso1' => $op($checklist, 'Curso 1', 15, 1), 'curso2' => $op($checklist, 'Curso 2', 15, 2),
            'curso3' => $op($checklist, 'Curso 3', 15, 3),
        ];
    };

    $this->k = ($this->criterios)($this->convocatoria);
    $this->convocatoria->update(['estado' => 'publicada']);

    $this->postulante = Postulante::create([
        'convocatoria_id' => $this->convocatoria->id, 'cedula' => '1780000001',
        'nombres' => 'Ana', 'apellidos' => 'Calificada', 'correo' => 'ana@test.ec',
        'genero' => 'femenino', 'fecha_inscripcion' => '2026-09-05', 'estado' => EstadoPostulante::INSCRITO,
    ]);

    $this->url = fn (?Postulante $p = null) => "/api/v1/seleccion/convocatorias/{$this->convocatoria->id}/postulantes/"
        .($p ?? $this->postulante)->id.'/calificaciones';

    // Una calificación completa; cada prueba cambia lo que necesita.
    $this->items = fn (array $cambios = []) => array_replace_recursive([
        0 => ['criterio_id' => $this->k['radio']->id, 'opcion_id' => $this->k['cuarto']->id],
        1 => ['criterio_id' => $this->k['checklist']->id, 'opcion_ids' => [$this->k['curso1']->id, $this->k['curso2']->id]],
        2 => ['criterio_id' => $this->k['numero']->id, 'valor_numerico' => 20],
    ], $cambios);
});

test('el checklist guarda todas las opciones marcadas y el total sale de lo guardado', function () {
    $this->postJson(($this->url)(), ['calificaciones' => ($this->items)()])
        ->assertOk()
        ->assertJsonPath('datos.puntaje_total', '90.00');

    $checklist = CalificacionPostulante::where('postulante_id', $this->postulante->id)
        ->where('criterio_id', $this->k['checklist']->id)->firstOrFail();
    expect($checklist->opciones()->pluck('seleccion_opciones.id')->sort()->values()->all())
        ->toBe([$this->k['curso1']->id, $this->k['curso2']->id])
        ->and($this->postulante->fresh()->estado)->toBe(EstadoPostulante::APROBADO);

    // Al volver a abrirla, las dos siguen marcadas.
    $this->getJson(($this->url)())->assertOk()
        ->assertJsonCount(2, "datos.calificaciones.{$this->k['checklist']->id}.opciones");
});

test('el checklist no pasa del máximo del criterio', function () {
    $todas = [$this->k['curso1']->id, $this->k['curso2']->id, $this->k['curso3']->id];

    $this->postJson(($this->url)(), ['calificaciones' => ($this->items)([1 => ['opcion_ids' => $todas]])])
        ->assertOk()
        ->assertJsonPath('datos.puntaje_total', '90.00'); // 40 + 30 (no 45) + 20
});

test('bajo 70 reprueba', function () {
    $this->postJson(($this->url)(), ['calificaciones' => ($this->items)([
        0 => ['opcion_id' => $this->k['tercero']->id], 2 => ['valor_numerico' => 10],
    ])])->assertOk()->assertJsonPath('datos.puntaje_total', '65.00');

    expect($this->postulante->fresh()->estado)->toBe(EstadoPostulante::REPROBADO);
});

test('no acepta criterios ni opciones de otra convocatoria', function () {
    $otra = Convocatoria::create([
        'puesto_id' => $this->puesto->id, 'codigo' => 'CNV-CAL-2', 'titulo' => 'Otra',
        'descripcion' => 'Otra', 'tipo' => 'externa', 'tipo_proceso' => 'formal',
        'estado' => 'borrador', 'vacantes' => 1, 'fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-09-20',
    ]);
    $ajenos = ($this->criterios)($otra);

    $this->postJson(($this->url)(), ['calificaciones' => ($this->items)([0 => ['opcion_id' => $ajenos['cuarto']->id]])])
        ->assertStatus(422)->assertJsonValidationErrors(['calificaciones.0.opcion_id'], 'errores');

    $this->postJson(($this->url)(), ['calificaciones' => ($this->items)([1 => ['opcion_ids' => [$ajenos['curso1']->id]]])])
        ->assertStatus(422)->assertJsonValidationErrors(['calificaciones.1.opcion_ids'], 'errores');

    $items = ($this->items)();
    $items[2] = ['criterio_id' => $ajenos['numero']->id, 'valor_numerico' => 30];
    $this->postJson(($this->url)(), ['calificaciones' => $items])
        ->assertStatus(422)->assertJsonValidationErrors(['calificaciones.2.criterio_id'], 'errores');

    expect(CalificacionPostulante::count())->toBe(0);
});

test('un criterio repetido no suma dos veces', function () {
    $items = ($this->items)();
    $items[] = $items[2];

    $this->postJson(($this->url)(), ['calificaciones' => $items])
        ->assertStatus(422)->assertJsonValidationErrors(['calificaciones.3.criterio_id'], 'errores');
});

test('faltar un criterio o pasarse del máximo es un error, no un recorte', function () {
    $this->postJson(($this->url)(), ['calificaciones' => array_slice(($this->items)(), 0, 2)])
        ->assertStatus(422)->assertJsonValidationErrors(['calificaciones'], 'errores');

    $this->postJson(($this->url)(), ['calificaciones' => ($this->items)([2 => ['valor_numerico' => 45]])])
        ->assertStatus(422)->assertJsonValidationErrors(['calificaciones.2.valor_numerico'], 'errores');
});

test('con criterios que no suman 100 no se califica ni se publica', function () {
    $this->k['numero']->update(['puntaje_maximo' => 20]);

    $this->postJson(($this->url)(), ['calificaciones' => ($this->items)()])
        ->assertStatus(422)->assertJsonPath('mensaje', fn ($m) => str_contains($m, 'suman 90'));

    $this->convocatoria->update(['estado' => 'borrador']);
    $this->patchJson("/api/v1/seleccion/convocatorias/{$this->convocatoria->id}/publicar")
        ->assertStatus(422)->assertJsonPath('mensaje', fn ($m) => str_contains($m, 'deben sumar 100'));
});

describe('criterios', function () {
    test('publicada, los criterios ya no se tocan', function () {
        $base = "/api/v1/seleccion/convocatorias/{$this->convocatoria->id}/criterios";

        $this->postJson($base, ['seccion' => 'meritos', 'nombre' => 'Otro', 'puntaje_maximo' => 5, 'tipo_input' => 'numero'])
            ->assertStatus(422);
        $this->patchJson("{$base}/{$this->k['numero']->id}", ['puntaje_maximo' => 10])->assertStatus(422);
        $this->deleteJson("{$base}/{$this->k['numero']->id}")->assertStatus(422);
    });

    test('en borrador: no pasan de 100 y una opción no vale más que su criterio', function () {
        $this->convocatoria->update(['estado' => 'borrador']);
        $base = "/api/v1/seleccion/convocatorias/{$this->convocatoria->id}/criterios";

        $this->postJson($base, ['seccion' => 'meritos', 'nombre' => 'Extra', 'puntaje_maximo' => 5, 'tipo_input' => 'numero'])
            ->assertStatus(422)->assertJsonValidationErrors(['puntaje_maximo'], 'errores');

        $this->k['numero']->update(['puntaje_maximo' => 20]);
        $this->postJson($base, [
            'seccion' => 'oposicion', 'nombre' => 'Entrevista', 'puntaje_maximo' => 10, 'tipo_input' => 'radio',
            'opciones' => [['etiqueta' => 'Excelente', 'puntaje' => 15]],
        ])->assertStatus(422)->assertJsonValidationErrors(['opciones.0.puntaje'], 'errores');

        $this->postJson($base, ['seccion' => 'oposicion', 'nombre' => 'Entrevista', 'puntaje_maximo' => 10, 'tipo_input' => 'radio'])
            ->assertStatus(422)->assertJsonValidationErrors(['opciones'], 'errores');
    });

    test('en express, reaplicar la plantilla retira los criterios usados y conserva sus calificaciones', function () {
        $contenedor = Convocatoria::create([
            'puesto_id' => null, 'codigo' => 'EXP-CAL', 'titulo' => 'Servicios ocasionales',
            'descripcion' => 'Express', 'tipo' => 'externa', 'tipo_proceso' => 'express',
            'tipo_nombramiento_previsto' => 'servicios_ocasionales',
            'estado' => 'publicada', 'vacantes' => 1, 'es_contenedor_permanente' => true,
            'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-01-01',
        ]);
        $k = ($this->criterios)($contenedor);
        $aspirante = Postulante::create([
            'convocatoria_id' => $contenedor->id, 'puesto_id' => $this->puesto->id, 'cedula' => '1780000002',
            'nombres' => 'Luis', 'apellidos' => 'Express', 'correo' => 'luis@test.ec',
            'genero' => 'masculino', 'fecha_inscripcion' => '2025-03-01', 'estado' => EstadoPostulante::INSCRITO,
        ]);

        $this->postJson("/api/v1/seleccion/convocatorias/{$contenedor->id}/postulantes/{$aspirante->id}/calificaciones", [
            'calificaciones' => [
                ['criterio_id' => $k['radio']->id, 'opcion_id' => $k['cuarto']->id],
                ['criterio_id' => $k['checklist']->id, 'opcion_ids' => []],
                ['criterio_id' => $k['numero']->id, 'valor_numerico' => 30],
            ],
        ])->assertOk()->assertJsonPath('datos.puntaje_total', '70.00');

        $plantilla = PlantillaEvaluacion::create(['nombre' => 'Nueva', 'descripcion' => 'x', 'activa' => true]);
        $this->postJson("/api/v1/seleccion/plantillas/{$plantilla->id}/aplicar/{$contenedor->id}")->assertOk();

        expect(CalificacionPostulante::where('postulante_id', $aspirante->id)->count())->toBe(3)
            ->and(CriterioEvaluacion::where('convocatoria_id', $contenedor->id)->where('activo', true)->count())->toBe(0)
            ->and(CriterioEvaluacion::where('convocatoria_id', $contenedor->id)->count())->toBe(3);
    });
});

test('se corrige mientras el puntaje decide algo; después ya no', function (EstadoPostulante $avanzado) {
    $this->postJson(($this->url)(), ['calificaciones' => ($this->items)()])->assertOk();

    // Aprobado: el trámite no avanzó, corregir un error de digitación sigue siendo posible.
    $this->postJson(($this->url)(), ['calificaciones' => ($this->items)([2 => ['valor_numerico' => 0]])])
        ->assertOk()->assertJsonPath('datos.puntaje_total', '70.00');

    // Enviado al Dispensario o incorporado: recalificarlo lo devolvía en
    // silencio a «aprobado» y se perdía el despacho.
    $this->postulante->update(['estado' => $avanzado]);
    $this->postJson(($this->url)(), ['calificaciones' => ($this->items)()])->assertStatus(422);

    expect($this->postulante->fresh()->estado)->toBe($avanzado);
})->with([EstadoPostulante::GANADOR_POTENCIAL, EstadoPostulante::INCORPORADO]);

test('el endpoint que ponía méritos y oposición a mano ya no existe', function () {
    $this->postJson("/api/v1/seleccion/postulantes/{$this->postulante->id}/calificar", [
        'puntaje_meritos' => 50, 'puntaje_oposicion' => 50,
    ])->assertNotFound();
});
