<?php

use App\Enums\RegimenLaboral;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Dispensario\ResultadoMedico;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

/*
 * Los resultados los registran el médico y el odontólogo (D2 y P1 de
 * docs/diseno-examenes-medicos.md). Enfermería los sigue viendo, para el
 * seguimiento, pero ya no los sube.
 */

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Model::unguard();
    Storage::fake('local');

    $this->conRol = function (string $alias, string $rol): User {
        $user = User::create([
            'email' => "{$alias}@example.com", 'usuario_ti' => $alias,
            'password' => bcrypt('123456'), 'primer_login' => false,
        ]);
        $user->assignRole(Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum']));

        return $user;
    };

    $unidad = unidadDePrueba(['nombre' => 'Direccion de Resultados por Rol']);
    $puesto = puestoDePrueba($unidad, 'Analista de Resultados por Rol');
    $servidor = Servidor::create([
        'cedula' => '0812450001', 'nombre' => 'Nora', 'apellido' => 'Prueba',
        'puesto_id' => $puesto->id, 'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(2), 'estado' => true,
    ]);
    $this->historia = HistoriaClinica::create(['servidor_id' => $servidor->id, 'grupo_sanguineo' => 'O+']);
});

function subirResultadoComo(User $usuario, int $historiaId): Illuminate\Testing\TestResponse
{
    return test()->actingAs($usuario, 'sanctum')->post('/api/v1/dispensario/resultados-medicos', [
        'historia_clinica_id' => $historiaId,
        'tipo'                => 'laboratorio',
        'descripcion'         => 'Biometría hemática',
        'fecha_resultado'     => now()->toDateString(),
        'archivo'             => UploadedFile::fake()->create('biometria.pdf', 40, 'application/pdf'),
    ], ['Accept' => 'application/json']);
}

test('el_medico_y_el_odontologo_suben_resultados', function () {
    subirResultadoComo(($this->conRol)('res-medico', 'medico'), $this->historia->id)->assertCreated();
    subirResultadoComo(($this->conRol)('res-odonto', 'odontologo'), $this->historia->id)->assertCreated();

    expect(ResultadoMedico::count())->toBe(2);
});

test('enfermeria_y_la_administracion_ya_no_suben_resultados', function () {
    subirResultadoComo(($this->conRol)('res-enfermera', 'enfermera'), $this->historia->id)->assertForbidden();
    subirResultadoComo(($this->conRol)('res-admin', 'admin-dispensario'), $this->historia->id)->assertForbidden();

    expect(ResultadoMedico::count())->toBe(0);
});

test('enfermeria_sigue_viendo_los_resultados', function () {
    subirResultadoComo(($this->conRol)('res-medico-2', 'medico'), $this->historia->id)->assertCreated();
    $id = ResultadoMedico::value('id');
    $enfermera = ($this->conRol)('res-enfermera-2', 'enfermera');

    $this->actingAs($enfermera, 'sanctum')
        ->getJson("/api/v1/dispensario/resultados-medicos?historia_clinica_id={$this->historia->id}")
        ->assertOk()
        ->assertJsonCount(1, 'datos');

    $this->actingAs($enfermera, 'sanctum')
        ->get("/api/v1/dispensario/resultados-medicos/{$id}/archivo")
        ->assertOk();
});
