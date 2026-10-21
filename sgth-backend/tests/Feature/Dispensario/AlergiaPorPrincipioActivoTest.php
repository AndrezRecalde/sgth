<?php

use App\Models\Dispensario\AlergiaPaciente;
use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Dispensario\InventarioMedicina;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

/*
 * El aviso de alergias al recetar comparaba la alergia solo con el NOMBRE de
 * la ficha del inventario, que suele ser el comercial. «Dolex» no avisaba a
 * quien es alérgico al paracetamol, aunque la ficha dijera «paracetamol» en el
 * principio activo.
 */

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Model::unguard();

    $this->medico = User::create([
        'email' => 'medico-alergias@example.com', 'usuario_ti' => 'medalerg',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $this->medico->assignRole(Role::firstOrCreate(['name' => 'medico', 'guard_name' => 'sanctum']));

    $this->historia = HistoriaClinica::create([
        'numero_historia' => '0912345681', 'cedula_paciente' => '0912345681',
        'tipo_paciente' => 'candidato', 'estado' => true,
    ]);
    $this->consulta = ConsultaMedica::create([
        'especialidad' => 'medicina_general', 'historia_clinica_id' => $this->historia->id,
        'medico_id' => $this->medico->id, 'fecha_consulta' => now(),
        'hora_consulta' => '09:00', 'motivo_consulta' => 'Fiebre',
    ]);
});

/** Emite una receta con un solo ítem y devuelve los avisos de alergia. */
function avisosAlRecetar(ConsultaMedica $consulta, array $item): array
{
    return test()->postJson('/api/v1/dispensario/recetas', [
        'consulta_medica_id' => $consulta->id,
        'fecha_emision'      => now()->toDateString(),
        'items' => [[
            'cantidad_prescrita' => 10, 'dosis' => '1 tableta',
            'frecuencia' => 'cada 8 horas', 'duracion' => '3 dias', ...$item,
        ]],
    ])->assertCreated()->json('datos.alertas_alergias');
}

function fichaDelCatalogo(string $nombre, string $principioActivo): InventarioMedicina
{
    return InventarioMedicina::create([
        'codigo' => 'ALG-' . uniqid(), 'nombre' => $nombre, 'principio_activo' => $principioActivo,
        'presentacion' => 'tableta', 'stock_actual' => 0, 'stock_minimo' => 0, 'estado' => true,
    ]);
}

test('avisa_cuando_el_alergeno_es_el_principio_activo_de_un_nombre_comercial', function () {
    AlergiaPaciente::create([
        'historia_clinica_id' => $this->historia->id, 'tipo' => 'medicamento',
        'descripcion' => 'Paracetamol', 'severidad' => 'grave',
    ]);
    $this->actingAs($this->medico, 'sanctum');

    $avisos = avisosAlRecetar($this->consulta, [
        'inventario_medicina_id' => fichaDelCatalogo('Dolex', 'paracetamol')->id,
    ]);

    expect($avisos)->toHaveCount(1)
        ->and($avisos[0])->toContain('Paracetamol');
});

test('el_aviso_no_distingue_tildes_ni_mayusculas', function () {
    AlergiaPaciente::create([
        'historia_clinica_id' => $this->historia->id, 'tipo' => 'medicamento',
        'descripcion' => 'Ácido acetilsalicílico', 'severidad' => 'moderada',
    ]);
    $this->actingAs($this->medico, 'sanctum');

    $avisos = avisosAlRecetar($this->consulta, [
        'inventario_medicina_id' => fichaDelCatalogo('Aspirina', 'ACIDO ACETILSALICILICO')->id,
    ]);

    expect($avisos)->toHaveCount(1);
});

test('una_alergia_sin_descripcion_no_avisa_de_todo', function () {
    AlergiaPaciente::create([
        'historia_clinica_id' => $this->historia->id, 'tipo' => 'medicamento',
        'descripcion' => '', 'severidad' => 'leve',
    ]);
    $this->actingAs($this->medico, 'sanctum');

    expect(avisosAlRecetar($this->consulta, ['medicamento_externo' => 'Loratadina 10 mg']))
        ->toBe([]);
});
