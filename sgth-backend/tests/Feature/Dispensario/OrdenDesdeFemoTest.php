<?php

use App\Enums\EstadoPostulante;
use App\Enums\TipoFichaFemo;
use App\Models\Dispensario\CatalogoExamen;
use App\Models\Dispensario\FichaSaludOcupacional;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Dispensario\OrdenExamen;
use App\Models\Dispensario\SolicitudCertificacionMedica;
use App\Models\Expediente\Servidor;
use App\Models\Seleccion\Convocatoria;
use App\Models\Seleccion\Postulante;
use App\Models\User;
use App\Services\Dispensario\PdfOrdenExamenService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
| La orden que nace de la ficha ocupacional (fase 5 de
| docs/diseno-examenes-medicos.md, 5.6): la pide quien evalúa, mientras la
| evaluación está en curso, nunca con exámenes sensibles, y queda en la
| historia del paciente aunque sea un aspirante que aún no la tenía.
*/

beforeEach(function () {
    Model::unguard();

    $medico = function () {
        $u = User::factory()->create();
        $u->assignRole(Role::firstOrCreate(['name' => 'medico', 'guard_name' => 'sanctum']));

        return $u;
    };
    $this->evaluador = $medico();
    $this->otroMedico = $medico();

    $this->servidor = Servidor::create(['cedula' => '0812520001', 'nombre' => 'Ana', 'apellido' => 'Vera']);
    $this->ficha = fichaParaOrden($this->evaluador, ['servidor_id' => $this->servidor->id], TipoFichaFemo::PERIODICA, 'en_proceso');
});

function fichaParaOrden(User $evaluador, array $paciente, TipoFichaFemo $tipo, string $estadoSolicitud): FichaSaludOcupacional
{
    $ficha = FichaSaludOcupacional::create([
        ...$paciente, 'fecha_evaluacion' => now()->toDateString(), 'tipo_ficha' => $tipo,
        'evaluador_id' => $evaluador->id, 'estado' => true,
    ]);
    $cedula = isset($paciente['servidor_id'])
        ? Servidor::find($paciente['servidor_id'])->cedula
        : Postulante::find($paciente['postulante_id'])->cedula;

    SolicitudCertificacionMedica::create([
        ...$paciente, 'tipo_evento' => $tipo->value === 'periodica' ? 'periodica' : 'ingreso',
        'origen' => isset($paciente['servidor_id']) ? 'expediente' : 'reclutamiento',
        'cedula_paciente' => $cedula, 'nombres_paciente' => 'Paciente', 'solicitado_por' => $evaluador->id,
        'estado' => $estadoSolicitud, 'fecha_limite' => now()->addDays(7), 'ficha_femo_id' => $ficha->id,
    ]);

    return $ficha;
}

function pedirDesdeFicha(User $quien, array $datos, array $examenes = ['Hemograma completo']): Illuminate\Testing\TestResponse
{
    return test()->actingAs($quien, 'sanctum')->postJson('/api/v1/dispensario/ordenes-examen', [
        'fecha_solicitud' => now()->toDateString(),
        'items'           => array_map(fn ($n) => ['catalogo_examen_id' => CatalogoExamen::where('nombre', $n)->value('id')], $examenes),
        ...$datos,
    ]);
}

test('quien_evalua_la_ficha_pide_sus_examenes_en_la_historia_del_servidor', function () {
    $orden = pedirDesdeFicha($this->evaluador, ['ficha_salud_ocupacional_id' => $this->ficha->id], ['Audiometría tonal', 'Espirometría'])
        ->assertCreated()
        ->json('datos');

    $historia = HistoriaClinica::where('cedula_paciente', '0812520001')->firstOrFail();
    expect($orden['ficha_salud_ocupacional_id'])->toBe($this->ficha->id)
        ->and($orden['consulta_medica_id'])->toBeNull()
        ->and($orden['historia_clinica_id'])->toBe($historia->id)
        ->and($historia->servidor_id)->toBe($this->servidor->id);

    // La ficha lista las suyas.
    $this->getJson("/api/v1/dispensario/ordenes-examen?ficha_salud_ocupacional_id={$this->ficha->id}")
        ->assertOk()
        ->assertJsonCount(1, 'datos');

    // El impreso dice qué evaluación la pide.
    $datos = app(PdfOrdenExamenService::class)->datosDelImpreso($orden['id']);
    expect($datos['servicio'])->toBe('Salud ocupacional · ' . TipoFichaFemo::PERIODICA->etiqueta());
});

test('un_aspirante_estrena_su_historia_con_la_orden_de_ingreso', function () {
    $convocatoria = Convocatoria::create([
        'puesto_id' => puestoDePrueba(unidadDePrueba(['nombre' => 'Direccion de Ingresos']), 'Analista de Ingresos')->id,
        'codigo' => 'CNV-2026-991', 'titulo' => 'Analista', 'descripcion' => 'Concurso',
        'fecha_inicio' => now()->subDays(20), 'fecha_fin' => now()->addDays(10),
        'tipo_proceso' => App\Enums\TipoProcesoConvocatoria::FORMAL,
    ]);
    $postulante = Postulante::create([
        'convocatoria_id' => $convocatoria->id, 'cedula' => '1756600011',
        'nombres' => 'Mariela', 'apellidos' => 'Chica', 'correo' => 'mariela@example.test',
        'genero' => 'femenino', 'estado_civil' => 'soltero', 'fecha_nacimiento' => '1996-02-20',
        'estado' => EstadoPostulante::GANADOR_POTENCIAL,
    ]);
    $ficha = fichaParaOrden($this->evaluador, ['postulante_id' => $postulante->id], TipoFichaFemo::INGRESO, 'en_proceso');

    pedirDesdeFicha($this->evaluador, ['ficha_salud_ocupacional_id' => $ficha->id])->assertCreated();

    $historia = HistoriaClinica::where('cedula_paciente', '1756600011')->firstOrFail();
    expect($historia->tipo_paciente)->toBe('candidato')
        ->and(OrdenExamen::firstOrFail()->historia_clinica_id)->toBe($historia->id);
});

test('solo_quien_evalua_y_mientras_la_evaluacion_esta_en_curso', function () {
    pedirDesdeFicha($this->otroMedico, ['ficha_salud_ocupacional_id' => $this->ficha->id])->assertForbidden();

    $cerrada = fichaParaOrden($this->evaluador, ['servidor_id' => $this->servidor->id], TipoFichaFemo::PERIODICA, 'completada');
    pedirDesdeFicha($this->evaluador, ['ficha_salud_ocupacional_id' => $cerrada->id])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'La evaluación de esta ficha ya no está en curso: no se le piden exámenes.');

    expect(OrdenExamen::count())->toBe(0);
});

test('nunca_examenes_sensibles_y_de_una_consulta_o_una_ficha_no_de_las_dos', function () {
    pedirDesdeFicha($this->evaluador, ['ficha_salud_ocupacional_id' => $this->ficha->id, 'consentimiento_firmado' => true], ['HIV 1/2/0'])
        ->assertStatus(422)
        ->assertJsonPath('errores.items.0', 'Una orden de la ficha ocupacional no lleva exámenes sensibles: quite HIV 1/2/0.');

    pedirDesdeFicha($this->evaluador, [])
        ->assertStatus(422)
        ->assertJsonPath('errores.consulta_medica_id.0', 'La orden sale de una consulta o de una ficha ocupacional.');

    expect(OrdenExamen::count())->toBe(0);
});
