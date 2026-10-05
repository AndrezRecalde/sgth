<?php

use App\Enums\EstadoPostulante;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Seleccion\Convocatoria;
use App\Models\Seleccion\Onboarding;
use App\Models\Seleccion\PlantillaEvaluacion;
use App\Models\Seleccion\Postulante;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
 * Lo que el perfil del candidato puede hacer (decisiones de TH, 2026-10-05):
 * corregir sus datos, eliminar una inscripción equivocada y llevar el
 * checklist de inducción del incorporado. Y la plantilla ya no se edita.
 */

beforeEach(function () {
    $this->seed(\Database\Seeders\RolPermisoSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin-uath');
    $this->actingAs($this->admin, 'sanctum');

    $unidad = UnidadAdministrativa::create(['codigo' => 'PERF-01', 'nombre' => 'Unidad Perfil', 'nivel' => 1]);
    $puesto = Puesto::create(['unidad_administrativa_id' => $unidad->id, 'plazas' => 2, 'regimen_laboral' => 'losep', 'activo' => true]);
    $this->convocatoria = Convocatoria::create([
        'puesto_id' => $puesto->id, 'codigo' => 'CNV-PERF-1', 'titulo' => 'Analista', 'descripcion' => 'Concurso',
        'tipo' => 'externa', 'tipo_proceso' => 'formal', 'estado' => 'publicada', 'vacantes' => 1,
        'fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-09-20',
    ]);
    $this->candidato = fn (string $cedula, EstadoPostulante $estado = EstadoPostulante::INSCRITO) => Postulante::create([
        'convocatoria_id' => $this->convocatoria->id, 'cedula' => $cedula, 'nombres' => 'Ana', 'apellidos' => 'Perfil',
        'correo' => "{$cedula}@test.ec", 'genero' => 'femenino', 'fecha_inscripcion' => '2026-09-05', 'estado' => $estado,
    ]);
    $this->url = fn (Postulante $p) => "/api/v1/seleccion/convocatorias/{$this->convocatoria->id}/postulantes/{$p->id}";
});

describe('corregir datos', function () {
    test('se corrigen todos los datos personales, también la cédula mientras no avanzó', function () {
        $p = ($this->candidato)('1790000001');

        $this->patchJson(($this->url)($p), [
            'cedula' => '1790000009', 'segundo_nombre' => 'María', 'genero' => 'otro', 'tipo_sangre' => 'O+',
        ])->assertOk();

        $p->refresh();
        expect($p->cedula)->toBe('1790000009')
            ->and($p->segundo_nombre)->toBe('María')
            ->and($p->genero)->toBe('otro')
            ->and($p->tipo_sangre)->toBe('O+');
    });

    test('una cédula que ya está inscrita vuelve a su campo', function () {
        ($this->candidato)('1790000001');
        $p = ($this->candidato)('1790000002');

        $this->patchJson(($this->url)($p), ['cedula' => '1790000001'])
            ->assertStatus(422)->assertJsonValidationErrors(['cedula'], 'errores');
    });

    test('enviado al Dispensario, la cédula ya no cambia; incorporado, nada', function () {
        $enviado = ($this->candidato)('1790000001', EstadoPostulante::GANADOR_POTENCIAL);
        $this->patchJson(($this->url)($enviado), ['cedula' => '1790000009'])
            ->assertStatus(422)->assertJsonValidationErrors(['cedula'], 'errores');
        // El teléfono sí: es un dato de contacto.
        $this->patchJson(($this->url)($enviado), ['telefono' => '0991112233'])->assertOk();

        $incorporado = ($this->candidato)('1790000002', EstadoPostulante::INCORPORADO);
        $this->patchJson(($this->url)($incorporado), ['telefono' => '0991112233'])->assertStatus(422);
    });

    test('el analista no corrige datos', function () {
        $p = ($this->candidato)('1790000001');
        $analista = User::factory()->create();
        $analista->assignRole('analista-uath');

        $this->actingAs($analista, 'sanctum')->patchJson(($this->url)($p), ['telefono' => '0991112233'])->assertForbidden();
    });
});

describe('inducción', function () {
    test('quien gestiona la incorporación marca el checklist', function () {
        $p = ($this->candidato)('1790000001', EstadoPostulante::INCORPORADO);
        $o = Onboarding::create(['postulante_id' => $p->id, 'created_by' => $this->admin->id]);

        $this->patchJson("/api/v1/seleccion/onboardings/{$o->id}", [
            'documentacion_entregada' => true, 'induccion_completada' => true, 'observaciones' => 'Recibió el reglamento.',
        ])->assertOk();

        $o->refresh();
        expect($o->documentacion_entregada)->toBeTrue()
            ->and($o->induccion_completada)->toBeTrue()
            ->and($o->contrato_firmado)->toBeFalse()
            ->and($o->observaciones)->toBe('Recibió el reglamento.');

        // Llega con el listado, para el perfil.
        $this->getJson("/api/v1/seleccion/convocatorias/{$this->convocatoria->id}/postulantes")
            ->assertJsonPath('datos.0.onboarding.induccion_completada', true);
    });

    test('el analista no la marca', function () {
        $p = ($this->candidato)('1790000001', EstadoPostulante::INCORPORADO);
        $o = Onboarding::create(['postulante_id' => $p->id, 'created_by' => $this->admin->id]);
        $analista = User::factory()->create();
        $analista->assignRole('analista-uath');

        $this->actingAs($analista, 'sanctum')
            ->patchJson("/api/v1/seleccion/onboardings/{$o->id}", ['induccion_completada' => true])
            ->assertForbidden();
    });
});

test('la plantilla ya no se edita y el candidato ya no se pide suelto', function () {
    $plantilla = PlantillaEvaluacion::create(['nombre' => 'P', 'activa' => true]);
    $this->patchJson("/api/v1/seleccion/plantillas/{$plantilla->id}", ['nombre' => 'Q'])->assertMethodNotAllowed();

    $p = ($this->candidato)('1790000001');
    $this->getJson(($this->url)($p))->assertMethodNotAllowed();
});
