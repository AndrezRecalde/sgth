<?php

use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Dispensario\ResultadoMedico;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
| «Mi jornada» y los resultados (fase 4 de docs/diseno-examenes-medicos.md, D3):
| cuántos de lo suyo nadie ha leído, y los críticos que revisó otro y él no
| ha visto, que quedan fijos hasta que los vea.
*/

beforeEach(function () {
    Model::unguard();

    $medico = function () {
        $u = User::factory()->create();
        $u->assignRole(Role::firstOrCreate(['name' => 'medico', 'guard_name' => 'sanctum']));

        return $u;
    };
    $this->medico = $medico();
    $this->otroMedico = $medico();

    $paciente = Servidor::create(['cedula' => '0812500001', 'nombre' => 'Rosa', 'apellido' => 'Cortez', 'estado' => true]);
    $this->historia = HistoriaClinica::create([
        'numero_historia' => '0812500001', 'cedula_paciente' => '0812500001',
        'tipo_paciente' => 'servidor', 'servidor_id' => $paciente->id, 'estado' => true,
    ]);
    $consulta = fn (User $m) => ConsultaMedica::create([
        'historia_clinica_id' => $this->historia->id, 'medico_id' => $m->id, 'especialidad' => 'medicina_general',
        'fecha_consulta' => now()->toDateString(), 'hora_consulta' => '09:00:00', 'motivo_consulta' => 'Control',
    ]);
    $this->suya = $consulta($this->medico);
    $this->ajena = $consulta($this->otroMedico);
});

function resultadoDeJornada(ConsultaMedica $consulta, array $extra = []): ResultadoMedico
{
    return ResultadoMedico::create([
        'historia_clinica_id' => $consulta->historia_clinica_id,
        'consulta_medica_id'  => $consulta->id,
        'subido_por'          => $consulta->medico_id,
        'tipo'                => 'laboratorio',
        'descripcion'         => 'Glucosa',
        'archivo'             => 'resultados-medicos/glucosa.pdf',
        'fecha_resultado'     => now()->toDateString(),
        ...$extra,
    ]);
}

function pendientesDeJornada(User $medico): array
{
    return test()->actingAs($medico, 'sanctum')
        ->getJson('/api/v1/dispensario/mi-jornada?perfil=medico')
        ->assertOk()
        ->json('datos.pendientes');
}

test('cuenta_lo_suyo_que_nadie_ha_leido', function () {
    resultadoDeJornada($this->suya);
    resultadoDeJornada($this->suya);
    resultadoDeJornada($this->suya, ['estado_revision' => 'revisado', 'interpretacion' => 'normal']);
    resultadoDeJornada($this->ajena);

    expect(pendientesDeJornada($this->medico)['resultados_por_revisar'])->toBe(2)
        ->and(pendientesDeJornada($this->otroMedico)['resultados_por_revisar'])->toBe(1);
});

test('un_critico_que_reviso_otro_queda_fijo_hasta_que_lo_ve', function () {
    $critico = resultadoDeJornada($this->suya, [
        'descripcion' => 'Potasio', 'estado_revision' => 'revisado', 'interpretacion' => 'critico',
        'comentario_medico' => 'K 6.8', 'revisado_por' => $this->otroMedico->id, 'revisado_en' => now(),
    ]);
    // Uno que ya vio y otro alterado no salen.
    resultadoDeJornada($this->suya, [
        'estado_revision' => 'revisado', 'interpretacion' => 'critico',
        'revisado_por' => $this->otroMedico->id, 'revisado_en' => now(), 'critico_visto_en' => now(),
    ]);
    resultadoDeJornada($this->suya, ['estado_revision' => 'revisado', 'interpretacion' => 'alterado']);

    $criticos = pendientesDeJornada($this->medico)['criticos'];
    expect($criticos)->toHaveCount(1)
        ->and($criticos[0])->toMatchArray([
            'id' => $critico->id, 'descripcion' => 'Potasio', 'paciente' => 'Rosa Cortez',
            'comentario' => 'K 6.8', 'revisor' => $this->otroMedico->nombre_completo,
        ])
        // No es suyo: al otro médico no le sale.
        ->and(pendientesDeJornada($this->otroMedico)['criticos'])->toBe([]);

    $this->actingAs($this->medico, 'sanctum')
        ->patchJson("/api/v1/dispensario/resultados-medicos/{$critico->id}/enterado")
        ->assertOk();

    expect(pendientesDeJornada($this->medico)['criticos'])->toBe([]);
});
