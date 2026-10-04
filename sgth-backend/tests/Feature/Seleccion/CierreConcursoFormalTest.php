<?php

use App\Enums\EstadoConvocatoria;
use App\Enums\EstadoPostulante;
use App\Enums\Permiso;
use App\Models\Dispensario\SolicitudCertificacionMedica;
use App\Models\Estructura\GrupoOcupacional;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\Seleccion\Convocatoria;
use App\Models\Seleccion\Postulante;
use App\Models\User;
use App\Services\Seleccion\SeleccionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
 * El cierre del concurso formal (decisión de TH, 2026-10-04). Antes
 * «Declarar ganador oficial» finalizaba sin mirar el dictamen y sin crear el
 * expediente: un concurso formal nunca terminaba en una persona contratada.
 * Ahora cada ganador apto se incorpora y, con el último, la convocatoria se
 * finaliza sola.
 */

beforeEach(function () {
    $permiso = Permission::firstOrCreate(['name' => Permiso::GESTIONAR_ONBOARDING->value, 'guard_name' => 'sanctum']);
    $rol = Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    $rol->givePermissionTo($permiso);
    $this->user = User::factory()->create();
    $this->user->assignRole($rol);
    $this->actingAs($this->user, 'sanctum');

    $unidad = UnidadAdministrativa::create(['codigo' => 'CIERRE-01', 'nombre' => 'Unidad Cierre', 'nivel' => 1]);
    $grupo = GrupoOcupacional::create([
        'grado_codigo' => 'SP4', 'grado_numerico' => 4, 'grupo' => 'Profesional',
        'denominacion_generica' => 'Analista', 'rmu' => 1212.00, 'regimen' => 'losep', 'activo' => true,
    ]);
    $puesto = Puesto::create([
        'unidad_administrativa_id' => $unidad->id, 'grupo_ocupacional_id' => $grupo->id,
        'plazas' => 5, 'regimen_laboral' => 'losep', 'activo' => true,
    ]);

    $this->convocatoria = Convocatoria::create([
        'puesto_id' => $puesto->id, 'codigo' => 'CNV-CIERRE-1', 'titulo' => 'Analista',
        'descripcion' => 'Concurso', 'tipo' => 'externa', 'tipo_proceso' => 'formal',
        'estado' => 'publicada', 'vacantes' => 2,
        'fecha_inicio' => now()->subDays(30), 'fecha_fin' => now()->subDays(2),
    ]);

    $this->contador = 0;
    $this->aprobado = function () {
        $this->contador++;

        return Postulante::create([
            'convocatoria_id' => $this->convocatoria->id,
            'cedula' => '17500000'.str_pad((string) $this->contador, 2, '0', STR_PAD_LEFT),
            'nombres' => 'Candidato', 'apellidos' => 'Cierre'.$this->contador,
            'correo' => "c{$this->contador}@test.ec", 'genero' => 'femenino',
            'fecha_inscripcion' => now()->subDays(20)->toDateString(),
            'estado' => EstadoPostulante::APROBADO,
        ]);
    };

    // El dictamen lo emite el Dispensario: aquí se da por emitido.
    $this->dictaminar = function (Postulante $p, string $dictamen = 'apto'): SolicitudCertificacionMedica {
        $s = SolicitudCertificacionMedica::where('postulante_id', $p->id)->latest('id')->firstOrFail();
        $s->update(['estado' => 'completada', 'dictamen' => $dictamen]);

        return $s;
    };

    $this->incorporar = fn (SolicitudCertificacionMedica $s) => $this->postJson(
        "/api/v1/dispensario/solicitudes-certificacion/{$s->id}/confirmar-incorporacion"
    );
});

test('incorporar al último ganador finaliza el concurso; los de lista de espera quedan no seleccionados', function () {
    [$a, $b, $c] = [($this->aprobado)(), ($this->aprobado)(), ($this->aprobado)()];
    app(SeleccionService::class)->declararGanadores($this->convocatoria->id, [$a->id, $b->id], $this->user->id);

    expect($this->convocatoria->fresh()->estado)->toBe(EstadoConvocatoria::EN_EVALUACION_MEDICA)
        ->and($c->fresh()->estado)->toBe(EstadoPostulante::LISTA_ESPERA);

    ($this->incorporar)(($this->dictaminar)($a))->assertOk()
        ->assertJsonPath('mensaje', fn ($m) => ! str_contains($m, 'finalizada'));

    // Queda un ganador por resolver: el concurso sigue abierto.
    expect($this->convocatoria->fresh()->estado)->toBe(EstadoConvocatoria::EN_EVALUACION_MEDICA)
        ->and($a->fresh()->estado)->toBe(EstadoPostulante::INCORPORADO);

    ($this->incorporar)(($this->dictaminar)($b))->assertOk()
        ->assertJsonPath('mensaje', fn ($m) => str_contains($m, 'la convocatoria quedó finalizada'));

    expect($this->convocatoria->fresh()->estado)->toBe(EstadoConvocatoria::FINALIZADA)
        ->and($b->fresh()->estado)->toBe(EstadoPostulante::INCORPORADO)
        ->and($c->fresh()->estado)->toBe(EstadoPostulante::NO_SELECCIONADO)
        ->and(Servidor::whereIn('cedula', [$a->cedula, $b->cedula])->count())->toBe(2);
});

test('un candidato que se volvió servidor después de inscribirse se incorpora con su expediente, sin 500', function () {
    $a = ($this->aprobado)();
    app(SeleccionService::class)->declararGanadores($this->convocatoria->id, [$a->id], $this->user->id);

    // Se hizo servidor por otra vía mientras el concurso seguía.
    $existente = Servidor::create([
        'cedula' => $a->cedula, 'nombre' => 'Candidato', 'apellido' => 'Existente',
        'regimen_laboral' => 'losep', 'estado' => true,
    ]);

    ($this->incorporar)(($this->dictaminar)($a))->assertOk()
        ->assertJsonPath('datos.servidor_id', $existente->id);

    expect(Servidor::where('cedula', $a->cedula)->count())->toBe(1);
});

test('ya no existe «Declarar ganador oficial»', function () {
    $this->postJson("/api/v1/seleccion/convocatorias/{$this->convocatoria->id}/confirmar-ganador")
        ->assertNotFound();
});
