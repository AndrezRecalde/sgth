<?php

namespace Tests\Feature\Seleccion;

use App\Enums\EstadoPostulante;
use App\Enums\TipoNombramiento;
use App\Models\Estructura\Cargo;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Seleccion\Convocatoria;
use App\Models\Seleccion\Postulante;
use App\Models\User;
use Database\Seeders\ContenedorExpressSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);

    $this->user = User::factory()->create();
    $this->user->assignRole('admin-uath');
    $this->actingAs($this->user, 'sanctum');

    $this->seed(ContenedorExpressSeeder::class);

    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'UATH-01', 'nombre' => 'Unidad de Talento Humano', 'nivel' => 1,
    ]);

    $cargo = Cargo::create(['nombre' => 'Analista', 'clasificacion_personal' => 'contratado']);

    $this->puesto = Puesto::create([
        'unidad_administrativa_id' => $this->unidad->id,
        'cargo_id' => $cargo->id,
        'plazas' => 30,
        'rmu' => 1200,
    ]);

    $this->profesionales = Convocatoria::where('codigo', 'EXP-PROFESIONALES')->firstOrFail();

    $this->contador = 0;

    $this->inscribir = function (Convocatoria $contenedor, string $fecha, array $extra = []) {
        $this->contador++;

        return $this->postJson("/api/v1/seleccion/convocatorias/{$contenedor->id}/postulantes", [
            'puesto_id'         => $this->puesto->id,
            'fecha_inscripcion' => $fecha,
            'cedula'            => str_pad((string) (1700000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombres'           => 'Aspirante',
            'apellidos'         => 'Express'.$this->contador,
            'correo'            => "aspirante{$this->contador}@test.ec",
            ...$extra,
        ]);
    };
});

// ── Los contenedores ────────────────────────────────────────────

test('existen cuatro contenedores permanentes, uno por modalidad', function () {
    $contenedores = Convocatoria::where('es_contenedor_permanente', true)->get();

    expect($contenedores)->toHaveCount(4)
        ->and($contenedores->pluck('tipo_nombramiento_previsto')->map->value->sort()->values()->all())
        ->toBe([
            TipoNombramiento::CODIGO_TRABAJO->value,
            TipoNombramiento::PROVISIONAL->value,
            TipoNombramiento::SERVICIOS_OCASIONALES->value,
            TipoNombramiento::SERVICIOS_PROFESIONALES->value,
        ]);

    expect($contenedores->every(fn ($c) => $c->puesto_id === null))->toBeTrue();
});

test('el seeder es idempotente: no duplica contenedores', function () {
    $this->seed(ContenedorExpressSeeder::class);
    $this->seed(ContenedorExpressSeeder::class);

    expect(Convocatoria::where('es_contenedor_permanente', true)->count())->toBe(4);
});

test('una convocatoria normal sigue exigiendo puesto', function () {
    expect(fn () => Convocatoria::create([
        'codigo' => 'CONV-SIN-PUESTO', 'titulo' => 'Sin puesto',
        'descripcion' => 'x', 'tipo' => 'externa', 'tipo_proceso' => 'formal',
        'estado' => 'borrador', 'vacantes' => 1,
        'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-02-01',
        'puesto_id' => null,
    ]))->toThrow(QueryException::class);
});

// ── Inscripción de aspirantes ───────────────────────────────────

test('el aspirante trae su propio puesto y su fecha de inscripción', function () {
    ($this->inscribir)($this->profesionales, '2026-04-10')->assertStatus(201);

    $aspirante = Postulante::where('convocatoria_id', $this->profesionales->id)->firstOrFail();

    expect($aspirante->puesto_id)->toBe($this->puesto->id)
        ->and($aspirante->fecha_inscripcion->toDateString())->toBe('2026-04-10')
        ->and($aspirante->puestoEfectivo()->id)->toBe($this->puesto->id);
});

test('en un contenedor el puesto del aspirante es obligatorio', function () {
    // ApiResponse::error() expone los errores de validación bajo 'errores'.
    $this->postJson("/api/v1/seleccion/convocatorias/{$this->profesionales->id}/postulantes", [
        'cedula' => '1700000099', 'nombres' => 'Sin', 'apellidos' => 'Puesto',
        'correo' => 'sin@test.ec',
    ])->assertStatus(422)->assertJsonStructure(['errores' => ['puesto_id']]);
});

test('la misma persona puede volver a inscribirse en otro año', function () {
    $cedula = '1712345678';

    ($this->inscribir)($this->profesionales, '2026-04-10', ['cedula' => $cedula])->assertStatus(201);
    ($this->inscribir)($this->profesionales, '2027-02-05', ['cedula' => $cedula])->assertStatus(201);

    expect(Postulante::where('cedula', $cedula)->count())->toBe(2);
});

test('la misma persona no puede inscribirse dos veces el mismo año', function () {
    $cedula = '1712345679';

    ($this->inscribir)($this->profesionales, '2026-04-10', ['cedula' => $cedula])->assertStatus(201);

    ($this->inscribir)($this->profesionales, '2026-09-01', ['cedula' => $cedula])
        ->assertStatus(422);
});

// ── Resumen por modalidad ───────────────────────────────────────

test('el resumen devuelve las cuatro tarjetas aunque no haya aspirantes', function () {
    $respuesta = $this->getJson('/api/v1/seleccion/express/resumen');

    $respuesta->assertOk();

    expect($respuesta->json('datos.contenedores'))->toHaveCount(4);
});

test('el resumen cuenta aspirantes y aprobados por modalidad', function () {
    ($this->inscribir)($this->profesionales, '2026-04-10');
    ($this->inscribir)($this->profesionales, '2026-05-11');
    ($this->inscribir)($this->profesionales, '2026-06-12');

    Postulante::where('convocatoria_id', $this->profesionales->id)
        ->limit(2)->get()
        ->each(fn ($p) => $p->update(['estado' => EstadoPostulante::APROBADO]));

    $respuesta = $this->getJson('/api/v1/seleccion/express/resumen');

    $tarjeta = collect($respuesta->json('datos.contenedores'))
        ->firstWhere('codigo', 'EXP-PROFESIONALES');

    expect($tarjeta['total_aspirantes'])->toBe(3)
        ->and($tarjeta['aprobados'])->toBe(2)
        ->and($tarjeta['pendientes'])->toBe(1);
});

test('el resumen filtra por año', function () {
    ($this->inscribir)($this->profesionales, '2026-04-10');
    ($this->inscribir)($this->profesionales, '2027-04-10');
    ($this->inscribir)($this->profesionales, '2027-08-20');

    $tarjeta2027 = collect($this->getJson('/api/v1/seleccion/express/resumen?anio=2027')->json('datos.contenedores'))
        ->firstWhere('codigo', 'EXP-PROFESIONALES');

    expect($tarjeta2027['total_aspirantes'])->toBe(2);
});

test('el resumen filtra por rango de años', function () {
    ($this->inscribir)($this->profesionales, '2025-04-10');
    ($this->inscribir)($this->profesionales, '2026-04-10');
    ($this->inscribir)($this->profesionales, '2027-04-10');
    ($this->inscribir)($this->profesionales, '2028-04-10');

    $tarjeta = collect(
        $this->getJson('/api/v1/seleccion/express/resumen?anio_desde=2026&anio_hasta=2027')
            ->json('datos.contenedores')
    )->firstWhere('codigo', 'EXP-PROFESIONALES');

    expect($tarjeta['total_aspirantes'])->toBe(2);
});

// ── Listado de aspirantes ───────────────────────────────────────

test('se listan los aspirantes de una modalidad filtrados por año', function () {
    ($this->inscribir)($this->profesionales, '2026-04-10');
    ($this->inscribir)($this->profesionales, '2027-04-10');

    $respuesta = $this->getJson(
        "/api/v1/seleccion/express/{$this->profesionales->id}/aspirantes?anio=2026"
    );

    $respuesta->assertOk();

    expect($respuesta->json('datos.data'))->toHaveCount(1)
        ->and($respuesta->json('datos.data.0.fecha_inscripcion'))->toContain('2026');
});

test('el listado rechaza una convocatoria que no es contenedor', function () {
    $convocatoria = Convocatoria::create([
        'codigo' => 'CONV-FORMAL-1', 'titulo' => 'Concurso formal',
        'descripcion' => 'x', 'tipo' => 'externa', 'tipo_proceso' => 'formal',
        'estado' => 'publicada', 'vacantes' => 1,
        'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-02-01',
        'puesto_id' => $this->puesto->id,
    ]);

    $this->getJson("/api/v1/seleccion/express/{$convocatoria->id}/aspirantes")
        ->assertStatus(404);
});

test('los años disponibles salen de las inscripciones existentes', function () {
    ($this->inscribir)($this->profesionales, '2026-04-10');
    ($this->inscribir)($this->profesionales, '2028-01-15');

    $respuesta = $this->getJson('/api/v1/seleccion/express/anios');

    expect($respuesta->json('datos'))->toBe([2028, 2026]);
});

// ── Concurso formal intacto ─────────────────────────────────────

test('en un concurso formal el puesto del aspirante queda prohibido', function () {
    $convocatoria = Convocatoria::create([
        'codigo' => 'CONV-FORMAL-2', 'titulo' => 'Concurso formal',
        'descripcion' => 'x', 'tipo' => 'externa', 'tipo_proceso' => 'formal',
        'estado' => 'publicada', 'vacantes' => 1,
        'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-02-01',
        'puesto_id' => $this->puesto->id,
    ]);

    $this->postJson("/api/v1/seleccion/convocatorias/{$convocatoria->id}/postulantes", [
        'puesto_id' => $this->puesto->id,
        'cedula'    => '1799999999',
        'nombres'   => 'Formal', 'apellidos' => 'Test',
        'correo'    => 'formal@test.ec',
    ])->assertStatus(422)->assertJsonStructure(['errores' => ['puesto_id']]);
});
