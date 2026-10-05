<?php

use App\Enums\EstadoPostulante;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Seleccion\Convocatoria;
use App\Models\Seleccion\CriterioEvaluacion;
use App\Models\Seleccion\Postulante;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
 * Quién hace qué en Reclutamiento (decisión 6 de TH, 2026-10-05). Antes las
 * rutas solo miraban el rol: el analista de la UATH creaba, publicaba,
 * borraba y declaraba ganadores igual que admin-uath. Ahora ve y califica.
 */

beforeEach(function () {
    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $this->usuario = function (string $rol): User {
        $u = User::factory()->create();
        $u->assignRole($rol);

        return $u;
    };

    $unidad = UnidadAdministrativa::create(['codigo' => 'PER-01', 'nombre' => 'Unidad Permisos', 'nivel' => 1]);
    $puesto = Puesto::create(['unidad_administrativa_id' => $unidad->id, 'plazas' => 2, 'regimen_laboral' => 'losep', 'activo' => true]);

    $this->convocatoria = Convocatoria::create([
        'puesto_id' => $puesto->id, 'codigo' => 'CNV-PER-1', 'titulo' => 'Analista',
        'descripcion' => 'Concurso', 'tipo' => 'externa', 'tipo_proceso' => 'formal',
        'estado' => 'publicada', 'vacantes' => 1, 'fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-09-20',
    ]);
    $this->criterio = CriterioEvaluacion::create([
        'convocatoria_id' => $this->convocatoria->id, 'seccion' => 'oposicion', 'nombre' => 'Prueba',
        'puntaje_maximo' => 100, 'tipo_input' => 'numero', 'orden' => 1, 'activo' => true,
    ]);
    $this->postulante = Postulante::create([
        'convocatoria_id' => $this->convocatoria->id, 'cedula' => '1790000001', 'nombres' => 'Ana',
        'apellidos' => 'Permisos', 'correo' => 'ana@test.ec', 'fecha_inscripcion' => '2026-09-05',
        'estado' => EstadoPostulante::INSCRITO,
    ]);

    $this->base = "/api/v1/seleccion/convocatorias/{$this->convocatoria->id}";
});

test('el analista ve y califica', function () {
    $this->actingAs(($this->usuario)('analista-uath'), 'sanctum');

    $this->getJson('/api/v1/seleccion/convocatorias')->assertOk();
    $this->getJson($this->base)->assertOk();
    $this->getJson("{$this->base}/postulantes")->assertOk();
    $this->getJson("{$this->base}/criterios")->assertOk();

    $this->postJson("{$this->base}/postulantes/{$this->postulante->id}/calificaciones", [
        'calificaciones' => [['criterio_id' => $this->criterio->id, 'valor_numerico' => 80]],
    ])->assertOk();
});

test('el analista no crea, edita, publica, cierra, inscribe ni declara', function (string $metodo, string $ruta, array $datos = []) {
    $this->actingAs(($this->usuario)('analista-uath'), 'sanctum');

    $ruta = str_replace(['{base}', '{p}'], [$this->base, $this->postulante->id], $ruta);
    $this->json($metodo, $ruta, $datos)->assertForbidden();
})->with([
    'crear'              => ['POST', '/api/v1/seleccion/convocatorias'],
    'editar'             => ['PATCH', '{base}', ['titulo' => 'X']],
    'borrar'             => ['DELETE', '{base}'],
    'publicar'           => ['PATCH', '{base}/publicar'],
    'cerrar'             => ['POST', '{base}/cerrar', ['estado' => 'cancelada', 'motivo' => 'Prueba de permisos']],
    'inscribir'          => ['POST', '{base}/postulantes'],
    'borrar candidato'   => ['DELETE', '{base}/postulantes/{p}'],
    'agregar criterio'   => ['POST', '{base}/criterios'],
    'declarar ganadores' => ['POST', '{base}/declarar-ganador', ['postulante_ganador_ids' => [1]]],
    'declarar siguiente' => ['POST', '{base}/declarar-siguiente'],
    'crear plantilla'    => ['POST', '/api/v1/seleccion/plantillas'],
]);

test('admin-uath gestiona', function () {
    $this->actingAs(($this->usuario)('admin-uath'), 'sanctum');

    $this->postJson("{$this->base}/cerrar", ['estado' => 'cancelada', 'motivo' => 'Prueba de permisos'])->assertOk();
});

test('quien no es de la UATH no entra ni a leer', function () {
    $this->actingAs(($this->usuario)('servidor'), 'sanctum');

    $this->getJson('/api/v1/seleccion/convocatorias')->assertForbidden();
    $this->getJson('/api/v1/seleccion/express/resumen')->assertForbidden();
});
