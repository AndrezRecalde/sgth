<?php

use App\Contracts\Dispensario\OrdenExamenServiceInterface;
use App\Enums\RegimenLaboral;
use App\Models\Dispensario\CatalogoExamen;
use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Dispensario\ResultadoMedico;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/*
 * La revisión de un resultado (fase 4 de docs/diseno-examenes-medicos.md, D3):
 * nace por revisar; un médico lo lee como normal, alterado o crítico, con un
 * comentario cifrado; un crítico que revisó otro queda pendiente de que lo vea
 * quien pidió el examen.
 */

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Model::unguard();

    $usuario = function (string $alias, string $rol) {
        $user = User::create([
            'email' => "{$alias}@example.com", 'usuario_ti' => $alias,
            'password' => bcrypt('123456'), 'primer_login' => false,
        ]);
        $user->assignRole(Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum']));

        return $user;
    };

    $this->solicitante = $usuario('revision-solicitante', 'medico');
    $this->otroMedico = $usuario('revision-otro', 'medico');
    $this->enfermera = $usuario('revision-enfermera', 'enfermera');

    $unidad = unidadDePrueba(['nombre' => 'Direccion de Revision de Resultados']);
    $puesto = puestoDePrueba($unidad, 'Analista de Revision de Resultados');
    $servidor = Servidor::create([
        'cedula' => '0812480001', 'nombre' => 'Paciente', 'apellido' => 'Revision',
        'puesto_id' => $puesto->id, 'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(2), 'estado' => true,
    ]);
    $this->historia = HistoriaClinica::create(['servidor_id' => $servidor->id, 'grupo_sanguineo' => 'O+']);
    $this->consulta = ConsultaMedica::create([
        'especialidad' => 'medicina_general', 'historia_clinica_id' => $this->historia->id,
        'medico_id' => $this->solicitante->id, 'fecha_consulta' => now()->subDay(),
        'hora_consulta' => '09:00:00', 'motivo_consulta' => 'Control',
    ]);
    $this->orden = app(OrdenExamenServiceInterface::class)->emitir(
        ['historia_clinica_id' => $this->historia->id, 'consulta_medica_id' => $this->consulta->id,
            'fecha_solicitud' => now()->toDateString()],
        [['catalogo_examen_id' => CatalogoExamen::where('nombre', 'Glucosa')->value('id')]],
        $this->solicitante->id,
    );
});

/** Un resultado recién subido de la orden del solicitante. */
function resultadoParaRevisar(array $extra = []): ResultadoMedico
{
    return ResultadoMedico::create([
        'historia_clinica_id' => test()->historia->id,
        'consulta_medica_id'  => test()->consulta->id,
        'orden_examen_id'     => test()->orden->id,
        'subido_por'          => test()->solicitante->id,
        'tipo'                => 'laboratorio',
        'descripcion'         => 'Glucosa',
        'archivo'             => 'resultados-medicos/glucosa.pdf',
        'fecha_resultado'     => now()->toDateString(),
        ...$extra,
    ])->fresh();
}

function revisarResultado(User $quien, ResultadoMedico $r, array $datos): Illuminate\Testing\TestResponse
{
    return test()->actingAs($quien, 'sanctum')
        ->patchJson("/api/v1/dispensario/resultados-medicos/{$r->id}/revisar", $datos);
}

test('un_resultado_nace_por_revisar_y_el_medico_lo_lee', function () {
    $r = resultadoParaRevisar();
    expect($r->estado_revision->value)->toBe('por_revisar');

    $datos = revisarResultado($this->solicitante, $r, ['interpretacion' => 'normal'])
        ->assertOk()->json('datos');

    expect($datos['estado_revision'])->toBe('revisado')
        ->and($datos['interpretacion'])->toBe('normal')
        ->and($datos['comentario_medico'])->toBeNull()
        ->and($datos['revisor']['id'])->toBe($this->solicitante->id)
        ->and($datos['revisado_en'])->not->toBeNull()
        // El paciente, para darle un turno de control desde la revisión.
        ->and($datos['historia_clinica']['servidor_id'])->toBe($this->historia->servidor_id);
});

test('subido_por_el_api_responde_por_revisar', function () {
    Illuminate\Support\Facades\Storage::fake('local');

    $this->actingAs($this->solicitante, 'sanctum')
        ->post('/api/v1/dispensario/resultados-medicos', [
            'historia_clinica_id' => $this->historia->id,
            'tipo'                => 'laboratorio',
            'descripcion'         => 'Glucosa',
            'fecha_resultado'     => now()->toDateString(),
            'archivo'             => Illuminate\Http\UploadedFile::fake()->create('glucosa.pdf', 40, 'application/pdf'),
        ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('datos.estado_revision', 'por_revisar');
});

test('un_alterado_o_un_critico_piden_comentario_y_se_guarda_cifrado', function () {
    $r = resultadoParaRevisar();

    revisarResultado($this->solicitante, $r, ['interpretacion' => 'alterado'])
        ->assertStatus(422)
        ->assertJsonPath('errores.comentario_medico.0', 'Explique qué está alterado: lo leerá quien vea el resultado después.');

    revisarResultado($this->solicitante, $r, ['interpretacion' => 'alterado', 'comentario_medico' => 'Glucosa 180 mg/dL'])
        ->assertOk()
        ->assertJsonPath('datos.comentario_medico', 'Glucosa 180 mg/dL');

    $guardado = DB::table('resultados_medicos')->where('id', $r->id)->value('comentario_medico');
    expect($guardado)->not->toContain('180');
});

test('revisa_cualquier_medico_pero_solo_quien_reviso_corrige', function () {
    $r = resultadoParaRevisar();

    revisarResultado($this->enfermera, $r, ['interpretacion' => 'normal'])->assertForbidden();

    revisarResultado($this->otroMedico, $r, ['interpretacion' => 'normal'])->assertOk();

    // Quien revisó corrige su lectura.
    revisarResultado($this->otroMedico, $r, ['interpretacion' => 'alterado', 'comentario_medico' => 'Releído: 130'])
        ->assertOk()->assertJsonPath('datos.interpretacion', 'alterado');

    // Otro no la pisa.
    $mensaje = revisarResultado($this->solicitante, $r, ['interpretacion' => 'normal'])
        ->assertStatus(422)->json('mensaje');
    expect($mensaje)->toStartWith('Este resultado ya lo revisó ')
        ->and($r->fresh()->interpretacion->value)->toBe('alterado');
});

test('un_critico_ajeno_queda_pendiente_de_ver_para_quien_lo_pidio', function () {
    $r = resultadoParaRevisar();

    revisarResultado($this->otroMedico, $r, ['interpretacion' => 'critico', 'comentario_medico' => 'Glucosa 450'])
        ->assertOk()
        ->assertJsonPath('datos.critico_visto_en', null);

    $url = "/api/v1/dispensario/resultados-medicos/{$r->id}/enterado";

    // Solo quien pidió el examen lo da por visto.
    $this->actingAs($this->otroMedico, 'sanctum')->patchJson($url)->assertForbidden();

    $this->actingAs($this->solicitante, 'sanctum')->patchJson($url)->assertOk();
    expect($r->fresh()->critico_visto_en)->not->toBeNull();

    // Una vez visto, no hay nada pendiente.
    $this->patchJson($url)->assertStatus(422);
});

test('un_critico_que_revisa_quien_lo_pidio_ya_queda_visto', function () {
    $r = resultadoParaRevisar();

    revisarResultado($this->solicitante, $r, ['interpretacion' => 'critico', 'comentario_medico' => 'Glucosa 450'])
        ->assertOk();

    expect($r->fresh()->critico_visto_en)->not->toBeNull();
});

test('sin_orden_responde_el_medico_de_la_consulta', function () {
    $r = resultadoParaRevisar(['orden_examen_id' => null]);

    revisarResultado($this->otroMedico, $r, ['interpretacion' => 'critico', 'comentario_medico' => 'Rx con derrame'])
        ->assertOk();

    $this->actingAs($this->solicitante, 'sanctum')
        ->patchJson("/api/v1/dispensario/resultados-medicos/{$r->id}/enterado")
        ->assertOk();
});
