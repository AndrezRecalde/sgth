<?php

use App\Enums\RegimenLaboral;
use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Dispensario\ResultadoMedico;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

/*
 * La pestaña de resultados solo enseñaba los de la consulta abierta: lo que
 * llegaba después de cerrar una consulta se veía abriendo el panel de aquella.
 * Ahora puede enseñar los de todo el paciente, y para eso cada resultado trae
 * la fecha de su consulta, si la tiene.
 */

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Model::unguard();

    $this->medico = User::create([
        'email' => 'resultados-paciente@example.com', 'usuario_ti' => 'resultados-paciente',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $this->medico->assignRole(Role::firstOrCreate(['name' => 'medico', 'guard_name' => 'sanctum']));

    $unidad = unidadDePrueba(['nombre' => 'Direccion de Resultados del Paciente']);
    $puesto = puestoDePrueba($unidad, 'Analista de Resultados del Paciente');
    $servidor = Servidor::create([
        'cedula' => '0812420001', 'nombre' => 'Olga', 'apellido' => 'Prueba',
        'puesto_id' => $puesto->id, 'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(2), 'estado' => true,
    ]);
    $this->historia = HistoriaClinica::create(['servidor_id' => $servidor->id, 'grupo_sanguineo' => 'O+']);

    $consulta = fn (string $fecha) => ConsultaMedica::create([
        'especialidad' => 'medicina_general', 'historia_clinica_id' => $this->historia->id,
        'medico_id' => $this->medico->id, 'fecha_consulta' => $fecha,
        'hora_consulta' => '09:00:00', 'motivo_consulta' => 'Control',
    ]);
    $this->anterior = $consulta('2026-10-01');
    $this->actual = $consulta('2026-10-15');

    $resultado = fn (?ConsultaMedica $c, string $descripcion, string $fecha) => ResultadoMedico::create([
        'historia_clinica_id' => $this->historia->id, 'consulta_medica_id' => $c?->id,
        'subido_por' => $this->medico->id, 'tipo' => 'laboratorio', 'descripcion' => $descripcion,
        'archivo' => "resultados-medicos/{$descripcion}.pdf", 'fecha_resultado' => $fecha,
    ]);
    $resultado($this->anterior, 'pedido en la anterior', '2026-10-08');
    $resultado($this->actual, 'de la actual', '2026-10-15');
    $resultado(null, 'sin consulta', '2026-10-10');
});

test('sin_consulta_en_el_filtro_salen_todos_los_del_paciente_con_la_fecha_de_su_consulta', function () {
    $datos = collect($this->actingAs($this->medico, 'sanctum')
        ->getJson("/api/v1/dispensario/resultados-medicos?historia_clinica_id={$this->historia->id}")
        ->assertOk()
        ->json('datos'))->keyBy('descripcion');

    expect($datos->keys()->all())->toBe(['de la actual', 'sin consulta', 'pedido en la anterior'])
        ->and(substr($datos['pedido en la anterior']['consulta_medica']['fecha_consulta'], 0, 10))->toBe('2026-10-01')
        ->and($datos['sin consulta']['consulta_medica'])->toBeNull();
});

test('con_la_consulta_en_el_filtro_salen_solo_los_suyos', function () {
    $datos = $this->actingAs($this->medico, 'sanctum')
        ->getJson("/api/v1/dispensario/resultados-medicos?historia_clinica_id={$this->historia->id}&consulta_medica_id={$this->actual->id}")
        ->assertOk()
        ->json('datos');

    expect(collect($datos)->pluck('descripcion')->all())->toBe(['de la actual']);
});
