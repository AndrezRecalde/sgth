<?php

use App\Enums\TipoExamenFemo;
use App\Models\Dispensario\FichaSaludOcupacional;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Dispensario\ResultadoMedico;
use App\Models\Dispensario\SolicitudCertificacionMedica;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
| La sección J de la ficha ocupacional alimentada desde los resultados (fase 5
| de docs/diseno-examenes-medicos.md, 5.6): la ficha ofrece los del paciente
| del último año, con la fila ya armada, y guarda de cuál salió cada una.
*/

beforeEach(function () {
    Model::unguard();

    $this->medico = User::factory()->create();
    $this->medico->assignRole([Role::firstOrCreate(['name' => 'medico', 'guard_name' => 'sanctum']), 'medico-ocupacional']);

    $this->servidor = Servidor::create(['cedula' => '0812510001', 'nombre' => 'Ana', 'apellido' => 'Vera']);
    $this->historia = HistoriaClinica::create([
        'numero_historia' => '0812510001', 'cedula_paciente' => '0812510001',
        'tipo_paciente' => 'servidor', 'servidor_id' => $this->servidor->id, 'estado' => true,
    ]);

    $otro = Servidor::create(['cedula' => '0812510002', 'nombre' => 'Luis', 'apellido' => 'Mena']);
    $this->otraHistoria = HistoriaClinica::create([
        'numero_historia' => '0812510002', 'cedula_paciente' => '0812510002',
        'tipo_paciente' => 'servidor', 'servidor_id' => $otro->id, 'estado' => true,
    ]);
});

function resultadoParaFemo(HistoriaClinica $historia, string $descripcion, string $fecha, array $extra = []): ResultadoMedico
{
    return ResultadoMedico::create([
        'historia_clinica_id' => $historia->id,
        'subido_por'          => test()->medico->id,
        'tipo'                => 'laboratorio',
        'descripcion'         => $descripcion,
        'archivo'             => "resultados-medicos/{$descripcion}.pdf",
        'fecha_resultado'     => $fecha,
        'estado_revision'     => 'por_revisar',
        ...$extra,
    ]);
}

function resultadosParaFemo(string $query): array
{
    return test()->actingAs(test()->medico, 'sanctum')
        ->getJson("/api/v1/dispensario/fichas-sso/resultados-del-paciente?{$query}")
        ->assertOk()
        ->json('datos');
}

test('ofrece_los_del_paciente_del_ultimo_ano_hasta_la_evaluacion', function () {
    resultadoParaFemo($this->historia, 'reciente', '2026-09-15');
    resultadoParaFemo($this->historia, 'hace-diez-meses', '2025-12-01');
    resultadoParaFemo($this->historia, 'hace-dos-anios', '2024-09-01');
    resultadoParaFemo($this->historia, 'despues-de-la-evaluacion', '2026-10-20');
    resultadoParaFemo($this->otraHistoria, 'de-otro', '2026-09-15');

    $descripciones = collect(resultadosParaFemo('cedula=0812510001&hasta=2026-10-01'))->pluck('descripcion')->all();

    expect($descripciones)->toBe(['reciente', 'hace-diez-meses']);
});

test('arma_la_fila_con_la_lectura_del_medico_y_el_tipo_unificado', function () {
    resultadoParaFemo($this->historia, 'Biometría', '2026-09-15', [
        'estado_revision' => 'revisado', 'interpretacion' => 'alterado', 'comentario_medico' => 'Hb 10.1',
    ]);
    resultadoParaFemo($this->historia, 'Rx de tórax', '2026-09-10', ['tipo' => 'rayos_x']);

    [$biometria, $rx] = resultadosParaFemo('cedula=0812510001&hasta=2026-10-01');

    expect($biometria['para_femo'])->toMatchArray([
        'nombre_examen' => 'Biometría', 'fecha_examen' => '2026-09-15',
        'tipo' => 'laboratorio', 'resultado' => 'Alterado: Hb 10.1',
        'resultado_medico_id' => $biometria['id'],
    ])
        // Sin revisar, el resultado se deja para que lo escriba el médico.
        ->and($rx['para_femo']['resultado'])->toBeNull()
        ->and($rx['para_femo']['tipo'])->toBe('imagen');

    expect(TipoExamenFemo::deResultado('ecografia'))->toBe(TipoExamenFemo::IMAGEN)
        ->and(TipoExamenFemo::deResultado('electrocardiograma'))->toBe(TipoExamenFemo::OTRO);
});

test('la_ficha_guarda_de_que_resultado_salio_cada_fila', function () {
    $resultado = resultadoParaFemo($this->historia, 'Glucosa', '2026-09-15');
    $solicitud = SolicitudCertificacionMedica::create([
        'tipo_evento' => 'periodica', 'origen' => 'expediente', 'servidor_id' => $this->servidor->id,
        'cedula_paciente' => $this->servidor->cedula, 'nombres_paciente' => 'Ana Vera',
        'solicitado_por' => $this->medico->id, 'estado' => 'en_proceso', 'fecha_limite' => now()->addDays(7),
    ]);

    $id = $this->actingAs($this->medico, 'sanctum')
        ->postJson('/api/v1/dispensario/fichas-sso', [
            'solicitud_id' => $solicitud->id,
            'ficha'        => ['fecha_evaluacion' => '2026-10-01'],
            'examenes'     => [
                ['nombre_examen' => 'Glucosa', 'tipo' => 'laboratorio', 'fecha_examen' => '2026-09-15',
                    'resultado' => 'Normal', 'resultado_medico_id' => $resultado->id],
                ['nombre_examen' => 'Audiometría', 'tipo' => 'otro', 'resultado' => 'Sin hallazgos'],
            ],
        ])
        ->assertCreated()
        ->json('datos.id');

    $filas = FichaSaludOcupacional::findOrFail($id)->examenes()->orderBy('id')->get();
    expect($filas->pluck('resultado_medico_id')->all())->toBe([$resultado->id, null]);

    // Un resultado que no existe no se cuela.
    $this->patchJson("/api/v1/dispensario/fichas-sso/{$id}", [
        'examenes' => [['nombre_examen' => 'X', 'tipo' => 'otro', 'resultado_medico_id' => 999999]],
    ])->assertStatus(422);
});

test('solo_el_personal_del_femo_lo_consulta', function () {
    $enfermera = User::factory()->create();
    $enfermera->assignRole(Role::firstOrCreate(['name' => 'enfermera', 'guard_name' => 'sanctum']));

    $this->actingAs($enfermera, 'sanctum')
        ->getJson('/api/v1/dispensario/fichas-sso/resultados-del-paciente?cedula=0812510001')
        ->assertForbidden();

    $this->actingAs($this->medico, 'sanctum')
        ->getJson('/api/v1/dispensario/fichas-sso/resultados-del-paciente')
        ->assertStatus(422);
});
