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
 * La fecha de un resultado admitía cualquier día, también los que no han
 * llegado: uno fechado el mes próximo quedaba primero en el listado.
 */

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Model::unguard();
    Storage::fake('local');

    $this->enfermera = User::create([
        'email' => 'resultado-fecha@example.com', 'usuario_ti' => 'resultado-fecha',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $this->enfermera->assignRole(Role::firstOrCreate(['name' => 'enfermera', 'guard_name' => 'sanctum']));

    $unidad = unidadDePrueba(['nombre' => 'Direccion de Fechas de Resultados']);
    $puesto = puestoDePrueba($unidad, 'Analista de Fechas de Resultados');
    $servidor = Servidor::create([
        'cedula' => '0812410001', 'nombre' => 'Irma', 'apellido' => 'Prueba',
        'puesto_id' => $puesto->id, 'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(2), 'estado' => true,
    ]);
    $this->historia = HistoriaClinica::create(['servidor_id' => $servidor->id, 'grupo_sanguineo' => 'O+']);
});

function resultadoFechado(int $historiaId, string $fecha): Illuminate\Testing\TestResponse
{
    return test()->post('/api/v1/dispensario/resultados-medicos', [
        'historia_clinica_id' => $historiaId,
        'tipo'                => 'imagen',
        'descripcion'         => 'Rx de tórax',
        'fecha_resultado'     => $fecha,
        'archivo'             => UploadedFile::fake()->create('rx.pdf', 40, 'application/pdf'),
    ], ['Accept' => 'application/json']);
}

test('un_resultado_con_fecha_futura_no_se_sube', function () {
    $this->actingAs($this->enfermera, 'sanctum');

    $errores = resultadoFechado($this->historia->id, now()->addDay()->toDateString())
        ->assertStatus(422)
        ->json('errores');

    expect($errores['fecha_resultado'][0] ?? null)->toBe('El resultado no puede tener fecha futura.')
        ->and(ResultadoMedico::count())->toBe(0);
});

test('hoy_y_dias_pasados_valen', function () {
    $this->actingAs($this->enfermera, 'sanctum');

    resultadoFechado($this->historia->id, now()->toDateString())->assertCreated();
    resultadoFechado($this->historia->id, now()->subMonth()->toDateString())->assertCreated();

    expect(ResultadoMedico::count())->toBe(2);
});
