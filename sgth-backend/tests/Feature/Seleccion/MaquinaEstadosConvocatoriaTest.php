<?php

use App\Enums\EstadoConvocatoria;
use App\Enums\EstadoPostulante;
use App\Models\Dispensario\SolicitudCertificacionMedica;
use App\Models\Estructura\GrupoOcupacional;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Seleccion\Convocatoria;
use App\Models\Seleccion\Postulante;
use App\Models\User;
use App\Services\Seleccion\SeleccionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
 * La máquina de estados de la convocatoria (decisión de TH, 2026-10-05).
 * Antes el PATCH aceptaba cualquier estado —dos que no existen daban 500— y
 * una finalizada podía volver a borrador y borrarse.
 */

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->assignRole(Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']));
    $this->actingAs($this->user, 'sanctum');

    $unidad = UnidadAdministrativa::create(['codigo' => 'MAQ-01', 'nombre' => 'Unidad Máquina', 'nivel' => 1]);
    $grupo = GrupoOcupacional::create([
        'grado_codigo' => 'SP4', 'grado_numerico' => 4, 'grupo' => 'Profesional',
        'denominacion_generica' => 'Analista', 'rmu' => 1212.00, 'regimen' => 'losep', 'activo' => true,
    ]);
    $this->puesto = Puesto::create([
        'unidad_administrativa_id' => $unidad->id, 'grupo_ocupacional_id' => $grupo->id,
        'plazas' => 5, 'regimen_laboral' => 'losep', 'activo' => true,
    ]);

    $this->convocatoria = fn (string $estado = 'borrador', array $extra = []) => Convocatoria::create([
        'puesto_id' => $this->puesto->id, 'codigo' => 'CNV-MAQ-'.uniqid(), 'titulo' => 'Analista',
        'descripcion' => 'Concurso', 'tipo' => 'externa', 'tipo_proceso' => 'formal',
        'estado' => $estado, 'vacantes' => 1,
        'fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-09-20', ...$extra,
    ]);

    $this->contador = 0;
    $this->postulante = function (Convocatoria $c, EstadoPostulante $estado) {
        $this->contador++;

        return Postulante::create([
            'convocatoria_id' => $c->id,
            'puesto_id' => $c->es_contenedor_permanente ? $this->puesto->id : null,
            'cedula' => '17700000'.str_pad((string) $this->contador, 2, '0', STR_PAD_LEFT),
            'nombres' => 'Candidato', 'apellidos' => 'Maq'.$this->contador,
            'correo' => "maq{$this->contador}@test.ec", 'genero' => 'femenino',
            'fecha_inscripcion' => '2026-09-05', 'estado' => $estado,
        ]);
    };

    $this->contenedor = fn () => Convocatoria::create([
        'puesto_id' => null, 'codigo' => 'EXP-MAQ', 'titulo' => 'Servicios ocasionales',
        'descripcion' => 'Express', 'tipo' => 'externa', 'tipo_proceso' => 'express',
        'tipo_nombramiento_previsto' => 'servicios_ocasionales',
        'estado' => 'publicada', 'vacantes' => 1, 'es_contenedor_permanente' => true,
        'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-01-01',
    ]);

    $this->url = fn (Convocatoria $c, string $sufijo = '') => "/api/v1/seleccion/convocatorias/{$c->id}{$sufijo}";
});

describe('editar', function () {
    test('en borrador se edita, pero el estado no cambia aunque se envíe', function () {
        $c = ($this->convocatoria)();

        $this->patchJson(($this->url)($c), ['titulo' => 'Otro título', 'estado' => 'finalizada', 'vacantes' => 3])
            ->assertOk();

        $c->refresh();
        expect($c->titulo)->toBe('Otro título')
            ->and($c->vacantes)->toBe(3)
            ->and($c->estado)->toBe(EstadoConvocatoria::BORRADOR);
    });

    test('publicada ya no se edita', function () {
        $c = ($this->convocatoria)('publicada');

        $this->patchJson(($this->url)($c), ['vacantes' => 5])
            ->assertStatus(422)
            ->assertJsonPath('mensaje', fn ($m) => str_contains($m, 'Solo se edita una convocatoria en borrador'));

        expect($c->fresh()->vacantes)->toBe(1);
    });

    test('los estados que no existen ya no llegan a la base como 500', function () {
        $c = ($this->convocatoria)('finalizada');

        // Antes: `cerrada` daba 500 por el CHECK; `borrador` la reabría.
        $this->patchJson(($this->url)($c), ['estado' => 'borrador'])->assertStatus(422);
        expect($c->fresh()->estado)->toBe(EstadoConvocatoria::FINALIZADA);
    });

    test('la fecha de cierre se compara con la guardada si solo llega una', function () {
        $c = ($this->convocatoria)();

        $this->patchJson(($this->url)($c), ['fecha_fin' => '2026-08-15'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fecha_fin'], 'errores');
    });

    test('un contenedor express no se edita, publica ni borra', function () {
        $c = ($this->contenedor)();

        $this->patchJson(($this->url)($c), ['titulo' => 'X'])->assertStatus(422);
        $this->patchJson(($this->url)($c, '/publicar'))->assertStatus(422);
        $this->deleteJson(($this->url)($c))->assertStatus(422);
        $this->postJson(($this->url)($c, '/cerrar'), ['estado' => 'cancelada', 'motivo' => 'Una prueba cualquiera'])
            ->assertStatus(422);

        expect(Convocatoria::find($c->id))->not->toBeNull();
    });
});

describe('cerrar sin ganadores', function () {
    test('cancelar exige motivo, lo guarda y saca de carrera a los que seguían', function () {
        $c = ($this->convocatoria)('publicada');
        $aprobado = ($this->postulante)($c, EstadoPostulante::APROBADO);
        $reprobado = ($this->postulante)($c, EstadoPostulante::REPROBADO);

        $this->postJson(($this->url)($c, '/cerrar'), ['estado' => 'cancelada'])
            ->assertStatus(422)->assertJsonValidationErrors(['motivo'], 'errores');

        $this->postJson(($this->url)($c, '/cerrar'), [
            'estado' => 'cancelada', 'motivo' => 'Se suprimió la partida presupuestaria.',
        ])->assertOk();

        $c->refresh();
        expect($c->estado)->toBe(EstadoConvocatoria::CANCELADA)
            ->and($c->motivo_cierre)->toBe('Se suprimió la partida presupuestaria.')
            ->and($aprobado->fresh()->estado)->toBe(EstadoPostulante::NO_SELECCIONADO)
            ->and($reprobado->fresh()->estado)->toBe(EstadoPostulante::REPROBADO);
    });

    test('desierta no cabe con aprobados: corresponde declararlos ganadores', function () {
        $c = ($this->convocatoria)('publicada');
        ($this->postulante)($c, EstadoPostulante::APROBADO);

        $this->postJson(($this->url)($c, '/cerrar'), [
            'estado' => 'desierta', 'motivo' => 'Nadie alcanzó el puntaje mínimo.',
        ])->assertStatus(422)->assertJsonPath('mensaje', fn ($m) => str_contains($m, 'aprobado'));
    });

    test('desierta sin aprobados', function () {
        $c = ($this->convocatoria)('publicada');
        ($this->postulante)($c, EstadoPostulante::REPROBADO);

        $this->postJson(($this->url)($c, '/cerrar'), [
            'estado' => 'desierta', 'motivo' => 'Nadie alcanzó el puntaje mínimo.',
        ])->assertOk();

        expect($c->fresh()->estado)->toBe(EstadoConvocatoria::DESIERTA);
    });

    test('solo se cierra una publicada', function (string $estado) {
        $c = ($this->convocatoria)($estado);

        $this->postJson(($this->url)($c, '/cerrar'), ['estado' => 'cancelada', 'motivo' => 'Una prueba cualquiera'])
            ->assertStatus(422);

        expect($c->fresh()->estado->value)->toBe($estado);
    })->with(['borrador', 'en_evaluacion_medica', 'finalizada', 'desierta']);
});

describe('una convocatoria cerrada no admite nada', function () {
    test('ni inscripciones ni ganadores', function () {
        $c = ($this->convocatoria)('cancelada');
        $aprobado = ($this->postulante)($c, EstadoPostulante::APROBADO);

        $this->postJson(($this->url)($c, '/postulantes'), [
            'cedula' => '1712345678', 'nombres' => 'Ana', 'apellidos' => 'Pérez', 'correo' => 'ana@test.ec',
        ])->assertStatus(422);

        expect(fn () => app(SeleccionService::class)->declararGanadores($c->id, [$aprobado->id], $this->user->id))
            ->toThrow(\App\Exceptions\ReglaNegocioException::class, 'ya está cerrada');
    });

    test('un borrador tampoco declara ganadores', function () {
        $c = ($this->convocatoria)();
        $aprobado = ($this->postulante)($c, EstadoPostulante::APROBADO);

        expect(fn () => app(SeleccionService::class)->declararGanadores($c->id, [$aprobado->id], $this->user->id))
            ->toThrow(\App\Exceptions\ReglaNegocioException::class, 'publíquela primero');
    });
});

describe('postulantes', function () {
    test('el PATCH del postulante ya no fija su estado', function () {
        $c = ($this->convocatoria)('publicada');
        $p = ($this->postulante)($c, EstadoPostulante::INSCRITO);

        $this->patchJson(($this->url)($c, "/postulantes/{$p->id}"), ['telefono' => '0991234567', 'estado' => 'seleccionado'])
            ->assertOk();

        expect($p->fresh()->estado)->toBe(EstadoPostulante::INSCRITO)
            ->and($p->fresh()->telefono)->toBe('0991234567');
    });

    test('no se borra a quien ya fue enviado al Dispensario', function () {
        $c = ($this->convocatoria)('en_evaluacion_medica');
        $p = ($this->postulante)($c, EstadoPostulante::GANADOR_POTENCIAL);

        $this->deleteJson(($this->url)($c, "/postulantes/{$p->id}"))->assertStatus(422);
        expect(Postulante::find($p->id))->not->toBeNull();
    });

    test('un inscrito de una publicada sí se borra', function () {
        $c = ($this->convocatoria)('publicada');
        $p = ($this->postulante)($c, EstadoPostulante::INSCRITO);

        $this->deleteJson(($this->url)($c, "/postulantes/{$p->id}"))->assertOk();
        expect(Postulante::find($p->id))->toBeNull();
    });
});

describe('solicitud médica cancelada', function () {
    beforeEach(function () {
        $this->user->givePermissionTo(Permission::firstOrCreate([
            'name' => 'solicitar-certificacion-medica', 'guard_name' => 'sanctum',
        ]));

        $this->cancelar = fn (Postulante $p) => $this->patchJson(
            '/api/v1/dispensario/solicitudes-certificacion/'
                .SolicitudCertificacionMedica::where('postulante_id', $p->id)->value('id').'/cancelar',
            ['motivo' => 'El candidato no se presentó.']
        );
    });

    test('en el formal, el candidato vuelve a la lista de espera', function () {
        $c = ($this->convocatoria)('publicada');
        $p = ($this->postulante)($c, EstadoPostulante::APROBADO);
        app(SeleccionService::class)->declararGanadores($c->id, [$p->id], $this->user->id);

        ($this->cancelar)($p)->assertOk()
            ->assertJsonPath('mensaje', fn ($m) => str_contains($m, 'vuelve a Reclutamiento'));

        expect($p->fresh()->estado)->toBe(EstadoPostulante::LISTA_ESPERA)
            ->and($c->fresh()->estado)->toBe(EstadoConvocatoria::EN_EVALUACION_MEDICA);
    });

    test('en el express vuelve a aprobado', function () {
        $c = ($this->contenedor)();
        $p = ($this->postulante)($c, EstadoPostulante::APROBADO);
        app(SeleccionService::class)->declararGanadores($c->id, [$p->id], $this->user->id);

        ($this->cancelar)($p)->assertOk();

        expect($p->fresh()->estado)->toBe(EstadoPostulante::APROBADO);
    });
});
