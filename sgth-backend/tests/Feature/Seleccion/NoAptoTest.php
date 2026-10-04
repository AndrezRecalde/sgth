<?php

use App\Enums\EstadoConvocatoria;
use App\Enums\EstadoPostulante;
use App\Models\Dispensario\SolicitudCertificacionMedica;
use App\Models\Estructura\GrupoOcupacional;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Seleccion\Convocatoria;
use App\Models\Seleccion\EvaluacionSeleccion;
use App\Models\Seleccion\Postulante;
use App\Models\User;
use App\Services\Seleccion\SeleccionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
 * El candidato no apto (decisión de TH, 2026-10-04). Antes se quedaba «en
 * evaluación médica» para siempre: no se podía incorporar, nadie ocupaba su
 * lugar y el concurso formal no se cerraba. Ahora queda descalificado; en el
 * formal, Talento Humano declara al siguiente del ranking y, si no queda
 * nadie, el concurso se cierra solo.
 */

beforeEach(function () {
    $this->th = User::factory()->create();
    $this->th->assignRole(Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']));
    $this->medico = User::factory()->create();
    $this->medico->assignRole(Role::firstOrCreate(['name' => 'medico', 'guard_name' => 'sanctum']));

    $unidad = UnidadAdministrativa::create(['codigo' => 'NOAPTO-01', 'nombre' => 'Unidad No Apto', 'nivel' => 1]);
    $grupo = GrupoOcupacional::create([
        'grado_codigo' => 'SP4', 'grado_numerico' => 4, 'grupo' => 'Profesional',
        'denominacion_generica' => 'Analista', 'rmu' => 1212.00, 'regimen' => 'losep', 'activo' => true,
    ]);
    $this->puesto = Puesto::create([
        'unidad_administrativa_id' => $unidad->id, 'grupo_ocupacional_id' => $grupo->id,
        'plazas' => 5, 'regimen_laboral' => 'losep', 'activo' => true,
    ]);

    $this->convocatoria = Convocatoria::create([
        'puesto_id' => $this->puesto->id, 'codigo' => 'CNV-NOAPTO-1', 'titulo' => 'Analista',
        'descripcion' => 'Concurso', 'tipo' => 'externa', 'tipo_proceso' => 'formal',
        'estado' => 'publicada', 'vacantes' => 1,
        'fecha_inicio' => now()->subDays(30), 'fecha_fin' => now()->subDays(2),
    ]);

    $this->contador = 0;
    // Aprobado con su puntaje: el siguiente lo decide el ranking.
    $this->aprobado = function (float $puntaje, ?Convocatoria $en = null) {
        $this->contador++;
        $p = Postulante::create([
            'convocatoria_id' => ($en ?? $this->convocatoria)->id,
            'puesto_id' => $en ? $this->puesto->id : null,
            'cedula' => '17600000'.str_pad((string) $this->contador, 2, '0', STR_PAD_LEFT),
            'nombres' => 'Candidato', 'apellidos' => 'NoApto'.$this->contador,
            'correo' => "na{$this->contador}@test.ec", 'genero' => 'femenino',
            'fecha_inscripcion' => now()->subDays(20)->toDateString(),
            'estado' => EstadoPostulante::APROBADO,
        ]);
        EvaluacionSeleccion::create([
            'postulante_id' => $p->id, 'puntaje_meritos' => $puntaje * 0.4,
            'puntaje_oposicion' => $puntaje * 0.6, 'puntaje_total' => $puntaje,
            'evaluador_id' => $this->th->id,
        ]);

        return $p;
    };

    $this->declarar = fn (array $ids, ?Convocatoria $en = null) => app(SeleccionService::class)
        ->declararGanadores(($en ?? $this->convocatoria)->id, $ids, $this->th->id);

    // El médico guarda la ficha con la aptitud y emite el dictamen, como en el Dispensario.
    $this->dictaminar = function (Postulante $p, string $aptitud) {
        $s = SolicitudCertificacionMedica::where('postulante_id', $p->id)->latest('id')->firstOrFail();
        $s->update(['estado' => 'en_proceso']);

        $this->actingAs($this->medico, 'sanctum')->postJson('/api/v1/dispensario/fichas-sso', [
            'solicitud_id' => $s->id,
            'ficha' => [
                'fecha_evaluacion' => now()->toDateString(), 'aptitud' => $aptitud,
                'restricciones' => $aptitud === 'no_apto' ? 'Motivo de la no aptitud.' : null,
            ],
        ])->assertSuccessful();

        return $this->patchJson("/api/v1/dispensario/solicitudes-certificacion/{$s->id}/completar")
            ->assertOk();
    };

    $this->siguiente = fn () => $this->actingAs($this->th, 'sanctum')
        ->postJson("/api/v1/seleccion/convocatorias/{$this->convocatoria->id}/declarar-siguiente");
});

test('el no apto queda descalificado y el siguiente del ranking cubre su vacante', function () {
    $a = ($this->aprobado)(90);
    $tercero = ($this->aprobado)(75);
    $segundo = ($this->aprobado)(80);
    ($this->declarar)([$a->id]);

    ($this->dictaminar)($a, 'no_apto');

    expect($a->fresh()->estado)->toBe(EstadoPostulante::DESCALIFICADO)
        // Queda gente en lista de espera: el concurso sigue abierto.
        ->and($this->convocatoria->fresh()->estado)->toBe(EstadoConvocatoria::EN_EVALUACION_MEDICA);

    ($this->siguiente)()->assertOk()->assertJsonPath('datos.id', $segundo->id);

    expect($segundo->fresh()->estado)->toBe(EstadoPostulante::GANADOR_POTENCIAL)
        ->and($tercero->fresh()->estado)->toBe(EstadoPostulante::LISTA_ESPERA)
        ->and(SolicitudCertificacionMedica::where('postulante_id', $segundo->id)->count())->toBe(1);

    // La vacante ya está en evaluación médica: no cabe otro.
    ($this->siguiente)()->assertStatus(422)
        ->assertJsonPath('mensaje', fn ($m) => str_contains($m, 'vacantes ya están cubiertas'));
});

test('sin nadie en lista de espera, el concurso queda desierto', function () {
    $a = ($this->aprobado)(90);
    ($this->declarar)([$a->id]);

    ($this->dictaminar)($a, 'no_apto');

    expect($a->fresh()->estado)->toBe(EstadoPostulante::DESCALIFICADO)
        ->and($this->convocatoria->fresh()->estado)->toBe(EstadoConvocatoria::DESIERTA);

    ($this->siguiente)()->assertStatus(422);
});

test('con un ganador incorporado y el otro no apto sin reemplazo, el concurso se finaliza', function () {
    $this->convocatoria->update(['vacantes' => 2]);
    [$a, $b] = [($this->aprobado)(90), ($this->aprobado)(85)];
    ($this->declarar)([$a->id, $b->id]);

    ($this->dictaminar)($a, 'apto');
    $s = SolicitudCertificacionMedica::where('postulante_id', $a->id)->firstOrFail();
    $this->th->givePermissionTo(
        Permission::firstOrCreate(['name' => 'gestionar-onboarding', 'guard_name' => 'sanctum'])
    );
    $this->actingAs($this->th, 'sanctum')->postJson("/api/v1/dispensario/solicitudes-certificacion/{$s->id}/confirmar-incorporacion")->assertOk();

    // Falta el dictamen de b: sigue abierto.
    expect($this->convocatoria->fresh()->estado)->toBe(EstadoConvocatoria::EN_EVALUACION_MEDICA);

    ($this->dictaminar)($b, 'no_apto');

    expect($this->convocatoria->fresh()->estado)->toBe(EstadoConvocatoria::FINALIZADA);
});

test('en el express el no apto cierra su caso sin tocar el contenedor', function () {
    $contenedor = Convocatoria::create([
        'puesto_id' => null, 'codigo' => 'EXP-NOAPTO', 'titulo' => 'Contrato ocasional',
        'descripcion' => 'Express', 'tipo' => 'externa', 'tipo_proceso' => 'express',
        'estado' => 'publicada', 'vacantes' => 1, 'es_contenedor_permanente' => true,
        'tipo_nombramiento_previsto' => 'servicios_ocasionales',
        'fecha_inicio' => now()->subYear(), 'fecha_fin' => now()->addYear(),
    ]);
    $a = ($this->aprobado)(80, $contenedor);
    ($this->declarar)([$a->id], $contenedor);

    ($this->dictaminar)($a, 'no_apto');

    expect($a->fresh()->estado)->toBe(EstadoPostulante::DESCALIFICADO)
        ->and($contenedor->fresh()->estado)->toBe(EstadoConvocatoria::PUBLICADA);
});

test('un apto no se descalifica', function () {
    $a = ($this->aprobado)(90);
    ($this->declarar)([$a->id]);

    ($this->dictaminar)($a, 'apto');

    expect($a->fresh()->estado)->toBe(EstadoPostulante::GANADOR_POTENCIAL);
});
