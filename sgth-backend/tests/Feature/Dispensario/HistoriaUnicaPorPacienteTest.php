<?php

/*
| Una historia clínica por paciente (hallazgo del 2026-10-23).
|
| La historia se buscaba solo por la cédula: a un servidor con una historia
| sin cédula —las que abría la recepción antes del 2026-09-05— o con la cédula
| corregida en el expediente se le abría una segunda, y cada pantalla elegía
| una distinta. En desarrollo, una misma atención quedó con el triaje en una y
| la consulta en la otra. Ahora se busca primero por el dueño, la cédula de la
| historia sigue a la del expediente, y si quedaron dos, todas eligen la misma.
*/

use App\Enums\RegimenLaboral;
use App\Models\Dispensario\AgendaMedica;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Expediente\CargaFamiliar;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Dispensario\AgendaService;
use App\Services\Dispensario\HistoriaClinicaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Model::unguard();

    $this->medico = User::create([
        'email' => 'historia-unica@example.com', 'usuario_ti' => 'historia-unica',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $this->medico->assignRole(Role::firstOrCreate(['name' => 'medico', 'guard_name' => 'sanctum']));

    $unidad = unidadDePrueba(['nombre' => 'Direccion de Historia Unica']);
    $this->servidor = Servidor::create([
        'cedula' => '0812500001', 'nombre' => 'Rosa', 'apellido' => 'Unica',
        'puesto_id' => puestoDePrueba($unidad, 'Analista de Historia Unica')->id,
        'unidad_administrativa_id' => $unidad->id, 'regimen_laboral' => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(2), 'estado' => true,
    ]);

    $this->actingAs($this->medico, 'sanctum');
});

/** Una historia del servidor como las que abría la recepción antes del 2026-09-05: sin cédula. */
function historiaSinCedulaUnica(Servidor $servidor): HistoriaClinica
{
    return HistoriaClinica::create(['tipo_paciente' => 'servidor', 'servidor_id' => $servidor->id, 'estado' => true]);
}

test('el alta desde la recepción usa la historia sin cédula del servidor y se la pone', function () {
    $vieja = historiaSinCedulaUnica($this->servidor);

    $this->postJson('/api/v1/dispensario/historias-clinicas', ['servidor_id' => $this->servidor->id])
        ->assertCreated()
        ->assertJsonPath('datos.id', $vieja->id);

    expect(HistoriaClinica::where('servidor_id', $this->servidor->id)->count())->toBe(1)
        ->and($vieja->fresh()->cedula_paciente)->toBe('0812500001')
        ->and($vieja->fresh()->numero_historia)->toBe('0812500001');
});

test('la orden desde la ficha ocupacional encuentra la del servidor aunque no tenga cédula', function () {
    $vieja = historiaSinCedulaUnica($this->servidor);

    // Es la búsqueda que usa `OrdenExamenController::historiaDeLaFicha`.
    $encontrada = HistoriaClinica::buscarOCrearPorCedula('0812500001', 'servidor', $this->servidor->id, null, $this->medico->id);

    expect($encontrada->id)->toBe($vieja->id)
        ->and(HistoriaClinica::count())->toBe(1);
});

test('corregir la cédula del servidor la lleva a su historia, y con la nueva se encuentra', function () {
    $historia = HistoriaClinica::buscarOCrearPorCedula('0812500001', 'servidor', $this->servidor->id);

    $this->servidor->update(['cedula' => '0812500099']);

    expect($historia->fresh()->cedula_paciente)->toBe('0812500099')
        ->and($historia->fresh()->numero_historia)->toBe('0812500099');

    $this->getJson('/api/v1/dispensario/historias-clinicas/buscar-por-cedula?cedula=0812500099')
        ->assertOk()
        ->assertJsonPath('datos.id', $historia->id);

    $this->postJson('/api/v1/dispensario/historias-clinicas', ['servidor_id' => $this->servidor->id])
        ->assertCreated()
        ->assertJsonPath('datos.id', $historia->id);
    expect(HistoriaClinica::count())->toBe(1);
});

test('si la cédula nueva ya es de otra historia, el expediente se guarda y la historia no se toca', function () {
    $historia = HistoriaClinica::buscarOCrearPorCedula('0812500001', 'servidor', $this->servidor->id);
    // La del preocupacional de esa misma persona, a nombre de la cédula.
    HistoriaClinica::buscarOCrearPorCedula('0812500099', 'candidato');

    $this->servidor->update(['cedula' => '0812500099']);

    expect($this->servidor->fresh()->cedula)->toBe('0812500099')
        ->and($historia->fresh()->cedula_paciente)->toBe('0812500001');
});

test('corregir la cédula de un familiar también la lleva a su historia', function () {
    $hijo = CargaFamiliar::create([
        'servidor_id' => $this->servidor->id, 'cedula' => '0812500002', 'nombres' => 'Tomás', 'apellidos' => 'Unica',
        'parentesco' => 'hijo', 'fecha_nacimiento' => '2015-03-01', 'genero' => 'M', 'estado' => true,
    ]);
    $historia = HistoriaClinica::buscarOCrearPorCedula('0812500002', 'familiar', null, $hijo->id);

    $hijo->update(['cedula' => '0812500098']);

    expect($historia->fresh()->cedula_paciente)->toBe('0812500098');
});

test('con dos historias, el triaje, la cola y la ficha del servidor eligen la misma', function () {
    // Como en desarrollo: una vieja sin cédula y otra, posterior, con ella.
    historiaSinCedulaUnica($this->servidor);
    $conCedula = HistoriaClinica::create([
        'tipo_paciente' => 'servidor', 'servidor_id' => $this->servidor->id, 'estado' => true,
        'cedula_paciente' => '0812500001', 'numero_historia' => '0812500001',
    ]);

    $turno = AgendaMedica::create([
        'folio' => 'TUR-2026-77001', 'medico_id' => $this->medico->id, 'servidor_id' => $this->servidor->id,
        'fecha' => today()->toDateString(), 'estado' => 'en_espera', 'tipo_atencion' => 'medicina_general',
        'registrado_en' => now(),
    ]);

    expect(HistoriaClinica::delDueno($this->servidor->id, null)->id)->toBe($conCedula->id)
        ->and($this->servidor->fresh()->historiaClinica->id)->toBe($conCedula->id)
        ->and(app(HistoriaClinicaService::class)->paraPacienteDeTurno($turno)->id)->toBe($conCedula->id)
        ->and(app(AgendaService::class)->obtenerPorFolio('TUR-2026-77001', $this->medico->id)->historia_clinica_id)
        ->toBe($conCedula->id);
});
