<?php

use App\Enums\RegimenLaboral;
use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\DiagnosticoCie10;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();
    ConsultaMedica::unguard();
    HistoriaClinica::unguard();

    $unidad = unidadDePrueba(['nombre' => 'Direccion Autoservicio']);
    $puesto = puestoDePrueba($unidad, 'Analista Autoservicio');

    $servidorMedico = Servidor::create([
        'cedula' => '0801234533', 'nombre' => 'Gregory', 'apellido' => 'House',
        'puesto_id' => $puesto->id, 'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(3), 'estado' => true,
    ]);

    $this->medico = User::create([
        'email' => 'house@example.com', 'usuario_ti' => 'house',
        'password' => bcrypt('123456'), 'primer_login' => false,
        'servidor_id' => $servidorMedico->id,
    ]);

    $this->paciente = Servidor::create([
        'cedula' => '0801234544', 'nombre' => 'Ana', 'apellido' => 'Mora',
        'puesto_id' => $puesto->id, 'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(3), 'estado' => true,
    ]);

    $this->pacienteUser = User::create([
        'email' => 'ana@example.com', 'usuario_ti' => 'ana',
        'password' => bcrypt('123456'), 'primer_login' => false,
        'servidor_id' => $this->paciente->id,
    ]);

    $this->historia = HistoriaClinica::create([
        'numero_historia' => $this->paciente->cedula,
        'cedula_paciente' => $this->paciente->cedula,
        'tipo_paciente'   => 'servidor',
        'servidor_id'     => $this->paciente->id,
        'estado'          => true,
    ]);
});

test('el_servidor_ve_sus_consultas_en_el_autoservicio', function () {
    // La consulta nombraba tres columnas que no existen —`fecha`,
    // `medicos.name` y `consultas_medicas.servidor_id`— y el endpoint devolvía
    // un 500 desde siempre. Nadie lo notó porque ninguna pantalla lo pide aún.
    $cie10 = DiagnosticoCie10::create([
        'codigo' => 'J00X', 'descripcion' => 'RINOFARINGITIS AGUDA',
        'categoria' => 'J00', 'activo' => true,
    ]);

    ConsultaMedica::create([
        'historia_clinica_id'   => $this->historia->id,
        'medico_id'             => $this->medico->id,
        'especialidad'          => 'medicina_general',
        'fecha_consulta'        => now()->subDays(3),
        'hora_consulta'         => '09:00:00',
        'motivo_consulta'       => 'Gripe',
        'diagnostico_detallado' => 'Cuadro viral',
        'diagnostico_cie10_id'  => $cie10->id,
    ]);

    $respuesta = $this->actingAs($this->pacienteUser, 'sanctum')
        ->getJson('/api/v1/autoservicio/mi-historia-clinica')
        ->assertOk();

    expect($respuesta->json('datos'))->toHaveCount(1);

    $fila = $respuesta->json('datos.0');
    expect($fila['medico'])->toBe('Gregory House');
    expect($fila['especialidad'])->toBe('medicina_general');
    expect($fila['diagnostico_codigo'])->toBe('J00X');
    expect($fila['diagnostico'])->toBe('RINOFARINGITIS AGUDA');
});

test('el_autoservicio_no_expone_la_nota_clinica', function () {
    ConsultaMedica::create([
        'historia_clinica_id'   => $this->historia->id,
        'medico_id'             => $this->medico->id,
        'especialidad'          => 'medicina_general',
        'fecha_consulta'        => now(),
        'hora_consulta'         => '10:00:00',
        'motivo_consulta'       => 'Motivo reservado',
        'enfermedad_actual'     => '<p>Anamnesis reservada</p>',
        'examen_fisico'         => 'Hallazgos reservados',
        'diagnostico_detallado' => 'Diagnostico reservado',
        'plan_tratamiento'      => 'Plan reservado',
        'notas_medico'          => 'Nota reservada',
    ]);

    $respuesta = $this->actingAs($this->pacienteUser, 'sanctum')
        ->getJson('/api/v1/autoservicio/mi-historia-clinica')
        ->assertOk();

    // Lo que sale es el resumen: fecha, quién atendió y el diagnóstico
    // codificado. La anamnesis y el plan se quedan en la historia clínica.
    foreach ([
        'Anamnesis reservada', 'Hallazgos reservados',
        'Diagnostico reservado', 'Plan reservado', 'Nota reservada',
    ] as $reservado) {
        $respuesta->assertDontSee($reservado);
    }
});

test('el_servidor_no_ve_las_consultas_de_otro', function () {
    $otro = Servidor::create([
        'cedula' => '0801234555', 'nombre' => 'Luis', 'apellido' => 'Vera',
        'puesto_id' => $this->paciente->puesto_id,
        'unidad_administrativa_id' => $this->paciente->unidad_administrativa_id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(3), 'estado' => true,
    ]);
    $historiaAjena = HistoriaClinica::create([
        'numero_historia' => $otro->cedula, 'cedula_paciente' => $otro->cedula,
        'tipo_paciente' => 'servidor', 'servidor_id' => $otro->id,
        'estado' => true,
    ]);

    ConsultaMedica::create([
        'historia_clinica_id'   => $historiaAjena->id,
        'medico_id'             => $this->medico->id,
        'especialidad'          => 'medicina_general',
        'fecha_consulta'        => now(),
        'hora_consulta'         => '11:00:00',
        'motivo_consulta'       => 'De otra persona',
        'diagnostico_detallado' => 'De otra persona',
    ]);

    $respuesta = $this->actingAs($this->pacienteUser, 'sanctum')
        ->getJson('/api/v1/autoservicio/mi-historia-clinica')
        ->assertOk();

    expect($respuesta->json('datos'))->toBe([]);
});

test('una_consulta_retirada_no_sigue_en_el_autoservicio', function () {
    $consulta = ConsultaMedica::create([
        'historia_clinica_id'   => $this->historia->id,
        'medico_id'             => $this->medico->id,
        'especialidad'          => 'medicina_general',
        'fecha_consulta'        => now(),
        'hora_consulta'         => '12:00:00',
        'motivo_consulta'       => 'Control',
        'diagnostico_detallado' => 'Sin hallazgos',
    ]);

    $consulta->delete();

    $respuesta = $this->actingAs($this->pacienteUser, 'sanctum')
        ->getJson('/api/v1/autoservicio/mi-historia-clinica')
        ->assertOk();

    expect($respuesta->json('datos'))->toBe([]);
});
