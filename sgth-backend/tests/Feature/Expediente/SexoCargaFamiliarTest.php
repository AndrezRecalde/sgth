<?php

use App\Models\Expediente\CargaFamiliar;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
 * El sexo de las cargas familiares: obligatorio al registrarlas o editarlas,
 * y leído por los reportes del Dispensario como el de un servidor.
 */

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    $this->uath = User::factory()->create();
    $this->uath->assignRole('admin-uath');

    $unidad = unidadDePrueba(['nombre' => 'Unidad Familias']);
    $this->titular = Servidor::forceCreate([
        'cedula' => '0807777771', 'nombre' => 'Rosa', 'apellido' => 'Titular',
        'puesto_id' => puestoDePrueba($unidad, 'Puesto Familias')->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => 'losep', 'fecha_ingreso_institucion' => '2020-01-01', 'estado' => true,
    ]);

    $this->datos = [
        'cedula' => '0807777772', 'nombres' => 'Lía', 'apellidos' => 'Titular',
        'parentesco' => 'hijo', 'fecha_nacimiento' => '2016-05-01',
        'persona_con_discapacidad' => false, 'posee_enfermedad_catastrofica' => false,
    ];
});

test('registrar un familiar pide el sexo y lo guarda', function () {
    $url = "/api/v1/expediente/servidores/{$this->titular->id}/cargas-familiares";
    $this->actingAs($this->uath, 'sanctum');

    $this->postJson($url, $this->datos)
        ->assertUnprocessable()
        ->assertJsonPath('errores.genero.0', 'Indique el sexo del familiar.');

    $this->postJson($url, [...$this->datos, 'genero' => 'otro'])->assertUnprocessable();

    $id = $this->postJson($url, [...$this->datos, 'genero' => 'femenino'])
        ->assertCreated()->json('datos.id');

    expect(CargaFamiliar::find($id)->genero)->toBe('femenino');
});

test('los reportes del Dispensario cuentan el sexo de los familiares', function () {
    $medico = User::factory()->create();
    $medico->assignRole(Role::firstOrCreate(['name' => 'medico', 'guard_name' => 'sanctum']));
    $admin = User::factory()->create();
    $admin->assignRole(Role::firstOrCreate(['name' => 'admin-dispensario', 'guard_name' => 'sanctum']));

    $hija = CargaFamiliar::create([...$this->datos, 'servidor_id' => $this->titular->id, 'genero' => 'femenino']);
    $historia = DB::table('historias_clinicas')->insertGetId([
        'numero_historia' => $hija->cedula, 'cedula_paciente' => $hija->cedula,
        'tipo_paciente' => 'familiar', 'carga_familiar_id' => $hija->id, 'estado' => true,
    ]);
    DB::table('consultas_medicas')->insert([
        'historia_clinica_id' => $historia, 'medico_id' => $medico->id,
        'fecha_consulta' => '2026-09-10', 'hora_consulta' => '09:00', 'especialidad' => 'medicina_general',
    ]);

    $this->actingAs($admin, 'sanctum');
    $filtros = '?desde=2026-09-01&hasta=2026-09-30';

    expect($this->getJson('/api/v1/dispensario/reportes/atenciones' . $filtros)->json('datos.filas.0.sexo'))
        ->toBe('Mujer');
    expect($this->getJson('/api/v1/dispensario/reportes/morbilidad' . $filtros)->json('datos.filas.0'))
        ->toMatchArray(['mujeres' => 1, 'sexo_sin_dato' => 0]);
});
