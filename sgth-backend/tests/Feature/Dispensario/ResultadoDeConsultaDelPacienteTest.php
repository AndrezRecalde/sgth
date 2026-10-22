<?php

use App\Enums\RegimenLaboral;
use App\Models\Dispensario\ConsultaMedica;
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
 * Subir un resultado solo comprobaba que la consulta existiera, no que fuera
 * del paciente: el laboratorio de uno quedaba colgado de la consulta de otro,
 * y el panel de atención de ese otro lo enseñaba como suyo.
 */

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Model::unguard();
    Storage::fake('local');

    $this->enfermera = User::create([
        'email' => 'resultado-paciente@example.com', 'usuario_ti' => 'resultado-paciente',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $this->enfermera->assignRole(Role::firstOrCreate(['name' => 'enfermera', 'guard_name' => 'sanctum']));

    $unidad = unidadDePrueba(['nombre' => 'Direccion de Resultados por Paciente']);
    $puesto = puestoDePrueba($unidad, 'Analista de Resultados por Paciente');
    $historia = function (string $cedula, string $nombre) use ($unidad, $puesto) {
        $servidor = Servidor::create([
            'cedula' => $cedula, 'nombre' => $nombre, 'apellido' => 'Prueba',
            'puesto_id' => $puesto->id, 'unidad_administrativa_id' => $unidad->id,
            'regimen_laboral' => RegimenLaboral::LOSEP,
            'fecha_ingreso_institucion' => now()->subYears(2), 'estado' => true,
        ]);

        return HistoriaClinica::create(['servidor_id' => $servidor->id, 'grupo_sanguineo' => 'O+']);
    };
    $consulta = fn (HistoriaClinica $h) => ConsultaMedica::create([
        'especialidad' => 'medicina_general', 'historia_clinica_id' => $h->id,
        'medico_id' => $this->enfermera->id, 'fecha_consulta' => now(),
        'hora_consulta' => '09:00:00', 'motivo_consulta' => 'Control',
    ]);

    $this->historia = $historia('0812390001', 'Elsa');
    $this->consulta = $consulta($this->historia);
    $this->consultaAjena = $consulta($historia('0812390002', 'Hugo'));
});

function subirResultado(int $historiaId, ?int $consultaId): Illuminate\Testing\TestResponse
{
    return test()->post('/api/v1/dispensario/resultados-medicos', array_filter([
        'historia_clinica_id' => $historiaId,
        'consulta_medica_id'  => $consultaId,
        'tipo'                => 'laboratorio',
        'descripcion'         => 'Glucosa en ayunas',
        'fecha_resultado'     => now()->toDateString(),
        'archivo'             => UploadedFile::fake()->create('glucosa.pdf', 40, 'application/pdf'),
    ], fn ($v) => $v !== null), ['Accept' => 'application/json']);
}

test('no_se_cuelga_un_resultado_de_la_consulta_de_otro_paciente', function () {
    $this->actingAs($this->enfermera, 'sanctum');

    $errores = subirResultado($this->historia->id, $this->consultaAjena->id)
        ->assertStatus(422)
        ->json('errores');

    expect($errores['consulta_medica_id'][0] ?? null)
        ->toBe('La consulta indicada no es de este paciente.')
        ->and(ResultadoMedico::count())->toBe(0);
});

test('se_sube_a_una_consulta_del_mismo_paciente', function () {
    $this->actingAs($this->enfermera, 'sanctum');

    subirResultado($this->historia->id, $this->consulta->id)->assertCreated();

    expect(ResultadoMedico::sole()->consulta_medica_id)->toBe($this->consulta->id);
});

test('se_sube_sin_consulta', function () {
    $this->actingAs($this->enfermera, 'sanctum');

    subirResultado($this->historia->id, null)->assertCreated();

    expect(ResultadoMedico::sole()->consulta_medica_id)->toBeNull();
});

test('no_se_cuelga_de_una_consulta_borrada', function () {
    $this->actingAs($this->enfermera, 'sanctum');
    $this->consulta->delete();

    subirResultado($this->historia->id, $this->consulta->id)->assertStatus(422);
});
