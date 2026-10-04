<?php

use App\Models\Dispensario\HistoriaClinica;
use App\Models\Dispensario\SolicitudCertificacionMedica;
use App\Models\Expediente\CargaFamiliar;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
 * Lo que el Expediente declara —discapacidad y enfermedad catastrófica, del
 * servidor o de su familiar— llega al médico en solo lectura.
 *
 * Hasta el 2026-10-03 el Dispensario no leía ninguna de esas tablas, y el FEMO
 * pedía teclear otra vez la discapacidad del servidor.
 */

beforeEach(function () {
    $this->medico = User::factory()->create();
    $this->medico->assignRole(Role::firstOrCreate(['name' => 'medico', 'guard_name' => 'sanctum']));

    $unidad = unidadDePrueba(['nombre' => 'Unidad Declarada']);
    $this->servidor = Servidor::forceCreate([
        'cedula' => '0802222221', 'nombre' => 'Rita', 'apellido' => 'Declarada',
        'puesto_id' => puestoDePrueba($unidad, 'Puesto Declarada')->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => 'losep', 'estado' => true,
    ]);

    $this->hija = CargaFamiliar::create([
        'servidor_id' => $this->servidor->id, 'cedula' => '0802222222',
        'nombres' => 'Sara', 'apellidos' => 'Declarada', 'parentesco' => 'hijo',
        'fecha_nacimiento' => '2015-02-01', 'genero' => 'femenino',
    ]);

    $this->actingAs($this->medico, 'sanctum');
});

function historiaDeCondicionDeclarada(array $atributos): HistoriaClinica
{
    return HistoriaClinica::create([
        'numero_historia' => $atributos['cedula_paciente'], 'estado' => true, ...$atributos,
    ]);
}

test('el médico ve la discapacidad y la enfermedad declaradas de un familiar, con su grado', function () {
    $this->hija->discapacidades()->create(['tipo_discapacidad' => 'intelectual', 'porcentaje' => 45]);
    $this->hija->enfermedadesCatastroficas()->create([
        'tipo_enfermedad' => 'Leucemia linfoblástica aguda', 'codigo_cie10' => 'C91.0',
    ]);
    $historia = historiaDeCondicionDeclarada([
        'cedula_paciente' => $this->hija->cedula, 'tipo_paciente' => 'familiar',
        'carga_familiar_id' => $this->hija->id,
    ]);

    $condicion = $this->getJson("/api/v1/dispensario/historias-clinicas/{$historia->id}/contexto-consulta")
        ->assertOk()
        ->json('datos.condicion_declarada');

    expect($condicion)->toBe([
        'discapacidades' => [[
            'etiqueta' => 'Discapacidad Intelectual', 'porcentaje' => 45, 'grado' => 'Moderada',
        ]],
        'enfermedades' => [['nombre' => 'Leucemia linfoblástica aguda', 'codigo_cie10' => 'C91.0']],
        'discapacidad_sin_detalle' => false,
        'enfermedad_sin_detalle'   => false,
    ]);
});

test('también la del servidor, y una marca sin registros se avisa como «sin detalle»', function () {
    $this->servidor->discapacidades()->create([
        'tipo_discapacidad' => 'fisica', 'porcentaje' => 80, 'numero_carnet_conadis' => '1',
    ]);
    $this->servidor->forceFill(['tiene_enfermedad_catastrofica' => true])->save();
    $historia = historiaDeCondicionDeclarada([
        'cedula_paciente' => $this->servidor->cedula, 'tipo_paciente' => 'servidor',
        'servidor_id' => $this->servidor->id,
    ]);

    $this->getJson("/api/v1/dispensario/historias-clinicas/{$historia->id}/contexto-consulta")
        ->assertOk()
        ->assertJsonPath('datos.condicion_declarada.discapacidades.0.grado', 'Muy grave o completa')
        ->assertJsonPath('datos.condicion_declarada.enfermedades', [])
        ->assertJsonPath('datos.condicion_declarada.enfermedad_sin_detalle', true)
        // Ni carné ni rutas de archivo: lo que sirve para atender.
        ->assertJsonMissingPath('datos.condicion_declarada.discapacidades.0.numero_carnet_conadis');
});

test('un candidato sin expediente no tiene condición declarada', function () {
    $historia = historiaDeCondicionDeclarada([
        'cedula_paciente' => '0802222229', 'tipo_paciente' => 'candidato',
    ]);

    $this->getJson("/api/v1/dispensario/historias-clinicas/{$historia->id}/contexto-consulta")
        ->assertOk()
        ->assertJsonPath('datos.condicion_declarada', null);
});

test('la solicitud trae la discapacidad del servidor para que el FEMO nazca con ella', function () {
    $this->servidor->discapacidades()->create([
        'tipo_discapacidad' => 'fisica', 'porcentaje' => 35, 'numero_carnet_conadis' => '1',
    ]);
    $solicitud = SolicitudCertificacionMedica::create([
        'servidor_id' => $this->servidor->id, 'tipo_evento' => 'periodica', 'origen' => 'expediente',
        'cedula_paciente' => $this->servidor->cedula, 'nombres_paciente' => 'Rita Declarada',
        'estado' => 'pendiente', 'solicitado_por' => $this->medico->id,
    ]);

    $this->getJson("/api/v1/dispensario/solicitudes-certificacion/{$solicitud->id}")
        ->assertOk()
        ->assertJsonPath('datos.servidor.tiene_discapacidad', true)
        ->assertJsonPath('datos.servidor.discapacidades.0.porcentaje', '35.00');
});
